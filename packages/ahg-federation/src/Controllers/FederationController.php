<?php

namespace AhgFederation\Controllers;

use AhgFederation\Services\FederationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
}
