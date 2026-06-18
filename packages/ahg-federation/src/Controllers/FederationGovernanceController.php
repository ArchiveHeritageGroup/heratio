<?php

/**
 * FederationGovernanceController - admin status + governance surface for the
 * Federation Query Protocol peer registry (F2, heratio#1315).
 *
 *   GET  /federation/governance              read view: each peer's discovery
 *                                             status (reachable / version /
 *                                             surfaces / last probed) + its
 *                                             governance (federation_enabled /
 *                                             trust_level / rate_limit).
 *   POST /federation/governance/{id}         save federation_enabled +
 *                                             trust_level + rate_limit_seconds +
 *                                             allowed_entity_types for one peer.
 *   POST /federation/governance/discover     run the discovery crawl now.
 *
 * Fresh, UNLOCKED controller - deliberately separate from the LOCKED F3
 * FederationController / edit-peer.blade.php. It reads / writes only the F2
 * governance + discovery-cache columns on federation_peer (added by
 * install_governance.sql); it never touches the connector / harvest config that
 * the locked peer editor owns. Trust levels + discovery statuses come from the
 * Dropdown Manager (ahg_dropdown), never hardcoded / ENUM.
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

namespace AhgFederation\Controllers;

use AhgFederation\Services\PeerDiscoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FederationGovernanceController extends Controller
{
    /** Read view: peer discovery status + governance. */
    public function index()
    {
        $peers = collect();
        $hasGovernance = false;
        $hasTrust = false;

        try {
            if (Schema::hasTable('federation_peer')) {
                $hasGovernance = Schema::hasColumn('federation_peer', 'federation_enabled');
                $hasTrust = Schema::hasColumn('federation_peer', 'pinned_key_fingerprint');
                $peers = DB::table('federation_peer')
                    ->orderByDesc('id')
                    ->limit(500)
                    ->get()
                    ->map(function ($p) {
                        $p->declared_surfaces_list = $this->decodeArray($p->declared_surfaces ?? null);
                        $p->allowed_entity_types_list = $this->decodeArray($p->allowed_entity_types ?? null);

                        return $p;
                    });
            }
        } catch (\Throwable $e) {
            $peers = collect();
        }

        return view('ahg-federation::governance', [
            'peers' => $peers,
            'hasGovernance' => $hasGovernance,
            'hasTrust' => $hasTrust,
            'trustLevels' => $this->dropdown('federation_trust_level'),
            'surfaces' => PeerDiscoveryService::KNOWN_SURFACES,
            // T2 (#1317): the per-instance require-verified policy, read via the
            // canonical settings helper so the toggle reflects what the live
            // federation services enforce. Default OFF (back-compat).
            'requireVerified' => $this->requireVerifiedSetting(),
        ]);
    }

    /**
     * Save the per-instance trust-threshold policy (T2 #1317):
     * federation_require_verified. When ON, the live federation services
     * (FederationGraphService / FederatedEndangeredService) DROP peer nodes/rows
     * whose cryptographic verification failed; when OFF they are included but
     * flagged. Stored in ahg_settings (group 'federation') via the canonical
     * settings mechanism - never a hardcoded constant. Never throws to the user.
     */
    public function savePolicy(Request $request): RedirectResponse
    {
        $request->validate([
            'federation_require_verified' => ['nullable', 'boolean'],
        ]);

        try {
            \AhgCore\Services\AhgSettingsService::set(
                \AhgFederation\Services\FederationGovernance::REQUIRE_VERIFIED_KEY,
                $request->boolean('federation_require_verified') ? '1' : '0',
                \AhgFederation\Services\FederationGovernance::SETTINGS_GROUP
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save the trust policy: '.$e->getMessage());
        }

        return back()->with('success', $request->boolean('federation_require_verified')
            ? 'Trust policy saved: only cryptographically-verified peer data will be included.'
            : 'Trust policy saved: unverified peer data is included but flagged.');
    }

    /**
     * Read the per-instance require-verified policy via the canonical settings
     * helper. Default OFF; never throws.
     */
    protected function requireVerifiedSetting(): bool
    {
        try {
            if (class_exists(\AhgCore\Services\AhgSettingsService::class)) {
                return \AhgCore\Services\AhgSettingsService::getBool(
                    \AhgFederation\Services\FederationGovernance::REQUIRE_VERIFIED_KEY,
                    false
                );
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return false;
    }

    /** Save per-peer governance. */
    public function save(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'federation_enabled' => ['nullable', 'boolean'],
            'trust_level' => ['nullable', 'string', 'max:32'],
            'rate_limit_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'allowed_entity_types' => ['nullable', 'array'],
            'allowed_entity_types.*' => ['string', 'in:graph,endangered,search,exhibition'],
        ]);

        try {
            if (! Schema::hasColumn('federation_peer', 'federation_enabled')) {
                return back()->with('error', 'Federation governance columns are not installed yet.');
            }

            $allowed = $validated['allowed_entity_types'] ?? [];

            DB::table('federation_peer')->where('id', $id)->update([
                'federation_enabled' => $request->boolean('federation_enabled') ? 1 : 0,
                'trust_level' => $validated['trust_level'] ?: 'basic',
                'rate_limit_seconds' => $validated['rate_limit_seconds'] ?? null,
                'allowed_entity_types' => ! empty($allowed) ? json_encode(array_values($allowed)) : null,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not save governance: '.$e->getMessage());
        }

        return back()->with('success', 'Peer governance updated.');
    }

    /**
     * Clear a peer's pinned key (federation trust handshake, T1 #1316). The next
     * successful signature verify re-pins the peer's key Trust-On-First-Use, so
     * this is the deliberate "the peer rotated its key, trust the new one"
     * control after a key_mismatch. Idempotent; never throws to the user.
     */
    public function clearPin(int $id): RedirectResponse
    {
        try {
            if (! Schema::hasColumn('federation_peer', 'pinned_key_fingerprint')) {
                return back()->with('error', 'Federation trust columns are not installed yet.');
            }

            DB::table('federation_peer')->where('id', $id)->update([
                'pinned_key_fingerprint' => null,
                'key_pinned_at' => null,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not clear the key pin: '.$e->getMessage());
        }

        return back()->with('success', 'Key pin cleared. The next verified fetch will re-pin the peer key.');
    }

    /** Run the discovery crawl now and report the summary. */
    public function discover(PeerDiscoveryService $service): RedirectResponse
    {
        try {
            $summary = $service->discoverAll(false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Discovery failed: '.$e->getMessage());
        }

        if (($summary['probed'] ?? 0) === 0) {
            return back()->with('success', 'No discoverable peers (a peer needs an http(s) base_url).');
        }

        return back()->with('success', sprintf(
            'Probed %d peer(s): %d ok, %d unreachable, %d non-compliant.',
            $summary['probed'],
            $summary['ok'] ?? 0,
            $summary['unreachable'] ?? 0,
            $summary['non_compliant'] ?? 0
        ));
    }

    /**
     * Dropdown values for a taxonomy, falling back to an empty list if the
     * table / rows are absent (the view supplies a sensible default).
     *
     * @return array<int,object>
     */
    protected function dropdown(string $taxonomy): array
    {
        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return [];
            }

            return DB::table('ahg_dropdown')
                ->where('taxonomy', $taxonomy)
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get(['code', 'label'])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
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
}
