<?php

/**
 * AuthorityGraphService - Service for Heratio
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



namespace AhgActorManage\Services;

use Illuminate\Support\Facades\DB;

/**
 * Authority Graph Service.
 *
 * Builds graph data from the `relation` table for agent-to-agent
 * visualization using Cytoscape.js.
 */
class AuthorityGraphService
{
    /**
     * Get graph data for an actor and its relations.
     */
    public function getGraphData(int $actorId, int $depth = 1): array
    {
        $nodes = [];
        $edges = [];
        $visited = [];

        $this->buildGraph($actorId, $depth, $nodes, $edges, $visited);

        return [
            'nodes' => array_values($nodes),
            'edges' => array_values($edges),
        ];
    }

    /**
     * Recursively build graph from relations.
     */
    protected function buildGraph(
        int $actorId,
        int $depth,
        array &$nodes,
        array &$edges,
        array &$visited
    ): void {
        if ($depth < 0 || isset($visited[$actorId])) {
            return;
        }

        $visited[$actorId] = true;

        // Add this actor as a node
        if (!isset($nodes[$actorId])) {
            $actor = $this->getActorInfo($actorId);
            if ($actor) {
                $nodes[$actorId] = [
                    'data' => [
                        'id'    => 'actor_' . $actorId,
                        'label' => $actor->name ?? ('Actor #' . $actorId),
                        'type'  => $actor->entity_type ?? 'unknown',
                        'slug'  => $actor->slug ?? '',
                    ],
                ];
            }
        }

        if ($depth === 0) {
            return;
        }

        // Get all relations involving this actor
        $relations = DB::table('relation as r')
            ->leftJoin('relation_i18n as ri', function ($j) {
                $j->on('r.id', '=', 'ri.id')
                    ->where('ri.culture', '=', 'en');
            })
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('r.type_id', '=', 'ti.id')
                    ->where('ti.culture', '=', 'en');
            })
            ->where(function ($q) use ($actorId) {
                $q->where('r.subject_id', $actorId)
                    ->orWhere('r.object_id', $actorId);
            })
            ->select(
                'r.id',
                'r.subject_id',
                'r.object_id',
                'r.type_id',
                'ri.description',
                'ri.date as relation_date',
                'ti.name as relation_type'
            )
            ->get()
            ->all();

        foreach ($relations as $rel) {
            $edgeKey = $rel->subject_id . '_' . $rel->object_id . '_' . $rel->type_id;
            if (!isset($edges[$edgeKey])) {
                $edges[$edgeKey] = [
                    'data' => [
                        'id'     => 'edge_' . $rel->id,
                        'source' => 'actor_' . $rel->subject_id,
                        'target' => 'actor_' . $rel->object_id,
                        'label'  => $rel->relation_type ?? 'related',
                        'date'   => $rel->relation_date ?? '',
                    ],
                ];
            }

            // Traverse to the related actor
            $relatedId = ($rel->subject_id == $actorId) ? $rel->object_id : $rel->subject_id;
            $this->buildGraph($relatedId, $depth - 1, $nodes, $edges, $visited);
        }
    }

    /**
     * Get basic actor info for graph node.
     */
    protected function getActorInfo(int $actorId): ?object
    {
        return DB::table('actor as a')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('a.id', '=', 'ai.id')
                    ->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('a.entity_type_id', '=', 'ti.id')
                    ->where('ti.culture', '=', 'en');
            })
            ->where('a.id', $actorId)
            ->select(
                'a.id',
                'ai.authorized_form_of_name as name',
                'slug.slug',
                'ti.name as entity_type'
            )
            ->first();
    }
}
