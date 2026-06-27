<?php

/**
 * GraphqlController - Controller for Heratio
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

namespace AhgGraphql\Controllers;

use AhgCore\Services\AclService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * GraphQL Playground Controller.
 * Provides an interactive GraphQL IDE for querying the archive.
 * Migrated from ahgGraphqlPlugin.
 */
class GraphqlController extends Controller
{
    /**
     * Publication-status taxonomy (matches ahg-api open-data controllers):
     * status.type_id=158 ("publication status"), status_id=160 ("Published").
     * A row is public only when it carries this exact status row. The
     * synthetic root (information_object.id=1) is never disclosed.
     */
    private const STATUS_TYPE_PUBLICATION = 158;

    private const STATUS_PUBLISHED = 160;

    /**
     * SECURITY (#1378): hard cap on any client-supplied limit so a single
     * GraphQL call can't bulk-extract the whole table.
     */
    private const MAX_LIMIT = 100;

    /**
     * SECURITY (#1378): does the current caller get to see drafts / private /
     * non-public rows? Mirrors the ahg-annotations #1365 model — admin/editor
     * (AclService::canAdmin) bypasses the publication/visibility filters; every
     * other authenticated caller is clamped to published/public (+ own) rows.
     */
    private function callerIsAdmin(): bool
    {
        return AclService::canAdmin(Auth::id());
    }

    /**
     * SECURITY (#1378): clamp a client-supplied limit to [1, MAX_LIMIT].
     */
    private function clampLimit($value, int $default = 25): int
    {
        $n = (int) ($value ?? $default);

        return max(1, min($n, self::MAX_LIMIT));
    }

    /**
     * SECURITY (#1378): clamp a client-supplied offset to a non-negative int.
     */
    private function clampOffset($value): int
    {
        return max(0, (int) ($value ?? 0));
    }

    /**
     * SECURITY (#1378): published-only scope for archive entities, matching
     * the ahg-api open-data predicate (status.type_id=158 AND status_id=160).
     * Admins/editors see drafts too, so the scope is a no-op for them. Applied
     * as a whereExists so the SELECT/response shape is untouched.
     */
    private function scopePublished($query, string $alias): void
    {
        if ($this->callerIsAdmin()) {
            return;
        }

        $query->whereExists(function ($sub) use ($alias) {
            $sub->select(DB::raw(1))
                ->from('status')
                ->whereColumn('status.object_id', $alias.'.id')
                ->where('status.type_id', self::STATUS_TYPE_PUBLICATION)
                ->where('status.status_id', self::STATUS_PUBLISHED);
        });
    }

    /**
     * GraphQL Playground — interactive IDE.
     */
    public function playground()
    {
        $schema = $this->getSchemaInfo();

        return view('ahg-graphql::playground', compact('schema'));
    }

    /**
     * GraphQL endpoint — processes queries.
     */
    public function execute(Request $request)
    {
        $query = $request->input('query', '');
        $variables = $request->input('variables', []);

        if (is_string($variables)) {
            $variables = json_decode($variables, true) ?? [];
        }

        $result = $this->resolveQuery($query, $variables);

        return response()->json($result);
    }

