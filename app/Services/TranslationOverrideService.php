<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Reads and writes admin translation overrides.
 *
 * Overrides are stored as JSON files in storage/app/translations/{locale}.json
 * and are registered with the translation loader after lang/. This lets an
 * admin change app and email copy at runtime without writing repository files.
 * Only edited keys are stored.
 */
class TranslationOverrideService
{
    /**
     * Locales editable from the admin panel.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(config('translation_overrides.locales', ['en', 'es']));
    }

    /**
     * Directory that holds the override files.
     */
    public function directory(): string
    {
        return (string) config('translation_overrides.path', storage_path('app/translations'));
    }

    /**
     * Override file for a locale.
     */
    public function path(string $locale): string
    {
        $this->ensureLocale($locale);

        return rtrim($this->directory(), '/').'/'.$locale.'.json';
    }

    /**
     * Default translations shipped in the repository for the locale.
     *
     * @return array<string, string>
     */
    public function source(string $locale): array
    {
        $this->ensureLocale($locale);

        $path = lang_path($locale.'.json');

        if (! File::exists($path)) {
            return [];
        }

        $translations = json_decode(File::get($path), true);

        if (! is_array($translations)) {
            throw new RuntimeException("Translation file [{$path}] contains an invalid JSON structure.");
        }

        return $translations;
    }

    /**
     * Admin overrides for the locale.
     *
     * @return array<string, string>
     */
    public function overrides(string $locale): array
    {
        $path = $this->path($locale);

        if (! File::exists($path)) {
            return [];
        }

        $overrides = json_decode(File::get($path), true);

        if (! is_array($overrides)) {
            throw new RuntimeException("Translation override file [{$path}] contains an invalid JSON structure.");
        }

        return $overrides;
    }

    /**
     * Source translations merged with the admin overrides.
     *
     * @return array<string, string>
     */
    public function effective(string $locale): array
    {
        return array_replace($this->source($locale), $this->overrides($locale));
    }

    /**
     * Effective value for a key, or null when the locale has no translation.
     */
    public function value(string $locale, string $key): ?string
    {
        return $this->effective($locale)[$key] ?? null;
    }

    /**
     * Whether the key has an admin override in the locale.
     */
    public function isOverridden(string $locale, string $key): bool
    {
        return array_key_exists($key, $this->overrides($locale));
    }

    /**
     * Every key known by either locale, sorted naturally.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->locales() as $locale) {
            $keys += $this->effective($locale);
        }

        $keys = array_keys($keys);
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        return $keys;
    }

    /**
     * Save one or more keys for one or more locales.
     *
     * Null, empty and unchanged values remove the override so the file only
     * contains real changes.
     *
     * @param  array<string, array<string, ?string>>  $valuesByLocale
     */
    public function save(array $valuesByLocale): void
    {
        foreach ($valuesByLocale as $locale => $values) {
            $this->ensureLocale($locale);

            $overrides = $this->overrides($locale);
            $source = $this->source($locale);

            foreach ($values as $key => $value) {
                $default = $source[$key] ?? ($locale === 'en' ? $key : null);

                if ($value === null || trim($value) === '' || ($default !== null && $value === $default)) {
                    unset($overrides[$key]);
                } else {
                    $overrides[$key] = $value;
                }
            }

            $this->write($locale, $overrides);
        }
    }

    /**
     * Remove the override for a key in every locale.
     */
    public function reset(string $key): void
    {
        foreach ($this->locales() as $locale) {
            $overrides = $this->overrides($locale);
            unset($overrides[$key]);
            $this->write($locale, $overrides);
        }
    }

    /**
     * Placeholders used by a translation string (for example ":name").
     *
     * @return list<string>
     */
    public function placeholders(string $value): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $value, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Persist the override file, or delete it when there is nothing to store.
     *
     * @param  array<string, string>  $overrides
     */
    protected function write(string $locale, array $overrides): void
    {
        $path = $this->path($locale);

        if ($overrides === []) {
            if (File::exists($path)) {
                File::delete($path);
            }

            return;
        }

        ksort($overrides, SORT_NATURAL | SORT_FLAG_CASE);

        File::ensureDirectoryExists($this->directory());

        $contents = json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($contents === false) {
            throw new RuntimeException('Could not encode translation overrides as JSON.');
        }

        $temporaryPath = $path.'.tmp';

        if (file_put_contents($temporaryPath, $contents.PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException("Could not write translation override file [{$temporaryPath}].");
        }

        if (! rename($temporaryPath, $path)) {
            throw new RuntimeException("Could not move translation override file to [{$path}].");
        }
    }

    /**
     * Reject locales that are not editable from the admin panel.
     */
    protected function ensureLocale(string $locale): void
    {
        if (! in_array($locale, $this->locales(), true)) {
            throw new RuntimeException("Unsupported translation locale [{$locale}].");
        }
    }
}
