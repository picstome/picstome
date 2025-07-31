# Copilot Instructions for picstome

Purpose: Help AI coding agents work effectively in this Laravel 12 + Livewire app. Keep responses concise, follow project conventions below, and prefer existing patterns shown in code and tests.

Architecture overview
- Backend: Laravel (PHP 8.3). Domain models in `app/Models` (e.g., `Photo`, `Gallery`, `Photoshoot`, `Contract`, `Team`, `User`). Policies in `app/Policies` gate access. Jobs in `app/Jobs` handle async work (e.g., `ProcessPhoto`, `DeleteFromDisk`, `ProcessPdfContract`, `NotifyContractExecuted`). Events/Listeners coordinate flows (e.g., `Events/PhotoAdded.php`, `Listeners/SchedulePhotoProcessing.php`).
- Frontend: Livewire + Blade + Tailwind. Views in `resources/views`, components in `app/Livewire/**`. Vite builds assets (`vite.config.js`, `resources/js`, `resources/css`).
- Routing: Primary HTTP routes in `routes/web.php`; console commands in `routes/console.php`. Providers configure features in `app/Providers/*` (notably `FolioServiceProvider.php` and `VoltServiceProvider.php`).
- Storage/files: Public asset symlink via `php artisan storage:link`. App writes to `storage/app` and serves via `public/storage`.
- Localization: Strings in `lang/en.json`, `lang/es.json`, and `lang/es/*`.

Key workflows
- Install: `composer install && npm install`; copy `.env`, `php artisan key:generate`.
- DB setup: SQLite by default — `touch database/database.sqlite && php artisan migrate:fresh --seed`.
- Serve + assets: `composer run dev` (starts Vite and Laravel dev server in watch). Alternatively: `npm run dev` for assets and `php artisan serve` separately.
- Tests: Use Pest. Run all: `./vendor/bin/pest`. Feature group: `./vendor/bin/pest --group=feature`. Single test: `php artisan test --filter=Name`. Test bootstrapping is in `tests/TestCase.php` and `tests/Pest.php`.
- Formatting: PHP via `./vendor/bin/pint` (if installed). JS/Blade via `npm run format` (Prettier). Follow `.editorconfig`.

Project conventions
- PHP style: 4-space indent, 120 col width, typed properties/returns, strict comparisons. Organize imports vendor→app with alpha sort. Naming: Classes StudlyCase; methods/vars camelCase; constants UPPER_SNAKE_CASE.
- Authorization: Always check policies for actions on `Photo`, `Gallery`, `Photoshoot`, `Contract`, `ContractTemplate`, and `Team`. Reference `app/Policies/*` in controllers and Livewire actions.
- Queued work: Prefer dispatching Jobs for IO-heavy tasks (image/PDF processing, deletion, notifications). See `app/Jobs/*` for patterns and test doubles.
- Files and links: When storing user files, place under `storage/app` and reference via `Storage` API. Expose only via signed routes or `public/storage` symlink where appropriate.
- Translations: Use `__()`/`@lang` and add to both `lang/en.json` and `lang/es.json` when modifying UI strings.

Common patterns with examples
- Event-driven processing: When a photo is added (`Events/PhotoAdded`), a listener schedules processing (`Listeners/SchedulePhotoProcessing`) which dispatches `Jobs/ProcessPhoto`.
- Contract flow: `Models/Contract` + `Notifications/ContractExecuted` + `Jobs/ProcessPdfContract`/`NotifyContractExecuted` coordinate signing and delivery; guard with `ContractPolicy`.
- Sharing galleries/photos: Public access uses signed/unguessable links and policies (`GalleryPolicy`, `PhotoPolicy`). Feature tests in `tests/Feature/*Shared*` illustrate expected behavior.

Files to study first
- Routes: `routes/web.php`
- Policies: `app/Policies/*.php`
- Jobs: `app/Jobs/*.php`
- Models: `app/Models/*.php`
- Tests: `tests/Feature/*.php` (define behavior and edge cases)

Testing guidance
- Prefer writing Feature tests with Pest colocated under `tests/Feature`. Use factories in `database/factories`. Boot kernel via `tests/TestCase.php` helpers. Group tests where useful (`->group('feature')`).

Build/deploy notes
- Vite builds to `public/build`. Ensure `php artisan storage:link` before serving user content.
- Environment defaults expect SQLite; configure other DBs via `.env` and `config/database.php`.

Gotchas
- Don’t bypass policies; tests assert unauthorized access paths.
- Large file ops must be queued; keep controllers/components thin and delegate to Jobs.
- Keep translations in sync across English/Spanish.

Quick commands
- All tests: `./vendor/bin/pest`
- Feature tests: `./vendor/bin/pest --group=feature`
- Dev servers: `composer run dev`
- Fresh DB: `php artisan migrate:fresh --seed`

If anything here seems unclear or you need more specifics (e.g., Livewire component conventions or job retry policies), ask and we’ll iterate.
