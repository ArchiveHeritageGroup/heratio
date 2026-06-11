<?php

/**
 * SystemBreakdownService - builds the Cytoscape element model for the help
 * System Breakdown (a 4-level functional capability tree).
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

class SystemBreakdownService
{
    /**
     * Load the raw breakdown definition (the data-driven source of truth).
     */
    public static function definition(): array
    {
        return require __DIR__.'/../../resources/data/system-breakdown.php';
    }

    /**
     * Build the Cytoscape element graph from the definition.
     *
     * Returns:
     *   [ 'elements' => [ {data:{...}}, ... ] ]   // nodes + hierarchy edges
     *
     * Node kinds map to the four levels:
     *   root (L1) | l2 (entity) | l3 (aspect) | l4 (feature)
     *
     * Every non-root node carries:
     *   parent   - its DIRECT parent id (for the drill model; the VIEW strips
     *              this from el.data so nodes never become Cytoscape compound
     *              parents, which would render at 10% opacity / blank).
     *   depth    - 1 (l2) .. 3 (l4); the root is depth 0.
     *   ancestors- ordered chain of ancestor ids from the root down to (but not
     *              including) the node itself, so the drill can reveal exactly
     *              one level at a time and the breadcrumb can be reconstructed.
     *   color    - inherited from the L2 entity so a whole branch shares a tint.
     *
     * Hierarchy edges connect consecutive levels so the breadthfirst layout
     * roots each open node on top with its items beneath:
     *   kind 'down-1' : root  -> entity (L1 -> L2)
     *   kind 'down-2' : entity-> aspect (L2 -> L3)
     *   kind 'down-3' : aspect-> feature (L3 -> L4)
     * Each edge carries `parentId` (the source/open node) so the drill model
     * can show only the open node's connectors.
     */
    public static function graph(): array
    {
        $def  = self::definition();
        $root = $def['root'];
        $rootColor = $root['color'] ?? '#212529';

        $elements = [];

        // ---- L1 root node ----
        $elements[] = ['data' => [
            'id'          => $root['id'],
            'label'       => $root['label'],
            'sub'         => $root['sub'] ?? '',
            'color'       => $rootColor,
            'help'        => $root['help'] ?? null,
            'kind'        => 'root',
            'depth'       => 0,
            'ancestors'   => [],
            'hasChildren' => ! empty($def['entities']),
        ]];

        foreach ($def['entities'] as $entity) {
            $entColor = $entity['color'] ?? $rootColor;

            // ---- L2 entity node ----
            $elements[] = ['data' => [
                'id'          => $entity['id'],
                'label'       => $entity['label'],
                'sub'         => $entity['sub'] ?? '',
                'color'       => $entColor,
                'help'        => $entity['help'] ?? null,
                'kind'        => 'l2',
                'depth'       => 1,
                'parent'      => $root['id'],
                'ancestors'   => [$root['id']],
                'hasChildren' => ! empty($entity['children']),
            ]];

            // L1 -> L2 hierarchy edge.
            $elements[] = ['data' => [
                'id'       => 'd1-'.$root['id'].'__'.$entity['id'],
                'source'   => $root['id'],
                'target'   => $entity['id'],
                'kind'     => 'down-1',
                'parentId' => $root['id'],
            ]];

            foreach ($entity['children'] ?? [] as $aspect) {
                // ---- L3 aspect node ----
                $elements[] = ['data' => [
                    'id'          => $aspect['id'],
                    'label'       => $aspect['label'],
                    'sub'         => $aspect['sub'] ?? '',
                    'color'       => $entColor,
                    'help'        => $aspect['help'] ?? null,
                    'kind'        => 'l3',
                    'depth'       => 2,
                    'parent'      => $entity['id'],
                    'ancestors'   => [$root['id'], $entity['id']],
                    'hasChildren' => ! empty($aspect['children']),
                ]];

                // L2 -> L3 hierarchy edge.
                $elements[] = ['data' => [
                    'id'       => 'd2-'.$entity['id'].'__'.$aspect['id'],
                    'source'   => $entity['id'],
                    'target'   => $aspect['id'],
                    'kind'     => 'down-2',
                    'parentId' => $entity['id'],
                ]];

                foreach ($aspect['children'] ?? [] as $feature) {
                    // ---- L4 feature node (leaf) ----
                    $elements[] = ['data' => [
                        'id'          => $feature['id'],
                        'label'       => $feature['label'],
                        'sub'         => $feature['sub'] ?? '',
                        'color'       => $entColor,
                        'help'        => $feature['help'] ?? null,
                        'kind'        => 'l4',
                        'depth'       => 3,
                        'parent'      => $aspect['id'],
                        'ancestors'   => [$root['id'], $entity['id'], $aspect['id']],
                        'hasChildren' => false,
                    ]];

                    // L3 -> L4 hierarchy edge.
                    $elements[] = ['data' => [
                        'id'       => 'd3-'.$aspect['id'].'__'.$feature['id'],
                        'source'   => $aspect['id'],
                        'target'   => $feature['id'],
                        'kind'     => 'down-3',
                        'parentId' => $aspect['id'],
                    ]];
                }
            }
        }

        // Only keep `help` links the current viewer can actually open. Some
        // nodes point at articles in admin-only categories (Technical / Plugin
        // Reference) or at slugs not (yet) ingested; for an anonymous visitor
        // those resolve to a 404. Null those slugs so the node renders as plain
        // text instead of a dead link. One query, mirrors HelpArticleService's
        // own visibility gate. Degrades safely (links kept) if the table is
        // absent or the lookup throws.
        try {
            $slugs = [];
            foreach ($elements as $el) {
                if (! empty($el['data']['help'])) {
                    $slugs[$el['data']['help']] = true;
                }
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
            }
        } catch (\Throwable $e) {
            // Keep the links as-is on any lookup failure - a possible 404 is
            // better than a blank diagram.
        }

        return [
            'elements' => $elements,
        ];
    }
}
