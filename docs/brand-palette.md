# Brand Palette and App Shell

## Overview

The authenticated Picstome interface uses the brand palette shipped with the
logo. The tokens live in `resources/css/app.css` under the Tailwind v4 `@theme`
block, so every utility (`bg-brand-green`, `text-brand-coral`, …) is available
in any view without extra configuration.

Guest pages (public profile, portfolio, shared galleries, login) are **not**
painted with the Picstome palette: they keep the team accent chosen in
`/branding/styling`, so a studio can brand its public site.

## Palette

| Token                   | Value     | Usage                                            |
| ----------------------- | --------- | ------------------------------------------------ |
| `--color-brand-green`   | `#316d61` | Primary actions, selected navigation, headings    |
| `--color-brand-green-deep` | `#27564c` | Sidebar group headings, links on light surfaces  |
| `--color-brand-ink`     | `#322f30` | Body copy, secondary buttons, revenue card       |
| `--color-brand-ink-strong` | `#2a2625` | Text on light-accent surfaces                    |
| `--color-brand-coral`   | `#ee6958` | Destructive actions, warning callouts, accents   |
| `--color-brand-coral-deep` | `#d95746` | Coral hover state                                |
| `--color-brand-sage`    | `#c6d8af` | Dark mode accents, hover tints                   |
| `--color-brand-sage-soft` | `#eef4e6` | Sidebar surface (light mode)                    |
| `--color-brand-cream`   | `#fff9ec` | Text on green/coral surfaces                     |
| `--color-brand-paper`   | `#fbfcf9` | Page and sidebar surface (light mode)            |
| `--color-brand-night`   | `#1b2320` | Page surface (dark mode)                         |
| `--color-brand-night-deep` | `#161e1b` | Sidebar surface (dark mode)                     |

## App shell vs. public pages

The app shell is marked with the `app-shell` class on `<body>`. It remaps the
Flux accent variables to the Picstome palette:

- light mode → accent `#316d61`, foreground white
- dark mode → accent `#c6d8af`, foreground `#2a2625`

Public pages keep the team accent. `layouts/guest.blade.php` resolves it from
the `color` slot and falls back to the Picstome palette when a team has no
accent selected, so an unbranded studio still looks consistent.

Three helper classes build on top of the accent variables and are meant to be
used with `variant="primary"` on Flux buttons:

- `.btn-ink` – secondary action, ink background with cream text
- `.btn-coral` – destructive action, coral background with cream text
- `.btn-outline-brand` – secondary action that must stay visible on white

## Sidebar navigation

The sidebar navigation scrolls on its own (`data-sidebar-scroll`) so the logo,
the search field and the account footer stay pinned while the menu is long.
Expanding a group scrolls the newly revealed items into view, so no option is
hidden below the fold. The logo links back to the dashboard.

## Confirmation dialogs

`resources/views/components/confirm-modal.blade.php` renders the shared
confirmation dialog used by every destructive action (delete customer, delete
gallery, disconnect Stripe, …). It replaced the native `window.confirm()`
dialogs, so the confirmation matches the rest of the interface.

`resources/views/components/selection-limit-modal.blade.php` is the notice shown
to visitors that reach the photo selection limit of a shared gallery.

Both are plain Blade components: include them and pass the Livewire action
through the `method` prop, or use the default slot when the confirmation has to
run Alpine code instead of a Livewire action.

## Camera upload and original size

Cameras upload photos to a gallery over FTP/SFTP. Whether the photos are
stored at their original size is a gallery setting
(`galleries.keep_original_size`) shared by:

- the create gallery modal,
- the edit gallery modal,
- the camera upload modal.

`User::galleryDefaults()` remembers the last chosen value per user, so new
galleries keep it. Galleries created by the camera flow (the *Connect camera*
action in the galleries list and `FtpAccount::resolveActiveGallery()`) are
created with that preference as well, otherwise they would silently fall back
to the database default and store resized photos.

The camera upload modal and the edit gallery modal stay in sync without a
reload: changing one updates the other immediately.
