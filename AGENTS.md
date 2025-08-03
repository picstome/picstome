# AGENTS.md

## Build, Lint, and Test Commands

- **Install dependencies:** `composer install && npm install`
- **Dev servers:** `composer run dev` (Laravel + Vite) or `npm run dev` (assets) + `php artisan serve`
- **Run all tests:** `./vendor/bin/pest`
- **Run feature tests:** `./vendor/bin/pest --group=feature`
- **Run a single test:** `php artisan test --filter=TestName`
- **Fresh DB:** `php artisan migrate:fresh --seed`
- **Format PHP:** `./vendor/bin/pint` (if installed)
- **Format JS/Blade:** `npm run format` (Prettier)

## Code Style Guidelines

- **Indentation:** 4 spaces, no tabs (`.editorconfig` enforced)
- **Line endings:** LF, UTF-8, trim trailing whitespace
- **PHP:** Typed properties/returns, strict comparisons, 120 col width
- **Imports:** Vendor before app, alphabetically sorted
- **Naming:** Classes StudlyCase, methods/vars camelCase, constants UPPER_SNAKE_CASE
- **Error handling:** Always check policies for actions on models; never bypass authorization
- **Jobs:** Queue IO-heavy work (image/PDF ops, notifications)
- **Files:** Store under `storage/app`, expose via signed routes or `public/storage` symlink
- **Translations:** Use `__()`/`@lang`, update both `lang/en.json` and `lang/es.json`
- **Tests:** Prefer Pest, group by feature, use factories, see `tests/Feature/*`
- **Frontend:** Use Livewire + Blade + Tailwind, follow existing component patterns

**Reference:** See `.github/copilot-instructions.md` for detailed architecture, workflows, and gotchas.
