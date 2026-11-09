<?php

/**
 * GridEntryService - many descriptions typed or pasted into a spreadsheet-style
 * grid under one parent description (heratio#1581; port of the AHG Extensions
 * for AtoM grid entry, atom-ahg-plugins#208).
 *
 * The grid never writes information_object rows itself. It stages its rows as
 * an ordinary ingest session (one ingest_row per grid row, identity column
 * mapping, the wizard's own enrich step) and the records are then created by
 * the wizard's commit job, so slugs, hierarchy, publication status, search
 * index and the audit trail behave exactly as in a CSV or Excel ingest.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgIngest\Services;

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;

class GridEntryService
{
    /** Grid column => label, also the CSV field name the ingest pipeline reads. */
    public const COLUMNS = [
        'identifier' => 'Identifier',
        'title' => 'Title',
        'levelOfDescription' => 'Level of description',
        'creationDates' => 'Dates (display)',
        'creationDatesStart' => 'Start date',
        'creationDatesEnd' => 'End date',
        'extentAndMedium' => 'Extent and medium',
        'scopeAndContent' => 'Scope and content',
    ];

    public const MAX_ROWS = 1000;

    private const LEVEL_TAXONOMY_ID = 34;

    public function __construct(protected IngestService $ingest) {}

    /** Level-of-description names in the given culture, falling back to the source culture. */
    public function levelNames(string $culture = 'en'): array
    {
        $rows = DB::table('term')
            ->leftJoin('term_i18n as cur', fn ($j) => $j->on('cur.id', '=', 'term.id')->where('cur.culture', '=', $culture))
            ->leftJoin('term_i18n as src', fn ($j) => $j->on('src.id', '=', 'term.id')->on('src.culture', '=', 'term.source_culture'))
            ->where('term.taxonomy_id', self::LEVEL_TAXONOMY_ID)
            ->orderBy('term.lft')
            ->select(DB::raw('COALESCE(cur.name, src.name) AS name'))
            ->pluck('name')
            ->all();

        return array_values(array_unique(array_filter(array_map('strval', $rows), 'strlen')));
    }

    /**
     * Trim cells, drop rows with nothing in them, keep the grid position.
     *
     * @return array<int, array<string, string>> grid row index => [field => value]
     */
    public function normalise(array $rows): array
    {
        $out = [];
        foreach (array_values($rows) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            foreach (array_keys(self::COLUMNS) as $field) {
                $v = $row[$field] ?? '';
                $v = is_scalar($v) ? trim((string) $v) : '';
                $clean[$field] = str_replace("\r\n", "\n", $v); // pasted cells
            }
            if (implode('', $clean) === '') {
                continue;
            }
            // A start/end date with no display date would otherwise create no
            // event at commit; derive the display text from them.
            if ($clean['creationDates'] === '' && ($clean['creationDatesStart'] !== '' || $clean['creationDatesEnd'] !== '')) {
                $s = $clean['creationDatesStart'];
                $e = $clean['creationDatesEnd'];
                $clean['creationDates'] = ($s === '' || $e === '' || $s === $e) ? ($s ?: $e) : $s.' - '.$e;
            }
            $out[$i] = $clean;
        }

        return $out;
    }

    /**
     * Blocking checks: [grid row index => [field => message]]. Level names match
     * without regard to case and are rewritten to the exact term name, since the
     * commit looks the term up by name.
     */
    public function validate(array &$rows, array $levelNames): array
    {
        $errors = [];
        $levels = [];
        foreach ($levelNames as $name) {
            $levels[mb_strtolower($name)] = $name;
        }
        if (count($rows) > self::MAX_ROWS) {
            $errors[array_key_first($rows)]['title'] = __('At most :n rows can be saved at once.', ['n' => self::MAX_ROWS]);

            return $errors;
        }
        foreach ($rows as $i => &$row) {
            if ($row['title'] === '') {
                $errors[$i]['title'] = __('Title is required.');
            } elseif (mb_strlen($row['title']) > 1024) {
                $errors[$i]['title'] = __('Title is longer than 1024 characters.');
            }
            if (mb_strlen($row['identifier']) > 1024) {
                $errors[$i]['identifier'] = __('Identifier is longer than 1024 characters.');
            }
            if ($row['levelOfDescription'] !== '') {
                $key = mb_strtolower($row['levelOfDescription']);
                if (isset($levels[$key])) {
                    $row['levelOfDescription'] = $levels[$key];
                } else {
                    $errors[$i]['levelOfDescription'] = __('Unknown level of description ":level".', ['level' => $row['levelOfDescription']]);
                }
            }
            foreach (['creationDatesStart', 'creationDatesEnd'] as $f) {
                if ($row[$f] !== '' && ! self::isIsoDate($row[$f])) {
                    $errors[$i][$f] = __('Use YYYY, YYYY-MM or YYYY-MM-DD.');
                }
            }
            if ($row['creationDatesStart'] !== '' && $row['creationDatesEnd'] !== ''
                && empty($errors[$i]['creationDatesStart']) && empty($errors[$i]['creationDatesEnd'])
                && self::fullDate($row['creationDatesStart'], false) > self::fullDate($row['creationDatesEnd'], true)) {
                $errors[$i]['creationDatesEnd'] = __('End date is before the start date.');
            }
        }
        unset($row);

        return $errors;
    }

    public static function isIsoDate(string $v): bool
    {
        return self::fullDate($v, false) !== null && (bool) preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $v);
    }

    /**
     * A start or end date as the DATE column takes it: a year or year-month
     * becomes its first (start) or last (end) day; anything else is null.
     * Same rule as the AHG Extensions for AtoM ingest (IngestCommitService::fullDate).
     */
    public static function fullDate(string $value, bool $end): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{4})$/', $value, $m)) {
            return $end ? $m[1].'-12-31' : $m[1].'-01-01';
        }
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $value, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            $first = sprintf('%s-%02d-01', $m[1], (int) $m[2]);

            return $end ? date('Y-m-t', strtotime($first)) : $first;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%s-%02d-%02d', $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /**
     * Stage validated grid rows as an ingest session under $parentId, enrich
     * them and move the session to its commit step. Returns the session id.
     */
    public function stage(int $userId, int $parentId, string $parentTitle, array $rows): int
    {
        $sessionId = $this->ingest->createSession($userId, [
            'title' => 'Grid entry under '.$parentTitle.' ('.count($rows).' rows)',
            'entity_type' => 'description',
            'sector' => 'archive',
            'standard' => 'isadg',
            'parent_id' => $parentId,
            'parent_placement' => 'existing',
            'output_create_records' => 1,
            'derivative_thumbnails' => 0,
            'derivative_reference' => 0,
            'process_virus_scan' => 0,
            'source' => 'grid',
        ]);

        $order = 0;
        foreach (array_keys(self::COLUMNS) as $field) {
            DB::table('ingest_mapping')->insert([
                'session_id' => $sessionId, 'source_column' => $field, 'target_field' => $field,
                'is_ignored' => 0, 'sort_order' => ++$order,
            ]);
        }
        $n = 0;
        foreach ($rows as $row) {
            DB::table('ingest_row')->insert([
                'session_id' => $sessionId, 'row_number' => ++$n,
                'level_of_description' => $row['levelOfDescription'] !== '' ? $row['levelOfDescription'] : null,
                'title' => $row['title'],
                'data' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }

        $this->ingest->enrichRows($sessionId);
        $this->ingest->updateSessionStatus($sessionId, 'commit');

        return $sessionId;
    }

    /** The existing child descriptions of $parentId, shown beside the grid. */
    public function children(int $parentId, string $culture = 'en', int $limit = 500): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as cur', fn ($j) => $j->on('cur.id', '=', 'io.id')->where('cur.culture', '=', $culture))
            ->leftJoin('information_object_i18n as src', fn ($j) => $j->on('src.id', '=', 'io.id')->on('src.culture', '=', 'io.source_culture'))
            ->leftJoin('term_i18n as lvl', fn ($j) => $j->on('lvl.id', '=', 'io.level_of_description_id')->where('lvl.culture', '=', $culture))
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.parent_id', $parentId)
            ->orderBy('io.lft')
            ->limit($limit)
            ->select(
                'io.id', 'io.identifier', 'slug.slug', 'lvl.name as level',
                DB::raw('COALESCE(cur.title, src.title) AS title'),
                DB::raw('COALESCE(cur.extent_and_medium, src.extent_and_medium) AS extent'),
                DB::raw('COALESCE(cur.scope_and_content, src.scope_and_content) AS scope'),
                DB::raw("(SELECT CONCAT_WS('|', COALESCE(ei.date, ''), COALESCE(e.start_date, ''), COALESCE(e.end_date, ''))
                          FROM event e LEFT JOIN event_i18n ei ON ei.id = e.id AND ei.culture = e.source_culture
                          WHERE e.object_id = io.id AND e.type_id = ".TermId::EVENT_TYPE_CREATION.' ORDER BY e.id LIMIT 1) AS dates')
            )
            ->get()
            ->all();
    }
}
