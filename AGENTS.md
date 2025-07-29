# AGENTS.md

## Build, Lint, and Test Commands

- **PHP tests:**
    - Run all: `php artisan test` or `./vendor/bin/pest`
    - Run single test: `php artisan test --filter=TestName`
- **JS/CSS build:**
    - Dev: `npm run dev`
    - Build: `npm run build`
- **Formatting:**
    - PHP: `./vendor/bin/pint` (if installed)
    - JS/Blade: `npm run format` (uses Prettier)
- **Run a single JS/Blade formatter:**
    - `npx prettier --write path/to/file`

## Code Style Guidelines

- **Imports:**
    - PHP: Group by vendor, then app, then blank line. Alphabetize within groups.
    - JS: Use ES modules, single quotes.
- **Formatting:**
    - 4 spaces, LF line endings, trim trailing whitespace, 120 char line width.
    - Always use semicolons in JS.
- **Naming:**
    - Classes: `StudlyCase`, methods/variables: `camelCase`, constants: `UPPER_SNAKE_CASE`.
- **Types:**
    - Use PHP type hints and return types.
- **Error Handling:**
    - Use try/catch for risky operations, log errors, never silence exceptions.
- **Other:**
    - Use strict comparisons, avoid magic strings, prefer explicitness.

See `.prettierrc` and `.editorconfig` for more formatting details.
