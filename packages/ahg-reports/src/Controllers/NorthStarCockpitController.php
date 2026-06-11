<?php

/**
 * NorthStarCockpitController - Controller for Heratio
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



namespace AhgReports\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * North Star Cockpit - a single read-only, demo-ready overview of the
 * platform's vision (north-star) capabilities.
 *
 * Each capability is rendered as an independent status card:
 *   - title + one-line description
 *   - a LIVE "Open" link, shown ONLY when its public route is registered
 *     (gated via Route::has so an absent package degrades to no-link)
 *   - a best-effort metric badge, computed from a cheap COUNT on a table
 *     that is first confirmed via Schema::hasTable and always wrapped in
 *     try/catch so a missing feature never breaks the page.
 *
 * No DB writes, no AI calls, no hard dependency on any service class.
 */
class NorthStarCockpitController extends Controller
{
    public function index(): View
    {
        $groups = [
            [
                'key'   => 'discover',
                'label' => 'Discover',
                'desc'  => 'Ways into the collection - browse, ask, and read in any language.',
                'cards' => [
                    $this->card(
                        'explore',
                        'Explore hub',
                        'A guided front door into the whole collection - themes, highlights and journeys.',
                        'compass',
                        'explore.index'
                    ),
                    $this->card(
                        'ask',
                        'Ask the collection',
                        'A natural-language question box grounded in the institution\'s own records.',
                        'chat-dots',
                        'ask.collection'
                    ),
                    $this->card(
                        'translate',
                        'Read in your language',
                        'On-demand translation of a record into the visitor\'s preferred language.',
                        'translate',
                        'record.translate',
                        ['route_param' => ['slug' => '_sample_']]
                    ),
                    $this->card(
                        'discoveries',
                        'Discoveries (generative scholarship)',
                        'Machine-suggested connections and reading paths surfaced for researchers.',
                        'lightbulb',
                        'scholarship.discoveries'
                    ),
                ],
            ],
            [
                'key'   => 'reconstruct',
                'label' => 'Reconstruct and walk',
                'desc'  => 'Rebuild lost context and walk visitors through it in space.',
                'cards' => [
                    $this->card(
                        'reconstructions',
                        'Reconstructions and montage',
                        'Layered, stage-by-stage reconstructions that re-assemble dispersed or damaged material.',
                        'images',
                        'exhibition-space.reconstructions',
                        [
                            'fallback_route' => 'reconstruction.demo',
                            'metric_table'   => 'reconstruction',
                            'metric_label'   => 'reconstructions',
                        ]
                    ),
                    $this->card(
                        'wayfinding',
                        'Exhibition wayfinding',
                        'Turn-by-turn guidance that routes a visitor between exhibits in a space.',
                        'signpost-2',
                        'exhibition-space.wayfinding',
                        [
                            'metric_table' => 'exhibition_space',
                            'metric_label' => 'spaces',
                        ]
                    ),
                ],
            ],
            [
                'key'   => 'trust',
                'label' => 'Trust and provenance',
                'desc'  => 'Tamper-evident credentials and a documented chain of custody.',
                'cards' => [
                    $this->card(
                        'c2pa',
                        'Content credentials / verify',
                        'Cryptographic content credentials so anyone can verify a digital object\'s authenticity.',
                        'patch-check',
                        'c2pa.authenticity',
                        [
                            'metric_table' => 'ahg_c2pa_manifest',
                            'metric_label' => 'signed manifests',
                        ]
                    ),
                    $this->card(
                        'trace',
                        'Provenance trace',
                        'A step-by-step provenance trail for a record - who did what, and when.',
                        'diagram-3',
                        'c2pa.verify.record.trace',
                        [
                            'metric_table' => 'ahg_c2pa_provenance',
                            'metric_label' => 'provenance entries',
                        ]
                    ),
                    $this->card(
                        'displaced',
                        'Displaced heritage register',
                        'A public register of displaced and contested heritage, supporting restitution claims.',
                        'globe2',
                        'displaced-heritage.index'
                    ),
                    $this->card(
                        'capture-priority',
                        'Race against loss (capture priority)',
                        'A ranked queue of at-risk material to digitise first, before it is lost.',
                        'hourglass-split',
                        'capture-priority.public',
                        ['fallback_route' => 'capture-priority.index']
                    ),
                ],
            ],
            [
                'key'   => 'access',
                'label' => 'Open access and orientation',
                'desc'  => 'Open data for reuse and a map of the whole platform.',
                'cards' => [
                    $this->card(
                        'graph',
                        'Open data graph',
                        'Linked-data endpoints that expose the catalogue as a reusable open dataset.',
                        'share',
                        'api.v1.graph.dataset',
                        ['fallback_route' => 'api.v1.graph.index']
                    ),
                    $this->card(
                        'system-map',
                        'System map',
                        'An orientation map of every module and capability across the platform.',
                        'map',
                        'help.system-map'
                    ),
                ],
            ],
        ];

        // Attach a live "Open" URL and a best-effort metric to each card.
        foreach ($groups as &$group) {
            foreach ($group['cards'] as &$card) {
                $card['url']    = $this->resolveUrl($card);
                $card['metric'] = $this->resolveMetric($card);
            }
            unset($card);
        }
        unset($group);

        $summary = $this->summarise($groups);

        return view('ahg-reports::north-star-cockpit.index', [
            'groups'  => $groups,
            'summary' => $summary,
        ]);
    }

