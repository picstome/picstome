# Archiving photos (#247)

Archiving is a **photographer-only** way to take a photo out of the client's
view **without deleting it**. Nothing is removed from disk: the row and every
file stay where they are, so restoring is instant and free.

## Data

- `photos.archived_at` (nullable timestamp, indexed with `gallery_id`).
  `null` = live, a value = archived at that moment. Mirrors `favorited_at`, so
  the existing favourite plumbing carries over unchanged.
- No backfill: every existing photo starts live (`null`).
- The external API **still returns archived photos** (`PhotoResource` exposes
  `archived_at`), because the companion app has to be able to show and
  unarchive them; a filter on the client side is enough.

## What archiving hides

Archived photos disappear from every client-facing surface, and from the
photographer's own live lists:

| Surface | Behaviour |
|---|---|
| Owner grid tabs (All / Favorites / Comments) | archived photos excluded |
| Client shared gallery | excluded, and the photo page **404s** |
| Public portfolio grid and photo page | excluded, and the photo page **404s** |
| Shared photo download and shared PDF route | **404** |
| Gallery ZIP (`all` and `favorites` variants) | excluded |
| Gallery media counts / download size | excluded |
| Cover photo | cleared when the current cover is archived |
| External API manifest | **included**, with `archived_at` |

The owner keeps a dedicated **Archived** tab, placed **immediately after
*Photos*** and rendered only when at least one photo is archived
(`data-archived-tab`).

The archived id list is deliberately **not cached**: its *presence* is what
decides whether that tab exists, so a cached copy made the tab appear late (or
not at all) right after archiving — exactly when the photographer looks for it.
It is a single pluck on an indexed column and only the owner runs it.

## Actions

- **Archive / Unarchive one photo**: dock button immediately **left of
  Delete** in the owner viewer, and on each gallery thumbnail. Instant, no
  modal (it is reversible), exactly like the favourite heart.
- **`A` shortcut** in the owner viewer: **toggles** the state of the current
  photo and jumps on, the same rhythm as `F`.
  - Live photo: archives it and moves to the next live one.
  - Archived photo (opened from the Archived tab, which passes
    `?navigateArchived=1`): **restores it** and moves to the next archived one,
    so one key both removes and puts photos back in the selection.
  - Neighbours are resolved *before* the change, because archiving/unarchiving
    changes the list the photo belongs to.
  - Restoring the **last** archived photo leaves the Archived tab empty, and
    the tab only exists while something is archived. In that case the viewer
    redirects to **Photos** instead of the (now unreachable) Archived tab.
  It ignores modifiers and never fires while typing in an input, textarea,
  select or contenteditable field.
- **Dock Unarchive** keeps the photographer on the current photo. When it restores
  the last archived photo it also returns to **Photos**, because the Archived
  tab disappears with its last item.
- **Delete all archived photos**: button inside the Archived tab. It opens a
  confirmation modal whose message states that **the action CANNOT be undone**
  and that the files and originals are gone for good. Requesting only opens the
  modal; nothing is deleted until the method runs.

## Rollback

The migration only adds a nullable column and an index, and `down()` drops
both. Reverting the code makes archived photos visible again (nothing is lost)
— it does not delete any photo.