    /**
     * Resolve a GraphQL query against the archive database.
     */
    private function resolveQuery(string $query, array $variables): array
    {
        $query = trim($query);

        if (preg_match('/\binformationObject\s*\(\s*id\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveInformationObject((int) $m[1]);
        }

        if (preg_match('/\binformationObjects\b/i', $query)) {
            // SECURITY (#1378): clamp client-supplied paging.
            $limit = $this->clampLimit($variables['limit'] ?? null);
            $offset = $this->clampOffset($variables['offset'] ?? null);

            return $this->resolveInformationObjects($limit, $offset);
        }

        if (preg_match('/\bactor\s*\(\s*id\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveActor((int) $m[1]);
        }

        if (preg_match('/\bactors\b/i', $query)) {
            return $this->resolveActors($this->clampLimit($variables['limit'] ?? null));
        }

        if (preg_match('/\brepositories\b/i', $query)) {
            return $this->resolveRepositories();
        }

        if (preg_match('/\bresearchProject\s*\(\s*id\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveResearchProject((int) $m[1]);
        }

        if (preg_match('/\bresearchProjects\b/i', $query)) {
            return $this->resolveResearchProjects($this->clampLimit($variables['limit'] ?? null));
        }

        if (preg_match('/\bresearchAnnotations\s*\(\s*targetIri\s*:\s*"([^"]+)"\s*\)/i', $query, $m)) {
            return $this->resolveResearchAnnotations($m[1]);
        }

        if (preg_match('/\bresearchCollections\s*\(\s*projectId\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveResearchCollections((int) $m[1]);
        }

        if (preg_match('/\bresearcherView\s*\(\s*researcherId\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveResearcherView((int) $m[1]);
        }

        if (preg_match('/\b__schema\b/i', $query)) {
            return ['data' => ['__schema' => $this->getSchemaInfo()]];
        }

        return ['errors' => [['message' => 'Unsupported query. Available: informationObject(id), informationObjects, actor(id), actors, repositories, researchProject(id), researchProjects, researchAnnotations(targetIri), researchCollections(projectId), researcherView(researcherId)']]];
    }

    private function resolveResearchProject(int $id): array
    {
        $q = DB::table('research_project as p')
            ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
            ->where('p.id', $id)
            ->select('p.id', 'p.title', 'p.description', 'p.project_type', 'p.status',
                'p.start_date', 'p.expected_end_date', 'p.created_at',
                'r.id as owner_id', 'r.first_name as owner_first_name', 'r.last_name as owner_last_name');

        // SECURITY (#1378): research_project uses a `visibility` enum
        // (private/collaborators/public). Non-admin callers only ever see
        // public projects; admins/editors see all. owner_id references
        // research_researcher.id (not the auth user id), so there is no
        // trustworthy auth-user->owner mapping to widen this — mirror the
        // ahg-annotations #1365 "safe subset" (public + admin) here too.
        if (! $this->callerIsAdmin()) {
            $q->where('p.visibility', 'public');
        }

        $project = $q->first();

        if (! $project) {
            return ['errors' => [['message' => "Research project {$id} not found"]]];
        }

        $collectionsQuery = DB::table('research_collection')
            ->where('project_id', $id)
            ->select('id', 'name', 'description', 'is_public');

        // SECURITY (#1378): only public collections for non-admin callers.
        if (! $this->callerIsAdmin()) {
            $collectionsQuery->where('is_public', 1);
        }

        $collections = $collectionsQuery
            ->get()
            ->map(fn ($c) => (array) $c)
            ->toArray();

        $studio = [];
        try {
            $studio = DB::table('research_studio_artefact')
                ->where('project_id', $id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->select('id', 'output_type', 'title', 'status', 'created_at')
                ->get()->map(fn ($a) => (array) $a)->toArray();
        } catch (\Throwable $e) {
        }

        $data = (array) $project;
        $data['collections'] = $collections;
        $data['studio_artefacts'] = $studio;

        return ['data' => ['researchProject' => $data]];
    }

    private function resolveResearchProjects(int $limit): array
    {
        $q = DB::table('research_project as p')
            ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
            ->orderByDesc('p.created_at')
            ->limit($limit)
            ->select('p.id', 'p.title', 'p.project_type', 'p.status',
                'r.id as owner_id', 'r.first_name as owner_first_name', 'r.last_name as owner_last_name');

        // SECURITY (#1378): public projects only for non-admin callers.
        if (! $this->callerIsAdmin()) {
            $q->where('p.visibility', 'public');
        }

        $rows = $q->get()->map(fn ($p) => (array) $p)->toArray();

        return ['data' => ['researchProjects' => $rows]];
    }

    private function resolveResearchAnnotations(string $targetIri): array
    {
        try {
            $q = DB::table('ahg_iiif_annotation')
                ->where('target_iri', $targetIri)
                ->orderBy('id')
                ->select('uuid', 'project_id', 'visibility', 'body_json', 'created_by', 'created_at');

            // SECURITY (#1378): mirror the ahg-annotations #1365 read model.
            // The resolver selected `visibility` but never enforced it, so any
            // caller bulk-read every private/project annotation on a target.
            //   - admin/editor: all rows;
            //   - authenticated non-admin: public OR own (created_by = id);
            //   - anonymous: public only.
            // 'project' rows stay hidden from non-owners (same deferral the
            // #1365 fix documents — no trustworthy project-membership lookup).
            if (! $this->callerIsAdmin()) {
                $userId = Auth::id();
                if (Auth::check()) {
                    $q->where(function ($w) use ($userId) {
                        $w->where('visibility', 'public')
                            ->orWhere('created_by', $userId);
                    });
                } else {
                    $q->where('visibility', 'public');
                }
            }

            $rows = $q
                ->get()
                ->map(function ($r) {
                    return [
                        'uuid' => $r->uuid,
                        'project_id' => $r->project_id,
                        'visibility' => $r->visibility,
                        'created_by' => $r->created_by,
                        'created_at' => $r->created_at,
                        'body' => json_decode($r->body_json, true),
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            $rows = [];
        }

        return ['data' => ['researchAnnotations' => $rows]];
    }

    private function resolveResearchCollections(int $projectId): array
    {
        $colsQuery = DB::table('research_collection')
            ->where('project_id', $projectId)
            ->select('id', 'name', 'description', 'is_public', 'created_at');

        // SECURITY (#1378): non-public collections were returned to every
        // caller. Restrict to is_public=1 for non-admin callers.
        if (! $this->callerIsAdmin()) {
            $colsQuery->where('is_public', 1);
        }

        $cols = $colsQuery->get()->map(fn ($c) => (array) $c)->toArray();

        $items = DB::table('research_collection_item as ci')
            ->whereIn('ci.collection_id', array_column($cols, 'id') ?: [0])
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ci.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->select('ci.collection_id', 'ci.object_id', 'ci.notes', 'ioi.title')
            ->get()
            ->groupBy('collection_id');

        foreach ($cols as &$c) {
            $c['items'] = $items->get($c['id'], collect())->map(fn ($i) => (array) $i)->toArray();
        }

        return ['data' => ['researchCollections' => $cols]];
    }

    /**
     * Combined query that powers external tools (Zotero, Tropy, LMS) - returns
     * researcher profile + their projects + the latest annotations they've
     * made in one round-trip.
     */
    private function resolveResearcherView(int $researcherId): array
    {
        $researcher = DB::table('research_researcher')->where('id', $researcherId)
            ->select('id', 'first_name', 'last_name', 'email', 'orcid_id', 'institution', 'researcher_type_id')
            ->first();

        if (! $researcher) {
            return ['errors' => [['message' => "Researcher {$researcherId} not found"]]];
        }

        $projectsQuery = DB::table('research_project as p')
            ->leftJoin('research_project_collaborator as pc', function ($j) use ($researcherId) {
                $j->on('pc.project_id', '=', 'p.id')->where('pc.researcher_id', '=', $researcherId);
            })
            ->where(function ($q) use ($researcherId) {
                $q->where('p.owner_id', $researcherId)->orWhereNotNull('pc.id');
            })
            ->orderByDesc('p.created_at')
            ->limit(50)
            ->select('p.id', 'p.title', 'p.project_type', 'p.status', 'p.created_at');

        // SECURITY (#1378): public projects only for non-admin callers.
        if (! $this->callerIsAdmin()) {
            $projectsQuery->where('p.visibility', 'public');
        }

        $projects = $projectsQuery->get()->map(fn ($p) => (array) $p)->toArray();

        $annotations = [];
        try {
            $annotationsQuery = DB::table('ahg_iiif_annotation')
                ->where('created_by', $researcherId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->select('uuid', 'target_iri', 'project_id', 'visibility', 'created_at');

            // SECURITY (#1378): only the researcher's public annotations are
            // exposed to non-admin callers (#1365 read model).
            if (! $this->callerIsAdmin()) {
                $annotationsQuery->where('visibility', 'public');
            }

            $annotations = $annotationsQuery->get()->map(fn ($a) => (array) $a)->toArray();
        } catch (\Throwable $e) {
        }

        $isAdmin = $this->callerIsAdmin();

        $orcid = null;
        try {
            // SECURITY (#1378): the ORCID link is researcher PII/contact data;
            // withhold it from non-admin callers (shape preserved as null).
            if ($isAdmin) {
                $orcid = DB::table('researcher_orcid_link')->where('researcher_id', $researcherId)
                    ->select('orcid_id', 'last_synced_at', 'last_works_count')
                    ->first();
                $orcid = $orcid ? (array) $orcid : null;
            }
        } catch (\Throwable $e) {
        }

        // SECURITY (#1378): strip researcher PII (email + ORCID) for non-admin
        // callers. Keys are kept (null) so the JSON response shape is stable
        // for the external tools (Zotero/Tropy/LMS) that consume it.
        $researcherData = (array) $researcher;
        if (! $isAdmin) {
            $researcherData['email'] = null;
            $researcherData['orcid_id'] = null;
        }

        return ['data' => ['researcherView' => [
            'researcher' => $researcherData,
            'projects' => $projects,
            'annotations' => $annotations,
            'orcid' => $orcid,
        ]]];
    }

    private function resolveInformationObject(int $id): array
    {
        $q = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $id)
            ->where('io.id', '!=', 1) // never disclose the synthetic root
            ->select('io.id', 'io.identifier', 'io.parent_id', 'io.repository_id',
                'io.level_of_description_id', 'ioi.title', 'ioi.scope_and_content', 'slug.slug');

        // SECURITY (#1378): non-admin callers only see Published records
        // (status.type_id=158, status_id=160) — a draft IO is never disclosed.
        $this->scopePublished($q, 'io');

        $io = $q->first();

        return $io ? ['data' => ['informationObject' => (array) $io]]
                   : ['errors' => [['message' => "Information object {$id} not found"]]];
    }

    private function resolveInformationObjects(int $limit, int $offset): array
    {
        $q = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '!=', 1)
            ->select('io.id', 'io.identifier', 'ioi.title', 'slug.slug')
            ->orderBy('ioi.title')
            ->offset($offset)->limit($limit);

        // SECURITY (#1378): published-only for non-admin callers (drafts hidden).
        $this->scopePublished($q, 'io');

        $items = $q->get();

        return ['data' => ['informationObjects' => $items->map(fn ($i) => (array) $i)->toArray()]];
    }

    private function resolveActor(int $id): array
    {
        $actor = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', $id)
            ->select('a.id', 'a.entity_type_id', 'ai.authorized_form_of_name', 'ai.history', 'slug.slug')
            ->first();

        return $actor ? ['data' => ['actor' => (array) $actor]]
                      : ['errors' => [['message' => "Actor {$id} not found"]]];
    }

    private function resolveActors(int $limit): array
    {
        $actors = DB::table('actor as a')
            ->join('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->where('a.id', '!=', 1)
            ->select('a.id', 'ai.authorized_form_of_name', 'slug.slug')
            ->orderBy('ai.authorized_form_of_name')
            ->limit($limit)
            ->get();

        return ['data' => ['actors' => $actors->map(fn ($a) => (array) $a)->toArray()]];
    }

    private function resolveRepositories(): array
    {
        $repos = DB::table('repository as r')
            ->join('actor_i18n as ai', function ($join) {
                $join->on('r.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'r.id', '=', 'slug.object_id')
            ->select('r.id', 'ai.authorized_form_of_name', 'slug.slug')
            ->orderBy('ai.authorized_form_of_name')
            ->get();

        return ['data' => ['repositories' => $repos->map(fn ($r) => (array) $r)->toArray()]];
    }

    private function getSchemaInfo(): array
    {
        return [
            'types' => [
                ['name' => 'InformationObject', 'fields' => ['id', 'identifier', 'title', 'slug', 'scope_and_content', 'parent_id', 'repository_id']],
                ['name' => 'Actor', 'fields' => ['id', 'authorized_form_of_name', 'history', 'slug', 'entity_type_id']],
                ['name' => 'Repository', 'fields' => ['id', 'authorized_form_of_name', 'slug']],
                ['name' => 'ResearchProject', 'fields' => ['id', 'title', 'description', 'project_type', 'status', 'collections', 'studio_artefacts']],
                ['name' => 'ResearchCollection', 'fields' => ['id', 'name', 'description', 'is_public', 'items']],
                ['name' => 'ResearchAnnotation', 'fields' => ['uuid', 'project_id', 'visibility', 'body', 'created_by', 'created_at']],
                ['name' => 'ResearcherView', 'fields' => ['researcher', 'projects', 'annotations', 'orcid']],
            ],
            'queries' => [
                'informationObject(id: Int!)' => 'InformationObject',
                'informationObjects(limit: Int, offset: Int)' => '[InformationObject]',
                'actor(id: Int!)' => 'Actor',
                'actors(limit: Int)' => '[Actor]',
                'repositories' => '[Repository]',
                'researchProject(id: Int!)' => 'ResearchProject',
                'researchProjects(limit: Int)' => '[ResearchProject]',
                'researchAnnotations(targetIri: String!)' => '[ResearchAnnotation]',
                'researchCollections(projectId: Int!)' => '[ResearchCollection]',
                'researcherView(researcherId: Int!)' => 'ResearcherView',
            ],
        ];
    }
}
