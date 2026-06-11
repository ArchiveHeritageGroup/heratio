<?php

/**
 * GraphExplorerController - the public, human-friendly GRAPH EXPLORER.
 *
 * Next slice of north-star #1204 ("the world heritage graph / open memory
 * protocol"). The /id/{slug}, /id/actor/{slug} and /id/term/{slug} endpoints
 * already publish every record, actor and term as dereferenceable linked data
 * for MACHINES. This controller gives a HUMAN the same graph to walk in a
 * browser:
 *
 *   GET /graph-explorer                  - a landing page: a search box plus a
 *                                          few high-degree starting entities, so
 *                                          a first-time visitor always has a way
 *                                          in.
 *   GET /graph-explorer/{type}/{slug}    - ONE entity (type in record|actor|
 *                                          term) rendered as a human page: its
 *                                          label and key facts, and its
 *                                          connections grouped (other records,
 *                                          people, places, subjects, repository,
 *                                          broader / narrower) as CLICKABLE links
 *                                          that navigate to the explorer for the
 *                                          connected entity - so the visitor
 *                                          walks the graph hop by hop.
 *
 * Each entity page also links OUT to the machine representation (the /id/...
 * JSON-LD / Turtle / RDF document) and to the canonical human record / authority
 * page, so the explorer is a hub between the human and machine views, never a
 * dead end.
 *
 * It is a THIN presentation layer over GraphExplorerService, which itself
 * mirrors the exact fetch + publication gate of the three entity controllers,
 * so the explorer can never drift from the linked-data output. Published-only;
 * an unknown / unpublished / mistyped slug yields a clean 404 (never a leak,
 * never a 500). Every link is built from url() / route(), never a hardcoded
 * host, so a fresh install on its own domain self-describes. Read-only: no DB
 * writes, no DDL. Jurisdiction-neutral.
 *
 * CATCH-ALL SAFETY: "/graph-explorer/{type}/{slug}" is a THREE-segment path, so
 * the single-segment /{slug} archival-record catch-all (in
 * ahg-information-object-manage) can never capture it; the bare
 * "/graph-explorer" landing is a single segment, but ahg-api is discovered
 * before ahg-information-object-manage (alphabetical package order), so this
 * route registers first and wins the match. The {type} segment is constrained
 * to record|actor|term and {slug} to the slug grammar, so neither can swallow a
 * sibling path. The slug may itself contain hyphens (a multi-segment-looking
 * label) but is a single path segment, so it is captured cleanly.
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

namespace AhgApi\Controllers;

use AhgApi\Services\GraphExplorerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GraphExplorerController extends Controller
{
    /** The entity types this explorer can navigate. */
    private const TYPES = ['record', 'actor', 'term'];

    protected GraphExplorerService $explorer;

    public function __construct(GraphExplorerService $explorer)
    {
        $this->explorer = $explorer;
    }

    /**
     * GET /graph-explorer
     *
     * The landing page: a search box plus a few high-degree starting entities.
     * Both the search results and the seed list are bounded and best-effort, so
     * an empty catalogue simply shows a friendly empty-state, never a 500.
     */
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));

        $results = $q !== '' ? $this->explorer->search($q) : [];
        $starting = $q === '' ? $this->explorer->startingPoints() : [];

        // Attach a resolved explorer URL to every connection so the blade stays
        // logic-free.
        $results = array_map(fn ($c) => $this->withUrl($c), $results);
        $starting = array_map(fn ($c) => $this->withUrl($c), $starting);

        return response()->view('ahg-api::graph-explorer.index', [
            'query' => $q,
            'results' => $results,
            'starting' => $starting,
        ]);
    }

    /**
     * GET /graph-explorer/{type}/{slug}
     *
     * Render ONE entity as a human page with its grouped, clickable connections.
     * An unknown type, or an unknown / unpublished slug, yields a clean 404.
     */
    public function show(Request $request, string $type, string $slug): Response
    {
        $type = strtolower($type);
        if (! in_array($type, self::TYPES, true)) {
            return $this->notFound();
        }

        $node = match ($type) {
            'record' => $this->explorer->record($slug),
            'actor' => $this->explorer->actor($slug),
            'term' => $this->explorer->term($slug),
            default => null,
        };

        if ($node === null) {
            return $this->notFound();
        }

        // Resolve a navigable explorer URL on every connection, plus the
        // out-links to the machine (/id/...) and the human authority page.
        $node['groups'] = array_map(function (array $group) {
            $group['items'] = array_map(fn ($c) => $this->withUrl($c), $group['items']);

            return $group;
        }, $node['groups']);

        $node['machine_url'] = $this->machineUrl($node);
        $node['authority_url'] = $this->authorityUrl($node);

        return response()->view('ahg-api::graph-explorer.show', [
            'node' => $node,
        ]);
    }

    // -----------------------------------------------------------------
    // Link building (url()-based, never a hardcoded host)
    // -----------------------------------------------------------------

    /**
     * Add the explorer URL for a connection, when it has a slug. A connection
     * without a slug stays non-clickable (rendered as plain text by the blade).
     *
     * @param  array<string,mixed>  $c
     * @return array<string,mixed>
     */
    protected function withUrl(array $c): array
    {
        $slug = $c['slug'] ?? null;
        $type = $c['type'] ?? null;

        if (! empty($slug) && in_array($type, self::TYPES, true)) {
            $c['url'] = url('/graph-explorer/'.$type.'/'.ltrim((string) $slug, '/'));
        } else {
            $c['url'] = null;
        }

        return $c;
    }

    /**
     * The machine (linked-data) representation URL for this entity - the
     * /id/{slug}, /id/actor/{slug} or /id/term/{slug} document. Built from the
     * existing named route so the explorer and the data endpoints stay aligned.
     *
     * @param  array<string,mixed>  $node
     */
    protected function machineUrl(array $node): ?string
    {
        $slug = $node['slug'] ?? null;
        if (empty($slug)) {
            return null;
        }

        $route = $node['machine_route'] ?? null;
        if ($route && \Illuminate\Support\Facades\Route::has($route)) {
            try {
                return route($route, ['slug' => $slug]);
            } catch (\Throwable $e) {
                // fall through to the literal builder
            }
        }

        // Literal fallback mirrors the entity route shapes.
        return match ($node['type'] ?? '') {
            'actor' => url('/id/actor/'.ltrim((string) $slug, '/')),
            'term' => url('/id/term/'.ltrim((string) $slug, '/')),
            default => url('/id/'.ltrim((string) $slug, '/')),
        };
    }

    /**
     * The canonical human page for this entity: the record page (/{slug}), the
     * actor authority page (/actor/{slug}), or - for a term, which has no
     * standalone authority page - the GLAM browse filtered by that term.
     *
     * @param  array<string,mixed>  $node
     */
    protected function authorityUrl(array $node): ?string
    {
        $type = $node['type'] ?? '';
        $slug = $node['slug'] ?? null;

        if ($type === 'record' && ! empty($slug)) {
            return url('/'.ltrim((string) $slug, '/'));
        }

        if ($type === 'actor' && ! empty($slug)) {
            return url('/actor/'.ltrim((string) $slug, '/'));
        }

        if ($type === 'term' && ! empty($node['authority_browse'])) {
            $b = $node['authority_browse'];
            $param = (string) ($b['param'] ?? 'subject');
            $id = (int) ($b['id'] ?? 0);
            if ($id > 0) {
                return url('/glam/browse').'?'.$param.'='.$id;
            }
        }

        return null;
    }

    /**
     * A clean themed 404 page. Never a 500; never leaks why (unknown vs draft).
     */
    protected function notFound(): Response
    {
        return response()->view('ahg-api::graph-explorer.not-found', [], 404);
    }
}
