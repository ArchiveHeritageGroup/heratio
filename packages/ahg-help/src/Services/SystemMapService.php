<?php

/**
 * SystemMapService - builds the Cytoscape element model for the help System Map
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

namespace AhgHelp\Services;

class SystemMapService
{
    /**
     * Load the raw map definition (the data-driven source of truth).
     */
    public static function definition(): array
    {
        return require __DIR__.'/../../resources/data/system-map.php';
    }

    /**
     * Build the Cytoscape element graph from the definition.
     *
     * Returns:
     *   [
     *     'elements' => [ {data:{...}}, ... ],   // nodes + edges
     *     'bands'    => [ id => {label,color,help}, ... ],
     *   ]
     *
     * Every node carries `parent` (for compound drill-in), `help` (slug or
     * null), `kind` (stage|child|grandchild), and `subId` so the client JS can
     * expand/collapse a single level cheaply:
     *   - child nodes      -> subId = stage id
     *   - grandchild nodes -> subId = parent child id (also `stageId`)
     * Edges carry `kind` (stage-edge|child-edge|grandchild-edge) and the same
     * `subId` so the drill model can show only the open level's edges.
     */
    public static function graph(): array
    {
        $def = self::definition();
        $elements = [];

        foreach ($def['stages'] as $stage) {
            // Top-level (compound) stage node.
            $elements[] = ['data' => [
                'id'     => $stage['id'],
                'label'  => $stage['label'],
                'sub'    => $stage['sub'] ?? '',
                'color'  => $stage['color'] ?? '#264653',
                'help'   => $stage['help'] ?? null,
                'kind'   => 'stage',
                'hasChildren' => ! empty($stage['children']),
            ]];

            // Child sub-flow nodes (parent => stage id so Cytoscape nests them).
            foreach ($stage['children'] ?? [] as $child) {
                $elements[] = ['data' => [
                    'id'     => $child['id'],
                    'label'  => $child['label'],
                    'sub'    => $child['sub'] ?? '',
                    'color'  => $stage['color'] ?? '#264653',
                    'help'   => $child['help'] ?? null,
                    'kind'   => 'child',
                    'parent' => $stage['id'],
                    'subId'  => $stage['id'],
                    'hasChildren' => ! empty($child['children']),
                ]];

                // Parent -> child hierarchy edge. Roots the drill-in layout so
                // the stage sits ABOVE its children (a proper parent/child tree).
                // Shown only while this stage is the open one.
                $elements[] = ['data' => [
                    'id'     => 'dn-'.$stage['id'].'__'.$child['id'],
                    'source' => $stage['id'],
                    'target' => $child['id'],
                    'kind'   => 'down-stage',
                    'subId'  => $stage['id'],
                ]];

                // Grandchild detail nodes (third level). parent => child id; the
                // view strips `parent` so they render as solid nodes, and the
                // drill model reveals them by subId === their parent child id.
                foreach ($child['children'] ?? [] as $grand) {
                    $elements[] = ['data' => [
                        'id'      => $grand['id'],
                        'label'   => $grand['label'],
                        'sub'     => $grand['sub'] ?? '',
                        'color'   => $stage['color'] ?? '#264653',
                        'help'    => $grand['help'] ?? null,
                        'kind'    => 'grandchild',
                        'parent'  => $child['id'],
                        'subId'   => $child['id'],
                        'stageId' => $stage['id'],
                    ]];

                    // Child -> grandchild hierarchy edge (same idea, one level
                    // deeper): the child sits ABOVE its detail nodes.
                    $elements[] = ['data' => [
                        'id'      => 'dn-'.$child['id'].'__'.$grand['id'],
                        'source'  => $child['id'],
                        'target'  => $grand['id'],
                        'kind'    => 'down-child',
                        'subId'   => $child['id'],
                        'stageId' => $stage['id'],
                    ]];
                }

                // Intra-child flow edges (only meaningful while the child is open).
                foreach ($child['child_edges'] ?? [] as $ge) {
                    $elements[] = ['data' => [
                        'id'      => 'ge-'.$ge[0].'__'.$ge[1],
                        'source'  => $ge[0],
                        'target'  => $ge[1],
                        'kind'    => 'grandchild-edge',
                        'subId'   => $child['id'],
                        'stageId' => $stage['id'],
                    ]];
                }
            }

            // Intra-stage flow edges (only meaningful while the stage is open).
            foreach ($stage['child_edges'] ?? [] as $ce) {
                $elements[] = ['data' => [
                    'id'     => 'e-'.$ce[0].'__'.$ce[1],
                    'source' => $ce[0],
                    'target' => $ce[1],
                    'kind'   => 'child-edge',
                    'subId'  => $stage['id'],
                ]];
            }
        }

        // Top-level spine edges between stages.
        foreach ($def['edges'] as $i => $e) {
            $elements[] = ['data' => [
                'id'     => 'se-'.$e[0].'__'.$e[1].'-'.$i,
                'source' => $e[0],
                'target' => $e[1],
                'kind'   => 'stage-edge',
            ]];
        }

        // Only keep `help` links the current viewer can actually open. Some
        // nodes point at articles in admin-only categories (Technical / Plugin
        // Reference) or at slugs not (yet) ingested; for an anonymous visitor
        // those resolve to a 404. Null those slugs so the node renders as plain
        // text instead of a dead link. One query, mirrors HelpArticleService's
        // own visibility gate. Degrades safely (links kept) if the table is
        // absent or the lookup throws.
        $bands = $def['bands'] ?? [];
        try {
            $slugs = [];
            foreach ($elements as $el) {
                if (! empty($el['data']['help'])) {
                    $slugs[$el['data']['help']] = true;
                }
            }
            // The cross-cutting legend bands (Auth / Settings / Rights) also carry
            // help slugs - gate them the same way, so an admin-only article (e.g.
            // the SHACL howto in the Technical category) is shown as plain text
            // for an anonymous visitor instead of a 404 link.
            foreach ($bands as $band) {
                if (! empty($band['help'])) { $slugs[$band['help']] = true; }
            }
            if ($slugs && \Illuminate\Support\Facades\Schema::hasTable('help_article')) {
                $q = \Illuminate\Support\Facades\DB::table('help_article')
                    ->whereIn('slug', array_keys($slugs))
                    ->where('is_published', 1);
                if (! HelpArticleService::isAdmin()) {
                    $q->whereNotIn('category', HelpArticleService::ADMIN_CATEGORIES);
                }
                $viewable = array_flip($q->pluck('slug')->all());
                foreach ($elements as &$el) {
                    if (! empty($el['data']['help']) && ! isset($viewable[$el['data']['help']])) {
                        $el['data']['help'] = null;
                    }
                }
                unset($el);
                foreach ($bands as $bid => $band) {
                    if (! empty($band['help']) && ! isset($viewable[$band['help']])) {
                        $bands[$bid]['help'] = null;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Keep the links as-is on any lookup failure - a possible 404 is
            // better than a blank map.
        }

        return [
            'elements' => $elements,
            'bands'    => $bands,
        ];
    }
}
