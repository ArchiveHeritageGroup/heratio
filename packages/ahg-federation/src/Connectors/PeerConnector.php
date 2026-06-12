<?php

/**
 * PeerConnector — contract for a federation peer that can be queried at search time.
 *
 * Each registered peer type (oai_pmh, sharepoint_graph_search, atom_local, …) maps
 * to exactly one implementing class. The federation dispatcher loads the connector
 * by peer_type, hands it the active federation_peer row, and invokes search().
 *
 * Connectors are responsible for:
 *   • translating the generic query + filters into their native query language
 *   • enforcing their native ACL (Graph permissions, AtoM ACL, OAI white-list)
 *   • returning results in the common PeerSearchResult shape
 *
 * Connectors SHOULD honour the $limit argument as a hard cap. Connectors MAY
 * exceed the federation default 5-second timeout — the dispatcher applies the
 * outer timeout via Guzzle/cURL futures.
 *
 * @phase B
 */

namespace AhgFederation\Connectors;

interface PeerConnector
{
    /**
     * Bind the connector to its federation_peer row. Called once after construction
     * by the dispatcher; subsequent search() calls re-use the same instance.
     *
     * @param object $peerRow  the federation_peer DB row (stdClass with config JSON
     *                         already decoded into ->config_decoded if convenient)
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
     *                        - 'culture'    => 'en' | 'fr' | …
     * @param int    $limit   per-peer hard cap on results
     *
     * @return PeerSearchResult[]
     */
    public function search(string $query, array $filters = [], int $limit = 50): array;

    /**
     * Capability probe. Used by the dispatcher to decide whether to skip a
     * connector for filters it doesn't understand.
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
