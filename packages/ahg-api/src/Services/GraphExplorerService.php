<?php

/**
 * GraphExplorerService - read-only data layer for the public GRAPH EXPLORER.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). The machine surfaces - EntityController (/id/{slug} records),
 * ActorEntityController (/id/actor/{slug}) and TermEntityController
 * (/id/term/{slug}) - already let a CLIENT dereference and crawl the open
 * linked-data graph. This service is the shared, read-only data layer that
 * lets a HUMAN do the same thing in a browser: it resolves ONE entity
 * (record, actor or term) and returns its label, key facts and its
 * connections grouped into human categories (other records, people, places,
 * subjects, repository, broader / narrower), each connection carrying the slug
 * the explorer needs to navigate to the next hop.
 *
 * It deliberately MIRRORS the exact fetch + gating logic of the three entity
 * controllers - the same tables, the same culture join, the same published-only
 * gate (status.type_id = 158, status_id = 160), the same synthetic-root
 * exclusion (id = 1), the same access-point taxonomies (35 subject, 42 place,
 * 78 genre) and the same actor entity_type ids - so the human explorer can
 * never drift from the /id/... linked-data output. It performs NO writes and
 * NO DDL; every query is guarded (Schema::hasTable + try/catch) so a schema
 * variance yields a thinner page, never a 500. Every list is bounded by an
 * explicit cap so a high-degree node cannot run away.
 *
 * Jurisdiction-neutral: no market assumptions, standards-based vocabularies
 * only. URIs are built from url() by the controller, never a hardcoded host.
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

namespace AhgApi\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GraphExplorerService
{
    /** Publication-status taxonomy: status.type_id for "publication status". */
    public const STATUS_TYPE_PUBLICATION = 158;

    /** Publication-status term id for "Published". */
    public const STATUS_PUBLISHED = 160;

    /** Synthetic root information_object / actor / term id, always excluded. */
    public const ROOT_ID = 1;

    /** Subject access-point taxonomy id (dcterms:subject). */
    public const TAXONOMY_SUBJECT = 35;

    /** Place access-point taxonomy id (dcterms:spatial / schema:Place). */
    public const TAXONOMY_PLACE = 42;

    /** Genre / form taxonomy id. */
    public const TAXONOMY_GENRE = 78;

    /** ISAAR(CPF) entity_type term ids (corporate body / person / family). */
    public const ENTITY_CORPORATE_BODY = 131;

    public const ENTITY_PERSON = 132;

    public const ENTITY_FAMILY = 133;

    /** Per-group cap so a high-degree node stays bounded and responsive. */
    public const MAX_PER_GROUP = 120;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    // =================================================================
    // RECORD (information_object) - the same gate as EntityController
    // =================================================================

    /**
     * Resolve a slug to ONE published record as a human-navigable node, or null
     * for an unknown / unpublished slug (never leaks a draft). The returned
     * array carries the label, key facts, and connection groups - each
     * connection a ['label','type','slug'] triple the explorer can link to.
     *
     * @return array<string,mixed>|null
     */
    public function record(string $slug): ?array
    {
        $node = $this->loadRecord($slug);
        if ($node === null) {
            return null;
        }

        $id = (int) $node['id'];

        $facts = [];
        $dates = $this->recordDates($id);
        if ($dates !== []) {
            $facts[] = ['label' => 'Dates', 'value' => implode(', ', $dates)];
        }
        if (! empty($node['level'])) {
            $facts[] = ['label' => 'Level of description', 'value' => (string) $node['level']];
        }
        if (! empty($node['identifier'])) {
            $facts[] = ['label' => 'Reference code', 'value' => (string) $node['identifier']];
        }

        $groups = [];

        // People / corporate bodies / families (via the event table).
        $creators = $this->recordCreators($id);
        $groups[] = $this->group('People and organisations', 'fas fa-user', 'actor', $creators);

        // Subjects (taxonomy 35).
        $subjects = $this->recordTerms($id, self::TAXONOMY_SUBJECT);
        $groups[] = $this->group('Subjects', 'fas fa-tag', 'term', $subjects);

        // Places (taxonomy 42).
        $places = $this->recordTerms($id, self::TAXONOMY_PLACE);
        $groups[] = $this->group('Places', 'fas fa-map-marker-alt', 'term', $places);

        // Holding repository (publisher).
        $repo = $this->recordRepository($node['repository_id'] ?? null);
        $groups[] = $this->group('Repository', 'fas fa-archive', 'actor', $repo === null ? [] : [$repo]);

        // Parent + child records (the archival hierarchy as graph edges).
        $relatedRecords = [];
        $parent = $this->recordParent((int) ($node['parent_id'] ?? 0));
        if ($parent !== null) {
            $parent['relation'] = 'Part of';
            $relatedRecords[] = $parent;
        }
        foreach ($this->recordChildren($id) as $child) {
            $child['relation'] = 'Contains';
            $relatedRecords[] = $child;
        }
        $groups[] = $this->group('Related records', 'fas fa-sitemap', 'record', $relatedRecords);

        return [
            'type' => 'record',
            'id' => $id,
            'slug' => $slug,
            'label' => (string) ($node['title'] ?: '[Untitled]'),
            'type_label' => $node['level'] ? (string) $node['level'] : 'Archival record',
            'description' => $this->plainText((string) ($node['scope_and_content'] ?? '')),
            'facts' => $facts,
            'groups' => $this->nonEmpty($groups),
            'machine_route' => 'open-data.entity',          // /id/{slug}
            'authority_route' => null,                       // record public page is /{slug}
            'authority_slug' => $slug,
        ];
    }

    /**
     * Load + gate one published record. Mirrors EntityController::loadNode().
     *
     * @return array<string,mixed>|null
     */
    protected function loadRecord(string $slug): ?array
    {
        try {
            if (! Schema::hasTable('information_object') || ! Schema::hasTable('slug')) {
                return null;
            }

            $row = DB::table('slug as s')
                ->join('information_object as io', 'io.id', '=', 's.object_id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->leftJoin('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
                })
                ->where('s.slug', $slug)
                ->where('io.id', '!=', self::ROOT_ID)
                ->select(
                    'io.id',
                    'io.identifier',
                    'io.level_of_description_id',
                    'io.repository_id',
                    'io.parent_id',
                    'i18n.title',
                    'i18n.scope_and_content',
                    'st.status_id'
                )
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row || (int) $row->status_id !== self::STATUS_PUBLISHED) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'identifier' => $row->identifier,
            'title' => $row->title,
            'scope_and_content' => $row->scope_and_content,
            'level' => $this->termName($row->level_of_description_id),
            'repository_id' => $row->repository_id,
            'parent_id' => $row->parent_id,
        ];
    }

    /**
     * Display dates for a record. Mirrors EntityController::dates().
     *
     * @return array<int,string>
     */
    protected function recordDates(int $objectId): array
    {
        try {
            $rows = DB::table('event as e')
                ->leftJoin('event_i18n as ei', function ($j) {
                    $j->on('e.id', '=', 'ei.id')->where('ei.culture', $this->culture);
                })
                ->where('e.object_id', $objectId)
                ->select('ei.date as display_date', 'e.start_date', 'e.end_date')
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $dates = [];
        foreach ($rows as $r) {
            if (! empty($r->display_date)) {
                $dates[] = (string) $r->display_date;
            } elseif (! empty($r->start_date)) {
                $dates[] = $this->trimDate((string) $r->start_date)
                    .(! empty($r->end_date) ? '/'.$this->trimDate((string) $r->end_date) : '');
            }
        }

        return array_values(array_unique(array_filter($dates)));
    }

    /**
     * Actors linked to a record via the event table, as navigable connections
     * (label + actor slug). Mirrors EntityController::creators() but resolves a
     * slug so the explorer can hop to the actor page. Repositories excluded.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function recordCreators(int $objectId): array
    {
        try {
            if (! Schema::hasTable('event') || ! Schema::hasTable('actor_i18n')) {
                return [];
            }

            $q = DB::table('event as e')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('e.actor_id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'e.actor_id')
                ->where('e.object_id', $objectId)
                ->whereNotNull('e.actor_id')
                ->where('e.actor_id', '!=', self::ROOT_ID)
                ->whereNotNull('ai.authorized_form_of_name');

            if (Schema::hasTable('repository')) {
                $q->whereNotIn('e.actor_id', function ($sub) {
                    $sub->from('repository')->select('id');
                });
            }

            $rows = $q->distinct()
                ->limit(self::MAX_PER_GROUP)
                ->get(['ai.authorized_form_of_name as label', 's.slug']);
        } catch (\Throwable $e) {
            return [];
        }

        return $this->toConnections($rows, 'actor');
    }

    /**
     * Term connections for a record within one taxonomy (35 subject / 42 place).
     * Mirrors EntityController::terms() but resolves a slug per term.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function recordTerms(int $objectId, int $taxonomyId): array
    {
        try {
            if (! Schema::hasTable('object_term_relation') || ! Schema::hasTable('term_i18n')) {
                return [];
            }

            $rows = DB::table('object_term_relation as otr')
                ->join('term as t', 'otr.term_id', '=', 't.id')
                ->join('term_i18n as ti', function ($j) {
                    $j->on('otr.term_id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'otr.term_id')
                ->where('otr.object_id', $objectId)
                ->where('t.taxonomy_id', $taxonomyId)
                ->where('t.id', '!=', self::ROOT_ID)
                ->whereNotNull('ti.name')
                ->distinct()
                ->limit(self::MAX_PER_GROUP)
                ->get(['ti.name as label', 's.slug']);
        } catch (\Throwable $e) {
            return [];
        }

        return $this->toConnections($rows, 'term');
    }

    /**
     * The holding repository as a single connection. Mirrors
     * EntityController::publisher() join, with a slug for navigation.
     *
     * @return array<string,mixed>|null
     */
    protected function recordRepository($repositoryId): ?array
    {
        if (empty($repositoryId)) {
            return null;
        }

        try {
            $row = DB::table('repository as r')
                ->join('actor_i18n as ai', function ($j) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->leftJoin('slug as s', 's.object_id', '=', 'r.id')
                ->where('r.id', (int) $repositoryId)
                ->first(['ai.authorized_form_of_name as label', 's.slug']);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row || empty($row->label)) {
            return null;
        }

        // Repositories navigate as actors (they share the actor identity space),
        // but only when a slug exists; otherwise it's a non-clickable fact.
        return [
            'label' => (string) $row->label,
            'type' => 'actor',
            'slug' => $row->slug ? (string) $row->slug : null,
        ];
    }

    /**
     * The parent record as a navigable connection - only when the parent is
     * itself published, non-root and slugged. Mirrors
     * EntityController::parentEntityUri()'s gate.
     *
     * @return array<string,mixed>|null
     */
    protected function recordParent(int $parentId): ?array
    {
        if ($parentId <= self::ROOT_ID) {
            return null;
        }

        $rows = $this->publishedRecordConnections([$parentId], 1);

        return $rows[0] ?? null;
    }

    /**
     * Published child records as navigable connections (the hierarchy below).
     *
     * @return array<int,array<string,mixed>>
     */
    protected function recordChildren(int $objectId): array
    {
        try {
            if (! Schema::hasTable('information_object')) {
                return [];
            }

            $ids = DB::table('information_object')
                ->where('parent_id', $objectId)
                ->where('id', '!=', self::ROOT_ID)
                ->orderBy('lft')
                ->limit(self::MAX_PER_GROUP)
                ->pluck('id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }

        return $this->publishedRecordConnections($ids, self::MAX_PER_GROUP);
    }

    // =================================================================
    // ACTOR - the same model as ActorEntityController (reference entity)
    // =================================================================

    /**
     * Resolve a slug to ONE actor as a human-navigable node, or null when the
     * slug is not an actor. Mirrors ActorEntityController::loadActor() (the
     * actor row has no publication gate, but every record it links to does).
     *
     * @return array<string,mixed>|null
     */
    public function actor(string $slug): ?array
    {
        $node = $this->loadActor($slug);
        if ($node === null) {
            return null;
        }

        $id = (int) $node['id'];

        $facts = [];
        $dates = $this->plainText((string) ($node['dates_of_existence'] ?? ''));
        if ($dates !== '') {
            $facts[] = ['label' => 'Dates of existence', 'value' => $dates];
        }
        $facts[] = ['label' => 'Type', 'value' => $this->actorTypeLabel((int) ($node['entity_type_id'] ?? 0))];

        $groups = [];
        $related = $this->actorRelatedRecords($id);
        $groups[] = $this->group('Records', 'fas fa-file-alt', 'record', $related);

        return [
            'type' => 'actor',
            'id' => $id,
            'slug' => $slug,
            'label' => (string) ($node['name'] ?: '[Unnamed]'),
            'type_label' => $this->actorTypeLabel((int) ($node['entity_type_id'] ?? 0)),
            'description' => $this->plainText((string) ($node['history'] ?? '')),
            'facts' => $facts,
            'groups' => $this->nonEmpty($groups),
            'machine_route' => 'open-data.entity.actor',     // /id/actor/{slug}
            'authority_route' => null,                        // /actor/{slug}
            'authority_slug' => $slug,
            'authority_prefix' => 'actor',
        ];
    }

    /**
     * Load an actor row. Mirrors ActorEntityController::loadActor() (repositories
     * excluded so this surface is people / corporate bodies / families).
     *
     * @return array<string,mixed>|null
     */
    protected function loadActor(string $slug): ?array
    {
        try {
            if (! Schema::hasTable('actor') || ! Schema::hasTable('slug')) {
                return null;
            }

            $query = DB::table('slug as s')
                ->join('actor as a', 'a.id', '=', 's.object_id')
                ->leftJoin('actor_i18n as ai', function ($j) {
                    $j->on('a.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                })
                ->where('s.slug', $slug)
                ->where('a.id', '!=', self::ROOT_ID);

            if (Schema::hasTable('repository')) {
                $query->whereNotIn('a.id', function ($sub) {
                    $sub->from('repository')->select('id');
                });
            }

            $row = $query->first([
                'a.id',
                'a.entity_type_id',
                'ai.authorized_form_of_name as name',
                'ai.dates_of_existence',
                'ai.history',
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'entity_type_id' => $row->entity_type_id,
            'name' => $row->name,
            'dates_of_existence' => $row->dates_of_existence,
            'history' => $row->history,
        ];
    }

    /**
     * Published records linked to this actor (event table + generic relation
     * table), as navigable connections. Mirrors
     * ActorEntityController::relatedRecordUris() + publishedRecordUris().
     *
     * @return array<int,array<string,mixed>>
     */
    protected function actorRelatedRecords(int $actorId): array
    {
        $objectIds = [];

        try {
            if (Schema::hasTable('event')) {
                foreach (DB::table('event')
                    ->where('actor_id', $actorId)
                    ->whereNotNull('object_id')
                    ->distinct()
                    ->limit(self::MAX_PER_GROUP * 4)
                    ->pluck('object_id') as $oid) {
                    $objectIds[(int) $oid] = true;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        try {
            if (Schema::hasTable('relation') && Schema::hasTable('information_object')) {
                $subjectSide = DB::table('relation as r')
                    ->join('information_object as io', 'io.id', '=', 'r.object_id')
                    ->where('r.subject_id', $actorId)
                    ->distinct()
                    ->limit(self::MAX_PER_GROUP * 4)
                    ->pluck('io.id')
                    ->all();
                $objectSide = DB::table('relation as r')
                    ->join('information_object as io', 'io.id', '=', 'r.subject_id')
                    ->where('r.object_id', $actorId)
                    ->distinct()
                    ->limit(self::MAX_PER_GROUP * 4)
                    ->pluck('io.id')
                    ->all();
                foreach (array_merge($subjectSide, $objectSide) as $oid) {
                    $objectIds[(int) $oid] = true;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        return $this->publishedRecordConnections(array_keys($objectIds), self::MAX_PER_GROUP);
    }

    // =================================================================
    // TERM - the same model as TermEntityController (reference entity)
    // =================================================================

    /**
     * Resolve a slug to ONE term as a human-navigable node, or null when the
     * slug is not a term. Mirrors TermEntityController::loadTerm().
     *
     * @return array<string,mixed>|null
     */
    public function term(string $slug): ?array
    {
        $node = $this->loadTerm($slug);
        if ($node === null) {
            return null;
        }

        $id = (int) $node['id'];
        $taxonomyId = (int) ($node['taxonomy_id'] ?? 0);

        $facts = [['label' => 'Kind', 'value' => $this->taxonomyLabel($taxonomyId)]];

        $groups = [];

        // Broader (skos:broader).
        $broader = $this->broaderTerm((int) ($node['parent_id'] ?? 0));
        $groups[] = $this->group('Broader term', 'fas fa-level-up-alt', 'term', $broader === null ? [] : [$broader]);

        // Narrower (skos:narrower).
        $narrower = $this->narrowerTerms($id);
        $groups[] = $this->group('Narrower terms', 'fas fa-level-down-alt', 'term', $narrower);

        // Records tagged with this term (published only).
        $records = $this->termRelatedRecords($id);
        $groups[] = $this->group('Records', 'fas fa-file-alt', 'record', $records);

        return [
            'type' => 'term',
            'id' => $id,
            'slug' => $slug,
            'label' => (string) ($node['name'] ?: '[Unlabelled]'),
            'type_label' => $this->taxonomyLabel($taxonomyId),
            'description' => '',
            'facts' => $facts,
            'groups' => $this->nonEmpty($groups),
            'machine_route' => 'open-data.entity.term',      // /id/term/{slug}
            'authority_route' => null,
            // A term's "authority" view is the filtered GLAM browse.
            'authority_browse' => $this->termBrowseParams($taxonomyId, $id),
        ];
    }

    /**
     * Load a term row. Mirrors TermEntityController::loadTerm().
     *
     * @return array<string,mixed>|null
     */
    protected function loadTerm(string $slug): ?array
    {
        try {
            if (! Schema::hasTable('term') || ! Schema::hasTable('slug')) {
                return null;
            }

            $row = DB::table('slug as s')
                ->join('term as t', 't.id', '=', 's.object_id')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('s.slug', $slug)
                ->where('t.id', '!=', self::ROOT_ID)
                ->first(['t.id', 't.taxonomy_id', 't.parent_id', 'ti.name']);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'taxonomy_id' => $row->taxonomy_id,
            'parent_id' => $row->parent_id,
            'name' => $row->name,
        ];
    }

    /**
     * The broader term as a navigable connection. Mirrors
     * TermEntityController::broaderTermUri().
     *
     * @return array<string,mixed>|null
     */
    protected function broaderTerm(int $parentId): ?array
    {
        if ($parentId <= self::ROOT_ID) {
            return null;
        }

        try {
            $row = DB::table('term as t')
                ->join('slug as s', 's.object_id', '=', 't.id')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('t.id', $parentId)
                ->first(['ti.name as label', 's.slug']);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row || empty($row->slug)) {
            return null;
        }

        return [
            'label' => $row->label ? (string) $row->label : (string) $row->slug,
            'type' => 'term',
            'slug' => (string) $row->slug,
        ];
    }

    /**
     * Narrower terms as navigable connections. Mirrors
     * TermEntityController::narrowerTermUris() with labels resolved.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function narrowerTerms(int $termId): array
    {
        try {
            $rows = DB::table('term as t')
                ->join('slug as s', 's.object_id', '=', 't.id')
                ->leftJoin('term_i18n as ti', function ($j) {
                    $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                })
                ->where('t.parent_id', $termId)
                ->where('t.id', '!=', self::ROOT_ID)
                ->orderBy('t.id')
                ->limit(self::MAX_PER_GROUP)
                ->get(['ti.name as label', 's.slug']);
        } catch (\Throwable $e) {
            return [];
        }

        return $this->toConnections($rows, 'term');
    }

    /**
     * Published records tagged with this term. Mirrors
     * TermEntityController::relatedRecordUris() (published-only gate applied).
     *
     * @return array<int,array<string,mixed>>
     */
    protected function termRelatedRecords(int $termId): array
    {
        try {
            if (! Schema::hasTable('object_term_relation')
                || ! Schema::hasTable('information_object')
                || ! Schema::hasTable('slug')
                || ! Schema::hasTable('status')) {
                return [];
            }

            $rows = DB::table('object_term_relation as otr')
                ->join('information_object as io', 'io.id', '=', 'otr.object_id')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->join('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                })
                ->where('otr.term_id', $termId)
                ->where('io.id', '!=', self::ROOT_ID)
                ->distinct()
                ->orderBy('s.slug')
                ->limit(self::MAX_PER_GROUP)
                ->get(['i18n.title as label', 's.slug']);
        } catch (\Throwable $e) {
            return [];
        }

        return $this->toConnections($rows, 'record');
    }

    // =================================================================
    // Landing - a few high-degree starting entities
    // =================================================================

    /**
     * A small set of high-degree published records to seed the landing page, so
     * a first-time visitor always has somewhere to start walking the graph.
     * Bounded and best-effort; returns [] (a clean empty-state) on any variance.
     *
     * @return array<int,array<string,mixed>>
     */
    public function startingPoints(int $limit = 12): array
    {
        try {
            if (! Schema::hasTable('object_term_relation')
                || ! Schema::hasTable('information_object')
                || ! Schema::hasTable('slug')
                || ! Schema::hasTable('status')) {
                return [];
            }

            $rows = DB::table('information_object as io')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->join('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                })
                ->leftJoin('object_term_relation as otr', 'otr.object_id', '=', 'io.id')
                ->where('io.id', '!=', self::ROOT_ID)
                ->groupBy('io.id', 's.slug', 'i18n.title')
                ->orderByRaw('COUNT(otr.id) DESC')
                ->limit(max(1, $limit))
                ->get(['io.id', 's.slug', 'i18n.title as label', DB::raw('COUNT(otr.id) as degree')]);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            if (empty($r->slug)) {
                continue;
            }
            $out[] = [
                'label' => $r->label ? (string) $r->label : (string) $r->slug,
                'type' => 'record',
                'slug' => (string) $r->slug,
                'degree' => (int) $r->degree,
            ];
        }

        return $out;
    }

    /**
     * Resolve a free-text query to a handful of matching entities (records,
     * actors, terms) as navigable connections, for the landing search box.
     * Bounded, read-only, best-effort. Records are published-only.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 25): array
    {
        $query = trim($query);
        if ($query === '' || mb_strlen($query) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        $out = [];

        // Published records by title.
        try {
            if (Schema::hasTable('information_object_i18n') && Schema::hasTable('slug') && Schema::hasTable('status')) {
                $rows = DB::table('information_object as io')
                    ->join('slug as s', 's.object_id', '=', 'io.id')
                    ->join('information_object_i18n as i18n', function ($j) {
                        $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                    })
                    ->join('status as st', function ($j) {
                        $j->on('io.id', '=', 'st.object_id')
                            ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                            ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                    })
                    ->where('io.id', '!=', self::ROOT_ID)
                    ->where('i18n.title', 'like', $like)
                    ->orderBy('i18n.title')
                    ->limit($limit)
                    ->get(['i18n.title as label', 's.slug']);
                foreach ($this->toConnections($rows, 'record') as $c) {
                    $out[] = $c;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        // Actors (people / corporate bodies / families) by authorised name.
        try {
            if (Schema::hasTable('actor_i18n') && Schema::hasTable('slug')) {
                $q = DB::table('actor as a')
                    ->join('slug as s', 's.object_id', '=', 'a.id')
                    ->join('actor_i18n as ai', function ($j) {
                        $j->on('a.id', '=', 'ai.id')->where('ai.culture', $this->culture);
                    })
                    ->where('a.id', '!=', self::ROOT_ID)
                    ->where('ai.authorized_form_of_name', 'like', $like);
                if (Schema::hasTable('repository')) {
                    $q->whereNotIn('a.id', function ($sub) {
                        $sub->from('repository')->select('id');
                    });
                }
                $rows = $q->orderBy('ai.authorized_form_of_name')
                    ->limit($limit)
                    ->get(['ai.authorized_form_of_name as label', 's.slug']);
                foreach ($this->toConnections($rows, 'actor') as $c) {
                    $out[] = $c;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        // Terms (subject / place / genre) by preferred label.
        try {
            if (Schema::hasTable('term_i18n') && Schema::hasTable('slug')) {
                $rows = DB::table('term as t')
                    ->join('slug as s', 's.object_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($j) {
                        $j->on('t.id', '=', 'ti.id')->where('ti.culture', $this->culture);
                    })
                    ->whereIn('t.taxonomy_id', [self::TAXONOMY_SUBJECT, self::TAXONOMY_PLACE, self::TAXONOMY_GENRE])
                    ->where('t.id', '!=', self::ROOT_ID)
                    ->where('ti.name', 'like', $like)
                    ->orderBy('ti.name')
                    ->limit($limit)
                    ->get(['ti.name as label', 's.slug']);
                foreach ($this->toConnections($rows, 'term') as $c) {
                    $out[] = $c;
                }
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        return array_slice($out, 0, $limit);
    }

    // =================================================================
    // Shared helpers
    // =================================================================

    /**
     * Given candidate information_object ids, return published, non-root,
     * slugged records as navigable connections. The published-only gate lives
     * here, exactly as in ActorEntityController::publishedRecordUris().
     *
     * @param  array<int,int|string>  $objectIds
     * @return array<int,array<string,mixed>>
     */
    protected function publishedRecordConnections(array $objectIds, int $limit): array
    {
        $objectIds = array_values(array_unique(array_filter(
            array_map('intval', $objectIds),
            fn ($v) => $v > self::ROOT_ID
        )));
        if ($objectIds === []) {
            return [];
        }

        try {
            $rows = DB::table('information_object as io')
                ->join('slug as s', 's.object_id', '=', 'io.id')
                ->join('information_object_i18n as i18n', function ($j) {
                    $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', $this->culture);
                })
                ->join('status as st', function ($j) {
                    $j->on('io.id', '=', 'st.object_id')
                        ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION)
                        ->where('st.status_id', '=', self::STATUS_PUBLISHED);
                })
                ->whereIn('io.id', $objectIds)
                ->where('io.id', '!=', self::ROOT_ID)
                ->orderBy('io.lft')
                ->limit(max(1, $limit))
                ->get(['i18n.title as label', 's.slug']);
        } catch (\Throwable $e) {
            return [];
        }

        return $this->toConnections($rows, 'record');
    }

    /**
     * Normalise a result set of {label, slug} rows into navigable connection
     * triples. A row without a slug is kept as a non-clickable label so the
     * page still shows the fact honestly.
     *
     * @param  iterable<int,object>  $rows
     * @return array<int,array<string,mixed>>
     */
    protected function toConnections(iterable $rows, string $type): array
    {
        $out = [];
        $seen = [];
        foreach ($rows as $r) {
            $label = isset($r->label) ? trim((string) $r->label) : '';
            $slug = isset($r->slug) && $r->slug !== null ? (string) $r->slug : null;
            if ($label === '' && $slug === null) {
                continue;
            }
            if ($label === '') {
                $label = (string) $slug;
            }
            $key = $type.'|'.($slug ?? $label);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['label' => $label, 'type' => $type, 'slug' => $slug];
        }

        return $out;
    }

    /**
     * Build one connection group, dropping it later if empty.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<string,mixed>
     */
    protected function group(string $heading, string $icon, string $type, array $items): array
    {
        return [
            'heading' => $heading,
            'icon' => $icon,
            'type' => $type,
            'items' => $items,
        ];
    }

    /**
     * Drop empty groups so the page only shows connections that exist.
     *
     * @param  array<int,array<string,mixed>>  $groups
     * @return array<int,array<string,mixed>>
     */
    protected function nonEmpty(array $groups): array
    {
        return array_values(array_filter($groups, fn ($g) => ! empty($g['items'])));
    }

    /**
     * GLAM-browse parameters for a term's "human authority" view.
     *
     * @return array{param:string,id:int}
     */
    protected function termBrowseParams(int $taxonomyId, int $termId): array
    {
        $param = match ($taxonomyId) {
            self::TAXONOMY_PLACE => 'place',
            self::TAXONOMY_GENRE => 'genre',
            default => 'subject',
        };

        return ['param' => $param, 'id' => $termId];
    }

    protected function actorTypeLabel(int $entityTypeId): string
    {
        return match ($entityTypeId) {
            self::ENTITY_CORPORATE_BODY => 'Corporate body',
            self::ENTITY_PERSON => 'Person',
            self::ENTITY_FAMILY => 'Family',
            default => 'Agent',
        };
    }

    protected function taxonomyLabel(int $taxonomyId): string
    {
        return match ($taxonomyId) {
            self::TAXONOMY_PLACE => 'Place',
            self::TAXONOMY_SUBJECT => 'Subject',
            self::TAXONOMY_GENRE => 'Genre or form',
            default => 'Concept',
        };
    }

    protected function termName($termId): ?string
    {
        if (empty($termId)) {
            return null;
        }

        try {
            $v = DB::table('term_i18n')
                ->where('id', (int) $termId)
                ->where('culture', $this->culture)
                ->value('name');

            return $v !== null ? (string) $v : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function plainText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    protected function trimDate(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/-00(-00)?$/', '', $value);

        return (string) preg_replace('/-00$/', '', $value);
    }
}
