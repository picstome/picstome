<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        // 1. Find all Blade files
        $bladeFiles = collect(glob(resource_path('views/**/*.blade.php')));
        // Fallback for non-recursive glob on some systems
        if ($bladeFiles->isEmpty()) {
            $bladeFiles = collect(
                iterator_to_array(
                    new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator(resource_path('views'))
                    )
                )
            )
            ->filter(fn($file) => $file->isFile() && str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn($file) => $file->getPathname());
        }

        // 2. Extract translation keys from Blade files
        $keys = collect();
        $pattern = "/__\(['\"](.*?)['\"]\)/";
        $patternLang = "/@lang\(['\"](.*?)['\"]\)/";
        foreach ($bladeFiles as $file) {
            $contents = file_get_contents($file);
            preg_match_all($pattern, $contents, $matches1);
            preg_match_all($patternLang, $contents, $matches2);
            $keys = $keys->merge($matches1[1] ?? [])->merge($matches2[1] ?? []);
        }
        $keys = $keys->unique()->values();

        // 3. For each lang JSON file, add missing keys
        $langPath = base_path('lang');
        $langFiles = collect(glob($langPath . '/*.json'));
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
        $this->info('Translation sync complete.');
    }
}
