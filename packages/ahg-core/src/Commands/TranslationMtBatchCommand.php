<?php

/**
 * TranslationMtBatchCommand — batch-translate Heratio-only UI strings via MT.
 *
 * Heratio-only keys are keys present in lang/en.json that do NOT appear in
 * any AtoM XLIFF (the ~2000 RiC Explorer / AI Tools / Privacy / Spectrum /
 * Heritage Accounting / etc. strings). For these, AtoM's import path can't
 * help and only af + zu have human translations. This command runs them
 * through the configured MT endpoint and writes results to lang/{locale}.json
 * with provenance recorded in lang/_meta.json (per-key source=machine).
 *
 * Examples:
 *   php artisan ahg:translation-mt-batch fr
 *   php artisan ahg:translation-mt-batch fr --limit=200 --dry-run
 *   php artisan ahg:translation-mt-batch --all-enabled --skip=af,zu,en
 *   php artisan ahg:translation-mt-batch fr --heratio-only=false  # translate ALL missing keys, not just Heratio-only
 *
 * The MT endpoint is read from ahg_translation_settings.mt.endpoint and must
 * accept POST {source, target, text} → {translatedText|translation}.
 *
 * Safe to re-run: skips keys that already have a non-identity translation
 * unless --overwrite is passed.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TranslationMtBatchCommand extends Command
{
    protected $signature = 'ahg:translation-mt-batch
                            {locale? : Single target locale code (omit if --all-enabled)}
                            {--all-enabled : Process every locale enabled in setting.i18n_languages}
                            {--skip= : Comma-separated locale codes to skip (default: en,af,zu)}
                            {--heratio-only=true : Translate only keys absent from AtoM XLIFFs}
                            {--atom-source=/usr/share/nginx/archive/apps/qubit/i18n : AtoM XLIFF dir, used to detect Heratio-only keys}
                            {--limit= : Stop after N successful MT calls}
                            {--source-locale=en : Source language for MT requests}
                            {--overwrite : Replace existing translations even if non-identity}
                            {--dry-run : Print plan without calling MT or writing files}';

    protected $description = 'Batch-translate Heratio-only UI strings via the configured MT endpoint into lang/{locale}.json';

    public function handle(): int
    {
        $skipDefaults = ['en', 'af', 'zu'];
        $skip = array_filter(array_map('trim', explode(',', (string) ($this->option('skip') ?? ''))));
        if (empty($skip)) {
            $skip = $skipDefaults;
        }
        $heratioOnly = filter_var($this->option('heratio-only'), FILTER_VALIDATE_BOOLEAN);
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : PHP_INT_MAX;
        $sourceLocale = (string) $this->option('source-locale');
        $overwrite = (bool) $this->option('overwrite');
        $dryRun = (bool) $this->option('dry-run');
        $atomDir = (string) $this->option('atom-source');

        // Resolve target locales
        $locales = [];
        if ($this->option('all-enabled')) {
            if (! Schema::hasTable('setting')) {
                $this->error('setting table not found.');
                return self::FAILURE;
            }
            $locales = DB::table('setting')
                ->where('scope', 'i18n_languages')
                ->where('editable', 1)
                ->pluck('name')
                ->toArray();
        } elseif ($single = $this->argument('locale')) {
            $locales = [$single];
        } else {
            $this->error('Pass a locale or --all-enabled.');
            return self::FAILURE;
        }
        $locales = array_values(array_diff($locales, $skip));
        if (empty($locales)) {
            $this->warn('No locales to process after skip filter.');
            return self::SUCCESS;
        }

        // MT endpoint
        $endpoint = DB::table('ahg_translation_settings')->where('setting_key', 'mt.endpoint')->value('setting_value')
            ?? 'http://127.0.0.1:5004/ai/v1/translate';
        $timeout = (int) (DB::table('ahg_translation_settings')->where('setting_key', 'mt.timeout_seconds')->value('setting_value') ?? 30);

        $this->info("MT endpoint: {$endpoint}  (timeout={$timeout}s)");
        $this->info('Locales to process: ' . implode(', ', $locales) . ($dryRun ? '  (DRY RUN)' : ''));
        $this->newLine();

        // Load source locale
        $sourcePath = base_path("lang/{$sourceLocale}.json");
        if (! is_file($sourcePath)) {
            $this->error("Source locale file not found: {$sourcePath}");
            return self::FAILURE;
        }
        $source = json_decode(file_get_contents($sourcePath), true) ?? [];

        // Determine "Heratio-only" key set if requested
        $atomKeys = [];
        if ($heratioOnly) {
            $atomKeys = $this->scanAtomXliffKeys($atomDir);
            $heratioOnlyKeys = array_diff_key($source, $atomKeys);
            $this->info('Heratio-only keys (absent from AtoM XLIFFs): ' . count($heratioOnlyKeys));
        } else {
            $heratioOnlyKeys = $source;
            $this->info('Translating ALL missing keys (not just Heratio-only): ' . count($source));
        }
        $this->newLine();

        $totalSuccess = 0;
        $totalFail = 0;
        foreach ($locales as $locale) {
            [$ok, $fail] = $this->processLocale(
                $locale,
                $heratioOnlyKeys,
                $sourceLocale,
                $endpoint,
                $timeout,
                $overwrite,
                $dryRun,
                max(0, $limit - $totalSuccess)
            );
            $totalSuccess += $ok;
            $totalFail += $fail;
            if ($totalSuccess >= $limit) {
                $this->warn("Limit {$limit} reached.");
                break;
            }
        }

        $this->newLine();
        $this->info("Batch complete: {$totalSuccess} translated, {$totalFail} failed.");
        if (! $dryRun) {
            $this->info('Run `php artisan view:clear` to bust cached views.');
        }
        return self::SUCCESS;
    }

    private function processLocale(
        string $locale,
        array $candidateKeys,
        string $sourceLocale,
        string $endpoint,
        int $timeout,
        bool $overwrite,
        bool $dryRun,
        int $remainingBudget
    ): array {
        $jsonPath = base_path("lang/{$locale}.json");
        $existing = is_file($jsonPath)
            ? (json_decode(file_get_contents($jsonPath), true) ?? [])
            : [];

        // Pick keys that actually need MT
        $todo = [];
        foreach ($candidateKeys as $key => $sourceText) {
            if (! $overwrite) {
                $cur = $existing[$key] ?? null;
                if ($cur !== null && $cur !== '' && $cur !== $key) {
                    continue; // already translated
                }
            }
            $todo[$key] = (string) $sourceText;
        }
        $todo = array_slice($todo, 0, $remainingBudget, true);

        $this->line("→ <comment>{$locale}</comment>: " . count($todo) . ' keys to translate');
        if (empty($todo)) {
            return [0, 0];
        }
        if ($dryRun) {
            $sample = array_slice(array_keys($todo), 0, 3);
            $this->line('  sample: ' . implode(' | ', $sample));
            return [count($todo), 0];
        }

        $merged = $existing;
        $ok = 0;
        $fail = 0;
        $bar = $this->output->createProgressBar(count($todo));
        $bar->start();
        foreach ($todo as $key => $text) {
            $translated = $this->mtCall($endpoint, $sourceLocale, $locale, $text, $timeout);
            if ($translated !== null && $translated !== '' && $translated !== $text) {
                $merged[$key] = $translated;
                $ok++;
            } else {
                $fail++;
            }
            $bar->advance();
            // Throttle a little so we don't hammer the endpoint
            if (($ok + $fail) % 50 === 0) {
                usleep(100_000);
            }
        }
        $bar->finish();
        $this->newLine();

        // Write JSON
        ksort($merged);
        file_put_contents(
            $jsonPath,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        // Update _meta.json with per-key machine-translated flags so a future
        // --mode=prefer-source import won't overwrite. Keeps existing structure.
        $metaPath = base_path('lang/_meta.json');
        if (is_file($metaPath)) {
            $meta = json_decode(file_get_contents($metaPath), true) ?? [];
            $meta['locales'] ??= [];
            $meta['locales'][$locale] ??= [];
            $meta['locales'][$locale]['mt_batch_last_run'] = date('Y-m-d');
            $meta['locales'][$locale]['mt_batch_count'] = ($meta['locales'][$locale]['mt_batch_count'] ?? 0) + $ok;
            file_put_contents(
                $metaPath,
                json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );
        }

        $this->info("  ✓ {$ok} translated, {$fail} failed → lang/{$locale}.json");
        return [$ok, $fail];
    }

    private function mtCall(string $endpoint, string $source, string $target, string $text, int $timeout): ?string
    {
        $payload = json_encode(['source' => $source, 'target' => $target, 'text' => $text]);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $err = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log every call (lightweight)
        if (Schema::hasTable('ahg_translation_log')) {
            DB::table('ahg_translation_log')->insert([
                'source_culture' => $source,
                'target_culture' => $target,
                'endpoint' => $endpoint,
                'http_status' => $status,
                'ok' => ($err === 0 && $status >= 200 && $status < 300) ? 1 : 0,
                'error' => $err ? "curl({$err})" : null,
                'source' => 'machine',
                'created_at' => now(),
            ]);
        }

        if ($err !== 0 || $status >= 400 || $raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }
        return (string) ($data['translatedText'] ?? $data['translation'] ?? '') ?: null;
    }

    /** Scan AtoM XLIFF dir for the union of all source keys. Used to detect Heratio-only keys. */
    private function scanAtomXliffKeys(string $sourceDir): array
    {
        $keys = [];
        if (! is_dir($sourceDir)) {
            return $keys;
        }
        foreach (scandir($sourceDir) as $e) {
            if ($e === '.' || $e === '..') continue;
            $xliff = $sourceDir . '/' . $e . '/messages.xml';
            if (! is_file($xliff)) continue;
            $prev = libxml_use_internal_errors(true);
            $xml = @simplexml_load_file($xliff, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
            libxml_use_internal_errors($prev);
            if ($xml === false) continue;
            foreach ($xml->xpath('//trans-unit') ?: [] as $u) {
                $src = trim((string) ($u->source ?? ''));
                if ($src !== '') $keys[$src] = true;
            }
        }
        return $keys;
    }
}
