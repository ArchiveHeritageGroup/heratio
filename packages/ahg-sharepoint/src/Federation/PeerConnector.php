<?php

/**
 * PeerConnector (ahg-sharepoint copy) — contract for a federation peer that can
 * be queried at search time.
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
 *
 * -----------------------------------------------------------------------------
 * INERT SCAFFOLD — issue #1221, step 1 (non-destructive).
 *
 * This is a copy of the GENERAL federation contract, placed here as the future
 * canonical home for the SharePoint-specific connector once the cutover in
 * MIGRATION.md is executed. It is NOT registered anywhere and is NOT loaded at
 * boot. The live federation contract remains the one in
 * `packages/ahg-federation/.../Connectors/PeerConnector.php` (general
 * federation); this copy exists ONLY so SharePointGraphConnector in this
 * package has a contract to implement without reaching into ahg-federation.
 *
 * At cutover, the SharePoint connector will implement EITHER this local
 * interface OR the ahg-federation interface (see MIGRATION.md, "Contract
 * placement decision"). Whichever is chosen, there is exactly one interface in
 * the live wiring — never two competing copies registered at once.
 * -----------------------------------------------------------------------------
 */

namespace AhgSharePoint\Federation;

interface PeerConnector
{
    /**
     * Bind the connector to its federation_peer row. Called once after
     * construction by the dispatcher; subsequent search() calls re-use the
     * same instance.
     *
     * @param object $peerRow the federation_peer DB row (stdClass with config
     *                        JSON already decoded into ->config when convenient)
     */
    public function bind(object $peerRow): void;

    /**
     * Run a federated search. The return value is a list of PeerSearchResult
     * value objects in relevance order (best first).
     *
     * @param string $query   free-text query as the user typed it
     * @param array  $filters dict of optional filters:
     *                        - 'date_range' => ['from' => '2024-01-01', 'to' => '2024-12-31']
     *                        - 'source'     => one of: 'archive', 'active', null
     *                        - 'culture'    => 'en' | 'fr' | ...
     * @param int    $limit   per-peer hard cap on results
     *
     * @return PeerSearchResult[]
     */
    public function search(string $query, array $filters = [], int $limit = 50): array;

    /**
     * Capability probe. Used by the dispatcher to decide whether to skip a
     * connector for filters it does not understand.
     *
     * Valid capability names:
     *   'full_text_search'  — accepts the $query string
     *   'metadata_filter'   — accepts any of the $filters keys
     *   'date_range'        — accepts a date_range filter
     *   'acl_user_scope'    — applies ACL relative to a specific user
     */
    public function supportsCapability(string $capability): bool;

    /**
     * Connector-self-describing key. MUST match the federation_peer.peer_type
     * value the connector is registered against.
     */
    public function peerTypeKey(): string;
}
