<?php

/**
 * GraphqlController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

        if (preg_match('/\b__schema\b/i', $query)) {
            return ['data' => ['__schema' => $this->getSchemaInfo()]];
        }

        return ['errors' => [['message' => 'Unsupported query. Available: informationObject(id), informationObjects, actor(id), actors, repositories']]];
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
            ],
            'queries' => [
                'informationObject(id: Int!)' => 'InformationObject',
                'informationObjects(limit: Int, offset: Int)' => '[InformationObject]',
                'actor(id: Int!)' => 'Actor',
                'actors(limit: Int)' => '[Actor]',
                'repositories' => '[Repository]',
            ],
        ];
    }
}
