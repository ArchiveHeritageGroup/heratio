<?php

/**
 * FederationGovernance - the F2/T2 enforcement helper (heratio#1317, the FINAL
 * phase of epic #1313 "federation backbone hardening").
 *
 * F2 (#1315) STORED per-peer governance on the federation_peer table
 * (federation_enabled, trust_level, rate_limit_seconds, allowed_entity_types)
 * and FederationGovernanceController (UNLOCKED) edits them. T1 (#1316) stamped
 * every cross-peer node/row with source_peer.verified (Ed25519 TOFU). What was
 * MISSING was enforcement: the stored governance was never consulted at query
 * time. This helper closes that gap so the federation layer becomes
 * "verifiable by construction" - it enforces what F2 stored and T1 verified.
 *
 * Two responsibilities, both fail-soft and additive:
 *
 *   1. SURFACE GATE (per peer). Given the surface being queried ('graph' |
 *      'endangered' | 'search') and a peer's base_url, decide whether the peer
 *      may be queried. A peer is allowed when its federation_peer row has
 *      federation_enabled = 1 AND its allowed_entity_types either is null/empty
 *      (= "all advertised surfaces allowed", see decision below) OR contains
 *      the surface. A peer with federation_enabled = 0, or whose
 *      allowed_entity_types is a non-empty list NOT containing the surface, is
 *      SKIPPED (the caller records a clear note - never silent).
 *
 *      DEFAULT DECISION (null / empty allowed_entity_types => ALL surfaces).
 *      F2 added allowed_entity_types as a nullable column, so every pre-F2 row
 *      and every freshly-discovered peer starts NULL. Treating NULL as "no
 *      surfaces allowed" would silently break every existing federation the
 *      moment this code shipped (a hard regression with no operator action).
 *      The governance UI already documents "No surfaces ticked = all advertised
 *      surfaces allowed", so NULL/empty = ALL is also the least-surprising
 *      reading of the stored state. The per-peer federation_enabled flag (which
 *      itself defaults to 0 / opt-in) is the real gate; allowed_entity_types is
 *      a narrowing refinement ON a peer that is already enabled. So: NULL/empty
 *      = all; a non-empty list = exactly that subset. Documented in
 *      docs/reference/federation-trust.md.
 *
 *      The federation_member <-> federation_peer link. The live federation
 *      services iterate federation_member (the lightweight peer registry: id /
 *      name / base_url / is_enabled / is_self), while F2 governance lives on
 *      federation_peer. The two tables share base_url, which is the natural and
 *      only stable join key, so governance is looked up by normalised base_url.
 *      A member with NO matching federation_peer row (governance not yet
 *      configured) is treated per $defaultEnabledWhenUnconfigured: the services
 *      pass TRUE for back-compat (a member already passed is_enabled=1 in its
 *      own table; absent governance must not retroactively disable it), and the
 *      absent row implies null allowed_entity_types => all surfaces.
 *
 *   2. REQUIRE-VERIFIED POLICY (per instance). A single ahg_settings key,
 *      federation_require_verified (default OFF for back-compat), read via the
 *      canonical AhgSettingsService. OFF = include unverified peer data, flagged
 *      (T1 already stamps source_peer.verified=false; the caller adds an
 *      aggregate "N results from unverified peers" notice). ON = the caller
 *      DROPS nodes/rows whose source_peer.verified is false and records how many
 *      were dropped. Local data (source_peer === null) is ALWAYS included.
 *      This helper exposes the policy read + the per-node predicate; the merge /
 *      drop / notice lives in each service so local data is never at risk.
 *
 * No new crypto, no new keypair, no new table - it reuses the F2 columns, the
 * T1 verified flag, and the existing settings mechanism. Never throws: every
 * lookup is guarded and degrades to the safe-for-back-compat answer.
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

namespace AhgFederation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FederationGovernance
{
    /**
     * The per-instance "require cryptographically-verified peers" policy key.
     * Lives in ahg_settings (setting_group = 'federation'). Default OFF so that
     * shipping this code does not change behaviour for any existing deployment.
     */
    public const REQUIRE_VERIFIED_KEY = 'federation_require_verified';

    /** ahg_settings group all federation governance settings live under. */
    public const SETTINGS_GROUP = 'federation';

    /**
     * Governance keyed by normalised base_url, lazily loaded once per request.
     * value: ['enabled' => bool, 'allowed' => array<int,string>|null].
     * 'allowed' === null means "no allowed_entity_types configured => all".
     *
     * @var array<string,array{enabled:bool,allowed:?array<int,string>}>|null
     */
    protected ?array $byBaseUrl = null;

    /** Cached require-verified policy verdict for this instance/request. */
    protected ?bool $requireVerified = null;

    // -----------------------------------------------------------------
    // Surface gate (per peer)
    // -----------------------------------------------------------------

    /**
     * May this peer be queried for $surface ('graph'|'endangered'|'search')?
     *
     * @param  string  $baseUrl  the federation_member base_url being queried
     * @param  string  $surface  the surface the caller is about to query
     * @param  bool  $defaultEnabledWhenUnconfigured  when no federation_peer row
     *         matches the base_url: TRUE => treat as enabled+all-surfaces
     *         (back-compat for the live services), FALSE => treat as not enabled.
     * @return array{allowed:bool,reason:string}  allowed=false carries a clear,
     *         operator-readable reason for the caller to surface in warnings.
     */
    public function peerAllowedFor(string $baseUrl, string $surface, bool $defaultEnabledWhenUnconfigured = true): array
    {
        $key = $this->normalise($baseUrl);
        $gov = $this->governance();

        if (! array_key_exists($key, $gov)) {
            // No governance row for this peer. Honour the caller's back-compat
            // default; an unconfigured peer implies "all surfaces" when enabled.
            if ($defaultEnabledWhenUnconfigured) {
                return ['allowed' => true, 'reason' => 'no governance row (default allow)'];
            }

            return ['allowed' => false, 'reason' => 'no federation governance configured for this peer'];
        }

        $row = $gov[$key];

        if (! $row['enabled']) {
            return ['allowed' => false, 'reason' => 'federation not enabled for this peer'];
        }

        // null / empty allowed_entity_types => ALL surfaces allowed (documented
        // default). A non-empty list is an explicit allow-list.
        $allowed = $row['allowed'];
        if ($allowed === null || $allowed === []) {
            return ['allowed' => true, 'reason' => 'all surfaces allowed'];
        }

        if (in_array($surface, $allowed, true)) {
            return ['allowed' => true, 'reason' => 'surface allowed'];
        }

        return [
            'allowed' => false,
            'reason'  => sprintf("surface '%s' not in this peer's allowed_entity_types", $surface),
        ];
    }

    // -----------------------------------------------------------------
    // Require-verified policy (per instance)
    // -----------------------------------------------------------------

    /**
     * The per-instance require-verified policy. true => EXCLUDE unverified peer
     * data from the merged result; false (default) => include it, flagged. Read
     * via the canonical AhgSettingsService; defaults OFF and never throws.
     */
    public function requireVerified(): bool
    {
        if ($this->requireVerified !== null) {
            return $this->requireVerified;
        }

        $value = false;
        try {
            if (class_exists(\AhgCore\Services\AhgSettingsService::class)) {
                $value = \AhgCore\Services\AhgSettingsService::getBool(self::REQUIRE_VERIFIED_KEY, false);
            }
        } catch (\Throwable $e) {
            $value = false;
        }

        return $this->requireVerified = $value;
    }

    /**
     * Should this peer node/row be DROPPED under the active require-verified
     * policy? Local data (source_peer === null) is never dropped. A peer node is
     * dropped only when the policy is ON and its source_peer.verified is false.
     *
     * @param  array<string,mixed>|null  $sourcePeer  the node/row's source_peer
     */
    public function shouldDropUnverified(?array $sourcePeer): bool
    {
        if ($sourcePeer === null) {
            return false; // local data is always included
        }
        if (! $this->requireVerified()) {
            return false; // policy OFF: keep, just flagged
        }

        return ($sourcePeer['verified'] ?? false) !== true;
    }

    // -----------------------------------------------------------------
    // Trust-dossier link (authenticity chain on borrowed records)
    // -----------------------------------------------------------------

    /**
     * The peer's trust-dossier URL for a record reference, so a consumer can
     * follow a borrowed record's lineage to the PEER's own authenticity chain.
     * Mirrors the peer's published surface ({base}/trust-dossier/{ref}). Returns
     * null for a blank base_url / ref so the caller simply omits the link.
     */
    public function trustDossierUrl(string $baseUrl, string $ref): ?string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $ref = trim($ref);
        if ($baseUrl === '' || $baseUrl === '-' || $ref === '') {
            return null;
        }

        return $baseUrl . '/trust-dossier/' . $this->encodeRef($ref);
    }

    /**
     * The peer's authenticity-report URL for a record reference (the companion
     * to the trust dossier). Same guards as trustDossierUrl().
     */
    public function authenticityUrl(string $baseUrl, string $ref): ?string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $ref = trim($ref);
        if ($baseUrl === '' || $baseUrl === '-' || $ref === '') {
            return null;
        }

        return $baseUrl . '/authenticity/' . $this->encodeRef($ref);
    }

    /**
     * URL-encode a record reference while PRESERVING path slashes - a peer ref
     * may be a multi-segment slug (the trust-dossier / authenticity routes
     * resolve fonds/series/item as one record), so each segment is encoded
     * individually rather than collapsing the slashes to %2F.
     */
    protected function encodeRef(string $ref): string
    {
        $segments = array_map('rawurlencode', explode('/', $ref));

        return implode('/', $segments);
    }

    // -----------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------

    /**
     * Load per-peer governance from federation_peer, keyed by normalised
     * base_url. Guarded against a missing table / missing F2 columns; on any
     * failure returns an empty map so peerAllowedFor() falls back to the
     * caller's back-compat default. Loaded once per instance.
     *
     * @return array<string,array{enabled:bool,allowed:?array<int,string>}>
     */
    protected function governance(): array
    {
        if ($this->byBaseUrl !== null) {
            return $this->byBaseUrl;
        }

        $map = [];
        try {
            if (Schema::hasTable('federation_peer')
                && Schema::hasColumn('federation_peer', 'federation_enabled')
                && Schema::hasColumn('federation_peer', 'allowed_entity_types')) {
                $rows = DB::table('federation_peer')
                    ->select('base_url', 'federation_enabled', 'allowed_entity_types')
                    ->whereNotNull('base_url')
                    ->where('base_url', '!=', '')
                    ->get();

                foreach ($rows as $r) {
                    $key = $this->normalise((string) $r->base_url);
                    if ($key === '') {
                        continue;
                    }
                    $map[$key] = [
                        'enabled' => ((int) $r->federation_enabled) === 1,
                        'allowed' => $this->decodeAllowed($r->allowed_entity_types),
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[federation-governance] governance load failed', ['error' => $e->getMessage()]);
            $map = [];
        }

        return $this->byBaseUrl = $map;
    }

    /**
     * Decode the allowed_entity_types JSON column into a clean list, or null
     * when it is null / blank / an empty array (= "all surfaces", per the
     * documented default).
     *
     * @return array<int,string>|null
     */
    protected function decodeAllowed($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);
        if (! is_array($decoded)) {
            return null;
        }
        $list = array_values(array_filter(array_map(
            static fn ($v): string => strtolower(trim((string) $v)),
            $decoded
        ), static fn (string $v): bool => $v !== ''));

        return $list === [] ? null : $list;
    }

    /**
     * Normalise a base_url for matching between federation_member and
     * federation_peer: trim, lower-case the scheme+host, drop a trailing slash.
     * Tolerant - a value that is not a URL is just trimmed + lower-cased so two
     * spellings of the same peer still match.
     */
    protected function normalise(string $baseUrl): string
    {
        $u = rtrim(trim($baseUrl), '/');
        if ($u === '') {
            return '';
        }

        return strtolower($u);
    }
}
