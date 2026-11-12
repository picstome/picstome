{{--
    Alpine factories for video playback.

    Plain statements, included inside the viewers' @script block exactly like
    partials/closeup-panel-script: Livewire re-runs @script after every
    `wire:navigate`, so the factories are cached on `window` and re-registering
    them with Alpine.data is harmless.

    Nothing here loads for images or PDFs: the viewers only include this
    partial when the current photo is a video, or when the version comparison
    modal compares two videos.

    `videoPlayer`  - one video with a custom control bar, comment markers on
                     the rail and the position remembered in the browser.
    `videoCompare` - two videos driven by one control bar, so the same second
                     of two cuts is on screen side by side.
--}}
if (! window.formatVideoTime) {
    /**
     * Format seconds as mm:ss, or h:mm:ss past the first hour. Mirrors
     * PhotoComment::formatTimecode() so comment chips and the live rail read
     * the same.
     */
    window.formatVideoTime = (seconds) => {
        const total = Math.max(0, Math.floor(Number(seconds) || 0))
        const hours = Math.floor(total / 3600)
        const minutes = Math.floor((total % 3600) / 60)
        const remaining = total % 60
        const pad = (value) => String(value).padStart(2, '0')

        return hours > 0
            ? `${hours}:${pad(minutes)}:${pad(remaining)}`
            : `${pad(minutes)}:${pad(remaining)}`
    }

    /**
     * localStorage throws in private modes and behind some intranet policies.
     * A playback position is a nicety, never a reason to break the player.
     */
    window.readVideoPosition = (key) => {
        try {
            const stored = window.localStorage.getItem(key)

            return stored === null ? null : Number(stored)
        } catch (exception) {
            return null
        }
    }

    window.writeVideoPosition = (key, seconds) => {
        try {
            window.localStorage.setItem(key, String(seconds))
        } catch (exception) {
            // Forgetting the position is acceptable.
        }
    }

    window.clearVideoPosition = (key) => {
        try {
            window.localStorage.removeItem(key)
        } catch (exception) {
            // Nothing to clean up.
        }
    }
}

