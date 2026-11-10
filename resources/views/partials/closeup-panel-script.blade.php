{{--
    Alpine factory for the Closeup Panel. Included from the @script block of the
    owner single-photo viewer: the panel is a photographer-only culling tool and
    clients never get the panel, the trigger or this script. Same pattern as
    partials/photo-masonry-script: the statements land inside the page
    component's @script, which Livewire re-runs after wire:navigate.

    The factory is cached on window because Livewire re-runs the script on
    every navigation: re-registering it with Alpine.data is harmless, but
    recreating the closure would drop the cached detector promise and force
    a fresh WASM download.

    Two-phase loading, on purpose:
    1. Fast pass on the 2048 preview the viewer already downloaded: detect
       the face, show the crop and the rating immediately.
    2. Slow pass on the untouched stored file (`original_url`, no CDN resize
       params) when one exists: retake the crop at native pixels and recompute
       the sharpness. The footer shows "Loading from original…" while this is
       in flight.

    Never measure sharpness on the CDN preview: it can be an upscale of a
    smaller original, so its pixels are invented and always read as sharp.

    Crops are kept at native resolution and handed over as PNG blobs (object
    URLs): a JPEG data URL is re-encoded, then decoded and upscaled by the
    browser, which shows up as blocky artifacts on a zoomed face.
--}}
if (! window.closeupPanelFactory) {
    window.closeupPanelFactory = ({ previewUrl, originalUrl, imageUrl, fullUrl }) => {
        const VERSION = '1.2.2'
        const ESM = `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${VERSION}/+esm`
        const WASM = `https://cdn.jsdelivr.net/npm/@mediapipe/tasks-vision@${VERSION}/wasm`

        // Full range is the right model for portraits at normal shooting
        // distance: it detected 12/12 of the studio samples, while short
        // range missed 3 of them. Short range is only a last-resort fallback
        // for extreme close-ups, where a face can overflow the frame.
        const MODELS = {
            full: 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_full_range/float16/1/blaze_face_full_range.tflite',
            short: 'https://storage.googleapis.com/mediapipe-models/face_detector/blaze_face_short_range/float16/1/blaze_face_short_range.tflite',
        }

        // The detector only finds the face box. Eyes need landmarks, so the
        // landmarker model runs on the face crop: it is what makes "is the
        // subject blinking" measurable instead of a guess.
        const LANDMARKER_MODEL = 'https://storage.googleapis.com/mediapipe-models/face_landmarker/face_landmarker/float16/1/face_landmarker.task'

        const CONFIDENCE = 0.5
        const FALLBACK_CONFIDENCE = 0.3
        const DETECT_MAX = 1280
        const THUMB_MAX = 112
        const LANDMARK_MAX = 640
        const CROP_PADDING = 0.25

        // Bound only to protect memory on huge originals; high enough that a
        // 2048px photo is never downscaled before display.
        const CROP_MAX = 2048

        // Every face is resampled so it is exactly this many pixels tall
        // before measuring. Laplacian variance depends on resolution: the same
        // photo scores differently at a 147px face and a 664px face, so a
        // canonical height is the only way to compare photos fairly.
        const SAMPLE_FACE_HEIGHT = 200

        // Exposure is read on the inner part of the detected face box: the
        // padded box includes hair, shoulders and background, which skew the
        // histogram far more than the skin does.
        const FACE_CORE_INSET = 0.12

        // Every sharpness measurement is taken at one canonical scale, so the
        // numbers compare across photos. Upscaling is allowed on purpose.
        const EYE_BAND_WIDTH = 240

        // Calibrated on the studio samples with the always-smooth resample
        // below, eye band at 240 px wide. Measured values:
        //   out of focus 0.16-0.20 (23, 22, 31), soft 0.23-0.31 (29, 30,
        //   33592, 33593), sharp 0.44-0.57 (24, 2, 33647, 10, 16, 33594, 33595).
        // The face-wide fallback uses its own range, measured the same way:
        //   0.18 out of focus to 0.54 sharp.
        const SHARPNESS_RANGE = [0.15, 0.57]
        const FACE_SHARPNESS_RANGE = [0.17, 0.55]

        // Shape of the panel column (320px wide by 560px tall). The crop is
        // shaped to this ratio so `object-fit: cover` fills the panel without
        // trimming the face; object-fit itself guarantees nothing is stretched.
        const STAGE_ASPECT = 320 / 560

        // Remembering the panel state across photos is part of the workflow:
        // once culling starts, every next photo should open with it ready.
        const STORAGE_KEY = 'picstome-closeup-open'

        let detectorPromise = null
        let fallbackPromise = null
        let landmarkerPromise = null

        const buildDetector = (model, confidence) => (async () => {
            const { FilesetResolver, FaceDetector } = await import(ESM)
            const vision = await FilesetResolver.forVisionTasks(WASM)

            return FaceDetector.createFromOptions(vision, {
                baseOptions: { modelAssetPath: MODELS[model] },
                runningMode: 'IMAGE',
                minDetectionConfidence: confidence,
                minSuppressionThreshold: 0.3,
            })
        })()

        const detector = () => (detectorPromise ??= buildDetector('full', CONFIDENCE))
        const fallbackDetector = () => (fallbackPromise ??= buildDetector('short', FALLBACK_CONFIDENCE))

        /**
         * Face landmarker, used only to measure the eyes. Loaded on the first
         * rating, so a gallery that never opens the panel pays nothing for it.
         * The blendshape output gives `eyeBlinkLeft` / `eyeBlinkRight`, which is
         * a direct "are the eyes shut" signal instead of a guess.
         */
        const landmarker = () => (landmarkerPromise ??= (async () => {
            const { FilesetResolver, FaceLandmarker } = await import(ESM)
            const vision = await FilesetResolver.forVisionTasks(WASM)

            return FaceLandmarker.createFromOptions(vision, {
                baseOptions: { modelAssetPath: LANDMARKER_MODEL },
                runningMode: 'IMAGE',
                numFaces: 1,
                outputFaceBlendshapes: true,
            })
        })())

        const loadImage = (url) =>
            new Promise((resolve, reject) => {
                const image = new Image()
                image.crossOrigin = 'anonymous'
                image.decoding = 'async'
                image.onload = () => resolve(image)
                image.onerror = () => reject(new Error('image-load-failed'))
                image.src = url
            })

        /**
         * Copy a region of the source into a canvas.
         *
         * `smooth` defaults to "smooth only when shrinking": growing an image
         * with a smoothed draw is a lie about the detail, which is fine for a
         * model input but never for something a photographer judges.
         *
         * Measurement crops pass `true` explicitly. That is the whole point:
         * a blurred face whose eye band is *smaller* than the canonical sample
         * used to be upscaled with nearest-neighbour, and that blockiness was
         * measured as detail. A 86 px band scored *higher* than a 473 px one
         * for exactly that reason. Always smoothing makes the resample cost the
         * same for every photo, so only real detail moves the ratio.
         */
        const canvasFrom = (source, sx, sy, sw, sh, width, height, smooth = null) => {
            const canvas = document.createElement('canvas')
            canvas.width = Math.max(1, Math.round(width))
            canvas.height = Math.max(1, Math.round(height))
            const context = canvas.getContext('2d', { willReadFrequently: true })
            context.imageSmoothingQuality = 'high'
            context.imageSmoothingEnabled = smooth ?? (width < sw || height < sh)
            context.drawImage(source, Math.round(sx), Math.round(sy), Math.round(sw), Math.round(sh), 0, 0, canvas.width, canvas.height)

            return { canvas, context, width: canvas.width, height: canvas.height }
        }

        const imagePixels = (image) => image.naturalWidth * image.naturalHeight

        /**
         * PNG blob URL: lossless and no base64 round trip, so the zoomed face
         * keeps every pixel the original has.
         */
        const toObjectUrl = (canvas) =>
            new Promise((resolve) => {
                canvas.toBlob((blob) => resolve(blob ? URL.createObjectURL(blob) : ''), 'image/png')
            })

        /**
         * Padded face region, clamped to the image bounds. The padding keeps
         * some hair and shoulders so the crop does not feel like a mugshot.
         */
        const faceBox = (face) => {
            const x = Math.max(0, face.x - CROP_PADDING * face.w)
            const y = Math.max(0, face.y - CROP_PADDING * face.h)

            return {
                x,
                y,
                w: Math.min(1 - x, face.w * (1 + 2 * CROP_PADDING)),
                h: Math.min(1 - y, face.h * (1 + 2 * CROP_PADDING)),
            }
        }

        /**
         * Crop the face at native resolution and shape it to the stage's
         * aspect ratio. No artificial downscale: the browser then reduces it
         * to the on-screen box, which is what keeps retina displays crisp.
         */
        const cropForStage = (source, face) => {
            const box = faceBox(face)
            const srcW = source.naturalWidth
            const srcH = source.naturalHeight
            const current = (box.w * srcW) / (box.h * srcH)

            let { x, y, w, h } = box

            if (current < STAGE_ASPECT) {
                // Too narrow: widen around the face centre.
                const wanted = Math.min(1, (h * srcH * STAGE_ASPECT) / srcW)
                const centre = x + w / 2
                w = wanted
                x = Math.min(Math.max(0, centre - w / 2), 1 - w)
            } else if (current > STAGE_ASPECT) {
                // Too wide: heighten around the face centre.
                const wanted = Math.min(1, (w * srcW) / (STAGE_ASPECT * srcH))
                const centre = y + h / 2
                h = wanted
                y = Math.min(Math.max(0, centre - h / 2), 1 - h)
            }

            const pxW = Math.min(CROP_MAX, w * srcW)
            const pxH = Math.min(CROP_MAX, h * srcH)
            // One uniform factor: both axes shrink by the same amount, so the
            // crop keeps its true aspect ratio.
            const scale = Math.min(1, pxW / (w * srcW), pxH / (h * srcH))

            return canvasFrom(source, x * srcW, y * srcH, w * srcW, h * srcH, w * srcW * scale, h * srcH * scale)
        }

        /**
         * Padded face crop for the landmarker, scaled so the longer side is
         * ~640 px. The landmarker needs a face that is reasonably big in its
         * own input; on the full frame it silently misses small faces.
         */
        const LANDMARK_PADDING = 0.35

        /**
         * Normalised box the landmarker sees, expressed in source-image units.
         * The landmarker reports points relative to its own input, so this is
         * what maps them back onto the original photo.
         */
        const landmarksBox = (face) => {
            const x = Math.max(0, face.x - LANDMARK_PADDING * face.w)
            const y = Math.max(0, face.y - LANDMARK_PADDING * face.h)

            return {
                x,
                y,
                w: Math.min(1 - x, face.w * (1 + 2 * LANDMARK_PADDING)),
                h: Math.min(1 - y, face.h * (1 + 2 * LANDMARK_PADDING)),
            }
        }

        const cropForLandmarks = (source, face) => {
            const box = landmarksBox(face)
            const pxW = box.w * source.naturalWidth
            const pxH = box.h * source.naturalHeight
            // The landmarker needs a decently large face: upscale small crops
            // up to 2x (it only feeds the model, never the displayed zoom).
            const scale = Math.min(2, Math.max(1, LANDMARK_MAX / Math.max(pxW, pxH)))

            return canvasFrom(source, box.x * source.naturalWidth, box.y * source.naturalHeight, pxW, pxH, pxW * scale, pxH * scale)
        }

        /**
         * Square thumbnail for the multi-face strip: square keeps the strip
         * uniform and `object-fit: cover` trims the sides, never squashes.
         */
        const cropForThumb = (source, face) => {
            const box = faceBox(face)
            const srcW = source.naturalWidth
            const srcH = source.naturalHeight
            const side = Math.min(box.w * srcW, box.h * srcH)
            const x = Math.min(Math.max(0, (box.x * srcW) + ((box.w * srcW - side) / 2)), srcW - side)
            const y = Math.min(Math.max(0, (box.y * srcH) + ((box.h * srcH - side) / 2)), srcH - side)
            const scale = Math.min(1, THUMB_MAX / side)

            return canvasFrom(source, x, y, side, side, side * scale, side * scale)
        }

        /**
         * Face region cropped at a FIXED canonical size and always resampled
         * with smoothing, even when that means upscaling.
         *
         * Measuring at native resolution made scores incomparable: a face that
         * is 150 px tall in the frame outscored a 278 px one, so an
         * out-of-focus shot read as sharp. Forcing every photo through the
         * same scale removes the resolution bias entirely.
         *
         * `inset` 0.12 keeps only skin: the padded box includes hair and
         * background, which dominate the histogram.
         */
        const faceSample = (source, face, target = SAMPLE_FACE_HEIGHT, inset = FACE_CORE_INSET) => {
            const x = face.x + (face.w * inset)
            const y = face.y + (face.h * inset)
            const w = face.w * (1 - 2 * inset)
            const h = face.h * (1 - 2 * inset)
            const srcW = source.naturalWidth
            const srcH = source.naturalHeight
            const pxW = w * srcW
            const pxH = h * srcH
            // No Math.min(1, ...): upscaling is allowed on purpose (always
            // smooth), so every measurement shares exactly one scale.
            const scale = target / (face.h * srcH)

            return canvasFrom(source, x * srcW, y * srcH, pxW, pxH, pxW * scale, pxH * scale, true)
        }

        /**
         * Eye band: both eyes plus the brows, cropped from landmarks and
         * resampled to a fixed width.
         *
         * Photographers focus on the eyes, so judging sharpness there separates
         * good from bad far better than the whole face: on the studio samples
         * blurry shots landed 0.13-0.17 and sharp ones 0.29-0.45, roughly twice
         * the dynamic range of a face-wide score.
         */
        const eyeBand = (source, points, target = EYE_BAND_WIDTH) => {
            if (!points || points.length === 0) {
                return null
            }

            // Eye corners and lid midpoints: 33/133 and 362/263 are the outer
            // and inner corners, 159/145 and 386/374 the lids.
            const indexes = [33, 133, 362, 263, 159, 145, 386, 374]
            const xs = indexes.map((index) => points[index].x)
            const ys = indexes.map((index) => points[index].y)
            const minX = Math.min(...xs)
            const maxX = Math.max(...xs)
            const minY = Math.min(...ys)
            const maxY = Math.max(...ys)

            // Margin so lashes and brows are included, not only the lids.
            const padX = (maxX - minX) * 0.35
            const padY = (maxY - minY) * 0.9
            const x = Math.max(0, minX - padX)
            const y = Math.max(0, minY - padY)
            const w = Math.min(1 - x, (maxX - minX) + 2 * padX)
            const h = Math.min(1 - y, (maxY - minY) + 2 * padY)

            const srcW = source.naturalWidth
            const srcH = source.naturalHeight
            const pxW = w * srcW
            const pxH = h * srcH

            return canvasFrom(source, x * srcW, y * srcH, pxW, pxH, target, target * (pxH / pxW), true)
        }

        /**
         * High-frequency energy relative to the local contrast.
         *
         * Raw Laplacian variance is not comparable across photos: it scales
         * with resolution and with subject contrast. Dividing by the grayscale
         * standard deviation cancels the contrast part; measuring at one
         * canonical size cancels the resolution part.
         */
        const sharpness = (context, w, h, [floor, ceiling] = SHARPNESS_RANGE) => {
            const { data } = context.getImageData(0, 0, w, h)
            const pixels = w * h
            const gray = new Float32Array(pixels)
            let sum = 0

            for (let i = 0; i < pixels; i++) {
                gray[i] = data[i * 4] * 0.299 + data[i * 4 + 1] * 0.587 + data[i * 4 + 2] * 0.114
                sum += gray[i]
            }

            const mean = sum / pixels
            let varianceGray = 0

            for (let i = 0; i < pixels; i++) {
                varianceGray += (gray[i] - mean) ** 2
            }

            const contrast = Math.sqrt(varianceGray / pixels)

            const laplacian = []
            for (let y = 1; y < h - 1; y++) {
                for (let x = 1; x < w - 1; x++) {
                    const i = y * w + x
                    laplacian.push(4 * gray[i] - gray[i - 1] - gray[i + 1] - gray[i - w] - gray[i + w])
                }
            }

            const lapMean = laplacian.reduce((total, value) => total + value, 0) / laplacian.length
            let varianceLap = 0

            for (let i = 0; i < laplacian.length; i++) {
                varianceLap += (laplacian[i] - lapMean) ** 2
            }

            const ratio = contrast > 1 ? Math.sqrt(varianceLap / laplacian.length) / contrast : 0

            const score = Math.min(10, Math.max(0, ((ratio - floor) / (ceiling - floor)) * 10))

            return { score: Math.round(score * 10) / 10, ratio: Math.round(ratio * 1000) / 1000 }
        }

        /**
         * Exposure, measured on the face core and with a *median* anchor.
         *
         * Why not mean around mid-grey:
         * - A well-exposed face in daylight sits around 145-215: skin is
         *   intrinsically brighter than 128. Anchoring to 128 punished every
         *   correctly exposed portrait.
         * - Mean is dragged down by dark hair and eye sockets that the face
         *   box unavoidably includes. The median ignores those outliers.
         * - Counting near-white pixels as "clipped" flagged highlights on
         *   skin (forehead, cheeks) as blown, even when nothing was lost.
         *
         * Now only real losses cost points: a median far from the healthy
         * 110-215 band, or a meaningful share of truly blown (>= 250) or
         * crushed (<= 6) pixels.
         */
        const exposure = (context, w, h) => {
            const { data } = context.getImageData(0, 0, w, h)
            const pixels = w * h
            const gray = new Float32Array(pixels)
            let blown = 0
            let crushed = 0

            for (let i = 0; i < pixels; i++) {
                const l = data[i * 4] * 0.299 + data[i * 4 + 1] * 0.587 + data[i * 4 + 2] * 0.114
                gray[i] = l
                if (l >= 250) blown++
                if (l <= 6) crushed++
            }

            const median = Array.from(gray).sort((a, b) => a - b)[Math.floor(pixels / 2)]
            const blownPct = (blown / pixels) * 100
            const crushedPct = (crushed / pixels) * 100

            let score = 10

            // Below 110 the face reads as underexposed; above 215 as washed.
            if (median < 110) {
                score -= (110 - median) * 0.12
            }

            if (median > 215) {
                score -= (median - 215) * 0.12
            }

            // Small highlight or shadow areas are normal; only real losses
            // (more than 2% of the face) start to cost points.
            if (blownPct > 2) {
                score -= (blownPct - 2) * 0.25
            }

            if (crushedPct > 2) {
                score -= (crushedPct - 2) * 0.25
            }

            return {
                score: Math.round(Math.min(10, Math.max(0, score)) * 10) / 10,
                median: Math.round(median),
                blown: Math.round(blownPct * 10) / 10,
                crushed: Math.round(crushedPct * 10) / 10,
            }
        }

        /**
         * Weighted overall score, with a focus gate.
         *
         * Sharpness carries half the weight because focus is what kills a
         * portrait: a perfectly lit, eyes-open frame that is out of focus is
         * still unusable. Without the gate, the samples that scored 0.1-1.1 on
         * sharpness still reached 6.3 overall, because exposure and eyes were
         * near perfect and outvoted the only failing metric.
         *
         * So blur caps the verdict: no amount of good exposure rescues a soft
         * photo. When eyes could not be measured (landmarker unavailable), the
         * weights are redistributed instead of inventing an eye score.
         */
        const overall = (sharp, light, eyes) => {
            const score = eyes === null
                ? sharp.score * 0.7 + light.score * 0.3
                : sharp.score * 0.5 + light.score * 0.2 + eyes.score * 0.3

            const floored = sharp.score < 3
                ? Math.min(score, 4.4)
                : (sharp.score < 5 ? Math.min(score, 6.4) : score)

            return Math.round(floored * 10) / 10
        }

        // Eyes: "is the subject blinking". The landmarker blendshapes are a
        // direct signal (0 = wide open, 1 = fully shut). Two eyes are averaged,
        // and the score only drops when both are clearly shut: one eye lower
        // than the other is a wink or a turned head, not a bad portrait.
        //
        // Thresholds measured on the studio samples: open eyes sit at
        // 0.05-0.18, so 0.35 is safely closed and 0.55 is a full blink.
        const eyesOpen = (blinkLeft, blinkRight) => {
            const blink = (blinkLeft + blinkRight) / 2

            if (blink >= 0.55) {
                return { score: 0, blink: Math.round(blink * 100) / 100 }
            }

            if (blink <= 0.35) {
                return { score: 10, blink: Math.round(blink * 100) / 100 }
            }

            const score = 10 - ((blink - 0.35) / 0.2) * 10

            return { score: Math.round(score * 10) / 10, blink: Math.round(blink * 100) / 100 }
        }

        /**
         * Extra observations taken from the same blendshape pass the eyes
         * already need. They are **informational only**: they never change the
         * score, they just tell the photographer what the subject was doing.
         *
         * Thresholds calibrated on the studio samples: broad smiles land at
         * 0.49-0.94, a faint smile at 0.19-0.30, a neutral mouth at 0.00-0.04;
         * jawOpen stays under 0.07 for closed mouths; brows lowered peaks at
         * 0.29 on a serious portrait but also fires on mid-blink and heads
         * tilted down, so the threshold stays high to avoid false positives.
         */
        const expressions = (blendshapes) => {
            const pick = (name) => blendshapes.find((category) => category.categoryName === name)?.score ?? null
            const pair = (left, right) => {
                const l = pick(left)
                const r = pick(right)

                return l === null || r === null ? null : (l + r) / 2
            }

            const smile = pair('mouthSmileLeft', 'mouthSmileRight')
            const squint = pair('eyeSquintLeft', 'eyeSquintRight')
            const brows = pair('browDownLeft', 'browDownRight')

            return {
                smile: smile === null ? null : Math.round(smile * 100) / 100,
                // 0.45+ is an actual smile, 0.15-0.45 is a hint of one.
                smiling: smile !== null && smile >= 0.45,
                slightSmile: smile !== null && smile >= 0.15 && smile < 0.45,
                mouthOpen: (pick('jawOpen') ?? 0) >= 0.15,
                squinting: squint !== null && squint >= 0.6,
                serious: brows !== null && brows >= 0.45 && (smile ?? 0) < 0.15,
                browsRaised: (pick('browInnerUp') ?? 0) >= 0.3,
            }
        }

        const readRememberedState = () => {
            try {
                return window.localStorage.getItem(STORAGE_KEY) === '1'
            } catch (exception) {
                return false
            }
        }

        const rememberState = (open) => {
            try {
                window.localStorage.setItem(STORAGE_KEY, open ? '1' : '0')
            } catch (exception) {
                // Private mode: the panel still works, it just forgets.
            }
        }

        return {
            previewUrl,
            originalUrl,
            imageUrl,
            fullUrl,
            open: false,
            state: 'idle',
            error: null,
            faces: [],
            selected: 0,
            rating: null,
            showBreakdown: false,
            running: false,

            // True while the untouched original is being fetched / re-measured
            // after the fast preview pass already gave a result.
            loadingOriginal: false,

            // Which source the current crops came from, so a reopened panel
            // does not redo the slow pass.
            source: 'preview',

            // Object URLs handed to the <img> tags; revoked when replaced so
            // a long culling session does not leak memory.
            objectUrls: [],

            init() {
                if (readRememberedState()) {
                    this.show()
                }
            },

            /**
             * Object URLs live in the document, not in the component: revoke
             * them when Livewire swaps the viewer to the next photo, otherwise
             * a long culling session keeps every crop of every photo in memory.
             */
            destroy() {
                this.objectUrls.forEach((url) => URL.revokeObjectURL(url))
                this.objectUrls = []
            },

            show() {
                this.open = true
                rememberState(true)
                this.$dispatch('closeup-opened')

                if (this.state === 'idle' || this.state === 'error') {
                    this.detect()
                }
            },

            /**
             * Toggle the panel from the C shortcut.
             *
             * Guarded so typing a comment never opens the panel: the key
             * presses inside inputs, textareas and contenteditable fields are
             * ignored, exactly like the existing F/DEL shortcuts.
             */
            toggleByKey(event) {
                const target = event?.target

                if (target instanceof Element && target.closest('input, textarea, select, [contenteditable]')) {
                    return
                }

                if (this.open) {
                    this.close()

                    return
                }

                this.show()
            },

            close() {
                this.open = false
                this.showBreakdown = false
                rememberState(false)
                this.$dispatch('closeup-closed')
            },

            async detect() {
                if (this.running) {
                    return
                }

                this.running = true
                this.state = 'loading'
                this.error = null
                this.loadingOriginal = false

                try {
                    const preview = await loadImage(this.previewSource)

                    this.state = 'detecting'

                    const faces = await this.findFaces(preview)

                    if (faces.length === 0) {
                        this.faces = []
                        this.selected = 0
                        this.state = 'no-face'

                        return
                    }

                    // Fast pass on the preview, so the panel shows something
                    // immediately and never looks empty while it works.
                    await this.applyResult(preview, faces, 'preview')

                    // Slow pass: the untouched stored file, used for the honest
                    // sharpness score. Only galleries that keep the original
                    // have it; the rest stay on the preview result shown above.
                    if (this.hasOriginal) {
                        this.loadingOriginal = true

                        const original = await loadImage(this.originalSource).catch(() => null)

                        if (original) {
                            // Crops come from whichever file carries more real
                            // pixels: the stored original is not always bigger
                            // than the CDN preview (that preview may be an
                            // upscale, or the gallery may store a resized file).
                            // The score always comes from the original.
                            const zoom = imagePixels(original) > imagePixels(preview) ? original : preview

                            await this.applyResult(
                                zoom,
                                this.faces.map(({ x, y, w, h, label }) => ({ x, y, w, h, label })),
                                'original',
                                original,
                            )
                        }

                        this.loadingOriginal = false
                    }
                } catch (exception) {
                    this.state = 'error'
                    this.error = 'cors-or-load'
                } finally {
                    this.running = false
                }
            },

            /**
             * Detect faces on the preview and return normalised boxes. The
             * preview is already in the browser cache, so this pass is instant.
             */
            async findFaces(preview) {
                const scale = Math.min(1, DETECT_MAX / Math.max(preview.naturalWidth, preview.naturalHeight))
                const detectCanvas = canvasFrom(preview, 0, 0, preview.naturalWidth, preview.naturalHeight,
                    preview.naturalWidth * scale, preview.naturalHeight * scale)

                const normalize = (result) => (result.detections ?? []).map((detection, index) => {
                    const box = detection.boundingBox

                    return {
                        x: (box.originX ?? 0) / detectCanvas.width,
                        y: (box.originY ?? 0) / detectCanvas.height,
                        w: (box.width ?? 0) / detectCanvas.width,
                        h: (box.height ?? 0) / detectCanvas.height,
                        label: `Face ${index + 1}`,
                    }
                })

                try {
                    const faces = normalize((await detector()).detect(detectCanvas.canvas))

                    if (faces.length > 0) {
                        return faces
                    }

                    // Full range misses extreme close-ups where the face
                    // overflows the frame; short range picks those up.
                    return normalize((await fallbackDetector()).detect(detectCanvas.canvas))
                } catch (exception) {
                    this.error = 'detector-unavailable'

                    return []
                }
            },

            /**
             * Build the face crops and the rating.
             *
             * `cropSource` is whichever file carries the most real pixels, so
             * the zoom is as crisp as the gallery allows. `ratingSource` is the
             * untouched original whenever it exists, because a CDN preview may
             * be an upscale of a smaller file and would always read as sharp.
             *
             * @param {HTMLImageElement|ImageBitmap} source     Image to crop.
             * @param {{x,y,w,h,label}[]}           faces      Normalised boxes.
             * @param {'preview'|'original'}        origin     Which pass this is.
             * @param {HTMLImageElement|null}       ratingFrom Override for rating.
             */
            async applyResult(source, faces, origin, ratingFrom = null) {
                const previous = this.objectUrls
                const fresh = []
                const prepared = []

                for (const face of faces) {
                    const crop = cropForStage(source, face)
                    const cropUrl = await toObjectUrl(crop.canvas)
                    const thumbUrl = await toObjectUrl(cropForThumb(source, face).canvas)
                    fresh.push(cropUrl, thumbUrl)

                    prepared.push({
                        ...face,
                        crop: cropUrl,
                        thumb: thumbUrl,
                        cropWidth: crop.width,
                        cropHeight: crop.height,
                    })
                }

                this.objectUrls = fresh
                previous.forEach((url) => URL.revokeObjectURL(url))

                this.source = origin
                this.faces = prepared
                this.selected = Math.min(this.selected, prepared.length - 1)

                await this.measure(ratingFrom ?? source, faces[0])

                this.state = 'ready'
            },

            /**
             * Rate one face: sharpness + exposure on the canonical sample, and
             * eyes from the landmarker on a padded face crop.
             *
             * The landmarker runs on its own crop, enlarged to ~640 px, because
             * it misses faces that are small inside the full frame (verified:
             * 3 of 10 studio samples produced no landmarks on the frame, all 10
             * succeeded on the crop).
             */
            async measure(source, face) {
                // Exposure reads the inner face region: hair, shoulders and
                // background in the padded box skew the histogram far more
                // than the skin does.
                const core = faceSample(source, face)

                const light = exposure(core.context, core.width, core.height)

                let eyes = null
                let signs = null
                let points = null

                try {
                    const detection = (await landmarker()).detect(cropForLandmarks(source, face).canvas)

                    const blendshapes = detection.faceBlendshapes?.[0]?.categories ?? []

                    const pick = (name) => blendshapes.find((category) => category.categoryName === name)?.score ?? null

                    const left = pick('eyeBlinkLeft')
                    const right = pick('eyeBlinkRight')

                    if (left !== null && right !== null) {
                        eyes = eyesOpen(left, right)
                    }

                    // Free extra data from the same pass: never scored, only
                    // shown, so the photographer sees what the subject did.
                    signs = expressions(blendshapes)

                    // Landmarks are normalised to the padded crop; convert them
                    // back to the source image so the eye band can be cut from
                    // the original, not from an upscaled preview.
                    const normalised = detection.faceLandmarks?.[0]
                    const box = landmarksBox(face)

                    points = normalised
                        ? normalised.map((point) => ({
                            x: box.x + point.x * box.w,
                            y: box.y + point.y * box.h,
                        }))
                        : null
                } catch (exception) {
                    // Landmarker unavailable (offline, or the model did not
                    // load): fall back to the face-wide score and the
                    // redistributed weights below.
                    eyes = null
                    signs = null
                    points = null
                }

                // Sharpness: prefer the eye band (photographers focus there and
                // it has twice the dynamic range), fall back to the face.
                const band = points ? eyeBand(source, points) : null

                const sharp = band
                    ? { ...sharpness(band.context, band.width, band.height, SHARPNESS_RANGE), zone: 'eyes' }
                    : { ...sharpness(core.context, core.width, core.height, FACE_SHARPNESS_RANGE), zone: 'face' }

                this.rating = { sharp, exposure: light, eyes, signs, overall: overall(sharp, light, eyes) }
            },

            retry() {
                this.state = 'idle'
                this.detect()
            },

            select(index) {
                this.selected = index
            },

            get selectedFace() {
                return this.faces[this.selected] ?? null
            },

            /**
             * Colour of the score pill, so the verdict is readable at a glance
             * while culling: green keep, amber maybe, red reject.
             */
            get ratingTone() {
                const score = this.rating?.overall ?? 0

                if (score >= 7) {
                    return 'bg-brand-green hover:bg-brand-green-deep'
                }

                if (score >= 4.5) {
                    return 'bg-amber-500 hover:bg-amber-600'
                }

                return 'bg-red-600 hover:bg-red-700'
            },

            /**
             * Same verdict as text, for the tooltip and the accessibility label.
             */
            get ratingToneLabel() {
                const score = this.rating?.overall ?? 0

                if (score >= 7) {
                    return 'keep'
                }

                if (score >= 4.5) {
                    return 'maybe'
                }

                return 'reject'
            },

            /** Detection source: the preview already used by the viewer. */
            get previewSource() {
                return this.previewUrl || this.imageUrl || this.fullUrl
            },

            /** Rating + zoom source: native pixels, no CDN resize params. */
            get originalSource() {
                return this.originalUrl || ''
            },

            /**
             * Only galleries with the original file stored have a second pass.
             * The original URL carries no CDN resize params, so a stored file
             * is distinguishable from the 2048 preview by the query string.
             */
            get hasOriginal() {
                const original = this.originalSource

                return original !== '' && ! original.includes('?') && original !== this.previewSource
            },

            // The stage shows the cropped face when there is one and falls
            // back to the whole photo otherwise. Both are <img> sources, so
            // object-fit (never background-size) decides the fit.
            get stageSource() {
                return this.selectedFace?.crop ?? this.fullUrl
            },

            /**
             * Never let the browser blow a small crop up into mush.
             *
             * A face that is only 180 px tall in the stored file cannot fill a
             * 512 px tall panel without turning blocky. The crop is drawn at
             * most at 2x its source pixels (divided by the device pixel
             * ratio), centred, so the face stays crisp and the dark stage
             * around it reads as a deliberate zoom view.
             *
             * Big crops collapse to the stage size through max-width /
             * max-height, so they simply fill it.
             */
            get stageStyle() {
                const face = this.selectedFace

                if (! face || ! face.cropWidth || ! face.cropHeight) {
                    return ''
                }

                const dpr = Math.max(1, window.devicePixelRatio || 1)
                const cap = 2

                return [
                    'width: ' + Math.round((face.cropWidth / dpr) * cap) + 'px',
                    'height: ' + Math.round((face.cropHeight / dpr) * cap) + 'px',
                    'max-width: 100%',
                    'max-height: 100%',
                ].join('; ')
            },
        }
    }
}

Alpine.data('closeupPanel', window.closeupPanelFactory)
