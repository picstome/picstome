# Closeup Panel (#312)

Desktop-only face zoom + quality rating on the single-photo viewer, **for the
photographer only** — one component (`<x-closeup-panel>`), one Alpine factory,
no flag, no client exposure.

## Data

- **Photographer only**: the owner viewer renders the trigger and the panel
  unconditionally (any image, any gallery); the shared client viewer renders
  neither the panel nor the trigger nor the script. There is no gallery flag,
  no share-modal switch and no user preference — those were removed.
- Ratings are **not persisted**: sharpness / exposure / eyes are computed per
  view in the browser. No server route, no new storage, no PII leaves the page.

## Opening behaviour

- **Nothing runs until the trigger is pressed.** Before the click there is
  0 bytes of MediaPipe traffic and no extra image request: the panel only holds
  an Alpine data object, the detector/WASM/model are imported from `detect()`,
  which is only reached from the button. Verified in the browser: 0 MediaPipe
  requests before the click, 6 after it.
- The trigger is `<x-closeup-panel-button>`, docked in the photo dock and
  hidden below `md` (desktop only, matching the panel).
- The same `C` shortcut opens and closes it; keys pressed inside inputs,
  textareas, selects or contenteditable fields are ignored, so typing a
  comment never opens the panel.
- Once opened, the state is remembered in `localStorage`
  (`picstome-closeup-open`). Navigating to the next photo (or reloading)
  reopens it automatically, which is what culling needs.
- Closing it clears the flag, so the next photo starts closed again.

## Two-phase loading

1. **Fast pass** on the 2048 preview the viewer already downloaded: detect the
   face, crop it and show the rating immediately (~100–500 ms).
2. **Slow pass** on the untouched stored file (`original_url`, no CDN resize
   params): retake the crop at native resolution and recompute the sharpness.
   While this runs the footer shows *Loading from original…*.

Galleries that do not keep the original file skip the second pass: they stay
on the preview result and never show the spinner.

## Sharpness

- Never measured on the CDN preview: `?width=2048` can be an **upscale** of a
  smaller original, so its pixels are invented and always read as sharp.
- Measured on a canonical sample: the face region is resampled so the face is
  exactly **200 px** tall. Laplacian variance depends on resolution, so without
  a canonical size the same photo scores differently when the face is 147 px
  or 664 px tall.
- Scored on high-frequency energy **relative to the face's own contrast**
  (`laplacianRms / grayscaleStd`), which cancels both the resolution and the
  subject-contrast bias.
- Every measurement crop is resampled with **smoothing always on**, even when
  the crop is being enlarged. This is the fix that made the metric trustworthy:
  before, a small blurred eye band was blown up with nearest-neighbour and that
  blockiness was measured as detail — an 86 px band outscored a 473 px one on
  the same session. Smoothing costs the same for every photo, so only real detail
  moves the ratio.
- Measured on the studio samples with that resample, eye band at 240 px wide:
  out of focus **0.15–0.20**, soft **0.23–0.31**, sharp **0.44–0.57**. That
  range maps onto 0–10. The face-wide fallback (when landmarks fail) uses
  0.17–0.55.
- Blur **caps the verdict**: sharpness carries 50 % of the score (70 % when the
  eyes could not be measured), and a sharpness score under 3 caps the overall at
  4.4, under 5 caps it at 6.4. Without the cap a perfectly exposed, eyes-open
  but out-of-focus frame still reached 6.3, because light and eyes outvoted the
  only failing metric.
- Eyes are now a real measurement, not a guess: **MediaPipe Face Landmarker**
  (same Apache-2.0 package, same lazy loading) runs on a padded face crop
  enlarged to ~640 px and returns blendshapes. `eyeBlinkLeft/Right`
  (0 = wide open, 1 = shut) are averaged; ≥ 0.55 scores 0, ≤ 0.35 scores 10,
  linear in between. Verified: 3 of 10 studio samples produced no landmarks on
  the full frame, all 10 succeeded on the crop — which is why the landmarker
  gets its own crop.
- Weights when eyes are measurable: sharpness 50 % / exposure 20 % / eyes 30 %.
  When the landmarker cannot load (offline, blocked CDN) eyes are `null` and the
  weights fall back to sharpness 70 % / exposure 30 %; the breakdown popover
  says so explicitly.

## Exposure

Measured on the **inner face core** (the detected box inset by 12 %), never on
the padded crop: hair, shoulders and background dominate the padded histogram,
so a blonde subject on a black backdrop read "underexposed" and a dark subject
on a white backdrop read "blown out".

The anchor is the **median**, not the mean:

- A correctly exposed face sits around **145–215**: skin is intrinsically
  brighter than mid-grey 128, so anchoring at 128 punished every good portrait.