if (! window.videoPlayerFactory) {
    /**
     * Options: persistKey (localStorage key), startAt (seconds from a deep
     * link) and markers (timestamped comments drawn on the rail).
     */
    window.videoPlayerFactory = ({ persistKey = null, startAt = null, markers = [] } = {}) => ({
        persistKey,
        startAt,
        markers,
        duration: 0,
        currentTime: 0,
        paused: true,
        muted: false,
        volume: 1,
        rate: 1,
        buffering: false,
        ready: false,
        errored: false,
        dragging: false,
        dragValue: 0,
        lastPersisted: null,

        init() {
            const video = this.$refs.video

            if (! video) {
                return
            }

            this.volume = video.volume
            this.muted = video.muted

            const onLoadedMetadata = () => {
                this.duration = Number.isFinite(video.duration) ? video.duration : 0
                this.restorePosition()
                this.ready = true
                this.$dispatch('video-metadata', {
                    width: video.videoWidth,
                    height: video.videoHeight,
                })
            }

            video.addEventListener('loadedmetadata', onLoadedMetadata)

            // A cached video can finish loading its metadata before Alpine
            // gets here, in which case the event above never fires and the
            // custom bar would stay hidden behind native controls forever.
            if (video.readyState >= 1) {
                onLoadedMetadata()
            }

            video.addEventListener('play', () => { this.paused = false })
            video.addEventListener('pause', () => {
                this.paused = true
                this.persist()
            })
            video.addEventListener('ended', () => {
                this.paused = true
                this.clearPosition()
            })
            video.addEventListener('timeupdate', () => {
                if (! this.dragging) {
                    this.currentTime = video.currentTime
                }

                this.persist()
            })
            video.addEventListener('waiting', () => { this.buffering = true })
            video.addEventListener('playing', () => { this.buffering = false })
            video.addEventListener('canplay', () => { this.buffering = false })
            video.addEventListener('error', () => { this.errored = true })

            // `beforeunload` is unreliable on mobile; `visibilitychange` and
            // `pagehide` cover navigating away and closing the tab.
            window.addEventListener('pagehide', () => this.persist())
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    this.persist()
                }
            })
        },

        get progress() {
            return this.duration > 0 ? (this.currentTime / this.duration) * 100 : 0
        },

        get displayCurrentTime() {
            return window.formatVideoTime(this.dragging ? this.dragValue : this.currentTime)
        },

        get displayDuration() {
            return window.formatVideoTime(this.duration)
        },

        get canPlay() {
            return this.ready && ! this.errored
        },

        get railMarkers() {
            return this.markers
                .filter((marker) => Number(marker.time) > 0 && this.duration > 0)
                .map((marker) => ({
                    ...marker,
                    left: Math.min(100, Math.max(0, (Number(marker.time) / this.duration) * 100)),
                }))
        },

        /**
         * Replace the comment markers drawn on the player rail after a
         * timestamped comment is added or deleted. Livewire morphs the markup
         * but Alpine only evaluates `x-data` once, so the new list has to be
         * pushed at runtime instead of re-reading the attribute.
         */
        setMarkers(markers) {
            this.markers = Array.isArray(markers) ? markers : []
        },

        /**
         * A deep link (`startAt`) wins over the remembered position: opening
         * a comment must land exactly where the comment was made.
         */
        restorePosition() {
            const video = this.$refs.video

            if (! video || ! this.duration) {
                return
            }

            let target = null

            if (this.startAt !== null && this.startAt !== undefined) {
                target = Number(this.startAt)
            } else if (this.persistKey) {
                target = window.readVideoPosition(this.persistKey)
            }

            if (target === null || Number.isNaN(target) || target <= 0) {
                return
            }

            // Within the last five seconds means effectively finished;
            // replaying it would show the final frame and free the position.
            if (target >= this.duration - 5) {
                return
            }

            video.currentTime = target
            this.currentTime = target
        },

        toggle() {
            const video = this.$refs.video

            if (! video) {
                return
            }

            if (video.paused) {
                video.play().catch(() => { this.paused = true })
            } else {
                video.pause()
            }
        },

        seek(seconds) {
            const video = this.$refs.video

            if (! video || ! this.duration) {
                return
            }

            const target = Math.min(Math.max(0, Number(seconds) || 0), this.duration)

            video.currentTime = target
            this.currentTime = target
            this.dragging = false
            this.persist()
        },

        seekBy(delta) {
            this.seek((this.$refs.video?.currentTime ?? 0) + Number(delta))
        },

        startDragging() {
            this.dragging = true
            this.dragValue = this.currentTime
        },

        updateDrag(event) {
            this.dragValue = Number(event.target.value)
        },

        commitDrag(event) {
            this.seek(event?.target?.value ?? this.dragValue)
        },

        cycleRate() {
            const rates = [1, 1.25, 1.5, 2, 0.5, 0.75]
            const index = rates.indexOf(this.rate)
            this.setRate(rates[(index + 1) % rates.length])
        },

        setRate(rate) {
            this.rate = rate

            if (this.$refs.video) {
                this.$refs.video.playbackRate = rate
            }
        },

        toggleMute() {
            const video = this.$refs.video

            if (! video) {
                return
            }

            video.muted = ! video.muted
            this.muted = video.muted
        },

        setVolume(event) {
            const video = this.$refs.video

            if (! video) {
                return
            }

            video.volume = Number(event.target.value)
            video.muted = video.volume === 0
            this.volume = video.volume
            this.muted = video.muted
        },

        /**
         * Fullscreen the whole player, not the button that was pressed.
         *
         * `this.$el` inside the Alpine component is the component root, but a
         * handler bound on a child button resolves `$el` to that button, which
         * is what used to happen: the browser fullscreened the 32px icon. The
         * root is located through the `data-video-player` hook instead, and
         * the native video element is the fallback for browsers that refuse
         * element fullscreen on a wrapper.
         */
        async toggleFullscreen() {
            const player = this.$refs.video?.closest('[data-video-player]') ?? this.$el

            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen()

                    return
                }

                if (player.requestFullscreen) {
                    await player.requestFullscreen()

                    return
                }

                await this.$refs.video?.requestFullscreen?.()
            } catch (exception) {
                // Fullscreen can be refused (permissions, iframe sandbox);
                // the element still plays normally.
            }
        },

        /**
         * Keep the last played second in this browser. Storage is written on a
         * whole-second boundary, not on every `timeupdate` (4x per second).
         */
        persist() {
            const video = this.$refs.video

            if (! this.persistKey || ! video || ! this.duration) {
                return
            }

            const seconds = Math.floor(video.currentTime)

            if (seconds <= 0 || seconds >= this.duration - 2 || seconds === this.lastPersisted) {
                return
            }

            this.lastPersisted = seconds
            window.writeVideoPosition(this.persistKey, seconds)
        },

        clearPosition() {
            if (this.persistKey) {
                window.clearVideoPosition(this.persistKey)
            }
        },

        /**
         * The playhead is the anchor of a timestamped comment: pause and hand
         * the exact second to Livewire so the composer can store it.
         */
        captureForComment() {
            const video = this.$refs.video

            if (! video) {
                return
            }

            video.pause()

            this.$wire?.setPendingTime(Number(video.currentTime.toFixed(2)))
        },

        /**
         * Space toggles playback and arrows scrub while the player has focus.
         * Scope is the player element, so the viewer's photo navigation keys
         * keep working until the visitor is actually watching.
         */
        handleKeydown(event) {
            if (event.target.closest('input, textarea, select')) {
                return
            }

            const key = event.key

            if (key === ' ' || key === 'k') {
                event.preventDefault()
                event.stopPropagation()
                this.toggle()
            } else if (key === 'arrowleft') {
                event.preventDefault()
                event.stopPropagation()
                this.seekBy(event.shiftKey ? -1 : -5)
            } else if (key === 'arrowright') {
                event.preventDefault()
                event.stopPropagation()
                this.seekBy(event.shiftKey ? 1 : 5)
            } else if (key === 'm') {
                event.preventDefault()
                event.stopPropagation()
                this.toggleMute()
            }
        },

        /**
         * The viewers navigate photos on `keyup`, which is a separate event
         * and would still reach window after `handleKeydown` stopped the
         * keydown. Swallow the matching keyup so an arrow press scrubs the
         * video instead of jumping to the next photo.
         */
        handleKeyup(event) {
            if (event.target.closest('input, textarea, select')) {
                return
            }

            if ([' ', 'k', 'm', 'arrowleft', 'arrowright'].includes(event.key)) {
                event.stopPropagation()
            }
        },
    })
}

