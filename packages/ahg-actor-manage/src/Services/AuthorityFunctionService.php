<?php

/**
 * AuthorityFunctionService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgActorManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Authority Function Service.
 *
 * Actor-to-function linking, function browse integration.
 * Manages ahg_actor_function_link table for structured ISDF function relations.
 * Supplements existing free-text actor_i18n.functions field.
 */
class AuthorityFunctionService
{
    /**
     * Relation types for actor-function links.
     */
    public const RELATION_TYPES = [
        'responsible'  => 'Is/was responsible for',
        'participates' => 'Participates in',
        'authorizes'   => 'Authorizes',
        'oversees'     => 'Oversees',
        'administers'  => 'Administers',
    ];

    /**
     * Get all function links for an actor.
     */
    public function getFunctionLinks(int $actorId): array
    {
        return DB::table('ahg_actor_function_link as afl')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('afl.function_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'afl.function_id', '=', 'slug.object_id')
            ->where('afl.actor_id', $actorId)
            ->select(
                'afl.*',
                'ioi.title as function_title',
                'slug.slug as function_slug'
            )
            ->orderBy('afl.sort_order')
            ->get()
            ->all();
    }

    /**
     * Get all actors linked to a function.
     */
    public function getActorsForFunction(int $functionId): array
    {
        return DB::table('ahg_actor_function_link as afl')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('afl.actor_id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'afl.actor_id', '=', 'slug.object_id')
            ->where('afl.function_id', $functionId)
            ->select(
                'afl.*',
                'ai.authorized_form_of_name as actor_name',
                'slug.slug as actor_slug'
            )
            ->orderBy('afl.sort_order')
            ->get()
            ->all();
    }

    /**
     * Save a function link (create or update).
     */
    public function save(int $actorId, array $data, int $linkId = 0): int
    {
        $row = [
            'actor_id'      => $actorId,
            'function_id'   => (int) ($data['function_id'] ?? 0),
            'relation_type' => $data['relation_type'] ?? 'responsible',
            'date_from'     => $data['date_from'] ?? null,
            'date_to'       => $data['date_to'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
        ];

        if ($linkId > 0) {
            DB::table('ahg_actor_function_link')
                ->where('id', $linkId)
                ->update($row);

            return $linkId;
        }

        $row['created_at'] = date('Y-m-d H:i:s');

        return (int) DB::table('ahg_actor_function_link')->insertGetId($row);
    }

    /**
     * Delete a function link.
     */
    public function delete(int $id): bool
    {
        return DB::table('ahg_actor_function_link')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Search functions for autocomplete.
     */
    public function searchFunctions(string $query, int $limit = 10): array
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->join('object', 'io.id', '=', 'object.id')
            ->where('object.class_name', 'QubitFunction')
            ->where('ioi.title', 'like', '%' . $query . '%')
            ->select('io.id', 'ioi.title', 'slug.slug')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Browse all functions with actor counts.
     */
    public function browseFunctions(): array
    {
        $functions = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->join('object', 'io.id', '=', 'object.id')
            ->where('object.class_name', 'QubitFunction')
            ->select('io.id', 'ioi.title', 'slug.slug')
            ->orderBy('ioi.title')
            ->get()
            ->all();

        foreach ($functions as &$func) {
            $func->actor_count = DB::table('ahg_actor_function_link')
                ->where('function_id', $func->id)
                ->count();
        }

        return $functions;
    }

    /**
     * Get function link statistics.
     */
    public function getStats(): array
    {
        return [
            'total_links'      => DB::table('ahg_actor_function_link')->count(),
            'unique_actors'    => DB::table('ahg_actor_function_link')->distinct('actor_id')->count('actor_id'),
            'unique_functions' => DB::table('ahg_actor_function_link')->distinct('function_id')->count('function_id'),
        ];
    }
}