- Mean is dragged down by dark eyebrows and eye sockets that the box
  unavoidably includes; the median ignores those outliers.
- Only real losses cost points: median outside 110–215, or more than 2 % of
  pixels at ≥ 250 (blown) or ≤ 6 (crushed). Highlights on a forehead are normal
  and no longer count as clipping.

Calibrated on the studio samples, where every well-exposed portrait scores
9.1–10 while the previously flagged frame `33647` moved from **3.8 → 10**.

## Extra observations (not scored)

The same blendshape pass that measures the eyes yields expression data for
free, so the panel shows them as small badges under the rating. They never
change the score.

- **Smiling** (`mouthSmile ≥ 0.45`) / **Slight smile** (0.15–0.45)
- **Mouth open** (`jawOpen ≥ 0.15`)
- **Squinting** (`eyeSquint ≥ 0.6`)
- **Serious** (`browDown ≥ 0.45` and no smile)
- **Brows raised** (`browInnerUp ≥ 0.3`)

Thresholds are calibrated on the studio samples (broad smiles 0.49–0.94,
faint smiles 0.19–0.30, neutral 0.00–0.04).

## Zoom rendering

- Crops are taken at native resolution (capped at 2048 px only to bound
  memory) and handed over as **PNG blob object URLs**, not JPEG data URLs:
  base64 + JPEG re-encoding is what made the face look blocky.
- The stage is an `<img>` with `object-fit: contain`, never a CSS
  `background-size` crop, so nothing can ever be stretched. The crop is shaped
  to the panel column ratio (320 × 560) so it fills the column.
- Big crops fill the stage. Small crops are capped at 2x their own pixels and
  centred: a face that only has 180 px in the stored file cannot fill a 512 px
  panel without turning to mush, so it fills ~75 % and keeps every real pixel.
- Square thumbnails (112 px) for the multi-face strip, also via `object-fit`.
- Object URLs are revoked when replaced and on `destroy()`, so a long culling
  session does not leak memory.

## Face detection (FOSS)

- **MediaPipe Tasks Vision FaceDetector, Apache License 2.0** (free and open
  source), pinned at **1.2.2**. `0.10.21` throws internal errors with the
  full-range model; `0.10.31` does not exist on jsDelivr.
- Model: **`blaze_face_full_range`** at confidence 0.5. It detected 12/12 of
  the studio samples, while `short_range` (built for extreme close-ups) missed
  3 of them. `short_range` at 0.3 is kept as a fallback for faces that overflow
  the frame.
- Detection runs on a 1280 px downscale of the preview, never on the original.
- The factory lives in `resources/views/partials/closeup-panel-script.blade.php`
  and is included from the `@script` of the owner viewer. It is cached as
  `window.closeupPanelFactory`, so Livewire's script re-run after
  `wire:navigate` re-registers `Alpine.data` without dropping the detector
  promise.
- If the CDN/WASM is unreachable or the image CDN lacks CORS headers, the
  panel shows a first-class error state with a retry.

## UI

### Photo dock

The single-photo viewer no longer has a labelled toolbar: every action is an
icon-only button in a frosted **dock** centred at the bottom of the photo
(`<x-photo-dock>`, `<x-dock-button>`, `<x-dock-divider>`). Icon-only was a
deliberate choice: nine labelled buttons did not fit on a laptop and could not
be scanned while culling. The tooltip carries the label plus the shortcut
("Closeup panel (C)", "Delete (DEL)").

- Groups are separated by dividers and ordered by intent: **previous / next** →
  **fav / cover** → **closeup / comments / annotate / versions** → **download**
  → **delete**. Filled tones are reserved for state: `ink` (dark) only when an
  action has content behind it (comments exist, versions exist), red only for
  the selected favourite and for delete.
- **Previous / next live in the dock too**, so the photo is not framed by two
  floating arrows. The `x-ref` names stay the same, so `←` / `→` keep working.
- The Closeup trigger is desktop-only (`max-md:hidden`), matching the panel: on
  mobile the button would open nothing.
- The destructive action is always **last and tinted red**, so it cannot be hit
  by accident. The favorite button turns **red** when the photo is favorited
  (`favorite` tone, `bg-red-600`, deliberately different from the coral delete
  tone), so the current state reads instantly and is never confused with the
  destructive action.
- The dock is shared by both viewers (DRY). The client version only shows the
  actions the client is allowed to use.
- At 375 px the pill fits: the buttons wrap onto a second row inside a
  rounded-3xl container instead of being clipped. `overflow-x-auto` was a trap —
  the last button (delete) was pushed outside the scroll viewport, so the mobile
  test could not reach it. Wrapping keeps every button inside the window at
  every width, and `sm:` keeps the single-row pill on desktop.