if (! window.videoCompareFactory) {
    /**
     * One control bar for two videos. The left player owns the audio and the
     * timeline; the right one follows it, corrected past a quarter second of
     * drift, which is below the perceptual threshold for a cut comparison.
     *
     * `items`, `left` and `right` are getters into the surrounding Alpine
     * scope (the versions modal), so switching either side re-syncs the
     * sources without duplicating the selection state.
     *
     * Nothing is fetched until the modal actually opens: the stage lives in
     * the modal markup, hidden inside a teleport, and assigning `src` on mount
     * would download two videos nobody asked for.
     */
    window.videoCompareFactory = ({ items, left, right }) => ({
        duration: 0,
        currentTime: 0,
        paused: true,
        rate: 1,
        muted: false,
        ready: false,
        errored: false,
        loading: false,
        started: false,

        init() {
            // The selects live in the surrounding Alpine scope, and the modal
            // is teleported to <body>, so no DOM event bubbles down here. The
            // parent dispatches on window when either side changes. The
            // listener reference is kept so `destroy()` can drop it when the
            // modal closes.
            this.refreshHandler = () => {
                if (this.started) {
                    this.syncSources()
                }
            }

            window.addEventListener('video-compare-refresh', this.refreshHandler)
        },

        destroy() {
            if (this.refreshHandler) {
                window.removeEventListener('video-compare-refresh', this.refreshHandler)
            }
        },

        /**
         * First open: attach the sources. Later opens resume where the
         * comparison was left, which is what the photographer expects after
         * closing the modal by accident.
         */
        open() {
            if (this.started) {
                return
            }

            this.started = true
            this.syncSources()
        },

        /**
         * The modal is hidden with CSS, not removed from the DOM, so both
         * videos would keep playing (and downloading) unless paused here.
         */
        close() {
            this.paused = true
            this.$refs.leftVideo?.pause()
            this.$refs.rightVideo?.pause()
        },

        get leftKey() {
            return String(left())
        },

        get rightKey() {
            return String(right())
        },

        get leftItem() {
            return (items() || []).find((item) => String(item.id) === this.leftKey) ?? null
        },

        get rightItem() {
            return (items() || []).find((item) => String(item.id) === this.rightKey) ?? null
        },

        get progress() {
            return this.duration > 0 ? (this.currentTime / this.duration) * 100 : 0
        },

        get displayCurrentTime() {
            return window.formatVideoTime(this.currentTime)
        },

        get displayDuration() {
            return window.formatVideoTime(this.duration)
        },

        syncSources() {
            this.$nextTick(() => {
                const leftVideo = this.$refs.leftVideo
                const rightVideo = this.$refs.rightVideo
                const leftUrl = this.leftItem?.video_url ?? ''
                const rightUrl = this.rightItem?.video_url ?? ''

                this.loading = true
                this.ready = false
                this.duration = 0
                this.currentTime = 0
                this.paused = true

                if (leftVideo && leftUrl) {
                    leftVideo.src = leftUrl
                    leftVideo.muted = this.muted
                    leftVideo.load()
                }

                if (rightVideo && rightUrl) {
                    rightVideo.src = rightUrl
                    // Only one soundtrack: the right player is a visual
                    // reference, and doubled audio reads as an echo.
                    rightVideo.muted = true
                    rightVideo.load()
                }
            })
        },

        onPrimaryLoaded() {
            const leftVideo = this.$refs.leftVideo
            const rightVideo = this.$refs.rightVideo

            if (! leftVideo) {
                return
            }

            this.duration = Number.isFinite(leftVideo.duration) ? leftVideo.duration : 0
            this.ready = this.duration > 0
            this.loading = false
            leftVideo.playbackRate = this.rate

            if (rightVideo) {
                rightVideo.playbackRate = this.rate
            }
        },

        onSecondaryError() {
            // A right side that cannot decode must not break the comparison:
            // the left player keeps working and the error is shown in place.
            this.errored = true
        },

        toggle() {
            const leftVideo = this.$refs.leftVideo
            const rightVideo = this.$refs.rightVideo

            if (! leftVideo) {
                return
            }

            if (leftVideo.paused) {
                this.paused = false
                rightVideo?.play().catch(() => {})
                leftVideo.play().catch(() => { this.paused = true })
            } else {
                this.paused = true
                leftVideo.pause()
                rightVideo?.pause()
            }
        },

        seek(seconds) {
            const leftVideo = this.$refs.leftVideo

            if (! leftVideo || ! this.duration) {
                return
            }

            const target = Math.min(Math.max(0, Number(seconds) || 0), this.duration)

            leftVideo.currentTime = target
            this.currentTime = target

            if (this.$refs.rightVideo) {
                this.$refs.rightVideo.currentTime = Math.min(
                    target,
                    this.$refs.rightVideo.duration || target,
                )
            }
        },

        onPrimaryTimeUpdate() {
            const leftVideo = this.$refs.leftVideo
            const rightVideo = this.$refs.rightVideo

            if (! leftVideo) {
                return
            }

            this.currentTime = leftVideo.currentTime

            if (! rightVideo || rightVideo.readyState < 1) {
                return
            }

            if (Math.abs(rightVideo.currentTime - leftVideo.currentTime) > 0.25) {
                rightVideo.currentTime = leftVideo.currentTime
            }

            if (! leftVideo.paused && rightVideo.paused) {
                rightVideo.play().catch(() => {})
            }
        },

        commitDrag(event) {
            this.seek(event.target.value)
        },

        cycleRate() {
            const rates = [1, 1.25, 1.5, 2, 0.5, 0.75]
            const index = rates.indexOf(this.rate)
            this.rate = rates[(index + 1) % rates.length]

            if (this.$refs.leftVideo) {
                this.$refs.leftVideo.playbackRate = this.rate
            }

            if (this.$refs.rightVideo) {
                this.$refs.rightVideo.playbackRate = this.rate
            }
        },

        toggleMute() {
            const leftVideo = this.$refs.leftVideo

            if (! leftVideo) {
                return
            }

            leftVideo.muted = ! leftVideo.muted
            this.muted = leftVideo.muted
        },
    })
}

Alpine.data('videoPlayer', window.videoPlayerFactory)
Alpine.data('videoCompare', window.videoCompareFactory)
