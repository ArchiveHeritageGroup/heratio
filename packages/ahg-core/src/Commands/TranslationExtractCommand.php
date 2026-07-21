<?php

/**
 * TranslationExtractCommand — harvest __() keys from the codebase into lang/en.json.
 *
 * Heratio had no extraction step, so UI written with correct __() wrapping never
 * reached the locale files. Laravel's __() falls back to rendering the key when
 * no entry exists, so those strings display as English and nothing reports a
 * problem — 13,914 keys were missing when this was first measured, leaving
 * en.json covering roughly 27% of the interface (#1420).
 *
 * This walks the source tree for __('...'), trans('...') and @lang('...') with a
 * literal string argument, and writes any key missing from lang/{locale}.json
 * using the key as its own value. For English that is the correct translation,
 * so the pass is deterministic and needs no MT.
 *
 * Keys present in the locale file but no longer found in code are reported, never
 * deleted: a static scan cannot see keys built at runtime, so removal would drop
 * live translations.
 *
 * Examples:
 *   php artisan ahg:translation-extract --dry-run
 *   php artisan ahg:translation-extract
 *   php artisan ahg:translation-extract --report-unused
 *   php artisan ahg:translation-extract --dry-run --fail-above=0   # CI gate
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class TranslationExtractCommand extends Command
{
    protected $signature = 'ahg:translation-extract
                            {--locale=en : Locale file to populate (only en is safe to auto-fill)}
                            {--path=* : Directories to scan, relative to base_path (default: packages, app, resources, routes)}
                            {--report-unused : Also list locale keys no longer referenced in code}
                            {--top=15 : How many packages to show in the per-package breakdown}
                            {--fail-above= : Exit non-zero if the missing-key count exceeds this (use 0 in CI)}
                            {--dry-run : Report only, do not write the locale file}';

    protected $description = 'Extract __() keys from the codebase into lang/{locale}.json (#1420)';

    /** Directories never worth walking. */
    private const SKIP_DIRS = ['node_modules', 'vendor', '.git', 'storage', 'bootstrap'];

    public function handle(): int
    {
        $locale = (string) $this->option('locale');
        $dryRun = (bool) $this->option('dry-run');

        $roots = $this->option('path') ?: ['packages', 'app', 'resources', 'routes'];

        $langFile = base_path("lang/{$locale}.json");
        if (! is_file($langFile)) {
            $this->error("Locale file not found: {$langFile}");

            return self::FAILURE;
        }

        $existing = json_decode((string) file_get_contents($langFile), true);
        if (! is_array($existing)) {
            $this->error("Could not parse {$langFile} as JSON.");

            return self::FAILURE;
        }

        // ── Scan ───────────────────────────────────────────────────────────────
        $keys = [];        // key => occurrence count
        $keyOwners = [];   // key => owning module (first seen)
        $filesScanned = 0;

        foreach ($roots as $root) {
            $dir = base_path($root);
            if (! is_dir($dir)) {
                continue;
            }
            foreach ($this->sourceFiles($dir) as $file) {
                $filesScanned++;
                $contents = @file_get_contents($file);
                if ($contents === false) {
                    continue;
                }
                foreach ($this->extractKeys($contents) as $key) {
                    $keys[$key] = ($keys[$key] ?? 0) + 1;
                    $keyOwners[$key] ??= $this->moduleOf($file);
                }
            }
        }

        $missing = array_diff_key($keys, $existing);
        $unused = array_diff_key($existing, $keys);

        // ── Report ─────────────────────────────────────────────────────────────
        $this->info(sprintf('Scanned %s files across: %s', number_format($filesScanned), implode(', ', $roots)));
        $this->line(sprintf('  distinct keys in code      : %s', number_format(count($keys))));
        $this->line(sprintf('  entries in lang/%s.json    : %s', $locale, number_format(count($existing))));
        $this->line(sprintf('  <fg=yellow>missing from locale file   : %s</>', number_format(count($missing))));
        $this->line(sprintf('  locale keys unused in code : %s', number_format(count($unused))));

        if (count($keys) > 0) {
            $covered = count($keys) - count($missing);
            $this->line(sprintf('  coverage                   : %.1f%%', 100 * $covered / count($keys)));
        }

        if (! empty($missing)) {
            $byModule = [];
            foreach (array_keys($missing) as $key) {
                $module = $keyOwners[$key] ?? '(unknown)';
                $byModule[$module] = ($byModule[$module] ?? 0) + 1;
            }
            arsort($byModule);

            $this->newLine();
            $this->line('Missing keys by module:');
            foreach (array_slice($byModule, 0, (int) $this->option('top'), true) as $module => $count) {
                $this->line(sprintf('  %-40s %s', $module, number_format($count)));
            }
        }

        if ($this->option('report-unused') && ! empty($unused)) {
            $this->newLine();
            $this->warn(sprintf('%s locale keys are not referenced in code. NOT removed - a static scan', number_format(count($unused))));
            $this->warn('cannot see keys built at runtime, so these may still be live. Review by hand:');
            foreach (array_slice(array_keys($unused), 0, 40) as $key) {
                $this->line('  '.$key);
            }
            if (count($unused) > 40) {
                $this->line(sprintf('  ... and %s more', number_format(count($unused) - 40)));
            }
        }

        // ── Write ──────────────────────────────────────────────────────────────
        if (empty($missing)) {
            $this->newLine();
            $this->info('Nothing to add - every key in the codebase is present in the locale file.');
        } elseif ($dryRun) {
            $this->newLine();
            $this->comment(sprintf('Dry run - %s keys would be added to lang/%s.json.', number_format(count($missing)), $locale));
        } else {
            if ($locale !== 'en') {
                // For any other locale the key is NOT a valid translation, it is
                // just English text that would masquerade as translated content
                // and inflate the coverage figures.
                $this->newLine();
                $this->error("Refusing to auto-fill lang/{$locale}.json: the key is only a correct value for English.");
                $this->line('Extract into en.json, then translate. See #1416.');

                return self::FAILURE;
            }

            $merged = $existing;
            foreach (array_keys($missing) as $key) {
                $merged[$key] = $key;
            }
            ksort($merged);

            $encoded = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                $this->error('json_encode failed; locale file left untouched.');

                return self::FAILURE;
            }

            // Write via a temp file so a failure cannot truncate the locale file.
            $tmp = $langFile.'.tmp';
            if (@file_put_contents($tmp, $encoded."\n") === false || ! @rename($tmp, $langFile)) {
                @unlink($tmp);
                $this->error("Could not write {$langFile}.");

                return self::FAILURE;
            }

            $this->newLine();
            $this->info(sprintf('Added %s keys to lang/%s.json (now %s entries).',
                number_format(count($missing)), $locale, number_format(count($merged))));
        }

        $failAbove = $this->option('fail-above');
        if ($failAbove !== null && $failAbove !== '' && count($missing) > (int) $failAbove) {
            $this->newLine();
            $this->error(sprintf('Missing-key count %s exceeds --fail-above=%s.', number_format(count($missing)), $failAbove));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Pull translation keys with a literal string argument out of one file.
     *
     * Deliberately ignores:
     *   - __($variable) and __(SOME_CONST), which cannot be resolved statically
     *   - 'package::key' namespaced lookups, which resolve to a package's own files
     *   - 'file.key' dotted references, which are PHP array-file translations
     *     rather than JSON keys
     *
     * @return string[]
     */
    private function extractKeys(string $contents): array
    {
        $pattern = '/(?:\b__|\btrans|@lang)\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s';

        if (! preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $keys = [];
        foreach ($matches as $match) {
            $quote = $match[1];
            $raw = $match[2];

            // Undo escaping for the quote style actually used.
            $key = str_replace(['\\'.$quote, '\\\\'], [$quote, '\\'], $raw);
            $key = trim($key);

            if ($key === '' || mb_strlen($key) > 200) {
                continue;
            }
            if (str_contains($key, '::')) {
                continue;
            }
            // Dotted key with no spaces, e.g. validation.required - a PHP array
            // translation reference, not a JSON key.
            if (! str_contains($key, ' ') && preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/i', $key)) {
                continue;
            }

            $keys[$key] = true;
        }

        return array_keys($keys);
    }

    /**
     * Owning module for reporting: the package name, or the top-level directory.
     */
    private function moduleOf(string $file): string
    {
        $relative = ltrim(str_replace(base_path(), '', $file), '/');
        $parts = explode('/', $relative);

        if (($parts[0] ?? '') === 'packages' && isset($parts[1])) {
            return $parts[1];
        }

        return $parts[0] ?? '(root)';
    }

    /**
     * @return \Generator<string>
     */
    private function sourceFiles(string $dir): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                static fn ($current) => ! ($current->isDir() && in_array($current->getFilename(), self::SKIP_DIRS, true))
            )
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                yield $file->getPathname();
            }
        }
    }
}
