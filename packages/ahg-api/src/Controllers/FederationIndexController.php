<?php

/**
 * FederationIndexController - the PUBLIC, machine-discoverable federation peer
 * index at /open-data/federation (F2, heratio#1315; epic heratio#1313).
 *
 * Where /open-data/protocol declares the surfaces THIS instance exposes to
 * peers (the federation block, F1), this endpoint publishes the peers this
 * instance KNOWS and federates with, so an external agent can bootstrap peer
 * discovery from one fetch instead of hardcoding a peer list. It lists, for
 * each federation_enabled peer, its name, base_url, declared surfaces and the
 * outcome of this instance's last discovery probe (status / protocol_version /
 * maturity / last_probed_at).
 *
 *   GET /open-data/federation        - content-negotiated (browser -> HTML,
 *                                       everyone else -> JSON).
 *   GET /open-data/federation.json   - the JSON index, explicitly.
 *
 * Read-only, CORS-open, never 500s: zero peers (or a fresh install before the
 * governance columns exist) yields a valid empty index. It reads only the
 * federation_peer registry (the governance + discovery-cache columns added by
 * the F2 install_governance.sql) and never performs any peer HTTP itself - the
 * cached probe outcomes were written by ahg:federation-discover.
 *
 * Jurisdiction-neutral; every URL is built from url(), never a hardcoded host.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @author     Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class FederationIndexController extends Controller
{
    /** CORS preflight. */
    public function options(): Response
    {
        return $this->withCors(response('', 204));
    }

    /**
     * The federation peer index - content-negotiated.
     */
    public function index(Request $request, bool $forceJson = false): Response
    {
        $doc = $this->document();

        if (! $forceJson && $this->wantsHtml($request)) {
            return $this->withCors(response($this->html($doc), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8'));
        }

        return $this->withCors(response()->json($doc));
    }

    /**
     * The federation index payload. Lists this instance's federation_enabled
     * peers and their declared surfaces + last probe outcome. Always returns a
     * valid structure - an empty peers[] when there are none / before the
     * governance columns exist.
     *
     * @return array<string,mixed>
     */
    protected function document(): array
    {
        return [
            '@context' => [
                'schema' => 'https://schema.org/',
            ],
            '@type' => 'schema:Dataset',
            'protocol' => 'Federation Query Protocol',
            'protocol_version' => '1.0',
            'name' => (string) config('app.name', 'Heratio').' federation peer index',
            'description' => 'The peers this instance knows and federates with. Each entry lists the '
                .'peer base URL, the surfaces it advertises (graph / endangered / search) and the outcome '
                .'of this instance\'s last discovery probe. An external agent can bootstrap peer discovery '
                .'from this single document. Read-only, open data.',
            'self' => $this->base(),
            'protocol_descriptor' => $this->resolve('open-data.protocol', '/open-data/protocol'),
            'cors' => 'Access-Control-Allow-Origin: *',
            'authentication' => 'none (open data)',
            'peer_count' => count($this->peers()),
            'peers' => $this->peers(),
        ];
    }

    /**
     * The federation_enabled peers with their cached discovery state. Never
     * throws; empty on a fresh install (no table / no governance columns).
     *
     * @return array<int,array<string,mixed>>
     */
    protected function peers(): array
    {
        if (! $this->tableReady('federation_peer') || ! $this->columnReady('federation_peer', 'federation_enabled')) {
            return [];
        }

        try {
            $rows = DB::table('federation_peer')
                ->where('federation_enabled', 1)
                ->whereNotNull('base_url')
                ->where('base_url', '!=', '')
                ->where('base_url', '!=', '-')
                ->orderBy('name')
                ->limit(200)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $surfaces = $this->decodeArray($r->declared_surfaces ?? null);

            $out[] = array_filter([
                'name' => (string) $r->name,
                'base_url' => rtrim((string) $r->base_url, '/'),
                'protocol_descriptor' => rtrim((string) $r->base_url, '/').'/open-data/protocol',
                'surfaces' => $surfaces,
                'protocol_version' => $this->nullable($r->protocol_version ?? null),
                'trust_level' => $this->nullable($r->trust_level ?? null),
                'discovery_status' => $this->nullable($r->discovery_status ?? null),
                'maturity' => $this->nullable($r->maturity_grade ?? null),
                'last_probed_at' => $this->nullable($r->last_probed_at ?? null),
            ], static fn ($v) => $v !== null && $v !== []);
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // negotiation + rendering
    // -----------------------------------------------------------------

    protected function wantsHtml(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/ld+json')) {
            return false;
        }

        return str_contains($accept, 'text/html') || str_contains($accept, 'application/xhtml');
    }

    /**
     * @param  array<string,mixed>  $doc
     */
    protected function html(array $doc): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($doc['peers'] as $p) {
            $surfaces = $e(implode(', ', $p['surfaces'] ?? []) ?: '-');
            $rows .= '<tr><td><strong>'.$e($p['name']).'</strong></td>'
                .'<td><a href="'.$e($p['base_url']).'">'.$e($p['base_url']).'</a></td>'
                .'<td><code>'.$surfaces.'</code></td>'
                .'<td>'.$e($p['discovery_status'] ?? '-').'</td>'
                .'<td>'.$e($p['protocol_version'] ?? '-').'</td>'
                .'<td>'.$e($p['trust_level'] ?? '-').'</td>'
                .'<td>'.$e($p['last_probed_at'] ?? '-').'</td></tr>'."\n";
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7"><em>No federation-enabled peers yet.</em></td></tr>';
        }

        $title = $e($doc['name']);
        $jsonUrl = $e($this->base().'/open-data/federation.json');

        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<title>'.$title.'</title>'
            .'<style>body{font-family:system-ui,Arial,sans-serif;max-width:68rem;margin:2rem auto;padding:0 1rem;color:#1a1a1a}'
            .'h1{font-size:1.5rem}table{border-collapse:collapse;width:100%}'
            .'td,th{border:1px solid #ddd;padding:.5rem;vertical-align:top;text-align:left}'
            .'th{background:#f5f5f5}code{background:#f2f2f2;padding:.1rem .3rem;border-radius:3px}'
            .'.meta{color:#555;margin:.3rem 0 1.2rem}</style></head><body>'
            .'<h1>'.$title.'</h1>'
            .'<p>'.$e($doc['description']).'</p>'
            .'<p class="meta">Protocol: <strong>Federation Query Protocol '.$e($doc['protocol_version']).'</strong> &middot; '
            .'Machine view: <a href="'.$jsonUrl.'">'.$jsonUrl.'</a></p>'
            .'<table><thead><tr><th>Peer</th><th>Base URL</th><th>Surfaces</th><th>Status</th>'
            .'<th>Protocol</th><th>Trust</th><th>Last probed</th></tr></thead>'
            .'<tbody>'."\n".$rows.'</tbody></table>'
            .'</body></html>';
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    protected function resolve(string $routeName, ?string $fallbackPath = null): ?string
    {
        if (\Illuminate\Support\Facades\Route::has($routeName)) {
            try {
                return route($routeName);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return $fallbackPath !== null ? url($fallbackPath) : null;
    }

    protected function base(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * @return array<int,string>
     */
    protected function decodeArray($v): array
    {
        if (is_array($v)) {
            return array_values(array_filter(array_map('strval', $v)));
        }
        if (is_string($v) && $v !== '') {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('strval', $decoded)));
            }
        }

        return [];
    }

    protected function nullable($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === '' || $v === null) ? null : (string) $v;
    }

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function columnReady(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function withCors(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type');
        $response->headers->set('Vary', 'Accept');
        $response->headers->set('X-Open-Data', 'true');

        return $this->signFederation($response);
    }

    /**
     * Federation trust handshake (T1, heratio#1316): attach a DETACHED Ed25519
     * signature header over the EXACT response bytes so a peer can verify this
     * federation index came from this instance. Reuses the platform's one
     * Ed25519 key via ahg-federation's FederationSigner; never mutates the body
     * (back-compat). Fail-soft: unsigned when the signer is absent, never errors.
     */
    protected function signFederation(Response $response): Response
    {
        $signerClass = \AhgFederation\Services\FederationSigner::class;
        if (! class_exists($signerClass)) {
            return $response;
        }

        try {
            return app($signerClass)->attach($response);
        } catch (\Throwable $e) {
            return $response;
        }
    }
}
