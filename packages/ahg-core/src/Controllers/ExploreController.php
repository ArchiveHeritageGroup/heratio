<?php

/**
 * ExploreController - Heratio ahg-core
 *
 * Public "Explore" hub: a single, jurisdiction-neutral landing page that makes the
 * collection's public-facing capabilities discoverable in one place. Each capability
 * lives in its own package and is reachable at its own URL; this hub simply gathers
 * them and links out.
 *
 * The card list is built from Route::has(...) checks so a capability only appears when
 * its package is installed and its route is registered. A missing package therefore
 * degrades gracefully to a smaller hub - never a 500 or a dead link.
 *
 * This surface is PUBLIC (no auth) and READ-ONLY.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class ExploreController extends Controller
{
    /**
     * Render the public Explore hub. Each candidate card declares the route name it
     * needs; only cards whose route is registered (Route::has) are passed to the view,
     * so the grid reflects exactly which capabilities are installed.
     */
    public function index()
    {
        // Candidate cards. `route` is resolved to a URL only when Route::has() passes;
        // `params` supplies any required route parameters (e.g. the open-data graph
        // example id). `note` is optional supplementary copy shown under the card body.
        $candidates = [
            [
                'route' => 'collection.overview',
                'icon' => 'fas fa-layer-group',
                'title' => __('This collection at a glance'),
                'desc' => __('See the size and shape of the collection at a glance - how many descriptions, across which levels and centuries, from which repositories, and how much you can explore online.'),
                'cta' => __('See the overview'),
            ],
            [
                'route' => 'ask.collection',
                'icon' => 'fas fa-comments',
                'title' => __('Ask the collection'),
                'desc' => __('Ask a plain-language question and get an answer drawn from this collection\'s own catalogue and knowledge base, with cited sources.'),
                'cta' => __('Ask a question'),
            ],
            [
                'route' => 'record.translate',
                'icon' => 'fas fa-language',
                'title' => __('Read a record in your language'),
                'desc' => __('Open any catalogue record and read its key details translated into your language on demand. The original is always shown and remains authoritative.'),
                'cta' => __('Learn how'),
                // record.translate needs a record id/slug, so we cannot deep-link a
                // specific record here. Point at the public browse so the visitor can
                // pick a record, then use the per-record "translate" action.
                'params' => ['idOrSlug' => 'example'],
                'note' => __('Pick any record, then choose "Read in your language".'),
            ],
            [
                'route' => 'capture-priority.public',
                'icon' => 'fas fa-hourglass-half',
                'title' => __('Race against loss'),
                'desc' => __('See the records this collection has identified as most at risk of being lost, and why - the items a digitisation effort should reach first.'),
                'cta' => __('See what is at risk'),
            ],
            [
                'route' => 'exhibition-space.reconstructions',
                'icon' => 'fas fa-cubes',
                'title' => __('Reconstructions gallery'),
                'desc' => __('Walk through reconstructions of places and objects that no longer exist, rebuilt from the evidence in the collection.'),
                'cta' => __('Walk the reconstructions'),
            ],
            [
                'route' => 'c2pa.authenticity',
                'icon' => 'fas fa-shield-alt',
                'title' => __('Content Credentials'),
                'desc' => __('Verify the provenance and authenticity of an image or document using its embedded Content Credentials (C2PA).'),
                'cta' => __('Verify authenticity'),
            ],
            [
                'route' => 'help.system-map',
                'icon' => 'fas fa-sitemap',
                'title' => __('System map'),
                'desc' => __('See how the pieces fit together - the catalogue, the public surfaces, and the services behind them.'),
                'cta' => __('View the system map'),
            ],
            [
                'route' => 'api.v1.graph.show',
                'icon' => 'fas fa-project-diagram',
                'title' => __('Open data graph (API)'),
                'desc' => __('A Linked-Data endpoint for developers and researchers: fetch any record and its relationships as a machine-readable graph.'),
                'cta' => __('Read the example'),
                // Developer card: show an example URL rather than a single button.
                'params' => ['idOrSlug' => 1],
                'developer' => true,
            ],
            [
                'route' => 'open-data.index',
                'icon' => 'fas fa-database',
                'title' => __('Open data and APIs'),
                'desc' => __('Take this collection as data: the linked-data graph, bulk dataset dumps, OAI-PMH harvesting, the API reference, and more - all open, no key required.'),
                'cta' => __('Browse the open data'),
            ],
        ];

        $cards = [];
        foreach ($candidates as $card) {
            $name = $card['route'];
            if (! Route::has($name)) {
                continue;
            }
            try {
                $card['url'] = route($name, $card['params'] ?? []);
            } catch (\Throwable $e) {
                // A registered route whose URL cannot be generated (e.g. a required
                // parameter we could not supply) is skipped rather than 500-ing.
                continue;
            }
            $cards[] = $card;
        }

        return view('ahg-core::explore', ['cards' => $cards]);
    }
}
