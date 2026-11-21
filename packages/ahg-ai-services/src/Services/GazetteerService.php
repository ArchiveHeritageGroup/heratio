<?php

/**
 * GazetteerService - check printed place names against an offline gazetteer
 * and list the likely misspellings for review (heratio#1524).
 *
 *   load()   reads a gazetteer dump from disk (a GeoNames country file such as
 *            ZA.txt or ZA.zip, or any file in that tab-separated shape) into
 *            ahg_gazetteer_place. Nothing is fetched from the web.
 *   check()  looks each place up by its squashed spelling - case, spaces,
 *            hyphens and apostrophes ignored, plus the period spelling Zw-/Sw-
 *            ("Zwartkop" = Swartkop) - in that gazetteer, then in the places
 *            the catalogue already holds (place access points and RiC places;
 *            the source column says which, since those can carry OCR slips).
 *            A name that is not found is retried without generic words
 *            ("Frere Camp" is checked as Frere), then compared with similar
 *            spellings: 85% alike or more is a possible misspelling.
 *
 * The result is a review list. Transcripts and records are never changed.
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

declare(strict_types=1);

namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GazetteerService
{
    public const TABLE = 'ahg_gazetteer_place';

    /** GeoNames feature class => a word a reviewer understands. */
    private const FEATURE = ['P' => 'town', 'H' => 'water', 'T' => 'hill/mountain', 'S' => 'farm/building',
        'L' => 'area', 'A' => 'district', 'R' => 'road', 'V' => 'veld'];

    private const GENERIC = '/\b(camp|station|river|hill|mountain|drift|spruit|kop|kopje|farm|nek|pass|bridge|junction|road|bay|port|fort|town)\b/i';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}>|null catalogue key => [name, feature, country, source] */
    private ?array $catalogue = null;

    /** The spelling-insensitive key both sides are compared on. */
    public static function key(string $name): string
    {
        $k = Str::lower(Str::ascii($name));
        $k = preg_replace("/'s\\b/", '', $k);
        $k = preg_replace('/[^a-z]/', '', $k);

        return preg_replace('/^zw/', 'sw', $k);
    }

    /**
     * Load a gazetteer dump (GeoNames columns: id, name, ASCII name, alternate
     * names, latitude, longitude, feature class, feature code, country). A
     * .zip is read for the .txt inside it. Re-loading a file adds nothing new.
     *
     * @return int spellings added
     */
    public function load(string $path, string $source = 'geonames'): int
    {
        $lines = $this->lines($path);
        $batch = [];
        $added = 0;
        foreach ($lines as $line) {
            $r = explode("\t", rtrim($line, "\r\n"));
            if (count($r) < 9 || ! ctype_digit($r[0])) {
                continue;
            }
            $names = array_unique(array_filter(array_map('trim', [$r[1], $r[2], ...explode(',', $r[3])])));
            $seen = [];
            foreach ($names as $n) {
                $k = self::key($n);
                if (strlen($k) < 4 || isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $batch[] = ['source' => $source, 'source_id' => $r[0], 'name' => mb_substr($r[1], 0, 255), 'name_key' => substr($k, 0, 191),
                    'feature_class' => $r[6] ?: null, 'feature_code' => $r[7] ?: null, 'country_code' => $r[8] ?: null,
                    'latitude' => is_numeric($r[4]) ? $r[4] : null, 'longitude' => is_numeric($r[5]) ? $r[5] : null];
            }
            if (count($batch) >= 1000) {
                $added += DB::table(self::TABLE)->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $added += DB::table(self::TABLE)->insertOrIgnore($batch);
        }

        return $added;
    }

    /**
     * The review list for a set of places.
     *
     * @param  list<array{name: string, variants: list<string>, items: list<int>}>  $places
     * @return list<array{status: string, name: string, variants: string, items: int, match: string, feature: string, country: string, source: string}>
     */
    public function check(array $places): array
    {
        $rows = [];
        foreach ($places as $p) {
            $forms = $p['variants'] ?: [$p['name']];
            usort($forms, fn ($a, $b) => mb_strlen($a) <=> mb_strlen($b));
            $hit = null;
            foreach ($forms as $v) {
                if ($hit = $this->exact(self::key($v))) {
                    break;
                }
            }
            $status = 'found';
            if (! $hit) {
                $core = self::key((string) preg_replace(self::GENERIC, '', $p['name']));
                if (strlen($core) >= 4 && ($hit = $this->exact($core))) {
                    $hit[1] .= ' (by name part)';
                }
            }
            if (! $hit) {
                [$score, $hit] = $this->nearest(self::key($p['name']));
                $status = $hit ? sprintf('possible misspelling (%.2f)', $score) : 'not in gazetteer';
            }
            $hit ??= ['', '', '', ''];
            $rows[] = ['status' => $status, 'name' => $p['name'], 'variants' => implode('; ', $p['variants']), 'items' => count($p['items']),
                'match' => $hit[0], 'feature' => $hit[1], 'country' => $hit[2], 'source' => $hit[3]];
        }
        $order = ['possible' => 0, 'not' => 1, 'found' => 2];
        usort($rows, fn ($a, $b) => $order[strtok($a['status'], ' ')] <=> $order[strtok($b['status'], ' ')]
            ?: $b['items'] <=> $a['items'] ?: strcmp($a['name'], $b['name']));

        return $rows;
    }

    /** Write the review list as CSV, with empty Decision and Correct form columns for the reviewer. */
    public static function writeCsv(string $path, array $rows): void
    {
        $fp = fopen($path, 'w');
        fputcsv($fp, ['status', 'printed name', 'all printed forms', 'items', 'gazetteer name', 'feature', 'country', 'source', 'Decision', 'Correct form']);
        foreach ($rows as $r) {
            fputcsv($fp, [$r['status'], $r['name'], $r['variants'], $r['items'], $r['match'], $r['feature'], $r['country'], $r['source'], '', '']);
        }
        fclose($fp);
    }

    /** @return array{0: string, 1: string, 2: string, 3: string}|null [name, feature, country, source] */
    private function exact(string $key): ?array
    {
        if (strlen($key) < 4) {
            return null;
        }
        // The gazetteer first: catalogue places can carry the same OCR slips.
        $g = Schema::hasTable(self::TABLE)
            ? DB::table(self::TABLE)->where('name_key', $key)->orderByRaw("feature_class = 'P' desc")->orderBy('id')->first()
            : null;

        return $g ? $this->row($g) : ($this->catalogue()[$key] ?? null);
    }

    /** @return array{0: float, 1: ?array} the closest spelling at 85% alike or more */
    private function nearest(string $key): array
    {
        $best = [0.0, null];
        if (strlen($key) < 4) {
            return $best;
        }
        $consider = function (string $other, callable $hit) use ($key, &$best) {
            similar_text($key, $other, $pct);
            if ($pct / 100 >= 0.85 && $pct / 100 > $best[0]) {
                $best = [round($pct / 100, 2), $hit()];
            }
        };
        $len = strlen($key);
        foreach ($this->catalogue() as $k => $c) {
            if (strncmp($k, $key, 2) === 0 && abs(strlen((string) $k) - $len) <= 2) {
                $consider((string) $k, fn () => $c);
            }
        }
        if (Schema::hasTable(self::TABLE)) {
            $cands = DB::table(self::TABLE)->where('name_key', 'like', substr($key, 0, 2).'%')
                ->whereRaw('CHAR_LENGTH(name_key) BETWEEN ? AND ?', [$len - 2, $len + 2])->limit(5000)->get();
            foreach ($cands as $g) {
                $consider($g->name_key, fn () => $this->row($g));
            }
        }

        return $best;
    }

    private function row(object $g): array
    {
        return [(string) $g->name, self::FEATURE[$g->feature_class] ?? (string) $g->feature_class, (string) $g->country_code, (string) $g->source];
    }

    /** Places the catalogue already holds: place access points and RiC places. */
    private function catalogue(): array
    {
        if ($this->catalogue !== null) {
            return $this->catalogue;
        }
        $this->catalogue = [];
        $add = function (iterable $names, string $source) {
            foreach ($names as $n) {
                $k = self::key((string) $n);
                if (strlen($k) >= 4) {
                    $this->catalogue[$k] ??= [(string) $n, 'catalogue place', '', $source];
                }
            }
        };
        $add(DB::table('term_i18n as ti')->join('term as t', 't.id', '=', 'ti.id')->where('t.taxonomy_id', 42)
            ->whereNotNull('ti.name')->distinct()->pluck('ti.name'), 'place access point');
        if (Schema::hasTable('ric_place_i18n')) {
            $add(DB::table('ric_place_i18n')->whereNotNull('name')->distinct()->pluck('name'), 'RiC place');
        }

        return $this->catalogue;
    }

    /** @return iterable<string> */
    private function lines(string $path): iterable
    {
        if (str_ends_with(strtolower($path), '.zip')) {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) {
                throw new \RuntimeException("Cannot open {$path}");
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with(strtolower($name), '.txt') && ! str_contains(strtolower($name), 'readme')) {
                    $fp = $zip->getStream($name);
                    while ($fp && ($line = fgets($fp)) !== false) {
                        yield $line;
                    }
                }
            }
            $zip->close();

            return;
        }
        $fp = fopen($path, 'r');
        if (! $fp) {
            throw new \RuntimeException("Cannot read {$path}");
        }
        while (($line = fgets($fp)) !== false) {
            yield $line;
        }
        fclose($fp);
    }
}
