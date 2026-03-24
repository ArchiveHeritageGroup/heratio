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

    /**
     * AJAX: Search agents (actors) for provenance autocomplete.
     * GET /provenance/searchAgents?term=...
     *
     * Returns JSON array of matching actors with id and name.
     */
    public function searchAgents(Request $request)
    {
        $term = $request->query('term', '');
        $culture = app()->getLocale();

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $results = \Illuminate\Support\Facades\DB::table('actor')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor_i18n.authorized_form_of_name', 'LIKE', '%' . $term . '%')
            ->where('actor.id', '!=', 3) // Root actor ID
            ->select([
                'actor.id',
                'actor_i18n.authorized_form_of_name as name',
                'slug.slug',
            ])
            ->limit(20)
            ->get();

        return response()->json($results);
    }
}
