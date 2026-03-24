<?php

namespace AhgFederation\Controllers;

use AhgFederation\Services\FederationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

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

        return view('ahg-federation::index', compact('stats', 'peers'));
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
     */
    public function runHarvest(Request $request)
    {
        $peerId = $request->input('peer_id');
        $peer = $this->service->getPeer((int) $peerId);

        if (!$peer) {
            return response()->json(['success' => false, 'error' => 'Peer not found']);
        }

        try {
            // Create harvest session
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

            // Attempt OAI-PMH harvest
            $baseUrl = rtrim($peer->base_url, '/');
            $params = [
                'verb' => 'ListRecords',
                'metadataPrefix' => $request->input('metadata_prefix', $peer->default_metadata_prefix ?? 'oai_dc'),
            ];

            if ($request->input('from')) {
                $params['from'] = $request->input('from');
            }
            if ($request->input('until')) {
                $params['until'] = $request->input('until');
            }
            if ($request->input('set', $peer->default_set ?? null)) {
                $params['set'] = $request->input('set', $peer->default_set);
            }

            $harvestUrl = $baseUrl . '?' . http_build_query($params);
            $client = new \Illuminate\Http\Client\Factory();
            $response = $client->timeout(120)->get($harvestUrl);

            $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];

            if ($response->successful()) {
                // Count records in response
                preg_match_all('/<record>/s', $response->body(), $records);
                $stats['total'] = count($records[0] ?? []);
            }

            // Update session
            DB::table('federation_harvest_session')
                ->where('id', $sessionId)
                ->update([
                    'completed_at' => now(),
                    'status' => 'completed',
                    'records_total' => $stats['total'],
                    'records_created' => $stats['created'],
                    'records_updated' => $stats['updated'],
                    'records_deleted' => $stats['deleted'],
                    'records_skipped' => $stats['skipped'],
                    'records_errors' => $stats['errors'],
                ]);

            return response()->json([
                'success' => true,
                'result' => $stats,
                'sessionId' => $sessionId,
            ]);
        } catch (\Exception $e) {
            if (isset($sessionId)) {
                DB::table('federation_harvest_session')
                    ->where('id', $sessionId)
                    ->update([
                        'completed_at' => now(),
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
