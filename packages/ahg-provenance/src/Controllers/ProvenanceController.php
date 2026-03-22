<?php

namespace AhgProvenance\Controllers;

use AhgProvenance\Services\ProvenanceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProvenanceController extends Controller
{
    public function __construct(
        protected ProvenanceService $service
    ) {}

    /**
     * Browse provenance records.
     */
    public function index()
    {
        $records = $this->service->browse();

        return view('ahg-provenance::index', compact('records'));
    }

    /**
     * View provenance for a specific IO.
     */
    public function view(string $slug)
    {
        $data = $this->service->getBySlug($slug);
        abort_unless($data['resource'], 404);

        return view('ahg-provenance::view', $data);
    }

    /**
     * Timeline view of provenance events.
     */
    public function timeline(string $slug)
    {
        $data = $this->service->getTimeline($slug);
        abort_unless($data['resource'], 404);

        return view('ahg-provenance::timeline', $data);
    }

    /**
     * Edit provenance for a specific IO.
     */
    public function edit(string $slug)
    {
        $data = $this->service->getBySlug($slug);
        abort_unless($data['resource'], 404);

        return view('ahg-provenance::edit', $data);
    }
}
