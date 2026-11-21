<?php

/**
 * UiStringReviewService - native-speaker review of machine-translated UI
 * strings (heratio#1445).
 *
 * One workflow, three doors:
 *   - a review workbook per locale (export, reviewer fills {locale}_corrected,
 *     import with a preview first);
 *   - the in-app review screen (step through strings, accept or correct);
 *   - staging of held machine translations (the zu/xh/ts NAS sheets) that are
 *     kept out of lang/ until a reviewer has seen them.
 *
 * The workbook layout is the one already sent to reviewers: id, english,
 * {locale}_machine (older sheets: {locale}_current), {locale}_corrected,
 * must_keep_unchanged, context, note. id is the first 10 hex characters of
 * sha1(english), so a correction always lands on the key the id names, even if
 * the english cell was edited. A blank corrected cell means "fine as is".
 *
 * A reviewed string is written to ui_string (what the site reads first) and to
 * lang/{locale}.json, and recorded in ui_string_review with the reviewer and
 * the date. The machine fill commands skip every key that has a review row, so
 * a reviewed string is never overwritten and a held string is never refilled.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use AhgCore\Commands\TranslationExtractCommand;
use AhgCore\Commands\TranslationLlmFillCommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class UiStringReviewService
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const CORRECTED = 'corrected';

    private string $langDir;

    public function __construct(?string $langDir = null)
    {
        $this->langDir = rtrim($langDir ?? base_path('lang'), '/');
    }

    public static function keyId(string $english): string
    {
        return substr(sha1($english), 0, 10);
    }

    public static function validLocale(string $locale): bool
    {
        return $locale !== 'en' && (bool) preg_match('/^[a-z]{2,3}(_[A-Z]{2})?(@[a-z]+)?$/', $locale);
    }

    /**
     * Keys the machine fill must leave alone in this locale: every key with a
     * review row, reviewed (the reviewer's text stands) or pending (a held
     * translation waiting for review). Empty when the table is not there yet.
     *
     * @return array<string, true>
     */
    public static function lockedKeys(string $locale): array
    {
        try {
            if (! Schema::hasTable('ui_string_review')) {
                return [];
            }

            return array_fill_keys(DB::table('ui_string_review')->where('culture', $locale)->pluck('key')->map(fn ($k) => (string) $k)->all(), true);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Locales that have a lang file, English excluded. */
    public function locales(): array
    {
        $out = [];
        foreach (glob($this->langDir.'/*.json') ?: [] as $f) {
            $code = basename($f, '.json');
            if (self::validLocale($code)) {
                $out[] = $code;
            }
        }
        sort($out);

        return $out;
    }

    /** @return array<string, string> lang/en.json, the review source */
    public function english(): array
    {
        return $this->readJson($this->langDir.'/en.json');
    }

    /** @return array<string, string> */
    public function fileMap(string $locale): array
    {
        return $this->readJson($this->langDir."/{$locale}.json");
    }

    /** @return array<string, string> what the site shows: ui_string over the lang file */
    public function liveMap(string $locale): array
    {
        $map = $this->fileMap($locale);
        if (Schema::hasTable('ui_string')) {
            foreach (DB::table('ui_string')->where('culture', $locale)->whereNotNull('value')->get(['key', 'value']) as $r) {
                if ((string) $r->value !== '') {
                    $map[(string) $r->key] = (string) $r->value;
                }
            }
        }

        return $map;
    }

    /** @return array<string, object> review rows for a locale, by key */
    public function reviewRows(string $locale): array
    {
        $out = [];
        if (! Schema::hasTable('ui_string_review')) {
            return $out;   // not migrated yet: nothing reviewed, nothing held
        }
        foreach (DB::table('ui_string_review')->where('culture', $locale)->get() as $r) {
            $out[(string) $r->key] = $r;
        }

        return $out;
    }

    /** @return array{pending:int, reviewed:int, held:int} */
    public function counts(string $locale): array
    {
        $rows = $this->reviewRows($locale);
        $reviewed = count(array_filter($rows, fn ($r) => $r->status !== self::PENDING));

        return [
            'pending' => count($this->queue($locale)),
            'reviewed' => $reviewed,
            'held' => count(array_filter($rows, fn ($r) => $r->status === self::PENDING && $r->held)),
        ];
    }

    /**
     * Strings waiting for review, in lang/en.json order: every live
     * translation that differs from the English and has no review yet, plus
     * every staged held translation.
     *
     * @return list<array{key:string, id:string, machine:string, held:bool}>
     */
    public function queue(string $locale, bool $heldOnly = false): array
    {
        $live = $this->liveMap($locale);
        $rows = $this->reviewRows($locale);
        $out = [];
        foreach (array_keys($this->english()) as $en) {
            $en = (string) $en;
            $row = $rows[$en] ?? null;
            if ($row && $row->status !== self::PENDING) {
                continue;
            }
            if ($row) {
                $machine = (string) ($row->machine_text ?? $live[$en] ?? '');
                $held = (bool) $row->held;
            } else {
                $machine = (string) ($live[$en] ?? '');
                $held = false;
                if ($machine === '' || $machine === $en || ! preg_match('/\p{L}.*\p{L}/u', $en)) {
                    continue;
                }
            }
            if ($heldOnly && ! $held) {
                continue;
            }
            $out[] = ['key' => $en, 'id' => self::keyId($en), 'machine' => $machine, 'held' => $held];
        }

        return $out;
    }

    /**
     * Rows for a review workbook. $all includes strings already reviewed
     * (their reviewed text goes in the machine column).
     *
     * @return list<array{id:string, english:string, machine:string, keep:string, context:string}>
     */
    public function exportRows(string $locale, bool $all = false, bool $withContext = true): array
    {
        $context = $withContext ? $this->contextMap() : [];
        $rows = [];
        $items = $this->queue($locale);
        if ($all) {
            foreach ($this->reviewRows($locale) as $key => $r) {
                if ($r->status !== self::PENDING) {
                    $items[] = ['key' => $key, 'id' => self::keyId($key), 'machine' => (string) $r->reviewed_text, 'held' => (bool) $r->held];
                }
            }
        }
        foreach ($items as $it) {
            $rows[] = [
                'id' => $it['id'],
                'english' => $it['key'],
                'machine' => $it['machine'],
                'keep' => implode(' ', TranslationLlmFillCommand::placeholders($it['key'])),
                'context' => $context[$it['key']] ?? '',
            ];
        }

        return $rows;
    }

    public function writeWorkbook(string $locale, array $rows, string $path): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(substr($locale, 0, 31));
        $sheet->fromArray([['id', 'english', "{$locale}_machine", "{$locale}_corrected", 'must_keep_unchanged', 'context', 'note']]);
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $r = 2;
        foreach ($rows as $row) {
            // Explicit strings: an id like 1234e56789 must not become a number.
            foreach (['A' => $row['id'], 'B' => $row['english'], 'C' => $row['machine'], 'E' => $row['keep'], 'F' => $row['context']] as $c => $v) {
                $sheet->setCellValueExplicit($c.$r, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
            $r++;
        }
        $sheet->freezePane('C2');
        foreach (['A' => 12, 'B' => 60, 'C' => 60, 'D' => 60, 'E' => 24, 'F' => 40, 'G' => 30] as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        $help = $book->createSheet();
        $help->setTitle('How to review');
        $lines = [
            "Heratio interface - {$locale} review (".count($rows).' strings, exported '.date('j F Y').')',
            '',
            "Each row is one interface text. Column {$locale}_machine is the current machine translation.",
            "If it is right, leave {$locale}_corrected empty. If it is wrong, write the correct text in {$locale}_corrected.",
            'Keep every token listed in must_keep_unchanged exactly as it is (for example :name or %1%) - the software fills these in.',
            'Product and organisation names (PayPal, Creative Commons, Archive.org) should normally stay as they are.',
            'Column context shows a file where the text is used, to help with meaning. Do not change the id column.',
        ];
        foreach ($lines as $i => $line) {
            $help->setCellValue('A'.($i + 1), $line);
        }
        $book->setActiveSheetIndex(0);
        IOFactory::createWriter($book, 'Xlsx')->save($path);
    }

    /**
     * Read a review workbook (xlsx or csv): the first sheet, header row first.
     *
     * @return list<array{id:string, machine:?string, corrected:string}>
     */
    public function readWorkbook(string $locale, string $file): array
    {
        $book = IOFactory::load($file);
        $rows = $book->getSheet(0)->toArray(null, false, false, false);
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $find = function (array $names) use ($head) {
            foreach ($names as $n) {
                if (($i = array_search($n, $head, true)) !== false) {
                    return $i;
                }
            }

            return false;
        };
        $cId = $find(['id']);
        $cFix = $find([strtolower($locale).'_corrected', 'corrected']);
        $cMachine = $find([strtolower($locale).'_machine', strtolower($locale).'_current', 'machine']);
        if ($cId === false || $cFix === false) {
            throw new \InvalidArgumentException("The sheet needs id and {$locale}_corrected columns.");
        }
        $out = [];
        foreach ($rows as $r) {
            $id = trim((string) ($r[$cId] ?? ''));
            if ($id === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'machine' => $cMachine !== false && trim((string) ($r[$cMachine] ?? '')) !== '' ? (string) $r[$cMachine] : null,
                'corrected' => trim((string) ($r[$cFix] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Stage held machine translations for review without making them live:
     * a pending, held review row per sheet row that has machine text. Rows
     * already reviewed are left alone.
     *
     * @return array{staged:int, skipped:int, unknown:int}
     */
    public function stageHeld(string $locale, string $file, string $source): array
    {
        $byId = $this->byId();
        $rows = $this->reviewRows($locale);
        $staged = $skipped = $unknown = 0;
        foreach ($this->readWorkbook($locale, $file) as $r) {
            $en = $byId[$r['id']] ?? null;
            if ($en === null) {
                $unknown++;

                continue;
            }
            if ($r['machine'] === null || (isset($rows[$en]) && $rows[$en]->status !== self::PENDING)) {
                $skipped++;

                continue;
            }
            $this->upsertRow($locale, $en, [
                'machine_text' => TranslationLlmFillCommand::plainDashes($r['machine']),
                'status' => self::PENDING,
                'held' => 1,
                'source' => mb_substr($source, 0, 255),
            ]);
            $staged++;
        }

        return ['staged' => $staged, 'skipped' => $skipped, 'unknown' => $unknown];
    }

    /**
     * What an import would do, before anything is written.
     *
     * Each item: id, key, machine, value (what goes live), action (correct |
     * accept) and problem (null, or why the row is refused).
     *
     * @return array{items:list<array>, unknown:int, unchanged:int}
     */
    public function preview(string $locale, string $file, bool $acceptBlank = true): array
    {
        $byId = $this->byId();
        $live = $this->liveMap($locale);
        $rows = $this->reviewRows($locale);
        $items = [];
        $unknown = $unchanged = 0;
        foreach ($this->readWorkbook($locale, $file) as $r) {
            $en = $byId[$r['id']] ?? null;
            if ($en === null) {
                $unknown++;

                continue;
            }
            $row = $rows[$en] ?? null;
            $reviewed = $row && $row->status !== self::PENDING;
            $machine = (string) ($reviewed ? $row->reviewed_text : ($row->machine_text ?? $live[$en] ?? $r['machine'] ?? ''));
            if ($r['corrected'] !== '') {
                $value = TranslationLlmFillCommand::plainDashes($r['corrected']);
                if ($reviewed && $value === (string) $row->reviewed_text) {
                    $unchanged++;

                    continue;
                }
                $action = $value === $machine ? self::ACCEPTED : self::CORRECTED;
            } elseif ($acceptBlank && ! $reviewed && $machine !== '') {
                $value = $machine;
                $action = self::ACCEPTED;
            } else {
                $unchanged++;

                continue;
            }
            $items[] = [
                'id' => $r['id'], 'key' => $en, 'machine' => $machine, 'value' => $value,
                'action' => $action, 'held' => (bool) ($row->held ?? false) || ! array_key_exists($en, $live),
                'problem' => self::placeholderProblem($en, $value),
            ];
        }

        return ['items' => $items, 'unknown' => $unknown, 'unchanged' => $unchanged];
    }

    /**
     * Why a translation cannot go live, or null when it can: the placeholders
     * (:name, %1%, {{ x }}, HTML tags and entities) must match the English.
     */
    public static function placeholderProblem(string $english, string $text): ?string
    {
        $want = TranslationLlmFillCommand::placeholders($english);
        $got = TranslationLlmFillCommand::placeholders($text);
        if ($want === $got) {
            return null;
        }
        $missing = self::multisetDiff($want, $got);
        $extra = self::multisetDiff($got, $want);
        $parts = [];
        if ($missing) {
            $parts[] = __('missing :tokens', ['tokens' => implode(' ', $missing)]);
        }
        if ($extra) {
            $parts[] = __('not in the English: :tokens', ['tokens' => implode(' ', $extra)]);
        }

        return implode('; ', $parts);
    }

    /**
     * Write the reviewed strings. Refused items (a placeholder problem) are
     * skipped. $writeDb writes ui_string, $writeFile writes lang/{locale}.json
     * and lang/_meta.json; on production use the database only, since the lang
     * file arrives with the release.
     *
     * @return array{accepted:int, corrected:int, refused:int}
     */
    public function apply(string $locale, array $items, string $reviewer, ?int $userId, bool $writeDb = true, bool $writeFile = true): array
    {
        $live = $this->liveMap($locale);
        $file = $writeFile ? $this->fileMap($locale) : [];
        $fileChanged = false;
        $done = [self::ACCEPTED => 0, self::CORRECTED => 0, 'refused' => 0];
        $strings = class_exists(\AhgTranslation\Services\UiStringService::class) ? app(\AhgTranslation\Services\UiStringService::class) : null;

        foreach ($items as $it) {
            if (! empty($it['problem']) || self::placeholderProblem($it['key'], $it['value']) !== null) {
                $done['refused']++;

                continue;
            }
            $key = $it['key'];
            $value = $it['value'];
            if ($writeDb && ($live[$key] ?? null) !== $value) {
                $note = "heratio#1445 review ({$it['action']}) by {$reviewer}";
                if ($strings) {
                    $strings->applyApproved((int) ($userId ?? 0), $locale, $key, $value, null, $note);
                } else {
                    DB::table('ui_string')->upsert([['key' => $key, 'culture' => $locale, 'value' => $value, 'updated_at' => now()]], ['key', 'culture'], ['value', 'updated_at']);
                }
            }
            if ($writeFile && ($file[$key] ?? null) !== $value) {
                $file[$key] = $value;
                $fileChanged = true;
            }
            $this->upsertRow($locale, $key, [
                'machine_text' => $it['machine'] !== '' ? $it['machine'] : null,
                'reviewed_text' => $value,
                'status' => $it['action'],
                'reviewer_name' => mb_substr($reviewer, 0, 255),
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
            ]);
            $done[$it['action']]++;
        }

        if ($writeFile && $fileChanged) {
            $this->saveJson($this->langDir."/{$locale}.json", $file);
        }
        $count = $done[self::ACCEPTED] + $done[self::CORRECTED];
        if ($writeFile && $count > 0) {
            $metaPath = $this->langDir.'/_meta.json';
            $meta = $this->readJson($metaPath) ?: ['locales' => []];
            $entry = $meta['locales'][$locale] ?? [];
            $entry['reviewed_at'] = date('Y-m-d');
            $entry['reviewed_by'] = $reviewer;
            $entry['reviewed_count'] = (int) ($entry['reviewed_count'] ?? 0) + $count;
            $entry['corrected_count'] = (int) ($entry['corrected_count'] ?? 0) + $done[self::CORRECTED];
            $meta['locales'][$locale] = $entry;
            $this->saveJson($metaPath, $meta);
        }

        return ['accepted' => $done[self::ACCEPTED], 'corrected' => $done[self::CORRECTED], 'refused' => $done['refused']];
    }

    /**
     * One decision from the review screen. $correction null or blank = accept
     * the machine text.
     *
     * @return array{accepted:int, corrected:int, refused:int}
     */
    public function decide(string $locale, string $key, ?string $correction, string $reviewer, ?int $userId, bool $writeFile = true): array
    {
        $item = null;
        foreach ($this->queue($locale) as $q) {
            if ($q['key'] === $key) {
                $item = $q;
                break;
            }
        }
        if ($item === null) {
            throw new \InvalidArgumentException('This string is not waiting for review.');
        }
        $correction = TranslationLlmFillCommand::plainDashes(trim((string) $correction));
        $value = $correction !== '' ? $correction : $item['machine'];
        $action = $value === $item['machine'] ? self::ACCEPTED : self::CORRECTED;

        return $this->apply($locale, [[
            'key' => $key, 'machine' => $item['machine'], 'value' => $value, 'action' => $action,
            'problem' => self::placeholderProblem($key, $value),
        ]], $reviewer, $userId, true, $writeFile);
    }

    /**
     * Where each key is used: the first source file (relative path) that
     * passes it to __(), trans() or @lang(), "+N" when there are more.
     * Cached for a day; the scan walks the whole source tree.
     *
     * @return array<string, string>
     */
    public function contextMap(): array
    {
        return Cache::remember('ui_string_review_context', 86400, function () {
            $hits = [];
            foreach (['packages', 'app', 'resources', 'routes'] as $root) {
                $dir = base_path($root);
                if (! is_dir($dir)) {
                    continue;
                }
                foreach (TranslationExtractCommand::sourceFiles($dir) as $path) {
                    $rel = ltrim(substr($path, strlen(base_path())), '/');
                    foreach (TranslationExtractCommand::extractKeys((string) @file_get_contents($path)) as $k) {
                        $hits[$k][] = $rel;
                    }
                }
            }
            $out = [];
            foreach ($hits as $k => $files) {
                $files = array_values(array_unique($files));
                $out[$k] = $files[0].(count($files) > 1 ? ' +'.(count($files) - 1) : '');
            }

            return $out;
        });
    }

    /** @return array<string, string> id => english key */
    private function byId(): array
    {
        $out = [];
        foreach (array_keys($this->english()) as $en) {
            $out[self::keyId((string) $en)] = (string) $en;
        }

        return $out;
    }

    private function upsertRow(string $locale, string $key, array $values): void
    {
        $now = now();
        $exists = DB::table('ui_string_review')->where('culture', $locale)->where('key', $key)->exists();
        if ($exists) {
            DB::table('ui_string_review')->where('culture', $locale)->where('key', $key)->update($values + ['updated_at' => $now]);
        } else {
            DB::table('ui_string_review')->insert($values + [
                'culture' => $locale, 'key' => $key, 'key_id' => self::keyId($key),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /** @return list<string> items of $a not matched in $b (multiset) */
    private static function multisetDiff(array $a, array $b): array
    {
        foreach ($b as $t) {
            if (($i = array_search($t, $a, true)) !== false) {
                unset($a[$i]);
            }
        }

        return array_values($a);
    }

    private function readJson(string $path): array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? $data : [];
    }

    /** Same encoding as the fill commands; atomic so a release never sees half a file. */
    private function saveJson(string $path, array $data): void
    {
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
        rename($tmp, $path);
    }
}
