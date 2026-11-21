<?php

/**
 * Turns Livewire single-file components into named classes PHPStan can analyse.
 *
 * Several page traits (gallery wall, sharing, uploads, queue manager, photo
 * viewer) are only used by `⚡*.blade.php` components. PHPStan cannot read
 * Blade, so without this step it reports those traits as "used zero times"
 * and never analyses their bodies: a missing `use` for an attribute such as
 * `#[On]` would pass silently. `composer analyse` runs this first and
 * analyses the generated copies in `storage/framework/phpstan-sfc`.
 *
 * Usage: php tests/PHPStan/extract-sfc-components.php
 */
$root = dirname(__DIR__, 2);
$output = $root.'/storage/framework/phpstan-sfc';

/** Traits whose only consumers are single-file components. */
$traitPattern = '/^\s*use \\\\?App\\\\Traits\\\\(Gallery\\\\|Queue\\\\|Concerns\\\\ManagesPhotoViewer|RevealsGalleryPhoto|SummarizesGalleryTabs)/m';

if (! is_dir($output) && ! mkdir($output, 0775, true) && ! is_dir($output)) {
    fwrite(STDERR, "Cannot create {$output}\n");
    exit(1);
}

array_map('unlink', glob($output.'/*.php') ?: []);

$components = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views', FilesystemIterator::SKIP_DOTS));
$generated = 0;

foreach ($components as $file) {
    $path = $file->getPathname();

    if (! str_ends_with($path, '.blade.php') || ! str_contains(basename($path), '⚡')) {
        continue;
    }

    $source = (string) file_get_contents($path);

    if (preg_match($traitPattern, $source) !== 1) {
        continue;
    }

    // Keep only the PHP block that precedes the template.
    $php = '';

    foreach (token_get_all($source) as $token) {
        if (is_array($token) && $token[0] === T_CLOSE_TAG) {
            break;
        }

        if (is_array($token) && $token[0] === T_OPEN_TAG) {
            continue;
        }

        $php .= is_array($token) ? $token[1] : $token;
    }

    $relative = substr($path, strlen($root) + 1);
    $name = 'Sfc_'.trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', str_replace(['resources/views/', '⚡', '.blade.php'], '', $relative)), '_');

    // Livewire exposes #[Computed] methods as read-only properties; declare
    // them so `$this->photoIds` is checked instead of reported as undefined.
    $computed = computedMethods($php);

    foreach (usedTraitFiles($php, $root) as $traitFile) {
        $computed = [...$computed, ...computedMethods((string) file_get_contents($traitFile))];
    }

    $docblock = $computed === []
        ? ''
        : "/**\n".implode('', array_map(fn (string $method) => " * @property-read mixed \${$method}\n", array_unique($computed)))." */\n";

    $class = preg_replace('/new\s+(#\[.*?\]\s*)?class\s+extends/s', $docblock.'$1final class '.$name.' extends', $php, 1);
    $class = preg_replace('/\}\s*;\s*$/', '}', rtrim((string) $class));

    file_put_contents(
        "{$output}/{$name}.php",
        "<?php\n\nnamespace PHPStanSfc;\n\n// Generated from {$relative} for static analysis only. Do not edit.\n{$class}\n",
    );

    $generated++;
}

fwrite(STDOUT, "Generated {$generated} single-file component classes for PHPStan.\n");

/**
 * Names of the methods marked #[Computed] in a PHP source.
 *
 * @return list<string>
 */
function computedMethods(string $source): array
{
    preg_match_all('/#\[Computed[^\]]*\]\s*(?:#\[[^\]]*\]\s*)*(?:public|protected)\s+function\s+(\w+)/', $source, $matches);

    return $matches[1];
}

/**
 * Files of the App\\Traits traits a component uses.
 *
 * @return list<string>
 */
function usedTraitFiles(string $source, string $root): array
{
    preg_match_all('/^\s*use \\\\?(App\\\\Traits\\\\[\w\\\\]+);/m', $source, $matches);

    $files = [];

    foreach ($matches[1] as $class) {
        $file = $root.'/app/'.str_replace('\\', '/', substr($class, strlen('App\\'))).'.php';

        if (is_file($file)) {
            $files[] = $file;
        }
    }

    return $files;
}
