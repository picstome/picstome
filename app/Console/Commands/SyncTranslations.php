<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncTranslations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translations:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs translation keys in Blade files with lang JSON files.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bladeFiles = $this->findBladeFiles();

        $translationKeys = $this->extractTranslationKeys($bladeFiles);

        $this->syncKeysToLangFiles($translationKeys);

        $this->info('Translation sync complete.');
    }

    /**
     * Recursively find all Blade files in the views directory.
     */
    private function findBladeFiles()
    {
        $files = collect(glob(resource_path('views/**/*.blade.php')));

        if ($files->isEmpty()) {
            $files = collect(
                iterator_to_array(
                    new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator(resource_path('views'))
                    )
                )
            )
            ->filter(fn($file) => $file->isFile() && str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn($file) => $file->getPathname());
        }

        return $files;
    }

    /**
     * Extract all translation keys from Blade files.
     */
    private function extractTranslationKeys(Collection $bladeFiles)
    {
        $keys = collect();

        $patterns = [
            "/__\(['\"](.*?)['\"]\)/",    // __('...')
            "/@lang\(['\"](.*?)['\"]\)/", // @lang('...')
        ];

        foreach ($bladeFiles as $file) {
            $contents = file_get_contents($file);

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $contents, $matches);
                $keys = $keys->merge($matches[1] ?? []);
            }
        }

        return $keys->unique()->values();
    }

    /**
     * Add missing translation keys to each lang JSON file.
     */
    private function syncKeysToLangFiles(Collection $keys)
    {
        $langFiles = collect(glob(base_path('lang/*.json')));

        foreach ($langFiles as $langFile) {
            $json = json_decode(file_get_contents($langFile), true) ?? [];

            $added = 0;

            foreach ($keys as $key) {
                if (!array_key_exists($key, $json)) {
                    $json[$key] = $key;
                    $added++;
                }
            }

            if ($added > 0) {
                ksort($json);
                file_put_contents($langFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $this->info("Added $added missing keys to " . basename($langFile));
            } else {
                $this->info("No missing keys in " . basename($langFile));
            }
        }
    }
}
