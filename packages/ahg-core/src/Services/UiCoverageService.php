<?php

/**
 * UiCoverageService - how much of the interface each locale really shows
 * (heratio#1416, #1510).
 *
 * The interface text a visitor sees is lang/{locale}.json with the ui_string
 * table on top (DbAwareLoader: a non-empty ui_string value wins). Coverage is
 * therefore measured on that merged, effective value, not on the file alone.
 *
 * Per reference key a locale is:
 *   - translated:     the effective value is non-empty and differs from the key;
 *   - same as source: the effective value is the key (or absent) but the key is
 *                     known to read the same in the locale (mt_same_as_source in
 *                     lang/_meta.json) - CSV, EAD,
 *                     Filters - so it counts as done (the #1416 metric flaw);
 *   - missing:        anything else.
 *
 * A key is "protected" when a person signed it off: its ui_string value is the
 * latest approved ui_string_change, or it has a ui_string_review row (#1445,
 * reviewed or held). Unprotected ui_string rows are compared with the file:
 * a stub row (value = key) that hides a file translation, a row identical to
 * the file (it freezes today's file value), or a row that differs from the
 * file translation. ahg:translation-db-reconcile acts on the first two.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UiCoverageService
{
    /**
     * The South African official languages other than English, Afrikaans
     * first. ponytail: a fixed list, not a Dropdown Manager group - it is the
     * fill's scheduling order and a highlight, never a value a user picks.
     */
    public const SA_LOCALES = ['af', 'zu', 'xh', 'nso', 'st', 'tn', 'ss', 'ts', 've', 'nr'];

    public function __construct(private ?string $langDir = null)
    {
        $this->langDir ??= base_path('lang');
    }

    public static function isSa(string $locale): bool
    {
        return in_array($locale, self::SA_LOCALES, true);
    }

    /**
     * Fill order: Afrikaans, then the other SA languages the gateway can
     * serve, then the rest, then the SA languages it cannot serve yet; each
     * group keeps the order given. $supported(locale) answers "can the gateway
     * serve it"; without it every SA language counts as served.
     *
     * @param  list<string>  $locales
     * @return list<string>
     */
    public static function priorityOrder(array $locales, ?callable $supported = null): array
    {
        $rank = fn (string $l) => match (true) {
            $l === 'af' => 0,
            ! self::isSa($l) => 2,
            $supported === null || $supported($l) => 1,
            default => 3,
        };
        $indexed = array_values($locales);
        $ranks = array_map($rank, $indexed);
        $order = array_keys($indexed);
        usort($order, fn ($a, $b) => [$ranks[$a], $a] <=> [$ranks[$b], $b]);

        return array_map(fn ($i) => $indexed[$i], $order);
    }

    /** @return list<string> locales with a lang/{locale}.json, en and _meta excluded */
    public function locales(): array
    {
        $out = [];
        foreach (glob($this->langDir.'/*.json') ?: [] as $f) {
            $l = pathinfo($f, PATHINFO_FILENAME);
            if ($l !== '_meta' && $l !== 'en') {
                $out[] = $l;
            }
        }
        sort($out);

        return $out;
    }

    /** @return array<string, string>|null the file map, or null when the file is not a JSON object (#1410) */
    public function fileMap(string $locale): ?array
    {
        $raw = trim((string) @file_get_contents($this->langDir.'/'.$locale.'.json'));
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return $raw[0] === '{' && is_array($data) ? $data : null;
    }

    public function meta(): array
    {
        return json_decode((string) @file_get_contents($this->langDir.'/_meta.json'), true) ?: ['locales' => []];
    }

    /** @return array<string, string> non-empty ui_string values for the locale; [] when the table is absent */
    public function dbMap(string $locale): array
    {
        $map = [];
        try {
            if (! Schema::hasTable('ui_string')) {
                return [];
            }
            DB::table('ui_string')->where('culture', $locale)->whereNotNull('value')->where('value', '!=', '')
                ->orderBy('id')->select(['key', 'value'])
                ->chunk(5000, function ($rows) use (&$map) {
                    foreach ($rows as $r) {
                        $map[(string) $r->key] = (string) $r->value;
                    }
                });
        } catch (\Throwable) {
            return [];
        }

        return $map;
    }

    /**
     * Keys the machine must leave alone in the locale: the latest approved
     * ui_string_change still matches the ui_string value (an edit made in
     * /admin/translation/strings), or the key has a ui_string_review row
     * (reviewed, or held for review - heratio#1445).
     *
     * @param  array<string, string>  $db
     * @return array<string, true>
     */
    public function protectedKeys(string $locale, array $db): array
    {
        $out = class_exists(UiStringReviewService::class) ? UiStringReviewService::lockedKeys($locale) : [];
        try {
            if (! Schema::hasTable('ui_string_change')) {
                return $out;
            }
            $latest = [];
            DB::table('ui_string_change')->where('locale', $locale)->where('status', 'approved')
                ->orderBy('id')->select(['id', 'key_text', 'new_value'])
                ->chunk(5000, function ($rows) use (&$latest) {
                    foreach ($rows as $r) {
                        $latest[(string) $r->key_text] = (string) ($r->new_value ?? '');
                    }
                });
            foreach ($latest as $k => $v) {
                if ($v !== '' && ($db[$k] ?? null) === $v) {
                    $out[$k] = true;
                }
            }
        } catch (\Throwable) {
            // No change log: only the review rows count.
        }

        return $out;
    }

    /**
     * Unreviewed ui_string rows measured against the file.
     *
     * @return array{stub_hides_file: list<string>, same_as_file: list<string>, differs_from_file: list<string>}
     */
    public function dbDrift(array $file, array $db, array $reviewed): array
    {
        $out = ['stub_hides_file' => [], 'same_as_file' => [], 'differs_from_file' => []];
        foreach ($db as $k => $v) {
            $f = $file[$k] ?? null;
            if (isset($reviewed[$k]) || ! is_string($f) || $f === '' || $f === $k) {
                continue;   // reviewed, or the file has no translation of its own to show
            }
            if ($v === $k) {
                $out['stub_hides_file'][] = $k;
            } elseif ($v === $f) {
                $out['same_as_file'][] = $k;
            } else {
                $out['differs_from_file'][] = $k;
            }
        }

        return $out;
    }

    /**
     * One row per locale, SA languages flagged.
     *
     * @param  array<string, mixed>  $referenceKeys  key => anything (the codebase __() keys)
     * @param  list<string>  $only  restrict to these locales
     * @return list<array<string, mixed>>
     */
    public function report(array $referenceKeys, array $only = [], bool $withDb = true): array
    {
        $meta = $this->meta();
        $total = count($referenceKeys);
        $rows = [];
        foreach ($this->locales() as $locale) {
            if ($only && ! in_array($locale, $only, true)) {
                continue;
            }
            $file = $this->fileMap($locale);
            $malformed = $file === null;
            $file ??= [];
            $db = $withDb ? $this->dbMap($locale) : [];
            $reviewed = $withDb ? $this->protectedKeys($locale, $db) : [];
            $same = array_flip((array) ($meta['locales'][$locale]['mt_same_as_source'] ?? []));

            $translated = $sameAsSource = 0;
            foreach ($referenceKeys as $k => $_) {
                $k = (string) $k;
                $v = $db[$k] ?? $file[$k] ?? null;
                if (is_string($v) && $v !== '' && $v !== $k) {
                    $translated++;
                } elseif (isset($same[$k])) {
                    $sameAsSource++;
                }
            }
            $drift = $this->dbDrift($file, $db, $reviewed);
            $done = $translated + $sameAsSource;
            $rows[] = [
                'locale' => $locale,
                'sa' => self::isSa($locale),
                'malformed' => $malformed,
                'file_keys' => count($file),
                'db_rows' => count($db),
                'translated' => $translated,
                'same_as_source' => $sameAsSource,
                'missing' => $total - $done,
                'reviewed' => count($reviewed),   // signed off or held for review
                'db_hides_file' => count($drift['stub_hides_file']),
                'db_differs' => count($drift['differs_from_file']),
                'machine' => ! empty($meta['locales'][$locale]['mt_batch_count']) || ! empty($meta['locales'][$locale]['mt_engine']),
                'reference' => $total,
                'pct' => $total > 0 ? round($done / $total * 100, 1) : 0.0,
            ];
        }
        usort($rows, fn ($a, $b) => [$b['sa'], $b['pct']] <=> [$a['sa'], $a['pct']]);

        return $rows;
    }

    /**
     * The __('...') keys in packages/ and app/ - the interface as it is coded,
     * which is what coverage is measured against (#1416: en.json alone was a
     * fifth of it before #1420).
     *
     * @return array<string, true>
     */
    public static function codebaseKeys(): array
    {
        $keys = [];
        foreach ([base_path('packages'), base_path('app')] as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($rii as $file) {
                $path = $file->getPathname();
                if (str_contains($path, '/worktree/') || str_contains($path, '/vendor/') || ! preg_match('/\.(blade\.php|php)$/', $path)) {
                    continue;
                }
                $contents = @file_get_contents($path);
                if ($contents !== false && preg_match_all("/__\(\s*['\"]([^'\"]{1,500})['\"]/", $contents, $m)) {
                    foreach ($m[1] as $k) {
                        $keys[$k] = true;
                    }
                }
            }
        }

        return $keys;
    }
}
