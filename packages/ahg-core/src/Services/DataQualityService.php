<?php

/**
 * DataQualityService - Heratio ahg-core
 *
 * Read-only metadata completeness / data-quality auditor for cataloguers. It
 * surfaces how many PUBLISHED archival descriptions are missing each key
 * descriptive field so the gaps can be closed. This is DISTINCT from the
 * capture-priority register (heratio#1205, which is about at-risk physical
 * capture): this is purely about the QUALITY of the metadata already recorded.
 *
 * "Key fields" audited per record:
 *   - title                 (information_object_i18n.title, non-empty)
 *   - scope / abstract      (information_object_i18n.scope_and_content, non-empty)
 *   - level of description  (information_object.level_of_description_id)
 *   - creation date         (an `event` row with a real, non-empty start_date)
 *   - creator               (an `event` row carrying an actor_id)
 *   - subjects              (an object_term_relation to a Subjects-taxonomy term)
 *   - digital object        (any digital_object row for the record)
 *   - master surrogate      (a MASTER/preservation digital_object, usage_id 140)
 *
 * Every figure is a cheap aggregate COUNT (no per-record loops in SQL), each
 * query Schema::hasTable-guarded and wrapped in its own try/catch so a missing
 * table or a transient failure yields a clean zero rather than a 500. The
 * service performs NO writes and makes NO AI calls.
 *
 * Published = a `status` row with type_id 158 (publication status) and
 * status_id 160 (published). The synthetic root description (id 1) is excluded
 * throughout - it is not a real description.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataQualityService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** usage_id of a MASTER (preservation) digital object. */
    private const USAGE_MASTER = 140;

    /** taxonomy_id of the Subjects taxonomy (object_term_relation -> term). */
    private const TAXONOMY_SUBJECTS = 35;

    /**
     * The key descriptive fields audited, in display order. The keys are stable
     * identifiers used in the per-issue breakdown and the sample table; the
     * label is the international, human-readable name.
     *
     * @var array<string, string>
     */
    public const ISSUE_LABELS = [
        'no_title'    => 'No title',
        'no_scope'    => 'No scope / abstract',
        'no_level'    => 'No level of description',
        'no_creation' => 'No creation date',
        'no_creator'  => 'No creator',
        'no_subjects' => 'No subjects',
        'no_digital'  => 'No digital object',
        'no_master'   => 'No master surrogate',
    ];

    /**
     * Build the data-quality report.
     *
     * @param  array  $opts  sampleLimit (int, default 50, hard cap 200)
     * @return array{
     *     total:int,
     *     completeness_pct:float,
     *     complete:int,
     *     issues: array<string, array{label:string,count:int,pct:float}>,
     *     sample: array<int, array{id:int,title:string,slug:?string,missing:array<int,string>,missing_count:int}>,
     *     generated_at:string,
     *     error:bool
     * }
     */
    public function report(array $opts = []): array
    {
        $sampleLimit = (int) ($opts['sampleLimit'] ?? 50);
        if ($sampleLimit < 0) {
            $sampleLimit = 0;
        } elseif ($sampleLimit > 200) {
            $sampleLimit = 200;
        }

        $total = $this->countPublished();

        // Per-issue missing counts. Each is an independent, guarded aggregate so a
        // single failing/absent table cannot take down the whole report.
        $issueCounts = [
            'no_title'    => $this->countMissingTitle(),
            'no_scope'    => $this->countMissingScope(),
            'no_level'    => $this->countMissingLevel(),
            'no_creation' => $this->countMissingCreationDate(),
            'no_creator'  => $this->countMissingCreator(),
            'no_subjects' => $this->countMissingSubjects(),
            'no_digital'  => $this->countMissingDigitalObject(),
            'no_master'   => $this->countMissingMaster(),
        ];

        $issues = [];
        foreach (self::ISSUE_LABELS as $key => $label) {
            $count = (int) ($issueCounts[$key] ?? 0);
            $issues[$key] = [
                'label' => $label,
                'count' => $count,
                'pct'   => $this->pct($count, $total),
            ];
        }

        // A record is "complete" when it is missing none of the key fields. We do
        // not have a per-record completeness count from the aggregates alone, so we
        // derive "complete" from the worst-record sample scan, which visits every
        // published record once (bounded select of ids + a few cheap presence sets)
        // and returns both the complete tally and the worst-N sample.
        $scan = $this->scanRecords($sampleLimit);
        $complete = $scan['complete'];
        $sample = $scan['sample'];

        return [
            'total'            => $total,
            'complete'         => $complete,
            'completeness_pct' => $this->pct($complete, $total),
            'issues'           => $issues,
            'sample'           => $sample,
            'generated_at'     => now()->toDateTimeString(),
            'error'            => $scan['error'],
        ];
    }

    /**
     * Total PUBLISHED, non-root information objects.
     *
     * SQL: SELECT COUNT(DISTINCT object_id) FROM status
     *      WHERE type_id=158 AND status_id=160 AND object_id > 1
     */
    private function countPublished(): int
    {
        if (! Schema::hasTable('status')) {
            return 0;
        }
        try {
            return (int) DB::table('status')
                ->where('type_id', self::STATUS_TYPE_PUBLICATION)
                ->where('status_id', self::STATUS_PUBLISHED)
                ->where('object_id', '>', self::ROOT_ID)
                ->distinct()
                ->count('object_id');
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countPublished failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * A reusable subquery: object_ids of every published, non-root record. Used by
     * every "missing" count so each one only ever measures the published set.
     */
    private function publishedIdSub()
    {
        return DB::table('status')
            ->select('object_id')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->where('object_id', '>', self::ROOT_ID);
    }

    /**
     * Published records whose source-culture title row is missing or blank.
     *
     * SQL: COUNT over published io LEFT JOIN i18n (id + source_culture)
     *      WHERE title IS NULL OR TRIM(title) = ''
     */
    private function countMissingTitle(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('information_object_i18n')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n', function ($j) {
                    $j->on('i18n.id', '=', 'io.id')
                        ->on('i18n.culture', '=', 'io.source_culture');
                })
                ->where(function ($q) {
                    $q->whereNull('i18n.title')
                        ->orWhereRaw("TRIM(i18n.title) = ''");
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingTitle failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no scope / abstract (scope_and_content blank).
     *
     * SQL: COUNT over published io LEFT JOIN i18n
     *      WHERE scope_and_content IS NULL OR TRIM(scope_and_content) = ''
     */
    private function countMissingScope(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('information_object_i18n')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n', function ($j) {
                    $j->on('i18n.id', '=', 'io.id')
                        ->on('i18n.culture', '=', 'io.source_culture');
                })
                ->where(function ($q) {
                    $q->whereNull('i18n.scope_and_content')
                        ->orWhereRaw("TRIM(i18n.scope_and_content) = ''");
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingScope failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no level_of_description_id set.
     *
     * SQL: COUNT over published io WHERE io.level_of_description_id IS NULL
     */
    private function countMissingLevel(): int
    {
        if (! Schema::hasTable('information_object')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNull('io.level_of_description_id')
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingLevel failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no creation date: no `event` row that carries a real,
     * non-empty start_date. (Any event type qualifies - a dated event is a date.)
     *
     * SQL: COUNT over published io WHERE NOT EXISTS (
     *        SELECT 1 FROM event e
     *        WHERE e.object_id = io.id
     *          AND e.start_date IS NOT NULL AND TRIM(e.start_date) <> '' )
     */
    private function countMissingCreationDate(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('event')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('event as e')
                        ->whereColumn('e.object_id', 'io.id')
                        ->whereNotNull('e.start_date')
                        ->whereRaw("TRIM(e.start_date) <> ''");
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingCreationDate failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no creator: no `event` row carrying an actor_id.
     *
     * SQL: COUNT over published io WHERE NOT EXISTS (
     *        SELECT 1 FROM event e
     *        WHERE e.object_id = io.id AND e.actor_id IS NOT NULL )
     */
    private function countMissingCreator(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('event')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('event as e')
                        ->whereColumn('e.object_id', 'io.id')
                        ->whereNotNull('e.actor_id');
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingCreator failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no subjects: no object_term_relation to a term in the
     * Subjects taxonomy (id 35).
     *
     * SQL: COUNT over published io WHERE NOT EXISTS (
     *        SELECT 1 FROM object_term_relation otr
     *        JOIN term t ON t.id = otr.term_id
     *        WHERE otr.object_id = io.id AND t.taxonomy_id = 35 )
     */
    private function countMissingSubjects(): int
    {
        if (! Schema::hasTable('information_object')
            || ! Schema::hasTable('object_term_relation')
            || ! Schema::hasTable('term')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('object_term_relation as otr')
                        ->join('term as t', 't.id', '=', 'otr.term_id')
                        ->whereColumn('otr.object_id', 'io.id')
                        ->where('t.taxonomy_id', self::TAXONOMY_SUBJECTS);
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingSubjects failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no digital object at all (no digital_object row).
     *
     * SQL: COUNT over published io WHERE NOT EXISTS (
     *        SELECT 1 FROM digital_object d WHERE d.object_id = io.id )
     */
    private function countMissingDigitalObject(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('digital_object')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('digital_object as d')
                        ->whereColumn('d.object_id', 'io.id');
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingDigitalObject failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records with no MASTER/preservation surrogate (no digital_object
     * row with usage_id 140).
     *
     * SQL: COUNT over published io WHERE NOT EXISTS (
     *        SELECT 1 FROM digital_object d
     *        WHERE d.object_id = io.id AND d.usage_id = 140 )
     */
    private function countMissingMaster(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('digital_object')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('digital_object as d')
                        ->whereColumn('d.object_id', 'io.id')
                        ->where('d.usage_id', self::USAGE_MASTER);
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality countMissingMaster failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Single bounded pass over the published set to (a) tally how many records are
     * fully complete and (b) collect the worst-N records (most missing fields) for
     * the drill-in table. This is the one place that needs per-record presence, so
     * we build it from a small number of cheap, set-based "which ids have X"
     * lookups (each guarded) and combine them in PHP - no per-record SQL.
     *
     * @return array{complete:int, sample: array<int, array>, error:bool}
     */
    private function scanRecords(int $sampleLimit): array
    {
        $empty = ['complete' => 0, 'sample' => [], 'error' => false];

        if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
            return $empty;
        }

        try {
            // Published, non-root records with their source-culture title + slug.
            $records = DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as i18n', function ($j) {
                    $j->on('i18n.id', '=', 'io.id')
                        ->on('i18n.culture', '=', 'io.source_culture');
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
                ->select([
                    'io.id',
                    'io.level_of_description_id',
                    'i18n.title',
                    'i18n.scope_and_content',
                    's.slug',
                ])
                ->orderBy('io.id')
                ->get();

            if ($records->isEmpty()) {
                return $empty;
            }

            // Presence sets keyed by io id, each O(1) isset() at scan time. Every set
            // is independently guarded; a missing optional table simply means "none
            // present", which is the correct, honest fallback (the field is absent).
            $hasCreationDate = $this->idSetWithCreationDate();
            $hasCreator      = $this->idSetWithCreator();
            $hasSubjects     = $this->idSetWithSubjects();
            $hasDigital      = $this->idSetWithDigitalObject();
            $hasMaster       = $this->idSetWithMaster();

            $complete = 0;
            $worst = [];

            foreach ($records as $rec) {
                $id = (int) $rec->id;
                $missing = [];

                if (trim((string) ($rec->title ?? '')) === '') {
                    $missing[] = self::ISSUE_LABELS['no_title'];
                }
                if (trim((string) ($rec->scope_and_content ?? '')) === '') {
                    $missing[] = self::ISSUE_LABELS['no_scope'];
                }
                if ((int) ($rec->level_of_description_id ?? 0) <= 0) {
                    $missing[] = self::ISSUE_LABELS['no_level'];
                }
                if (! isset($hasCreationDate[$id])) {
                    $missing[] = self::ISSUE_LABELS['no_creation'];
                }
                if (! isset($hasCreator[$id])) {
                    $missing[] = self::ISSUE_LABELS['no_creator'];
                }
                if (! isset($hasSubjects[$id])) {
                    $missing[] = self::ISSUE_LABELS['no_subjects'];
                }
                if (! isset($hasDigital[$id])) {
                    $missing[] = self::ISSUE_LABELS['no_digital'];
                }
                if (! isset($hasMaster[$id])) {
                    $missing[] = self::ISSUE_LABELS['no_master'];
                }

                if (empty($missing)) {
                    $complete++;

                    continue;
                }

                $worst[] = [
                    'id' => $id,
                    'title' => trim((string) ($rec->title ?? '')) !== ''
                        ? (string) $rec->title
                        : '(untitled record #'.$id.')',
                    'slug' => $rec->slug !== null ? (string) $rec->slug : null,
                    'missing' => $missing,
                    'missing_count' => count($missing),
                ];
            }

            // Worst first (most missing fields), ties broken by id for stable order.
            usort($worst, function ($a, $b) {
                return $b['missing_count'] <=> $a['missing_count'] ?: $a['id'] <=> $b['id'];
            });

            if ($sampleLimit > 0) {
                $worst = array_slice($worst, 0, $sampleLimit);
            }

            return ['complete' => $complete, 'sample' => $worst, 'error' => false];
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality scanRecords failed: '.$e->getMessage());

            return ['complete' => 0, 'sample' => [], 'error' => true];
        }
    }

    /**
     * io ids that have at least one event with a real start_date. One grouped query.
     *
     * @return array<int,int> [id => 1] for O(1) isset()
     */
    private function idSetWithCreationDate(): array
    {
        if (! Schema::hasTable('event')) {
            return [];
        }
        try {
            return DB::table('event')
                ->whereNotNull('object_id')
                ->whereNotNull('start_date')
                ->whereRaw("TRIM(start_date) <> ''")
                ->distinct()
                ->pluck('object_id')
                ->mapWithKeys(fn ($v) => [(int) $v => 1])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality idSetWithCreationDate failed: '.$e->getMessage());

            return [];
        }
    }

    /** io ids that have at least one event carrying an actor_id (a creator). */
    private function idSetWithCreator(): array
    {
        if (! Schema::hasTable('event')) {
            return [];
        }
        try {
            return DB::table('event')
                ->whereNotNull('object_id')
                ->whereNotNull('actor_id')
                ->distinct()
                ->pluck('object_id')
                ->mapWithKeys(fn ($v) => [(int) $v => 1])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality idSetWithCreator failed: '.$e->getMessage());

            return [];
        }
    }

    /** io ids that have at least one Subjects-taxonomy term relation. */
    private function idSetWithSubjects(): array
    {
        if (! Schema::hasTable('object_term_relation') || ! Schema::hasTable('term')) {
            return [];
        }
        try {
            return DB::table('object_term_relation as otr')
                ->join('term as t', 't.id', '=', 'otr.term_id')
                ->where('t.taxonomy_id', self::TAXONOMY_SUBJECTS)
                ->whereNotNull('otr.object_id')
                ->distinct()
                ->pluck('otr.object_id')
                ->mapWithKeys(fn ($v) => [(int) $v => 1])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality idSetWithSubjects failed: '.$e->getMessage());

            return [];
        }
    }

    /** io ids that have at least one digital object of any usage. */
    private function idSetWithDigitalObject(): array
    {
        if (! Schema::hasTable('digital_object')) {
            return [];
        }
        try {
            return DB::table('digital_object')
                ->whereNotNull('object_id')
                ->distinct()
                ->pluck('object_id')
                ->mapWithKeys(fn ($v) => [(int) $v => 1])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality idSetWithDigitalObject failed: '.$e->getMessage());

            return [];
        }
    }

    /** io ids that have a MASTER/preservation digital object (usage_id 140). */
    private function idSetWithMaster(): array
    {
        if (! Schema::hasTable('digital_object')) {
            return [];
        }
        try {
            return DB::table('digital_object')
                ->where('usage_id', self::USAGE_MASTER)
                ->whereNotNull('object_id')
                ->distinct()
                ->pluck('object_id')
                ->mapWithKeys(fn ($v) => [(int) $v => 1])
                ->all();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] data-quality idSetWithMaster failed: '.$e->getMessage());

            return [];
        }
    }

    /** Safe percentage (0-100, one decimal). Zero total -> 0.0, never divide-by-zero. */
    private function pct(int $part, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
