<?php

/**
 * FederationController - Controller for Heratio
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



namespace AhgFederation\Controllers;

use AhgFederation\Services\FederatedSearchService;
use AhgFederation\Services\FederationProvenance;
use AhgFederation\Services\FederationService;
use AhgFederation\Services\HarvestService;
use AhgFederation\Services\HarvestException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FederationController extends Controller
{
    public function __construct(
        protected FederationService $service
    ) {}

    /**
     * Federation dashboard.
     */
    public function index()
    {
        $stats = $this->service->getStats();
        $peers = $this->service->getPeers();
        $recentSessions = $this->service->getRecentHarvestSessions();
        $recentSearches = $this->service->getRecentSearches();
        $vocabSyncConfigs = $this->service->getVocabSyncConfigs();

        return view('ahg-federation::index', compact(
            'stats', 'peers', 'recentSessions', 'recentSearches', 'vocabSyncConfigs',
        ));
    }

    /**
     * List all peers.
     */
    public function peers()
    {
        $peers = $this->service->getPeers();

        return view('ahg-federation::peers', compact('peers'));
    }

    /**
     * Edit/Add peer form.
     */
    public function editPeer(Request $request, ?int $id = null)
    {
        $peer = $id ? $this->service->getPeer($id) : null;
        $isNew = is_null($peer);

        return view('ahg-federation::edit-peer', compact('peer', 'isNew'));
    }

    /**
     * Harvest dashboard.
     */
    public function harvest(Request $request)
    {
        $peers = $this->service->getPeers();

        return view('ahg-federation::harvest', compact('peers'));
    }

    /**
     * Harvest log viewer.
     */
    public function log(Request $request)
    {
        $peerId = $request->query('peer_id');
        $logs = $this->service->getHarvestLogs($peerId);
        $peers = $this->service->getPeers();

        return view('ahg-federation::log', compact('logs', 'peers', 'peerId'));
    }

    /**
     * AJAX: Test peer connection.
     * POST /admin/federation/api/test-peer
     * POST /federation/peers/{id}/test
     */
    public function testPeer(Request $request, ?int $id = null)
    {
        $baseUrl = $request->input('base_url');

        // If ID provided but no base_url, look up the peer
        if ($id && empty($baseUrl)) {
            $peer = $this->service->getPeer($id);
            $baseUrl = $peer->base_url ?? null;
        }

        if (empty($baseUrl)) {
            return response()->json(['success' => false, 'error' => 'Base URL is required']);
        }

        try {
            // Test OAI-PMH endpoint with Identify verb
            $testUrl = rtrim($baseUrl, '/') . '?verb=Identify';
            $client = new \Illuminate\Http\Client\Factory();
            $response = $client->timeout(30)->get($testUrl);

            if ($response->successful()) {
                $body = $response->body();
                // Parse basic OAI-PMH identify response
                $identify = [];
                if (preg_match('/<repositoryName>(.*?)<\/repositoryName>/s', $body, $m)) {
                    $identify['repositoryName'] = trim($m[1]);
                }
                if (preg_match('/<baseURL>(.*?)<\/baseURL>/s', $body, $m)) {
                    $identify['baseURL'] = trim($m[1]);
                }
                if (preg_match('/<protocolVersion>(.*?)<\/protocolVersion>/s', $body, $m)) {
                    $identify['protocolVersion'] = trim($m[1]);
                }

                // Get metadata formats
                $formatsUrl = rtrim($baseUrl, '/') . '?verb=ListMetadataFormats';
                $fmtResponse = $client->timeout(15)->get($formatsUrl);
                $formats = [];
                if ($fmtResponse->successful()) {
                    preg_match_all('/<metadataPrefix>(.*?)<\/metadataPrefix>/s', $fmtResponse->body(), $fmtMatches);
                    $formats = $fmtMatches[1] ?? [];
                }

                return response()->json([
                    'success' => true,
                    'identify' => $identify,
                    'formats' => $formats,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => 'Peer returned HTTP ' . $response->status(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save peer (create or update).
     * POST /federation/peers/save
     */
    public function savePeer(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'required|url',
        ]);

        $data = [
            'name' => $request->input('name'),
            'base_url' => rtrim($request->input('base_url'), '/'),
            'oai_identifier' => $request->input('oai_identifier'),
            'api_key' => $request->input('api_key'),
            'description' => $request->input('description'),
            'contact_email' => $request->input('contact_email'),
            'default_metadata_prefix' => $request->input('default_metadata_prefix', 'oai_dc'),
            'default_set' => $request->input('default_set'),
            'harvest_interval_hours' => (int) $request->input('harvest_interval_hours', 24),
            'is_active' => $request->boolean('is_active') ? 1 : 0,
            'updated_at' => now(),
        ];

        $peerId = $request->input('peer_id');

        if ($peerId) {
            DB::table('federation_peer')->where('id', $peerId)->update($data);
            session()->flash('success', 'Peer updated successfully.');
        } else {
            $data['created_at'] = now();
            DB::table('federation_peer')->insert($data);
            session()->flash('success', 'Peer created successfully.');
        }

        return redirect()->route('federation.peers');
    }

    /**
     * AJAX: Run harvest for a peer.
     * POST /federation/harvest/run
     * POST /admin/federation/harvest/
     *
     * Wraps HarvestService so records actually persist to information_object.
     * Synchronous; for very large peers operators should prefer the
     * `php artisan ahg:federation-harvest --peer={id}` CLI path.
     */
    public function runHarvest(Request $request, HarvestService $harvestService)
    {
        $peerId = (int) $request->input('peer_id');
        $peer = $this->service->getPeer($peerId);

        if (!$peer) {
            return response()->json(['success' => false, 'error' => 'Peer not found']);
        }

        $sessionId = DB::table('federation_harvest_session')->insertGetId([
            'peer_id' => $peerId,
            'started_at' => now(),
            'status' => 'running',
            'metadata_prefix' => $request->input('metadata_prefix', $peer->default_metadata_prefix ?? 'oai_dc'),
            'harvest_from' => $request->input('from'),
            'harvest_until' => $request->input('until'),
            'harvest_set' => $request->input('set', $peer->default_set ?? null),
            'is_full_harvest' => $request->boolean('full_harvest') ? 1 : 0,
            'initiated_by' => auth()->id(),
        ]);

        try {
            $options = array_filter([
                'metadataPrefix' => $request->input('metadata_prefix'),
                'from' => $request->input('from'),
                'until' => $request->input('until'),
                'set' => $request->input('set'),
                'fullHarvest' => $request->boolean('full_harvest'),
            ], fn ($v) => $v !== null && $v !== false && $v !== '');

            $result = $harvestService->harvestPeer($peerId, $options);

            DB::table('federation_harvest_session')
                ->where('id', $sessionId)
                ->update([
                    'completed_at' => now(),
                    'status' => $result->isSuccessful() ? 'completed' : 'partial',
                    'records_total' => $result->stats['total'],
                    'records_created' => $result->stats['created'],
                    'records_updated' => $result->stats['updated'],
                    'records_deleted' => $result->stats['deleted'],
                    'records_skipped' => $result->stats['skipped'],
                    'records_errors' => $result->stats['errors'],
                    'error_message' => $result->stats['errors'] > 0
                        ? implode("\n", array_slice($result->stats['errorMessages'], 0, 20))
                        : null,
                ]);

            return response()->json([
                'success' => $result->isSuccessful(),
                'result' => $result->stats,
                'summary' => $result->getSummary(),
                'sessionId' => $sessionId,
            ]);
        } catch (HarvestException | \Throwable $e) {
            DB::table('federation_harvest_session')
                ->where('id', $sessionId)
                ->update([
                    'completed_at' => now(),
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);

            Log::warning('[federation] runHarvest failed', [
                'peer_id' => $peerId,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'sessionId' => $sessionId,
            ]);
        }
    }

    /**
     * Run a federated search across active peers.
     *
     * GET /federation/search?q=...
     *
     * Anonymous-readable so it can be embedded in public browse pages, but
     * gated by EnsureFederationEnabled at the route level.
     */
    public function search(Request $request, FederatedSearchService $searchService)
    {
        $query = trim((string) $request->query('q', ''));
        if ($query === '') {
            return response()->json(['success' => false, 'error' => 'Query parameter q is required'], 400);
        }

        $options = array_filter([
            'limit' => $request->query('limit') !== null ? (int) $request->query('limit') : null,
            'type' => $request->query('type'),
            'repository' => $request->query('repository'),
            'dateFrom' => $request->query('dateFrom'),
            'dateTo' => $request->query('dateTo'),
            'cache' => $request->query('cache') !== '0',
        ], fn ($v) => $v !== null);

        try {
            $result = $searchService->search($query, $options);
            return response()->json($result->toJsonResponse());
        } catch (\Throwable $e) {
            Log::warning('[federation] federated search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Configure search settings for a peer.
     *
     * POST /federation/peers/{id}/search-config
     */
    public function savePeerSearchConfig(Request $request, FederatedSearchService $searchService, int $id)
    {
        $request->validate([
            'search_api_url' => 'nullable|url',
            'search_api_key' => 'nullable|string|max:255',
            'search_enabled' => 'nullable|boolean',
            'search_timeout_ms' => 'nullable|integer|min:500|max:60000',
            'search_max_results' => 'nullable|integer|min:1|max:1000',
            'search_priority' => 'nullable|integer|min:0|max:1000',
        ]);

        $settings = $request->only([
            'search_api_url', 'search_api_key', 'search_enabled',
            'search_timeout_ms', 'search_max_results', 'search_priority',
        ]);

        $ok = $searchService->configurePeerSearch($id, $settings);

        return response()->json(['success' => (bool) $ok]);
    }

    /**
     * Get federation provenance for an information object.
     *
     * GET /federation/provenance/{objectId}.json
     *
     * Returns 200 with provenance details when the IO was harvested, or
     * 200 with {federated:false} when it's a local record.
     */
    public function provenance(int $objectId, FederationProvenance $provenance)
    {
        $info = $provenance->getProvenance($objectId);
        if (!$info) {
            return response()->json(['federated' => false, 'objectId' => $objectId]);
        }

        $info['federated'] = true;
        $info['objectId'] = $objectId;
        $info['harvestHistory'] = $provenance->getHarvestHistory($objectId);

        return response()->json($info);
    }
}
