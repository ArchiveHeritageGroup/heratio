<?php

/**
 * StatsController - the open heritage graph "at a glance" statistics surface.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). Where ProtocolController INDEXES the open-data surfaces and
 * CatalogController re-describes them as DCAT, this surface answers a different
 * question: "how big and what shape is the open graph?". It publishes cheap
 * aggregate counts that describe the SIZE and SHAPE of the published open-data
 * graph - the same published corpus every other open surface exposes - in three
 * representations chosen by content negotiation:
 *
 *   GET /data/stats        - content-negotiated:
 *                            text/html (a browser) -> the human "graph at a
 *                              glance" dashboard (big numbers + plain CSS bars,
 *                              no charting library);
 *                            application/ld+json   -> a VoID-aligned JSON-LD
 *                              dataset description;
 *                            everything else / ?format=json -> plain JSON.
 *   GET /data/stats.json   - the machine JSON, explicitly (CORS-open).
 *
 * The figures (all cheap COUNT / GROUP BY aggregates, never a per-record loop):
 *   - published records (void:entities) and the void:triples estimate;
 *   - records by level of description (void:classPartition style);
 *   - actors by kind (person / corporate body / family);
 *   - terms by kind (subject / place / genre);
 *   - relation edges (total + the associative record-to-record subset, the
 *     non-hierarchical cross-references that knit collections together);
 *   - records carrying a dereferenceable linked-data URI (a published slug);
 *   - descriptive coverage: records with dates, with a creator, with a subject;
 *   - distinct holding repositories.
 *
 * Published-only gate, identical to the rest of the public open surfaces:
 * information_object joined to a Published status row (status.type_id=158,
 * status_id=160), the synthetic root id=1 excluded. Actor / term / relation
 * counts are corpus-wide cardinalities of the controlled vocabularies and the
 * link table (they describe the graph the published records sit in); no draft
 * record description is ever disclosed by a count.
 *
 * Honest + safe: read-only throughout (COUNT / GROUP BY only, no writes, no
 * ALTER); every aggregate is Schema::hasTable-guarded and try/wrapped so a
 * missing table or schema variance yields a zero, never a 500; an empty corpus
 * yields a valid all-zero document. Permissive open-data CORS. URIs come from
 * url() / route(), never a hardcoded host. Jurisdiction-neutral.
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

namespace AhgApi\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class StatsController extends Controller
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    private const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    private const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object id, always excluded. */
    private const ROOT_ID = 1;

    /** ISAAR(CPF) actor entity-type ids (taxonomy "actor entity type"). */
    private const ENTITY_CORPORATE_BODY = 131;

    private const ENTITY_PERSON = 132;

    private const ENTITY_FAMILY = 133;

    /** Controlled-vocabulary taxonomy ids (mirrors TermEntityController). */
    private const TAXONOMY_SUBJECT = 35;

    private const TAXONOMY_PLACE = 42;

    private const TAXONOMY_GENRE = 78;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * OPTIONS preflight for the stats endpoints.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /data/stats  (and /data/stats.json when $forceJson)
     *
     * Content negotiation: a browser (text/html) gets the human dashboard;
     * application/ld+json gets the VoID-aligned JSON-LD; everyone else (and the
     * .json route / ?format=json) gets plain JSON. Resilient: any data fault
     * degrades to zeros, never a 500.
     */
    public function index(Request $request, bool $forceJson = false): Response
    {
        $stats = $this->compute();

        $format = $this->negotiate($request, $forceJson);

        if ($format === 'html') {
            return $this->withCors(response(
                view('ahg-api::stats.dashboard', ['s' => $stats, 'links' => $this->links()]),
                200,
                ['Content-Type' => 'text/html; charset=utf-8']
            ));
        }

        if ($format === 'jsonld') {
            $body = json_encode(
                $this->jsonLd($stats),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            return $this->withCors(response($body, 200, [
                'Content-Type' => 'application/ld+json; charset=utf-8',
            ]));
        }

        $body = json_encode(
            $this->jsonDocument($stats),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(response($body, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]));
    }

    // -----------------------------------------------------------------
    // Aggregate computation (cheap COUNT / GROUP BY only)
    // -----------------------------------------------------------------

    /**
     * Compute every figure as a flat array. Each aggregate is independently
     * guarded so one missing table cannot blank the rest.
     *
     * @return array<string,mixed>
     */
    protected function compute(): array
    {
        $publishedRecords = $this->publishedCount();

        $recordsByLevel = $this->recordsByLevel();
        $actorsByKind = $this->actorsByKind();
        $termsByKind = $this->termsByKind();

        $relationTotal = $this->countTable('relation');
        $relationRecordToRecord = $this->recordToRecordEdges();

        $withUri = $this->publishedWithSlug();
        $withDates = $this->publishedWithDates();
        $withCreator = $this->publishedWithCreator();
        $withSubject = $this->publishedWithSubject();

        $repositories = $this->distinctRepositories();

        $termsTotal = array_sum($termsByKind);
        $actorsTotal = array_sum($actorsByKind);

        // A deliberately conservative void:triples estimate: every node carries a
        // handful of asserted statements (type, label and the core descriptive
        // predicates) and every relation edge is one statement. This is an
        // order-of-magnitude figure for the VoID dataset description, not an
        // exact count - labelled as an estimate everywhere it surfaces.
        $tripleEstimate = ($publishedRecords * 8)
            + ($actorsTotal * 5)
            + ($termsTotal * 4)
            + $relationTotal
            + $this->countTable('object_term_relation');

        return [
            'published_records' => $publishedRecords,
            'records_by_level' => $recordsByLevel,
            'actors_total' => $actorsTotal,
            'actors_by_kind' => $actorsByKind,
            'terms_total' => $termsTotal,
            'terms_by_kind' => $termsByKind,
            'relation_edges_total' => $relationTotal,
            'relation_record_to_record' => $relationRecordToRecord,
            'records_with_uri' => $withUri,
            'records_with_dates' => $withDates,
            'records_with_creator' => $withCreator,
            'records_with_subject' => $withSubject,
            'repositories' => $repositories,
            'triple_estimate' => $tripleEstimate,
        ];
    }

    /**
     * Published record count: the shared open-data gate.
     */
    protected function publishedCount(): int
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
                return 0;
            }

            return (int) $this->publishedQuery()->count('io.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Published records grouped by level-of-description label. The label is
     * resolved per distinct level id (a tiny set), not per record, so this stays
     * cheap. Ordered by descending count.
     *
     * @return array<int,array{label:string,count:int}>
     */
    protected function recordsByLevel(): array
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
                return [];
            }

            $rows = $this->publishedQuery()
                ->select('io.level_of_description_id as lid', DB::raw('COUNT(*) as n'))
                ->groupBy('io.level_of_description_id')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $label = $this->termName($row->lid) ?? 'Unspecified';
                $out[] = ['label' => (string) $label, 'count' => (int) $row->n];
            }

            usort($out, static fn ($a, $b) => $b['count'] <=> $a['count']);

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Actor cardinality split by ISAAR(CPF) entity type. One grouped query over
     * the actor table; the three known kinds are surfaced and anything else is
     * folded into "other".
     *
     * @return array<string,int>
     */
    protected function actorsByKind(): array
    {
        $base = ['person' => 0, 'corporate_body' => 0, 'family' => 0, 'other' => 0];

        try {
            if (! Schema::hasTable('actor')) {
                return $base;
            }

            $rows = DB::table('actor')
                ->where('id', '!=', self::ROOT_ID)
                ->select('entity_type_id as t', DB::raw('COUNT(*) as n'))
                ->groupBy('entity_type_id')
                ->get();

            foreach ($rows as $row) {
                $n = (int) $row->n;
                switch ((int) $row->t) {
                    case self::ENTITY_PERSON:
                        $base['person'] += $n;
                        break;
                    case self::ENTITY_CORPORATE_BODY:
                        $base['corporate_body'] += $n;
                        break;
                    case self::ENTITY_FAMILY:
                        $base['family'] += $n;
                        break;
                    default:
                        $base['other'] += $n;
                        break;
                }
            }

            return $base;
        } catch (\Throwable $e) {
            return $base;
        }
    }

    /**
     * Controlled-vocabulary term cardinality split by the three open taxonomies
     * (subject, place, genre). One grouped query over the term table.
     *
     * @return array<string,int>
     */
    protected function termsByKind(): array
    {
        $base = ['subject' => 0, 'place' => 0, 'genre' => 0];

        try {
            if (! Schema::hasTable('term')) {
                return $base;
            }

            $rows = DB::table('term')
                ->whereIn('taxonomy_id', [self::TAXONOMY_SUBJECT, self::TAXONOMY_PLACE, self::TAXONOMY_GENRE])
                ->select('taxonomy_id as t', DB::raw('COUNT(*) as n'))
                ->groupBy('taxonomy_id')
                ->get();

            foreach ($rows as $row) {
                $n = (int) $row->n;
                switch ((int) $row->t) {
                    case self::TAXONOMY_SUBJECT:
                        $base['subject'] += $n;
                        break;
                    case self::TAXONOMY_PLACE:
                        $base['place'] += $n;
                        break;
                    case self::TAXONOMY_GENRE:
                        $base['genre'] += $n;
                        break;
                }
            }

            return $base;
        } catch (\Throwable $e) {
            return $base;
        }
    }

    /**
     * Associative record-to-record relation edges: rows of the generic relation
     * table whose subject AND object are both information_objects. The relation
     * table is the non-hierarchical link layer (the parent/child hierarchy lives
     * on information_object.parent_id), so each such edge is a genuine
     * cross-reference that knits records / collections together. Cheap: two
     * joins, no per-record work.
     */
    protected function recordToRecordEdges(): int
    {
        try {
            if (! Schema::hasTable('relation') || ! Schema::hasTable('information_object')) {
                return 0;
            }

            return (int) DB::table('relation as r')
                ->join('information_object as s', 's.id', '=', 'r.subject_id')
                ->join('information_object as o', 'o.id', '=', 'r.object_id')
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Published records that carry a dereferenceable linked-data URI, i.e. have a
     * slug row (so /id/{slug} resolves). Cheap inner join + distinct count.
     */
    protected function publishedWithSlug(): int
    {
        try {
            if (! Schema::hasTable('slug')) {
                return 0;
            }

            return (int) $this->publishedQuery()
                ->join('slug as sl', 'sl.object_id', '=', 'io.id')
                ->distinct()
                ->count('io.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Published records that have at least one date (an event row). Distinct
     * count over a single join.
     */
    protected function publishedWithDates(): int
    {
        try {
            if (! Schema::hasTable('event')) {
                return 0;
            }

            return (int) $this->publishedQuery()
                ->join('event as e', 'e.object_id', '=', 'io.id')
                ->distinct()
                ->count('io.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Published records that have at least one creator (an event row carrying an
     * actor_id). Distinct count over a single join.
     */
    protected function publishedWithCreator(): int
    {
        try {
            if (! Schema::hasTable('event')) {
                return 0;
            }

            return (int) $this->publishedQuery()
                ->join('event as e', function ($j) {
                    $j->on('e.object_id', '=', 'io.id')->whereNotNull('e.actor_id');
                })
                ->distinct()
                ->count('io.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Published records that reference at least one subject/term (an
     * object_term_relation row). Distinct count over a single join.
     */
    protected function publishedWithSubject(): int
    {
        try {
            if (! Schema::hasTable('object_term_relation')) {
                return 0;
            }

            return (int) $this->publishedQuery()
                ->join('object_term_relation as otr', 'otr.object_id', '=', 'io.id')
                ->distinct()
                ->count('io.id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Distinct holding repositories referenced by published records.
     */
    protected function distinctRepositories(): int
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('status')) {
                return 0;
            }

            return (int) $this->publishedQuery()
                ->whereNotNull('io.repository_id')
                ->distinct()
                ->count('io.repository_id');
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // -----------------------------------------------------------------
    // Shared helpers
    // -----------------------------------------------------------------

    /**
     * The shared published-only query: information_object joined to a Published
     * status row (type_id=158, status_id=160), synthetic root (id 1) excluded.
     * Identical gate to GraphController / DatasetController.
     */
    protected function publishedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('information_object as io')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                    ->where('st.status_id', '=', self::STATUS_PUBLISHED);
            })
            ->where('io.id', '!=', self::ROOT_ID);
    }

    /**
     * Count a whole table defensively (used for the relation total and the
     * object_term_relation contribution to the triple estimate). Guarded.
     */
    protected function countTable(string $table): int
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0;
            }

            return (int) DB::table($table)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Resolve a term label by id at the current culture. Called only per distinct
     * level id (a small set), never per record.
     */
    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            if (! Schema::hasTable('term_i18n')) {
                return null;
            }

            return DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // -----------------------------------------------------------------
    // Representations
    // -----------------------------------------------------------------

    /**
     * The plain-JSON document: the figures plus a small self-description and the
     * links out to the rest of the open-data offering.
     *
     * @param  array<string,mixed>  $s
     * @return array<string,mixed>
     */
    protected function jsonDocument(array $s): array
    {
        return [
            'name' => (string) config('app.name', 'Heratio').' open graph statistics',
            'description' => 'Aggregate size-and-shape statistics for the published open-data heritage graph. '
                .'All figures are cheap aggregate counts over published records only; read-only; open data under CC-BY-4.0.',
            'license' => 'https://creativecommons.org/licenses/by/4.0/',
            'generatedAt' => now()->toIso8601String(),
            'statistics' => $s,
            'links' => $this->links(),
        ];
    }

    /**
     * A VoID-aligned JSON-LD dataset description: the headline figures as
     * void:entities / void:triples / void:classPartition, plus the descriptive
     * coverage and the link-out as rdfs:seeAlso.
     *
     * @param  array<string,mixed>  $s
     * @return array<string,mixed>
     */
    protected function jsonLd(array $s): array
    {
        $base = $this->base();

        $classPartition = [];
        foreach ($s['records_by_level'] as $row) {
            $classPartition[] = [
                'void:class' => $this->levelToSchemaType($row['label']),
                'void:entities' => (int) $row['count'],
                'rdfs:label' => (string) $row['label'],
            ];
        }

        $links = $this->links();

        return [
            '@context' => [
                'void' => 'http://rdfs.org/ns/void#',
                'dcat' => 'http://www.w3.org/ns/dcat#',
                'dcterms' => 'http://purl.org/dc/terms/',
                'schema' => 'https://schema.org/',
                'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            ],
            '@id' => $base.'/data/stats',
            '@type' => ['void:Dataset', 'dcat:Dataset'],
            'dcterms:title' => (string) config('app.name', 'Heratio').' open graph statistics',
            'dcterms:description' => 'Aggregate size-and-shape statistics for the published open-data heritage graph.',
            'dcterms:license' => 'https://creativecommons.org/licenses/by/4.0/',
            'dcterms:modified' => now()->toIso8601String(),
            // Headline VoID cardinalities.
            'void:entities' => (int) $s['published_records'],
            'void:triples' => (int) $s['triple_estimate'],
            'void:classPartition' => $classPartition,
            // Extra shape figures as schema.org cardinalities (descriptive, not
            // a misuse of VoID's exact-triple semantics).
            'schema:additionalProperty' => [
                ['schema:name' => 'actors', 'schema:value' => (int) $s['actors_total']],
                ['schema:name' => 'terms', 'schema:value' => (int) $s['terms_total']],
                ['schema:name' => 'relationEdges', 'schema:value' => (int) $s['relation_edges_total']],
                ['schema:name' => 'recordToRecordEdges', 'schema:value' => (int) $s['relation_record_to_record']],
                ['schema:name' => 'recordsWithUri', 'schema:value' => (int) $s['records_with_uri']],
                ['schema:name' => 'recordsWithDates', 'schema:value' => (int) $s['records_with_dates']],
                ['schema:name' => 'recordsWithCreator', 'schema:value' => (int) $s['records_with_creator']],
                ['schema:name' => 'recordsWithSubject', 'schema:value' => (int) $s['records_with_subject']],
                ['schema:name' => 'repositories', 'schema:value' => (int) $s['repositories']],
            ],
            'rdfs:seeAlso' => array_values(array_filter([
                $links['catalog'] ?? null,
                $links['protocol'] ?? null,
                $links['void'] ?? null,
            ])),
        ];
    }

    /**
     * Links out to the rest of the open-data offering, each resolved defensively
     * (Route::has + literal fallback) so a slimmer install drops a dead link
     * rather than emitting one.
     *
     * @return array<string,string>
     */
    protected function links(): array
    {
        $links = [
            'json' => $this->base().'/data/stats.json',
            'catalog' => $this->resolve('open-data.catalog', '/data/catalog'),
            'protocol' => $this->resolve('open-data.protocol', '/open-data/protocol'),
            'graphExplorer' => $this->resolve('graph-explorer.index', '/graph-explorer'),
            'void' => $this->resolve('wellknown.void', '/.well-known/void'),
        ];

        return array_filter($links, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Resolve a named route to its absolute URL, falling back to a literal path
     * when the route is not registered.
     */
    protected function resolve(string $routeName, ?string $fallbackPath = null): ?string
    {
        if (Route::has($routeName)) {
            try {
                return route($routeName);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return $fallbackPath !== null ? url($fallbackPath) : null;
    }

    /**
     * Map a level-of-description label to a schema.org class for the VoID
     * classPartition (mirrors GraphController / DatasetController so the open
     * surfaces stay consistent).
     */
    protected function levelToSchemaType(string $level): string
    {
        $l = strtolower($level);
        if (str_contains($l, 'collection') || str_contains($l, 'fonds')) {
            return 'schema:Collection';
        }
        if (str_contains($l, 'item')) {
            return 'schema:CreativeWork';
        }

        return 'schema:ArchiveComponent';
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Resolve the wire format: 'html' | 'jsonld' | 'json'. The .json route forces
     * JSON. ?format= overrides the Accept header. A browser (text/html) gets the
     * dashboard; an application/ld+json client gets JSON-LD; everyone else gets
     * plain JSON (curl's catch-all Accept is NOT treated as HTML).
     */
    protected function negotiate(Request $request, bool $forceJson): string
    {
        if ($forceJson) {
            return 'json';
        }

        $fmt = strtolower((string) $request->query('format', ''));
        if ($fmt === 'json') {
            return 'json';
        }
        if ($fmt === 'jsonld') {
            return 'jsonld';
        }
        if ($fmt === 'html') {
            return 'html';
        }

        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'application/ld+json')) {
            return 'jsonld';
        }
        if (str_contains($accept, 'application/json')) {
            return 'json';
        }
        if (str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml')) {
            return 'html';
        }

        return 'json';
    }

    // -----------------------------------------------------------------
    // CORS
    // -----------------------------------------------------------------

    /**
     * Apply permissive open-data CORS headers. These figures are intentionally
     * world-readable (open data), so any origin may fetch them.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