- Only the **labelled** "back to gallery" button stays at the top-left.
- Filled tones paint the whole 36 x 36 button (desktop) / 32 px (mobile); the
  previous TallStackUI button painted a shorter block inside the pill, which is
  what looked like an uneven fill.

### Score colours

The rating pill is tinted by verdict so a session can be scanned at a glance:

| Score | Colour | Verdict |
|---|---|---|
| ≥ 7 | brand green | **keep** |
| 4.5 – 6.9 | amber | **maybe** |
| < 4.5 | red | **reject** |


- Desktop only: the component lives inside `hidden md:block`, and the toolbar
  trigger uses `max-md:hidden`.
- Docked on the right of the photo stage, full height (`w-80`, `z-30`), with
  header (photo name + close), stage, multi-face strip and rating footer.
- Display strategy: the aside binds its inline `display`; every inner block
  toggles with `x-bind:class` (`flex` / `hidden`), never `x-show`, so a stale
  inline style cannot fight a Tailwind display utility.
### Photographer only

The panel is a **culling tool for the photographer**, never for the client:

- It renders only in the owner viewer (`pages::galleries.photos.show`). The
  shared client viewer has no panel, no trigger and no script include, so no
  rating, no score and no face crop is ever exposed to a client.
- There is **no gallery flag and no share option**: the old
  `is_closeup_enabled` column, its migration, the share-modal switch, the
  gallery toolbar toggle and the `User::shareGalleryDefaults()` key were all
  removed. Nothing about scoring is configurable or shareable.
- Both viewers keep the same dock for the actions the audience is allowed to
  use; only the owner dock has the Closeup button (additionally
  desktop-only).

### File info panel

A separate floating panel at the top-right (`<x-photo-info>`, `lg:` only) shows
the filename and, on open, the facts stored on the photo row: file size, type,
resolution, aspect ratio and upload date. **Photographer only**: the shared
client viewer does not include it.

It is a native `<details>` element, so it needs no Alpine for the toggle; the
only script is the open/closed state, remembered in `localStorage`
(`picstome-photo-info-open`), so checking the same data across a shoot does not
require reopening it on every photo.

Rows whose value is null are skipped instead of shown as "unknown". `uploaded`
is the only date: `uploaded_at` and `created_at` hold the same instant for a
photo that was never re-uploaded, and showing both duplicated the same line
under two labels. Resolution and ratio come from `photos.width/height`, which
`ProcessPhoto` now writes from the file it actually stores (the API upload path
already did).

### Grid thumbnails (mini dock)

Each gallery thumbnail carries the same frosted pill as the viewer dock, only
smaller (`<x-photo-mini-dock>` + `x-dock-button compact`), **centred at the
bottom of the thumbnail** exactly like the viewer dock: favourite, cover and
delete for the photographer; favourite and download for the client. The old
grid used bare TallStackUI overlay buttons tucked in the corner, which made the
same actions look like a different product one click apart.

On the public gallery the **download** action takes the `accent` tone: it is
filled with `--color-accent`, the colour the photographer picked for their
public branding, so the main client action carries the studio's own accent.

### Annotation controls

- **Annotate** uses the same rule and colour as comments: filled dark (`ink`)
  when the photo already has annotations (there is something to open), quiet
  otherwise. While the mode runs, the button is replaced by **Exit annotation
  mode** in sage, so there is always a visible way out. Both live in the dock,
  and the dock therefore stays visible during annotation mode (only zoom hides
  it).
- **Versions** is a quiet dock button placed immediately **left of Download**,
  so the two file actions sit together and read as one group. It does not get a
  filled tone: the count is already in its tooltip.
- **Comments** is filled only when the photo actually has comments, the same
  rule as the favourite heart turning red only when it is selected.
- **Hide / show annotations** renders only when the photo has annotations (and,
  on the shared viewer, only when the gallery has annotations enabled), and it
  uses the same "filled means there is something here" rule as the comments
  button: pins visible = **green** (`bg-brand-green`) with the `eye` icon,
  "Hide annotations"; pins hidden = **sage** (`active` tone, a different green)
  with the `eye-slash` icon, "Show annotations". One button occupies the slot,
  so the dock never shifts.
  The choice is remembered in `localStorage` (`picstome-annotations-visible`),
  the same key the overlay reads on mount, so hiding the pins keeps them hidden
  while moving photo to photo in both viewers instead of resetting on every
  render. Entering annotation mode re-shows the pins and persists that too.

## Tests

`tests/Feature/CloseupPanelTest.php` covers the photographer-only contract:
the panel always renders in the owner viewer, never in the shared viewer (no
panel, no trigger, no factory), the share modal has no closeup option, and the
panel is fed the untouched original URL (no `width=`, no `format=`).

## Rollback

Pure revert: nothing is persisted. No column, no migration, no stored rating
and no client-facing flag, so reverting the branch removes the panel cleanly.
