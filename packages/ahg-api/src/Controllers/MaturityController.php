<?php

/**
 * MaturityController - the Open Data Maturity scorecard.
 *
 * Next slice of north-star #1204 ("the open memory protocol"). Where
 * ProtocolController publishes the machine-discoverable INDEX of every open
 * surface, this controller GRADES that offering against Tim Berners-Lee's
 * 5-star Open Data deployment scheme (https://5stardata.info/), and shows the
 * concrete EVIDENCE for each star - the real surfaces that prove it - so the
 * claim "this platform is 5-star open data" is self-verifying rather than
 * asserted.
 *
 *   GET /open-data/maturity        - content-negotiated:
 *                                    text/html (a browser) -> a human scorecard,
 *                                    everyone else (and ?format=json) -> JSON.
 *   GET /open-data/maturity.json   - the JSON scorecard, explicitly (CORS-open).
 *
 * The 5 stars and the evidence cited for each:
 *   - 1 star  : data on the web under an open licence
 *               -> the CC-BY-4.0 licence + the public open-data surfaces.
 *   - 2 stars : machine-readable structured data
 *               -> the CSV / JSON bulk dataset dumps + the schema.org Dataset.
 *   - 3 stars : non-proprietary open format
 *               -> the JSON-LD / Turtle / RDF-XML graph + CIDOC-CRM dump.
 *   - 4 stars : URIs to denote things
 *               -> the dereferenceable /id/{record|actor|term} entity URIs.
 *   - 5 stars : linked to other data
 *               -> the VoID discovery doc, RiC / CIDOC-CRM vocabularies and the
 *                  sameAs / seeAlso outbound links carried in the entity graphs.
 *
 * Each star resolves its evidence from ProtocolController::surfaces() (the ONE
 * canonical surface list, so the scorecard can never drift from what is really
 * served) and is marked achieved=true ONLY when at least one of its evidence
 * surfaces actually resolves to a registered route. On a slimmer install where a
 * star's evidence surface is absent, that star is honestly reported as not
 * achieved rather than asserted - the scorecard never claims a capability the
 * deployment does not expose.
 *
 * Honest + safe: read-only; performs NO DB access and NO AI calls. It only
 * resolves route URLs and inspects the protocol surface list, so it can never
 * 500 over data. Permissive open-data CORS. Jurisdiction-neutral; every URI is
 * built from url() / route(), never a hardcoded host.
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

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class MaturityController extends Controller
{
    private const LICENSE = 'https://creativecommons.org/licenses/by/4.0/';

    private const MODEL = 'https://5stardata.info/';

    /**
     * Each star, and the ProtocolController surface ids whose presence is the
     * evidence that the star is achieved. The first matching surface (or several)
     * is cited; a star is achieved when at least one of its evidence surfaces
     * resolves to a real, registered surface.
     *
     * @var array<int,array<string,mixed>>
     */
    private const STARS = [
        [
            'stars' => 1,
            'symbol' => 'OL',
            'name' => 'Open licence',
            'requirement' => 'Make your data available on the web (in whatever format) under an open licence.',
            'evidenceSurfaceIds' => ['discovery', 'dataset-schema-org', 'graph-dataset'],
            'note' => 'Every surface is public, requires no authentication, and is released as open data under CC-BY-4.0.',
        ],
        [
            'stars' => 2,
            'symbol' => 'RE',
            'name' => 'Machine-readable structured data',
            'requirement' => 'Make it available as structured, machine-readable data (e.g. a spreadsheet instead of a scanned image).',
            'evidenceSurfaceIds' => ['dataset-csv', 'dataset-jsonld', 'dataset-schema-org'],
            'note' => 'The whole published catalogue is downloadable as structured CSV and as a JSON dataset, one row / node per record.',
        ],
        [
            'stars' => 3,
            'symbol' => 'OF',
            'name' => 'Non-proprietary open format',
            'requirement' => 'Use a non-proprietary open format (e.g. CSV / JSON-LD / RDF instead of a vendor format).',
            'evidenceSurfaceIds' => ['graph-entity', 'graph-dataset', 'dataset-cidoc-crm', 'context'],
            'note' => 'The graph is served in open W3C / ISO formats: JSON-LD, Turtle and RDF-XML, plus a combined CIDOC-CRM (ISO 21127) Turtle dump.',
        ],
        [
            'stars' => 4,
            'symbol' => 'URI',
            'name' => 'URIs to denote things',
            'requirement' => 'Use URIs to denote things, so that people can point at your data.',
            'evidenceSurfaceIds' => ['entity', 'entity-actor', 'entity-term'],
            'note' => 'Every record, actor and term has a stable, dereferenceable /id/... URI that content-negotiates RDF for machines and a human page for browsers.',
        ],
        [
            'stars' => 5,
            'symbol' => 'LD',
            'name' => 'Linked to other data',
            'requirement' => 'Link your data to other data to provide context.',
            'evidenceSurfaceIds' => ['discovery', 'graph-entity', 'dataset-cidoc-crm', 'entity'],
            'note' => 'Entity graphs carry outbound sameAs / seeAlso links and use shared vocabularies (Records in Contexts and CIDOC-CRM); the VoID discovery document declares the dataset linkset.',
        ],
    ];

    /**
     * OPTIONS preflight for the maturity endpoint.
     */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * GET /open-data/maturity  (and /open-data/maturity.json)
     *
     * A browser (text/html) gets the human scorecard; everyone else (and
     * ?format=json) gets the JSON document. The .json route always forces JSON.
     */
    public function index(Request $request, bool $forceJson = false): Response
    {
        if (! $forceJson && $this->wantsHtml($request)) {
            return $this->withCors(response($this->html(), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]));
        }

        $body = json_encode(
            $this->document(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return $this->withCors(response($body, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]));
    }

    /**
     * Build the maturity scorecard as a neutral PHP array. Never touches the DB;
     * only inspects the protocol surface list and resolves route URLs.
     *
     * @return array<string,mixed>
     */
    protected function document(): array
    {
        $byId = $this->surfacesById();

        $stars = [];
        $achievedCount = 0;
        foreach (self::STARS as $spec) {
            $evidence = [];
            foreach ((array) $spec['evidenceSurfaceIds'] as $id) {
                if (! isset($byId[$id])) {
                    continue;
                }
                $surface = $byId[$id];
                $url = $surface['url'] ?? ($surface['urlTemplate'] ?? null);
                if (empty($url)) {
                    continue;
                }
                $evidence[] = [
                    'surface' => (string) $id,
                    'title' => (string) ($surface['title'] ?? $id),
                    'url' => $url,
                    'isTemplate' => empty($surface['url']),
                    'mediaTypes' => is_array($surface['mediaTypes'] ?? null) ? $surface['mediaTypes'] : [],
                ];
            }

            $achieved = $evidence !== [];
            if ($achieved) {
                $achievedCount++;
            }

            $stars[] = [
                '@type' => 'schema:Rating',
                'stars' => $spec['stars'],
                'symbol' => $spec['symbol'],
                'name' => $spec['name'],
                'requirement' => $spec['requirement'],
                'achieved' => $achieved,
                'note' => $spec['note'],
                'evidence' => $evidence,
            ];
        }

        return [
            '@context' => [
                'schema' => 'https://schema.org/',
                'dcterms' => 'http://purl.org/dc/terms/',
            ],
            '@type' => 'schema:Rating',
            'name' => (string) config('app.name', 'Heratio').' open data maturity',
            'description' => 'A self-verifying scorecard of this platform\'s open-data offering against the '
                .'5-star Open Data deployment scheme (Tim Berners-Lee). Each star lists the concrete, '
                .'live surfaces that prove it; a star is only marked achieved when its evidence surface is '
                .'actually served by this deployment. All surfaces are read-only, expose published records '
                .'only, and are open data under CC-BY-4.0.',
            'model' => self::MODEL,
            'modelName' => '5-star Open Data deployment scheme',
            'license' => self::LICENSE,
            'bestRating' => 5,
            'worstRating' => 0,
            'ratingValue' => $achievedCount,
            'ratingScale' => $achievedCount.'/5',
            'stars' => $stars,
            'related' => [
                'protocol' => $this->resolve('open-data.protocol', '/open-data/protocol'),
                'catalog' => $this->resolve('open-data.catalog', '/data/catalog'),
                'hub' => $this->base().'/open-data',
            ],
            'cors' => 'Access-Control-Allow-Origin: *',
            'authentication' => 'none (open data)',
        ];
    }

    /**
     * The canonical surface list from ProtocolController, indexed by id for
     * stable evidence lookup. Resolved defensively: if the protocol controller is
     * unavailable for any reason the scorecard degrades to "no evidence" (every
     * star honestly not-achieved) rather than throwing.
     *
     * @return array<string,array<string,mixed>>
     */
    protected function surfacesById(): array
    {
        try {
            $surfaces = app(ProtocolController::class)->surfaces();
        } catch (\Throwable $e) {
            return [];
        }

        $byId = [];
        foreach ($surfaces as $surface) {
            if (! empty($surface['id'])) {
                $byId[(string) $surface['id']] = $surface;
            }
        }

        return $byId;
    }

    /**
     * Resolve a named route to its absolute URL, falling back to a literal root
     * path when the route is not registered. Returns null when neither is
     * available (so the link can be dropped, never dead).
     */
    protected function resolve(string $routeName, ?string $fallbackPath = null): ?string
    {
        if (Route::has($routeName)) {
            try {
                return route($routeName);
            } catch (\Throwable $e) {
                // fall through to the literal path
            }
        }

        return $fallbackPath !== null ? url($fallbackPath) : null;
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * Whether the client prefers HTML (a browser). curl's default catch-all
     * Accept does NOT count as HTML, so a bare curl gets JSON.
     */
    protected function wantsHtml(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));

        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json')) {
            return false;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml');
    }

    /**
     * Render a small, dependency-free human scorecard from the same document()
     * array so the two views can never drift. No Blade (this package has no
     * public layout), just escaped inline HTML.
     */
    protected function html(): string
    {
        $doc = $this->document();
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $cards = '';
        foreach ($doc['stars'] as $star) {
            $filled = str_repeat('&#9733;', (int) $star['stars']);
            $empty = str_repeat('&#9734;', 5 - (int) $star['stars']);
            $badge = $star['achieved']
                ? '<span class="ok">achieved</span>'
                : '<span class="no">not available on this install</span>';

            $items = '';
            foreach ($star['evidence'] as $ev) {
                $link = $ev['isTemplate']
                    ? '<code>'.$e($ev['url']).'</code>'
                    : '<a href="'.$e($ev['url']).'">'.$e($ev['url']).'</a>';
                $types = $e(implode(', ', $ev['mediaTypes'] ?? []));
                $items .= '<li><strong>'.$e($ev['title']).'</strong> &mdash; '.$link
                    .($types !== '' ? ' <code>'.$types.'</code>' : '').'</li>';
            }
            if ($items === '') {
                $items = '<li><em>No evidence surface is registered on this deployment.</em></li>';
            }

            $cards .= '<section class="star'.($star['achieved'] ? '' : ' dim').'">'
                .'<h2><span class="rate">'.$filled.$empty.'</span> '.$e($star['stars']).'-star: '.$e($star['name']).' '.$badge.'</h2>'
                .'<p class="req">'.$e($star['requirement']).'</p>'
                .'<p class="note">'.$e($star['note']).'</p>'
                .'<p class="evlabel">Evidence on this platform:</p><ul>'.$items.'</ul>'
                .'</section>'."\n";
        }

        $title = $e($doc['name']);
        $desc = $e($doc['description']);
        $jsonUrl = $e($this->base().'/open-data/maturity.json');
        $score = $e($doc['ratingScale']);
        $protocol = $e((string) ($doc['related']['protocol'] ?? ''));
        $catalog = $e((string) ($doc['related']['catalog'] ?? ''));

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>body{font-family:system-ui,Arial,sans-serif;max-width:62rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a}'
            .'h1{font-size:1.6rem;margin-bottom:.2rem}h2{font-size:1.15rem;margin:.2rem 0}'
            .'.score{font-size:2.4rem;font-weight:700;color:#1a7a3a}.rate{color:#d39a00;letter-spacing:1px}'
            .'.star{border:1px solid #ddd;border-radius:8px;padding:1rem 1.2rem;margin:1rem 0}'
            .'.star.dim{opacity:.6;background:#fafafa}'
            .'.req{font-weight:600;color:#333}.note{color:#444}.evlabel{margin:.6rem 0 .2rem;font-weight:600}'
            .'ul{margin:.2rem 0 0}li{margin:.2rem 0}'
            .'code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px}'
            .'.ok{font-size:.7rem;background:#e3f6e8;color:#1a7a3a;padding:.15rem .5rem;border-radius:10px;vertical-align:middle}'
            .'.no{font-size:.7rem;background:#fbe9e9;color:#a33;padding:.15rem .5rem;border-radius:10px;vertical-align:middle}'
            .'.meta{color:#555;margin:.3rem 0 1.2rem}</style></head><body>'
            .'<h1>'.$title.'</h1>'
            .'<p><span class="score">'.$score.'</span> against the '
            .'<a href="'.$e($doc['model']).'">5-star Open Data</a> deployment scheme.</p>'
            .'<p>'.$desc.'</p>'
            .'<p class="meta">Licence: <a href="'.$e($doc['license']).'">CC-BY-4.0</a> &middot; '
            .'Authentication: '.$e($doc['authentication']).' &middot; '
            .'Machine view: <a href="'.$jsonUrl.'">'.$jsonUrl.'</a></p>'
            .$cards
            .'<p class="meta">See also: <a href="'.$protocol.'">the open-data protocol index</a> &middot; '
            .'<a href="'.$catalog.'">the DCAT data catalogue</a>.</p>'
            .'</body></html>';
    }

    /**
     * Apply permissive open-data CORS headers. This document is intentionally
     * world-readable (open data), so any origin may fetch it.
     */
    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $response;
    }
}
