<?php

/**
 * UiStringService - load, edit and save lang/{locale}.json files
 *
 * Heratio - In-app UI-string editor (issue #54 MVP).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * Licensed under AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgTranslation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UiStringService
{
    /**
     * Locales enabled in the app (driven by ahg_settings 'i18n_languages' rows
     * with editable=1, falling back to lang/*.json on disk).
     */
    public function enabledLocales(): array
    {
        $codes = [];
        try {
            if (Schema::hasTable('setting')) {
                $codes = DB::table('setting')
                    ->where('scope', 'i18n_languages')
                    ->where('editable', 1)
                    ->pluck('name')
                    ->toArray();
            }
        } catch (\Throwable $e) {
            // fall through
        }
        if (empty($codes)) {
            $files = glob(base_path('lang/*.json')) ?: [];
            $codes = array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), $files);
        }
        sort($codes);
        return $codes ?: ['en'];
    }

    /**
     * Load lang/en.json as the source of truth for translation keys.
     * en values are themselves the canonical English strings.
     */
    public function sourceKeys(): array
    {
        $path = base_path('lang/en.json');
        if (!is_readable($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : [];
    }

    /**
     * Load lang/{locale}.json. Returns empty array if missing — that's the
     * "fully untranslated" baseline rather than an error.
     */
    public function localeMap(string $locale): array
    {
        $path = $this->localePath($locale);
        if (!is_readable($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : [];
    }

    /**
     * Atomically write a single key+value into lang/{locale}.json.
     *
     *  - Opens an exclusive flock on the destination file
     *  - Reads current JSON, sets the key to the new value (or unsets when value === null)
     *  - Writes to a sibling .tmp.<pid> file
     *  - Validates the new file parses as JSON
     *  - rename() atomically replaces the live file
     *
     * Returns the new key→value map for downstream callers.
     */
    public function setKey(string $locale, string $key, ?string $value): array
    {
        $this->guardLocale($locale);
        if ($key === '') {
            throw new \InvalidArgumentException('key cannot be empty');
        }

        $path = $this->localePath($locale);
        if (!is_dir(dirname($path))) {
            throw new \RuntimeException('lang/ directory missing');
        }

        // Lock-protect concurrent writes. Use a sibling .lock file so we can
        // both lock + safely create the destination on first write.
        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c');
        if (!$lock) {
            throw new \RuntimeException('cannot open lock file');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('cannot acquire write lock');
            }

            $current = $this->localeMap($locale);
            if ($value === null || $value === '') {
                unset($current[$key]);
            } else {
                $current[$key] = $value;
            }

            // Stable order for diffability + git-friendliness.
            ksort($current);

            $encoded = json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($encoded === false) {
                throw new \RuntimeException('json_encode failed: ' . json_last_error_msg());
            }
            // Re-parse round-trip as paranoid validation.
            if (json_decode($encoded, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('round-trip JSON parse failed');
            }

            $tmp = $path . '.tmp.' . getmypid();
            if (file_put_contents($tmp, $encoded . "\n") === false) {
                throw new \RuntimeException('failed to write tmp file');
            }
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                throw new \RuntimeException('atomic rename failed');
            }
            // Match parent dir mode + ownership best-effort so php-fpm doesn't
            // end up the only writer over time.
            @chmod($path, 0664);
            return $current;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Build the matrix the editor view consumes.
     *
     * @param string|null $localeFilter   restrict to a single locale (default: all enabled)
     * @param string|null $missingLocale  only return rows missing in this locale
     * @param string|null $contains       case-insensitive substring (matches key OR any locale value)
     * @param int         $limit          page size (0 = unlimited; sensible default for MVP)
     * @param int         $offset
     * @return array{rows:array, total:int, locales:array}
     */
    public function matrix(
        ?string $localeFilter = null,
        ?string $missingLocale = null,
        ?string $contains = null,
        int $limit = 200,
        int $offset = 0
    ): array {
        $allLocales = $this->enabledLocales();
        $locales = $localeFilter
            ? (in_array($localeFilter, $allLocales, true) ? [$localeFilter] : $allLocales)
            : $allLocales;

        // Pre-load every locale once so we don't open files in a loop.
        $maps = ['en' => $this->localeMap('en')];
        foreach ($locales as $code) {
            if (!isset($maps[$code])) {
                $maps[$code] = $this->localeMap($code);
            }
        }

        $keys = array_keys($maps['en'] ?: []);
        sort($keys);

        // Filter — missing
        if ($missingLocale && in_array($missingLocale, $allLocales, true)) {
            $target = $maps[$missingLocale] ?? [];
            $keys = array_values(array_filter(
                $keys,
                fn ($k) => !array_key_exists($k, $target) || (string) ($target[$k] ?? '') === ''
            ));
        }

        // Filter — substring
        if ($contains !== null && $contains !== '') {
            $needle = mb_strtolower($contains);
            $keys = array_values(array_filter($keys, function ($k) use ($needle, $maps, $locales) {
                if (mb_stripos($k, $needle) !== false) return true;
                foreach ($locales as $code) {
                    $v = (string) ($maps[$code][$k] ?? '');
                    if ($v !== '' && mb_stripos($v, $needle) !== false) return true;
                }
                $en = (string) ($maps['en'][$k] ?? '');
                if ($en !== '' && mb_stripos($en, $needle) !== false) return true;
                return false;
            }));
        }

        $total = count($keys);
        if ($limit > 0) {
            $keys = array_slice($keys, $offset, $limit);
        }

        $rows = [];
        foreach ($keys as $k) {
            $row = ['key' => $k, 'en' => (string) ($maps['en'][$k] ?? $k), 'translations' => []];
            foreach ($locales as $code) {
                if ($code === 'en') continue;
                $v = $maps[$code][$k] ?? null;
                $row['translations'][$code] = [
                    'value'  => $v === null ? null : (string) $v,
                    'missing'=> ($v === null || (string) $v === ''),
                ];
            }
            $rows[] = $row;
        }

        return [
            'rows'    => $rows,
            'total'   => $total,
            'locales' => array_values(array_filter($locales, fn ($c) => $c !== 'en')),
        ];
    }

    /**
     * Insert a pending change row (editor save, or admin opt-in to review).
     * Does NOT touch the JSON file — applyApproved() does that.
     */
    public function submitChange(int $userId, string $locale, string $key, ?string $value): int
    {
        $this->guardLocale($locale);
        $current = $this->localeMap($locale);
        $old = $current[$key] ?? null;
        return DB::table('ui_string_change')->insertGetId([
            'locale'                => $locale,
            'key_text'              => $key,
            'old_value'             => $old,
            'new_value'             => $value,
            'status'                => 'pending',
            'submitted_by_user_id'  => $userId,
            'submitted_at'          => now(),
        ]);
    }

    /**
     * Apply an approved change to the JSON file + write the audit row.
     * Use this for the immediate-approve path (admin without ?review=1)
     * AND for the approve-pending path (admin clicking Approve in the queue).
     */
    public function applyApproved(int $userId, string $locale, string $key, ?string $value, ?int $pendingId = null, ?string $note = null): int
    {
        $current = $this->localeMap($locale);
        $old = $current[$key] ?? null;
        $this->setKey($locale, $key, $value);

        if ($pendingId) {
            DB::table('ui_string_change')->where('id', $pendingId)->update([
                'status'              => 'approved',
                'reviewed_by_user_id' => $userId,
                'reviewed_at'         => now(),
                'review_note'         => $note,
            ]);
            return (int) $pendingId;
        }

        return DB::table('ui_string_change')->insertGetId([
            'locale'                => $locale,
            'key_text'              => $key,
            'old_value'             => $old,
            'new_value'             => $value,
            'status'                => 'approved',
            'submitted_by_user_id'  => $userId,
            'submitted_at'          => now(),
            'reviewed_by_user_id'   => $userId,
            'reviewed_at'           => now(),
            'review_note'           => $note,
        ]);
    }

    public function rejectPending(int $userId, int $pendingId, ?string $note = null): bool
    {
        return DB::table('ui_string_change')
            ->where('id', $pendingId)->where('status', 'pending')
            ->update([
                'status'              => 'rejected',
                'reviewed_by_user_id' => $userId,
                'reviewed_at'         => now(),
                'review_note'         => $note,
            ]) > 0;
    }

    public function pendingChanges(?string $locale = null): \Illuminate\Support\Collection
    {
        $q = DB::table('ui_string_change as c')
            ->leftJoin('user as u', 'u.id', '=', 'c.submitted_by_user_id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'u.id')->where('ai.culture', '=', 'en');
            })
            ->where('c.status', 'pending')
            ->select('c.*', 'u.username', 'u.email', 'ai.authorized_form_of_name as submitted_by_name')
            ->orderByDesc('c.submitted_at');
        if ($locale) {
            $q->where('c.locale', $locale);
        }
        return $q->get();
    }

    private function localePath(string $locale): string
    {
        $this->guardLocale($locale);
        return base_path('lang/' . $locale . '.json');
    }

    private function guardLocale(string $locale): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{0,15}$/i', $locale)) {
            throw new \InvalidArgumentException('invalid locale code: ' . $locale);
        }
    }
}
