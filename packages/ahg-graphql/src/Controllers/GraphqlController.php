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

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GraphQL Playground Controller.
 * Provides an interactive GraphQL IDE for querying the archive.
 * Migrated from ahgGraphqlPlugin.
 */
class GraphqlController extends Controller
{
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
            $limit = $variables['limit'] ?? 25;
            $offset = $variables['offset'] ?? 0;
            return $this->resolveInformationObjects((int) $limit, (int) $offset);
        }

        if (preg_match('/\bactor\s*\(\s*id\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveActor((int) $m[1]);
        }

        if (preg_match('/\bactors\b/i', $query)) {
            return $this->resolveActors($variables['limit'] ?? 25);
        }

        if (preg_match('/\brepositories\b/i', $query)) {
            return $this->resolveRepositories();
        }

        if (preg_match('/\bresearchProject\s*\(\s*id\s*:\s*(\d+)\s*\)/i', $query, $m)) {
            return $this->resolveResearchProject((int) $m[1]);
        }

        if (preg_match('/\bresearchProjects\b/i', $query)) {
            return $this->resolveResearchProjects((int) ($variables['limit'] ?? 25));
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
        $project = DB::table('research_project as p')
            ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
            ->where('p.id', $id)
            ->select('p.id', 'p.title', 'p.description', 'p.project_type', 'p.status',
                     'p.start_date', 'p.expected_end_date', 'p.created_at',
                     'r.id as owner_id', 'r.first_name as owner_first_name', 'r.last_name as owner_last_name')
            ->first();

        if (!$project) return ['errors' => [['message' => "Research project {$id} not found"]]];

        $collections = DB::table('research_collection')
            ->where('project_id', $id)
            ->select('id', 'name', 'description', 'is_public')
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
        } catch (\Throwable $e) {}

        $data = (array) $project;
        $data['collections'] = $collections;
        $data['studio_artefacts'] = $studio;

        return ['data' => ['researchProject' => $data]];
    }

    private function resolveResearchProjects(int $limit): array
    {
        $rows = DB::table('research_project as p')
            ->leftJoin('research_researcher as r', 'p.owner_id', '=', 'r.id')
            ->orderByDesc('p.created_at')
            ->limit($limit)
            ->select('p.id', 'p.title', 'p.project_type', 'p.status',
                     'r.id as owner_id', 'r.first_name as owner_first_name', 'r.last_name as owner_last_name')
            ->get()
            ->map(fn ($p) => (array) $p)
            ->toArray();

        return ['data' => ['researchProjects' => $rows]];
    }

    private function resolveResearchAnnotations(string $targetIri): array
    {
        try {
            $rows = DB::table('ahg_iiif_annotation')
                ->where('target_iri', $targetIri)
                ->orderBy('id')
                ->select('uuid', 'project_id', 'visibility', 'body_json', 'created_by', 'created_at')
                ->get()
                ->map(function ($r) {
                    return [
                        'uuid'        => $r->uuid,
                        'project_id'  => $r->project_id,
                        'visibility'  => $r->visibility,
                        'created_by'  => $r->created_by,
                        'created_at'  => $r->created_at,
                        'body'        => json_decode($r->body_json, true),
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
        $cols = DB::table('research_collection')
            ->where('project_id', $projectId)
            ->select('id', 'name', 'description', 'is_public', 'created_at')
            ->get()->map(fn ($c) => (array) $c)->toArray();

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

        if (!$researcher) return ['errors' => [['message' => "Researcher {$researcherId} not found"]]];

        $projects = DB::table('research_project as p')
            ->leftJoin('research_project_collaborator as pc', function ($j) use ($researcherId) {
                $j->on('pc.project_id', '=', 'p.id')->where('pc.researcher_id', '=', $researcherId);
            })
            ->where(function ($q) use ($researcherId) {
                $q->where('p.owner_id', $researcherId)->orWhereNotNull('pc.id');
            })
            ->orderByDesc('p.created_at')
            ->limit(50)
            ->select('p.id', 'p.title', 'p.project_type', 'p.status', 'p.created_at')
            ->get()->map(fn ($p) => (array) $p)->toArray();

        $annotations = [];
        try {
            $annotations = DB::table('ahg_iiif_annotation')
                ->where('created_by', $researcherId)
                ->orderByDesc('created_at')
                ->limit(50)
                ->select('uuid', 'target_iri', 'project_id', 'visibility', 'created_at')
                ->get()->map(fn ($a) => (array) $a)->toArray();
        } catch (\Throwable $e) {}

        $orcid = null;
        try {
            $orcid = DB::table('researcher_orcid_link')->where('researcher_id', $researcherId)
                ->select('orcid_id', 'last_synced_at', 'last_works_count')
                ->first();
            $orcid = $orcid ? (array) $orcid : null;
        } catch (\Throwable $e) {}

        return ['data' => ['researcherView' => [
            'researcher'  => (array) $researcher,
            'projects'    => $projects,
            'annotations' => $annotations,
            'orcid'       => $orcid,
        ]]];
    }

    private function resolveInformationObject(int $id): array
    {
        $io = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $id)
            ->select('io.id', 'io.identifier', 'io.parent_id', 'io.repository_id',
                     'io.level_of_description_id', 'ioi.title', 'ioi.scope_and_content', 'slug.slug')
            ->first();

        return $io ? ['data' => ['informationObject' => (array) $io]]
                   : ['errors' => [['message' => "Information object {$id} not found"]]];
    }

    private function resolveInformationObjects(int $limit, int $offset): array
    {
        $items = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', '!=', 1)
            ->select('io.id', 'io.identifier', 'ioi.title', 'slug.slug')
            ->orderBy('ioi.title')
            ->offset($offset)->limit($limit)
            ->get();

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
