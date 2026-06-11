<?php

/**
 * CollectionOverviewService - Heratio ahg-core
 *
 * Read-only, visitor-facing "Collection at a glance" aggregator. It produces a
 * POSITIVE snapshot of how rich and how large the PUBLISHED collection is - the
 * opposite framing of the admin DataQualityService (which surfaces gaps). This
 * service answers "what does this collection hold?" for the general public:
 *
 *   - total published descriptions
 *   - the shape of the collection by level of description (fonds / series / item)
 *   - which repositories hold the most (top N)
 *   - how much of it is digitised (any digital object), in deep-zoom IIIF, or in 3D
 *   - how many actors, repositories, subject and place access points it connects
 *   - a by-century distribution of creation/event dates, when cheaply available
 *
 * Every figure is a cheap aggregate (COUNT / grouped COUNT) - there are NO
 * per-record loops. Each query is Schema::hasTable-guarded and wrapped in its own
 * try/catch, so a missing table or a transient failure yields a clean zero (or an
 * empty breakdown) rather than a 500. The service performs NO writes and makes NO
 * AI calls.
 *
 * Published = a `status` row with type_id 158 (publication status) and status_id
 * 160 (published). The synthetic root description (id 1) is excluded throughout -
 * it is not a real description.
 *
 * Jurisdiction-neutral: no country-specific assumptions; all copy lives in the
 * view and is internationalised there.
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

class CollectionOverviewService
{
    /** Publication-status taxonomy: status.type_id of a publication-status row. */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** status.status_id meaning "published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information object / root actor - never a real entity. */
    private const ROOT_ID = 1;

    /** taxonomy_id of the Level of Description taxonomy (term -> level labels). */
    private const TAXONOMY_LEVEL = 34;

    /** taxonomy_id of the Subjects taxonomy. */
    private const TAXONOMY_SUBJECTS = 35;

    /** taxonomy_id of the Places taxonomy. */
    private const TAXONOMY_PLACES = 42;

    /** How many holding repositories to surface in the "top holdings" breakdown. */
    private const TOP_REPOSITORIES = 8;

    /** How many distinct centuries to surface in the date-span breakdown. */
    private const MAX_CENTURIES = 12;

    /**
     * Filename extensions that mark a digital object as a 3D model. Matched against
     * digital_object.name with a case-insensitive LIKE so the check stays a cheap,
     * index-free aggregate (the set is small and bounded).
     *
     * @var array<int,string>
     */
    private const EXT_3D = ['glb', 'gltf', 'obj', 'fbx', 'stl', 'ply', 'splat'];

    /**
     * Filename extensions that mark a digital object as a deep-zoom (IIIF) image -
     * the pyramidal/tiled formats Cantaloupe serves.
     *
     * @var array<int,string>
     */
    private const EXT_IIIF = ['tif', 'tiff', 'jp2', 'jpx', 'jpf'];

    /**
     * Build the public "Collection at a glance" snapshot.
     *
     * Shape (every key always present, every number an int, every list an array):
     *
     * @return array{
     *     total:int,
     *     by_level: array<int, array{id:?int,label:string,count:int,pct:float}>,
     *     by_repository: array<int, array{id:?int,label:string,count:int,pct:float}>,
     *     by_century: array<int, array{century:int,label:string,from:int,to:int,count:int,pct:float}>,
     *     digital: array{any:int,iiif:int,three_d:int,any_pct:float,iiif_pct:float,three_d_pct:float},
     *     entities: array{actors:int,repositories:int,subjects:int,places:int},
     *     generated_at:string,
     *     error:bool
     * }
     */
    public function overview(): array
    {
        $error = false;

        $total = $this->countPublished();

        $byLevel = $this->byLevel($total);
        $byRepository = $this->byRepository($total);
        $byCentury = $this->byCentury($total);

        $anyDigital = $this->countWithDigitalObject();
        $iiif = $this->countWithIiifImage();
        $threeD = $this->countWith3dModel();

        $digital = [
            'any'          => $anyDigital,
            'iiif'         => $iiif,
            'three_d'      => $threeD,
            'any_pct'      => $this->pct($anyDigital, $total),
            'iiif_pct'     => $this->pct($iiif, $total),
            'three_d_pct'  => $this->pct($threeD, $total),
        ];

        $entities = [
            'actors'       => $this->countActors(),
            'repositories' => $this->countRepositories(),
            'subjects'     => $this->countTerms(self::TAXONOMY_SUBJECTS),
            'places'       => $this->countTerms(self::TAXONOMY_PLACES),
        ];

        return [
            'total'         => $total,
            'by_level'      => $byLevel,
            'by_repository' => $byRepository,
            'by_century'    => $byCentury,
            'digital'       => $digital,
            'entities'      => $entities,
            'generated_at'  => now()->toDateTimeString(),
            'error'         => $error,
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
            \Log::warning('[ahg-core] collection-overview countPublished failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * A reusable subquery: object_ids of every published, non-root record. Used by
     * every breakdown/coverage count so each only ever measures the published set.
     */
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
     * Published descriptions grouped by level of description, richest first.
     *
     * One grouped COUNT over the published set joined to the level taxonomy's
     * source-culture i18n label. Records with no level land under a single
     * "Not specified" row (id null) so the bars still add up to the total.
     *
     * SQL (essence):
     *   SELECT io.level_of_description_id AS id, ti.name AS label, COUNT(*) AS cnt
     *   FROM information_object io
     *   JOIN (published ids) pub ON pub.object_id = io.id
     *   LEFT JOIN term t  ON t.id = io.level_of_description_id
     *   LEFT JOIN term_i18n ti ON ti.id = t.id AND ti.culture = t.source_culture
     *   GROUP BY io.level_of_description_id, ti.name
     *   ORDER BY cnt DESC
     *
     * @return array<int, array{id:?int,label:string,count:int,pct:float}>
     */
    private function byLevel(int $total): array
    {
        if (! Schema::hasTable('information_object')) {
            return [];
        }
        try {
            $hasTerm = Schema::hasTable('term') && Schema::hasTable('term_i18n');

            $q = DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id');

            if ($hasTerm) {
                $q->leftJoin('term as t', 't.id', '=', 'io.level_of_description_id')
                    ->leftJoin('term_i18n as ti', function ($j) {
                        $j->on('ti.id', '=', 't.id')
                            ->on('ti.culture', '=', 't.source_culture');
                    })
                    ->select([
                        'io.level_of_description_id as id',
                        'ti.name as label',
                        DB::raw('COUNT(*) as cnt'),
                    ])
                    ->groupBy('io.level_of_description_id', 'ti.name');
            } else {
                $q->select([
                    'io.level_of_description_id as id',
                    DB::raw('NULL as label'),
                    DB::raw('COUNT(*) as cnt'),
                ])->groupBy('io.level_of_description_id');
            }

            $rows = $q->orderByDesc('cnt')->get();

            $out = [];
            foreach ($rows as $r) {
                $id = $r->id !== null ? (int) $r->id : null;
                $label = trim((string) ($r->label ?? ''));
                if ($label === '') {
                    $label = __('Not specified');
                }
                $count = (int) $r->cnt;
                $out[] = [
                    'id'    => $id,
                    'label' => $label,
                    'count' => $count,
                    'pct'   => $this->pct($count, $total),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview byLevel failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Published descriptions grouped by holding repository, largest holdings first,
     * capped at TOP_REPOSITORIES. Records with no repository are not surfaced as a
     * holding (a "holding" needs a holder); they still count in the grand total.
     *
     * SQL (essence):
     *   SELECT io.repository_id AS id, rai.authorized_form_of_name AS label, COUNT(*) AS cnt
     *   FROM information_object io
     *   JOIN (published ids) pub ON pub.object_id = io.id
     *   LEFT JOIN actor_i18n rai ON rai.id = io.repository_id AND rai.culture = <source>
     *   WHERE io.repository_id IS NOT NULL
     *   GROUP BY io.repository_id, rai.authorized_form_of_name
     *   ORDER BY cnt DESC LIMIT 8
     *
     * Repository names live in actor_i18n (CTI: a repository is an actor). We join
     * the repository's own source_culture row for a stable label.
     *
     * @return array<int, array{id:?int,label:string,count:int,pct:float}>
     */
    private function byRepository(int $total): array
    {
        if (! Schema::hasTable('information_object')) {
            return [];
        }
        try {
            $hasNames = Schema::hasTable('actor') && Schema::hasTable('actor_i18n');

            $q = DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereNotNull('io.repository_id')
                ->where('io.repository_id', '>', self::ROOT_ID);

            if ($hasNames) {
                $q->leftJoin('actor as ra', 'ra.id', '=', 'io.repository_id')
                    ->leftJoin('actor_i18n as rai', function ($j) {
                        $j->on('rai.id', '=', 'ra.id')
                            ->on('rai.culture', '=', 'ra.source_culture');
                    })
                    ->select([
                        'io.repository_id as id',
                        'rai.authorized_form_of_name as label',
                        DB::raw('COUNT(*) as cnt'),
                    ])
                    ->groupBy('io.repository_id', 'rai.authorized_form_of_name');
            } else {
                $q->select([
                    'io.repository_id as id',
                    DB::raw('NULL as label'),
                    DB::raw('COUNT(*) as cnt'),
                ])->groupBy('io.repository_id');
            }

            $rows = $q->orderByDesc('cnt')->limit(self::TOP_REPOSITORIES)->get();

            $out = [];
            foreach ($rows as $r) {
                $label = trim((string) ($r->label ?? ''));
                if ($label === '') {
                    $label = __('Unnamed repository');
                }
                $count = (int) $r->cnt;
                $out[] = [
                    'id'    => $r->id !== null ? (int) $r->id : null,
                    'label' => $label,
                    'count' => $count,
                    'pct'   => $this->pct($count, $total),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview byRepository failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Published descriptions distributed by century of their earliest dated event.
     *
     * The `event.start_date` column is a free-text/ISO date; we derive a 4-digit
     * year cheaply in SQL (LEFT(...,4)) and bucket by century. Only events that
     * belong to a published record and whose start_date begins with a plausible
     * 4-digit year (1000-2999) are counted - one record contributes once, by its
     * MIN(year), so the buckets sum to "records with a usable date" (<= total).
     *
     * SQL (essence):
     *   SELECT FLOOR((yr - 1) / 100) + 1 AS century, COUNT(*) AS cnt FROM (
     *     SELECT e.object_id, MIN(CAST(LEFT(e.start_date,4) AS UNSIGNED)) AS yr
     *     FROM event e JOIN (published ids) pub ON pub.object_id = e.object_id
     *     WHERE e.start_date REGEXP '^[12][0-9]{3}'
     *     GROUP BY e.object_id
     *   ) d
     *   GROUP BY century ORDER BY century
     *
     * Everything is grouped/aggregate; no per-record PHP loop touches the DB.
     *
     * @return array<int, array{century:int,label:string,from:int,to:int,count:int,pct:float}>
     */
    private function byCentury(int $total): array
    {
        if (! Schema::hasTable('event') || ! Schema::hasTable('status')) {
            return [];
        }
        try {
            // Inner: earliest plausible year per published record.
            $perRecord = DB::table('event as e')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'e.object_id')
                ->whereNotNull('e.start_date')
                ->whereRaw("e.start_date REGEXP '^[12][0-9]{3}'")
                ->groupBy('e.object_id')
                ->select([
                    'e.object_id',
                    DB::raw('MIN(CAST(LEFT(e.start_date,4) AS UNSIGNED)) as yr'),
                ]);

            $rows = DB::query()
                ->fromSub($perRecord, 'd')
                ->whereBetween('d.yr', [1000, 2999])
                ->select([
                    DB::raw('(FLOOR((d.yr - 1) / 100) + 1) as century'),
                    DB::raw('COUNT(*) as cnt'),
                ])
                ->groupBy(DB::raw('(FLOOR((d.yr - 1) / 100) + 1)'))
                ->orderBy('century')
                ->limit(self::MAX_CENTURIES)
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $century = (int) $r->century;
                if ($century <= 0) {
                    continue;
                }
                $from = ($century - 1) * 100 + 1;
                $to = $century * 100;
                $count = (int) $r->cnt;
                $out[] = [
                    'century' => $century,
                    'label'   => $this->ordinalCentury($century),
                    'from'    => $from,
                    'to'      => $to,
                    'count'   => $count,
                    'pct'     => $this->pct($count, $total),
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview byCentury failed: '.$e->getMessage());

            return [];
        }
    }

    // ---------------------------------------------------------------------
    // Digital coverage
    // ---------------------------------------------------------------------

    /**
     * Published records that have at least one digital object of any kind.
     *
     * SQL: COUNT over published io WHERE EXISTS (
     *        SELECT 1 FROM digital_object d WHERE d.object_id = io.id )
     */
    private function countWithDigitalObject(): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('digital_object')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('digital_object as d')
                        ->whereColumn('d.object_id', 'io.id');
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview countWithDigitalObject failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Published records that carry at least one deep-zoom (IIIF) image surrogate -
     * a pyramidal/tiled format (TIFF / JP2). Detected cheaply on filename extension
     * or an image/tiff|jp2 mime type. One EXISTS, no per-record loop.
     */
    private function countWithIiifImage(): int
    {
        return $this->countWithExtension(self::EXT_IIIF, ['image/tiff', 'image/jp2', 'image/jpx']);
    }

    /**
     * Published records that carry at least one 3D model surrogate (glb/gltf/obj/
     * fbx/stl/ply/splat, or a model/* mime type). One EXISTS, no per-record loop.
     */
    private function countWith3dModel(): int
    {
        return $this->countWithExtension(self::EXT_3D, ['model/gltf-binary', 'model/gltf+json']);
    }

    /**
     * Shared helper: published records with a digital object whose name ends in one
     * of $exts (case-insensitive) OR whose mime_type is one of $mimes. The OR set is
     * folded into a single EXISTS so the outer query remains one grouped aggregate.
     *
     * SQL (essence):
     *   COUNT over published io WHERE EXISTS (
     *     SELECT 1 FROM digital_object d WHERE d.object_id = io.id AND (
     *        LOWER(d.name) LIKE '%.glb' OR ... OR d.mime_type IN ('model/...') ) )
     *
     * @param  array<int,string>  $exts   bare extensions, no dot
     * @param  array<int,string>  $mimes  exact mime types
     */
    private function countWithExtension(array $exts, array $mimes): int
    {
        if (! Schema::hasTable('information_object') || ! Schema::hasTable('digital_object')) {
            return 0;
        }
        try {
            return (int) DB::table('information_object as io')
                ->joinSub($this->publishedIdSub(), 'pub', 'pub.object_id', '=', 'io.id')
                ->whereExists(function ($q) use ($exts, $mimes) {
                    $q->select(DB::raw(1))
                        ->from('digital_object as d')
                        ->whereColumn('d.object_id', 'io.id')
                        ->where(function ($w) use ($exts, $mimes) {
                            foreach ($exts as $ext) {
                                $w->orWhereRaw('LOWER(d.name) LIKE ?', ['%.'.strtolower($ext)]);
                            }
                            if (! empty($mimes)) {
                                $w->orWhereIn('d.mime_type', $mimes);
                            }
                        });
                })
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview countWithExtension failed: '.$e->getMessage());

            return 0;
        }
    }

    // ---------------------------------------------------------------------
    // Connected entities
    // ---------------------------------------------------------------------

    /**
     * Distinct non-root actors (people, families, organisations) in the catalogue.
     *
     * SQL: SELECT COUNT(*) FROM actor WHERE id > 1
     */
    private function countActors(): int
    {
        if (! Schema::hasTable('actor')) {
            return 0;
        }
        try {
            return (int) DB::table('actor')
                ->where('id', '>', self::ROOT_ID)
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview countActors failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Distinct repositories (holding institutions).
     *
     * SQL: SELECT COUNT(*) FROM repository
     */
    private function countRepositories(): int
    {
        if (! Schema::hasTable('repository')) {
            return 0;
        }
        try {
            return (int) DB::table('repository')->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview countRepositories failed: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Distinct terms in a given taxonomy (e.g. subject or place access points).
     *
     * SQL: SELECT COUNT(*) FROM term WHERE taxonomy_id = ?
     */
    private function countTerms(int $taxonomyId): int
    {
        if (! Schema::hasTable('term')) {
            return 0;
        }
        try {
            return (int) DB::table('term')
                ->where('taxonomy_id', $taxonomyId)
                ->count();
        } catch (\Throwable $e) {
            \Log::warning('[ahg-core] collection-overview countTerms failed: '.$e->getMessage());

            return 0;
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * A neutral century label, e.g. 19 -> "19th century", 21 -> "21st century".
     * Jurisdiction- and calendar-neutral (Gregorian ordinal only); no localised
     * era assumptions baked in.
     */
    private function ordinalCentury(int $century): string
    {
        $mod100 = $century % 100;
        $mod10 = $century % 10;

        if ($mod100 >= 11 && $mod100 <= 13) {
            $suffix = 'th';
        } elseif ($mod10 === 1) {
            $suffix = 'st';
        } elseif ($mod10 === 2) {
            $suffix = 'nd';
        } elseif ($mod10 === 3) {
            $suffix = 'rd';
        } else {
            $suffix = 'th';
        }

        return __(':n:suffix century', ['n' => $century, 'suffix' => $suffix]);
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