    /**
     * Build a capability card definition. Link/metric resolution happens
     * later so that a single failure can never abort the list build.
     *
     * @param  array<string,mixed>  $opts
     * @return array<string,mixed>
     */
    private function card(
        string $key,
        string $title,
        string $description,
        string $icon,
        string $route,
        array $opts = []
    ): array {
        return [
            'key'            => $key,
            'title'          => $title,
            'description'    => $description,
            'icon'           => $icon,
            'route'          => $route,
            'fallback_route' => $opts['fallback_route'] ?? null,
            'route_param'    => $opts['route_param'] ?? [],
            'metric_table'   => $opts['metric_table'] ?? null,
            'metric_label'   => $opts['metric_label'] ?? null,
            'url'            => null,
            'metric'         => null,
        ];
    }

    /**
     * Resolve the public "Open" link for a card, preferring the primary
     * route and falling back to an alternate. Returns null (no link)
     * whenever neither route is registered, so the card still renders.
     *
     * @param  array<string,mixed>  $card
     */
    private function resolveUrl(array $card): ?string
    {
        foreach ([$card['route'], $card['fallback_route']] as $name) {
            if (! $name) {
                continue;
            }

            try {
                if (Route::has($name)) {
                    return route($name, $card['route_param'] ?? []);
                }
            } catch (\Throwable $e) {
                // URL generation can fail (e.g. a required parameter we
                // cannot satisfy with a sample value). Skip silently and
                // let the card render without a live link.
            }
        }

        return null;
    }

    /**
     * Best-effort metric: a single cheap COUNT against a table that is
     * confirmed present first. Every step is guarded so a missing table,
     * a permissions error, or any driver exception simply yields no badge.
     *
     * @param  array<string,mixed>  $card
     * @return array<string,mixed>|null
     */
    private function resolveMetric(array $card): ?array
    {
        $table = $card['metric_table'] ?? null;

        if (! $table) {
            return null;
        }

        try {
            if (! Schema::hasTable($table)) {
                return null;
            }

            $count = DB::table($table)->count();

            return [
                'value' => $count,
                'label' => $card['metric_label'] ?? 'records',
            ];
        } catch (\Throwable $e) {
            // Absent package, missing column, locked table, driver error -
            // none of these should ever break the cockpit. No metric shown.
            return null;
        }
    }

    /**
     * Roll up headline numbers for the hero strip.
     *
     * @param  array<int,array<string,mixed>>  $groups
     * @return array<string,int>
     */
    private function summarise(array $groups): array
    {
        $total = 0;
        $live  = 0;

        foreach ($groups as $group) {
            foreach ($group['cards'] as $card) {
                $total++;
                if (! empty($card['url'])) {
                    $live++;
                }
            }
        }

        return [
            'total' => $total,
            'live'  => $live,
        ];
    }
}
