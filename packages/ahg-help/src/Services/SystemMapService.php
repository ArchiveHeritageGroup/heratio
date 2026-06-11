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
     * null), `kind` (stage|child), and `subId` (the parent stage id) so the
     * client JS can expand/collapse a single stage cheaply.
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
                ]];
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

        return [
            'elements' => $elements,
            'bands'    => $def['bands'] ?? [],
        ];
    }
}
