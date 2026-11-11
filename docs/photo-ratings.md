# Photo ratings and gallery sorting

Photographer-only star ratings (1–5), the filters and sorts built on top of
them, and how they interact with favourites. Clients never see or change a
rating: shared galleries and the public portfolio keep heart-only favourites.

## Data model

`photos.rating` is a nullable `unsignedTinyInteger` (1–5). `null` means
"not rated". One rating per logical photo, shared by the whole team, exactly
like `favorited_at`.

Indexes added with the column:

- `(gallery_id, rating)`
- `(gallery_id, archived_at, uploaded_at)`
- `(gallery_id, archived_at, size)`

## Rating rules

| Action | `rating` | `favorited_at` |
| --- | --- | --- |
| Press `1`–`4` / click a 1–4 star | set | untouched |
| Press `5` / click the 5th star | set | set (auto-heart if empty) |
| Press `0` / Clear rating | `null` | `null` (un-heart) |
| Heart on | `5` | set |
| Heart off | `null` | `null` |

Ratings 1–4 leave the heart untouched, so a client's heart and a
photographer's mid rating can coexist. Five stars and the heart are the same
selection internally: hearting sets five stars, and un-hearting clears the
rating. Clients only have the heart, so their selection also lands as five
stars for the photographer.

In the viewer, re-pressing the current star is a no-op: `0` is the only clear
gesture, so a rating cannot un-heart a photo by accident. The dock popover also
has an explicit **Clear rating** action. In the filter popover, clicking the
active star (or **Any rating**) clears the filter.

In the single-photo viewer:

- `1`–`5` save the rating, flash a short star HUD and advance to the next photo
  in the current filtered/sorted set.
- `0` clears the rating and the heart and stays on the photo.
- The stars live in a compact dock button that opens upwards; most culling is
  done with the keyboard, so the dock stays short.
- Clearing inside the Favorites filter captures the neighbour before mutating,
  so the viewer still moves to the next favourite instead of stranding the
  photographer on a photo that left the set.

## Filters

`GalleryPhotoQuery` (`app/Services/GalleryPhotoQuery.php`) is the single source
of truth. The gallery wall and the viewer both build it from the same URL
parameters, so arrow keys walk exactly the list on screen.

| URL param | Values | Default |
| --- | --- | --- |
| `rating` | `1`–`5` (exact) | none |
| `favorites` | `1` | off |
| `sort` | `name`, `date`, `rating`, `favorites`, `size`, `versions` | `name` |
| `direction` | `asc`, `desc` | field default |

The rating filter is **exact**: selecting 3 stars shows only 3-star photos.
Comparisons (`>=`, `<=`) were removed because they were hard to read at a
glance.

Filters and sorts apply to the **Photos** tab. The **Favorites** tab is a
shortcut that applies the favourites filter on that same grid (`?favorites=1`);
it does not have its own panel. Archived and Comments keep their own natural
order.

## Sorting

| Field | Column | Default direction |
| --- | --- | --- |
| Name | natural sort (`SORT_NATURAL`) | A–Z |
| Date added | `COALESCE(uploaded_at, created_at)` | newest first |
| Rating | `rating`, unrated last | highest first |
| Favorites | `favorited_at`, unfavourited last | most recent first |
| File size | `size` | largest first |
| Versions | `version_count` | most versions first |

Unrated and unfavourited rows always sort last, whatever the direction.

## Owner UI

- The tabs and the filter/sort controls share one **sticky row**. The controls
  are compact icon buttons with tooltips, not a full-width pill.
- The rating filter is a popover with five stars; the active filter shows the
  exact star count. Clicking the active star (or **Any rating**) clears it.
- The viewer dock has **one** rating button (star + current value) that opens
  the five-star picker upwards, plus an explicit **Clear rating** action.
- Archiving or restoring shows a short HUD (Archived / Restored); the viewer
  waits ~500ms before moving on, so the confirmation is visible.
- The photo info card lists the stars, comment count, favourite date, whether
  it is the cover, and the archive date.
- Clicking **Photos** clears the rating and favourites filters.
- **Sync** and **Download** are icon-only tabs (tooltip and `aria-label` carry
  the name). **Export favorites** appears as an icon right after Favorites and
  as a labelled button in the top right of the grid; both open the same
  favourite list modal.
- Gallery thumbnails show versions and comments top-left, and only the earned
  rating stars top-right (small, no pill).
- The cover action uses the `photo` icon with a tooltip, never a star, so it
  cannot be confused with a rating.

## API v1

`GET /api/v1/manifest?gallery={ulid}` returns per photo:

```json
{
    "favorited_at": "2026-11-10T10:00:00+00:00",
    "is_favorite": true,
    "rating": 4
}
```

`rating` is `null` when the photographer has not rated the photo. The manifest
`rev` changes automatically because rating updates touch `photos.updated_at`.

## Caching

The ordered id list for every filter/sort combination is cached for one hour
under `gallery:{id}:q:{version}:{signature}`. `Photo` bumps a monotonic
`gallery:{id}:cache_version` on every create, delete and update, which makes all
old combinations unreachable at once; stale entries expire on their own. This
keeps rapid culling through the viewer from re-plucking thousands of ids per
keystroke.

## Visibility

- Owner gallery grid and viewer: stars, filters and sorts.
- Shared gallery and portfolio: no stars, no rating filter, no rating in any
  payload.
- Regression tests assert the shared grid and viewer never render
  `data-photo-rating*` or `data-gallery-filter-bar`.
