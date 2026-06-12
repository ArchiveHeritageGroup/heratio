<?php

/**
 * TrustConsoleController - Controller for Heratio
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
 * Trust and Transparency Console - a single read-only operator console that
 * ties together the many trust, preservation, accessibility and open-data
 * surfaces that already exist across the platform but are otherwise scattered
 * and hard to find from one place.
 *
 * This is a HUB. It owns NO trust/preservation/open-data logic of its own and
 * re-implements NOTHING. Every card simply LINKS to a surface that another
 * package already provides, and only when that surface's named route is
 * actually registered on THIS install (gated via Route::has so an absent
 * package degrades to a "Not configured" card with no dead link).
 *
 * Each card may carry a best-effort metric badge: a single cheap COUNT against
 * a table that is first confirmed via Schema::hasTable and always wrapped in
 * try/catch so a missing feature never breaks the page. Some surfaces are
 * per-record (they need a record reference to address); those carry a sample
 * route parameter only so that Route::has + route() resolve to a usable demo
 * link without inventing data.
 *
 * No DB writes, no ALTER, no AI calls, no hard dependency on any service class.
 * Never 500s: every link and every metric is guarded.
 */
class TrustConsoleController extends Controller
{
    public function index(): View
    {
        $groups = [
            [
                'key'   => 'authenticity',
                'label' => 'Authenticity and provenance',
                'desc'  => 'Tamper-evident credentials and a documented, accountable chain of custody.',
                'cards' => [
                    $this->card(
                        'trust-home',
                        'Trust home',
                        'The public front door to the institution\'s trust signals - content credentials, provenance and verification, in one place.',
                        'patch-check',
                        'c2pa.trust'
                    ),
                    $this->card(
                        'verified-records',
                        'Verified records',
                        'A public roll of records that carry verifiable content credentials, so visitors can see what has been signed.',
                        'shield-check',
                        'c2pa.verified.records',
                        [
                            'metric_table' => 'ahg_c2pa_manifest',
                            'metric_label' => 'signed manifests',
                        ]
                    ),
                    $this->card(
                        'authenticity',
                        'Authenticity report (per record)',
                        'A plain-language "what we can and cannot verify" report for one published record. Opens a sample record.',
                        'file-earmark-check',
                        'c2pa.authenticity.report',
                        ['route_param' => ['idOrSlug' => '_sample_']]
                    ),
                    $this->card(
                        'inference-provenance',
                        'AI inference provenance (per record)',
                        'An honest, read-only view of which AI inferences contributed to a record\'s metadata, and that a human stayed accountable. Opens a sample record.',
                        'diagram-3',
                        'c2pa.inference.provenance',
                        [
                            'route_param'  => ['idOrSlug' => '_sample_'],
                            'metric_table' => 'ahg_ai_inference',
                            'metric_label' => 'inferences recorded',
                        ]
                    ),
                    $this->card(
                        'verify-front-door',
                        'Verify authenticity',
                        'The public "is this real?" trust anchor: verify a record or check the content credentials of any uploaded file.',
                        'patch-question',
                        'c2pa.authenticity'
                    ),
                    $this->card(
                        'check-file',
                        'Check content credentials of a file',
                        'A throwaway file-drop tool: upload any image and get its C2PA verdict in plain language. No account needed.',
                        'upload',
                        'c2pa.verify.check'
                    ),
                    $this->card(
                        'coverage',
                        'Authenticity coverage (operator)',
                        'How much of the collection actually carries content credentials - headline coverage and a per-repository gap table.',
                        'pie-chart',
                        'c2pa.coverage'
                    ),
                ],
            ],
            [
                'key'   => 'preservation',
                'label' => 'Preservation',
                'desc'  => 'Digital-preservation integrity, maturity and a per-record lifecycle history.',
                'cards' => [
                    $this->card(
                        'preservation',
                        'Preservation dashboard',
                        'The operator hub for fixity, events, formats, virus scans, policies and preservation packages.',
                        'hdd-stack',
                        'preservation.index'
                    ),
                    $this->card(
                        'fixity',
                        'Fixity and integrity report',
                        'How many digital objects carry a verifiable checksum baseline, and the result roll-up of the most recent verification sweep.',
                        'fingerprint',
                        'fixity.index',
                        [
                            'metric_table' => 'core_fixity_check_log',
                            'metric_label' => 'fixity checks logged',
                        ]
                    ),
                    $this->card(
                        'preservation-maturity',
                        'Preservation maturity (NDSA levels)',
                        'An evidence-based self-assessment of the running instance against the NDSA Levels of Digital Preservation.',
                        'bar-chart-steps',
                        'preservation-maturity.index'
                    ),
                    $this->card(
                        'preservation-timeline',
                        'Preservation timeline (per record)',
                        'The PREMIS-style preservation lifecycle of one record\'s digital objects in chronological order. Opens a sample record.',
                        'clock-history',
                        'c2pa.preservation.timeline',
                        ['route_param' => ['idOrSlug' => '_sample_']]
                    ),
                ],
            ],
            [
                'key'   => 'accessibility',
                'label' => 'Accessibility',
                'desc'  => 'How readable and usable the collection is for everyone.',
                'cards' => [
                    $this->card(
                        'accessibility',
                        'Accessibility coverage report',
                        'A heuristic coverage report over accessibility-relevant metadata - text descriptions, captions, transcripts - with WCAG 2.1 AA cited as a reference.',
                        'universal-access-circle',
                        'accessibility.index'
                    ),
                    $this->card(
                        'alt-text',
                        'Alt-text curation',
                        'A worklist of published image surrogates missing human-authored alternative text, with an inline add or edit form.',
                        'card-image',
                        'alt-text.index',
                        [
                            'metric_table' => 'image_alt_text',
                            'metric_label' => 'alt texts curated',
                        ]
                    ),
                ],
            ],
            [
                'key'   => 'open-data',
                'label' => 'Open data and transparency',
                'desc'  => 'Open the catalogue to the wider world as reusable, linked open data.',
                'cards' => [
                    $this->card(
                        'open-data',
                        'Open data home',
                        'The front door to the institution\'s open-data offering - what is published, how to reuse it, and under which terms.',
                        'share',
                        'open-data.index'
                    ),
                    $this->card(
                        'open-data-protocol',
                        'Open data protocol',
                        'The machine- and human-readable statement of how this institution publishes open data and what consumers can rely on.',
                        'file-earmark-text',
                        'open-data.protocol'
                    ),
                    $this->card(
                        'open-data-maturity',
                        'Open data maturity scorecard',
                        'A self-assessment of how open the published data actually is, against recognised open-data maturity criteria.',
                        'graph-up',
                        'open-data.maturity'
                    ),
                    $this->card(
                        'dcat-catalog',
                        'DCAT data catalog',
                        'A standards-based DCAT catalog describing the datasets this institution publishes, for harvesters and data portals.',
                        'collection',
                        'open-data.catalog'
                    ),
                    $this->card(
                        'rdf-dataset',
                        'Linked-data / RDF dataset dump',
                        'The catalogue exposed as a reusable linked-data dataset - the open-data graph endpoint.',
                        'bezier2',
                        'open-data.dataset',
                        ['fallback_route' => 'api.v1.graph.dataset']
                    ),
                    $this->card(
                        'union-catalogue',
                        'Union catalogue',
                        'A shared, cross-institution discovery surface that federates published holdings into one searchable catalogue.',
                        'building',
                        'union.catalogue'
                    ),
                    $this->card(
                        'oai-harvest',
                        'OAI-PMH harvest endpoint',
                        'The standards-based OAI-PMH endpoint other systems use to harvest this institution\'s metadata.',
                        'arrow-repeat',
                        'api.oai'
                    ),
                    $this->card(
                        'themes',
                        'Public themes',
                        'A curated, public set of themes that group records into accessible entry points into the collection.',
                        'tags',
                        'themes.index'
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

        return view('ahg-reports::trust-console.index', [
            'groups'  => $groups,
            'summary' => $summary,
        ]);
    }

    /**
     * Build a console card definition. Link/metric resolution happens later so
     * that a single failure can never abort the list build.
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
     * Resolve the live link for a card, preferring the primary route and
     * falling back to an alternate. Returns null (no link) whenever neither
     * route is registered, so the card still renders as "Not configured".
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
                // URL generation can fail (e.g. a required parameter we cannot
                // satisfy with a sample value). Skip silently and let the card
                // render without a live link.
            }
        }

        return null;
    }

    /**
     * Best-effort metric: a single cheap COUNT against a table that is
     * confirmed present first. Every step is guarded so a missing table, a
     * permissions error, or any driver exception simply yields no badge.
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
            // none of these should ever break the console. No metric shown.
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
