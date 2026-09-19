# Log in and out

Users sign in with email and password at `/login`, get bounced to `/login` when they hit a protected page while logged out, land back on that page after signing in, and sign out from the profile dropdown.

## Sub-features

- `login-success` authenticates `test@example.com` and reaches the dashboard.
- `login-failure` rejects a wrong password and shows an error on the same form.
- `guest-redirect` sends a logged-out visitor of a protected page to `/login`.
- `intended-redirect` returns to the originally requested page after login.
- `logout` ends the session from the profile dropdown.

## How to get to it (user POV)

- Open `https://app.picstome.com.test/login` and fill the email and password fields.
- Visit any protected page such as `/galleries` while logged out.
- Open the profile dropdown (top right) and choose `Logout` while logged in.

## Driving it with browser-use

Preconditions:

- `doctor.sh` prints `DOCTOR OK`.
- A fresh (logged-out) browser tab.

- **Guest redirect.** Visit `/galleries` logged out. `browser: goto https://app.picstome.com.test/galleries`. The URL ends at `/login` and the email/password form is visible. Save `01-guest-redirect.png`.
- **Login success.** Sign in as the seeded user. `browser: fill input[type="email"] = test@example.com`, `browser: fill input[type="password"] = password`, `browser: click button "Log in"`. The intended page (`/galleries`) loads with the sidebar visible (`Galleries`, `Contracts`, …), because the login form redirects to the intended URL. Save `02-login-success.png`.
- **Intended redirect.** The previous bullet already proves it when login followed the guest redirect from `/galleries`. To prove it standalone: logged out, `browser: goto .../contracts`, then log in; the URL returns to `/contracts`, not `/dashboard`.
- **Login failure.** Log out (next bullet), then `browser: fill input[type="password"] = wrong-password` and submit. The form re-renders with an error message; the URL stays on `/login`. Save `03-login-failure.png`.
- **Logout.** From any app page, `browser: click the profile dropdown trigger (top right)`, `browser: click menu item "Logout"`. The browser lands on `/login` (or the landing page) and visiting `/galleries` again redirects to `/login`.
- **Session side effect.** Confirm the session ended: `php artisan tinker --execute="echo DB::table('sessions')->count();"` before and after logout is not required to change (sessions are GC'd lazily) — the authoritative proof is the guest redirect after logout.

## Gotchas

- Only use `test@example.com`. `oliver@example.com` / `chema@example.com` own the developer's real dev data.
- `redirectIntended` means the post-login URL depends on where the user came from; assert the intended page, not a fixed `/dashboard`.
- The login form uses `wire:submit`; wait for the DOM/URL change after clicking `Log in` instead of assuming navigation.
- The login form rate-limits at 5 failed attempts per email+IP (`LoginForm::ensureIsNotRateLimited`) and then locks out for 60 seconds; use at most one wrong-password attempt per run.
