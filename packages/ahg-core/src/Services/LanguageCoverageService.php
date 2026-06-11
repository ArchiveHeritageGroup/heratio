<?php

/**
 * LanguageCoverageService - Heratio ahg-core
 *
 * heratio#1211 north-star ("every museum for everyone - universal multilingual
 * access"), NEXT public slice: a read-only LANGUAGE-COVERAGE analytic. It answers
 * "in which languages can a visitor actually read this collection's catalogue, and
 * how much of it?" - the discovery + invitation layer that sits in front of the
 * on-demand per-record translation surface (MultilingualRecordService).
 *
 * What it measures (cheap aggregate COUNTs only - NO per-record loops):
 *   - How many PUBLISHED archival descriptions carry a real title in each culture
 *     (information_object_i18n grouped by culture).
 *   - How many of those also carry descriptive prose (scope/content) per culture,
 *     so we can show "described", not just "titled", coverage.
 *   - The same culture grouping for the supporting authority records (actor_i18n)
 *     and the controlled vocabulary (term_i18n), so the picture is collection-wide.
 *   - A single headline "primary language" + a count of distinct languages present.
 *
 * Every figure is a grouped COUNT, each query Schema::hasTable-guarded and wrapped
 * in its own try/catch, so a missing table or a transient failure yields an empty
 * breakdown rather than a 500. The service performs NO writes and makes NO AI
 * calls. It is the analytics half of the slice; the on-demand gateway translation
 * lives in MultilingualRecordService and is reached only from the controller.
 *
 * Published = a `status` row with type_id 158 (publication status) and status_id
 * 160 (published). The synthetic root description (id 1) is excluded.
 *
 * Jurisdiction-neutral: no country-specific assumptions. Per the project rule
 * (feedback_af_before_nl) Afrikaans leads Dutch wherever both appear; the human
 * language labels are drawn from a shared, internationalisable map.
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

class LanguageCoverageService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object - never a real description. */
    private const ROOT_ID = 1;

    /** Cap on how many language rows any one breakdown surfaces (richest first). */
    private const MAX_ROWS = 40;

    /**
     * Human labels for language CODES, keyed on the lower-cased base subtag. Kept in
     * step with MultilingualRecordService so the coverage view and the translate
     * picker read the same names. Unknown codes fall back to the upper-cased code.
     *
     * @var array<string,string>
     */
    private const LANGUAGE_LABELS = [
        'en'  => 'English',
        'af'  => 'Afrikaans',
        'fr'  => 'Français',
        'nl'  => 'Nederlands',
        'pt'  => 'Português',
        'es'  => 'Español',
        'de'  => 'Deutsch',
        'it'  => 'Italiano',
        'zu'  => 'isiZulu',
        'xh'  => 'isiXhosa',
        'st'  => 'Sesotho',
        'tn'  => 'Setswana',
        'nso' => 'Sepedi',
        'ts'  => 'Xitsonga',
        'ss'  => 'siSwati',
        've'  => 'Tshivenda',
        'nr'  => 'isiNdebele',
        'sn'  => 'chiShona',
        'sw'  => 'Kiswahili',
        'ar'  => 'العربية',
        'zh'  => '中文',
        'ja'  => '日本語',
        'ru'  => 'Русский',
        'pl'  => 'Polski',
        'cy'  => 'Cymraeg',
        'hr'  => 'Hrvatski',
        'bs'  => 'Bosanski',
        'mk'  => 'Македонски',
    ];

    /**
     * Build the full language-coverage snapshot.
     *
     * Shape (every key always present; numbers are ints; lists are arrays):
     *
     * @return array{
     *     total_published:int,
     *     primary:?array{code:string,label:string,count:int,pct:float},
     *     language_count:int,
     *     descriptions: array<int, array{code:string,label:string,titled:int,described:int,pct:float}>,
     *     actors: array<int, array{code:string,label:string,count:int,pct:float}>,
     *     terms: array<int, array{code:string,label:string,count:int,pct:float}>,
     *     generated_at:string,
     *     error:bool
     * }
     */
    public function snapshot(): array
    {
        $total = $this->countPublished();

        $descriptions = $this->descriptionCoverage($total);
        $actors = $this->actorCoverage();
        $terms = $this->termCoverage();

        // Primary language = the description-culture with the most titled records.
        $primary = null;
        if (! empty($descriptions)) {
            $top = $descriptions[0];
            $primary = [
                'code'  => $top['code'],
                'label' => $top['label'],
                'count' => $top['titled'],
                'pct'   => $top['pct'],
            ];
        }

        return [
            'total_published' => $total,
            'primary'         => $primary,
            'language_count'  => count($descriptions),
            'descriptions'    => $descriptions,
            'actors'          => $actors,
            'terms'           => $terms,
            'generated_at'    => now()->toDateTimeString(),
            'error'           => false,
        ];
    }

    // ---------------------------------------------------------------------
    // Totals
    // ---------------------------------------------------------------------

    /**
     * Total PUBLISHED, non-root information objects.
     *
     * SQL: SELECT COUNT(DISTINCT object_id) FROM status
     *      WHERE type_id=158 AND status_id=160 AND object_id > 1
     */
    public function countPublished(): int
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
            \Log::warning('[ahg-core] language-coverage countPublished failed: '.$e->getMessage());

            return 0;
        }
    }

    /** Reusable subquery: object_ids of every published, non-root record. */
    private function publishedIdSub()
    {
        return DB::table('status')
            ->select('object_id')
            ->where('type_id', self::STATUS_TYPE_PUBLICATION)
            ->where('status_id', self::STATUS_PUBLISHED)
            ->where('object_id', '>', self::ROOT_ID);
    }

    // ---------------------------------------------------------------------
    // Breakdowns
    // ---------------------------------------------------------------------

    /**
     * Per-language coverage of PUBLISHED archival descriptions.
     *
     * Two grouped COUNTs over the published set's i18n rows: how many have a real
     * (non-blank) title in each culture, and how many ALSO carry descriptive prose
     * (scope_and_content). One row per culture, richest (most titled) first. The
     * pct is of the total published descriptions, so the bars communicate reach.
     *
     * SQL (essence):
     *   SELECT i.culture,
     *          SUM(TRIM(i.title) <> '') AS titled,
     *          SUM(TRIM(COALESCE(i.scope_and_content,'')) <> '') AS described
     *   FROM information_object_i18n i
     *   JOIN (published ids) pub ON pub.object_id = i.id
     *   GROUP BY i.culture ORDER BY titled DESC
     *
     * Everything is grouped/aggregate; no per-record PHP loop touches the DB.
     *
     * @return array<int, array{code:string,label:string,titled:int,described:int,pct:float}>
     */
    public function descriptionCoverage(int $total): array
    {
        if (! Schema::hasTable('information_object_i18n') || ! Schema::hasTable('status')) {
            return [];
        }
        try {
            $rows = DB::table('information_object_i18n as i')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'i.id')
                ->whereNotNull('i.culture')
                ->whereRaw("TRIM(i.culture) <> ''")
                ->groupBy('i.culture')
                ->select([
                    'i.culture as culture',
                    DB::raw("SUM(CASE WHEN TRIM(COALESCE(i.title,'')) <> '' THEN 1 ELSE 0 END) as titled"),
                    DB::raw("SUM(CASE WHEN TRIM(COALESCE(i.scope_and_content,'')) <> '' THEN 1 ELSE 0 END) as described"),
                ])
                ->orderByDesc('titled')
                ->limit(self::MAX_ROWS)
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $titled = (int) $r->titled;
                if ($titled <= 0) {
                    continue; // a culture row with no real title is not coverage
                }
                $code = $this->normaliseLang((string) $r->culture);
                $out[] = [
                    'code'      => $code,
                    'label'     => $this->label($code),
                    'titled'    => $titled,
                    'described' => (int) $r->described,
                    'pct'       => $this->pct($titled, $total),
                ];
            }

            return $this->afBeforeNl($out);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] language-coverage descriptionCoverage failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Per-language coverage of the authority records (actors: people, families,
     * organisations). One grouped COUNT over actor_i18n with a real authorised
     * name. pct is of the largest single-language total, so the bars are relative
     * to the best-covered language (there is no "grand total" of authority records
     * that is meaningful across languages here).
     *
     * @return array<int, array{code:string,label:string,count:int,pct:float}>
     */
    public function actorCoverage(): array
    {
        return $this->namedI18nCoverage(
            'actor_i18n',
            'authorized_form_of_name',
            'language-coverage actorCoverage'
        );
    }

    /**
     * Per-language coverage of the controlled vocabulary (terms: subjects, places,
     * etc.). One grouped COUNT over term_i18n with a real name.
     *
     * @return array<int, array{code:string,label:string,count:int,pct:float}>
     */
    public function termCoverage(): array
    {
        return $this->namedI18nCoverage(
            'term_i18n',
            'name',
            'language-coverage termCoverage'
        );
    }

    /**
     * Shared grouped-COUNT over an *_i18n table: rows per culture that carry a
     * real (non-blank) value in $nameColumn, richest first. pct is relative to the
     * top language's count (the best-covered language reads as 100%). Never throws.
     *
     * @return array<int, array{code:string,label:string,count:int,pct:float}>
     */
    private function namedI18nCoverage(string $table, string $nameColumn, string $tag): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }
        try {
            $rows = DB::table($table)
                ->whereNotNull('culture')
                ->whereRaw("TRIM(culture) <> ''")
                ->whereNotNull($nameColumn)
                ->whereRaw("TRIM(COALESCE($nameColumn,'')) <> ''")
                ->groupBy('culture')
                ->select([
                    'culture',
                    DB::raw('COUNT(*) as cnt'),
                ])
                ->orderByDesc('cnt')
                ->limit(self::MAX_ROWS)
                ->get();

            $max = 0;
            foreach ($rows as $r) {
                $max = max($max, (int) $r->cnt);
            }

            $out = [];
            foreach ($rows as $r) {
                $count = (int) $r->cnt;
                if ($count <= 0) {
                    continue;
                }
                $code = $this->normaliseLang((string) $r->culture);
                $out[] = [
                    'code'  => $code,
                    'label' => $this->label($code),
                    'count' => $count,
                    'pct'   => $this->pct($count, $max),
                ];
            }

            return $this->afBeforeNl($out);
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] '.$tag.' failed: '.$e->getMessage());

            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** Human label for a language code, falling back to the upper-cased code. */
    private function label(string $code): string
    {
        return self::LANGUAGE_LABELS[$code] ?? strtoupper($code);
    }

    /**
     * Project rule (feedback_af_before_nl): Afrikaans is canonical / first-class and
     * Dutch is the secondary contrastive term. When BOTH appear in a breakdown,
     * ensure the 'af' row is ordered immediately before the 'nl' row so coverage
     * never leads Afrikaans with Dutch. Pure presentation reorder - counts and the
     * set of rows are unchanged; the relative order of every other row is preserved.
     *
     * @param  array<int,array{code:string}>  $rows
     * @return array<int,array>
     */
    private function afBeforeNl(array $rows): array
    {
        $codes = array_column($rows, 'code');
        if (! in_array('af', $codes, true) || ! in_array('nl', $codes, true)) {
            return $rows;
        }

        // Pull the af row out, then splice it back in immediately before nl.
        $afRow = null;
        $rest = [];
        foreach ($rows as $row) {
            if ($row['code'] === 'af' && $afRow === null) {
                $afRow = $row;
                continue;
            }
            $rest[] = $row;
        }
        if ($afRow === null) {
            return $rows;
        }

        $nlPos = null;
        foreach ($rest as $i => $row) {
            if ($row['code'] === 'nl') {
                $nlPos = $i;
                break;
            }
        }
        array_splice($rest, $nlPos === null ? count($rest) : $nlPos, 0, [$afRow]);

        return $rest;
    }

    /**
     * Normalise a culture/locale code to its lower-cased base subtag
     * (e.g. "pt_BR" / "fr-CA" -> "pt" / "fr"). Leaves multi-letter codes (nso) intact.
     */
    private function normaliseLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        $base = preg_split('/[-_]/', $lang)[0] ?? $lang;

        return $base !== '' ? $base : $lang;
    }

    /** Safe percentage (0-100, one decimal). Zero base -> 0.0, never divide-by-zero. */
    private function pct(int $part, int $base): float
    {
        if ($base <= 0) {
            return 0.0;
        }

        return round(($part / $base) * 100, 1);
    }
}
