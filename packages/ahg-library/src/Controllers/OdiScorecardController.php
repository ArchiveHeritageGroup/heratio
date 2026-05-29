<?php

/**
 * OdiScorecardController - ODI quality-scorecard admin UI.
 *
 * Renders the Open Discovery Initiative scorecard listing every library
 * collection with its four conformance metrics and composite quality score,
 * and exposes a POST action to trigger an on-demand recompute.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\OdiScorecardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class OdiScorecardController extends Controller
{
    public function __construct(private OdiScorecardService $service)
    {
    }

    /**
     * GET /library-manage/odi/scorecard
     */
    public function index(): \Illuminate\View\View
    {
        $scorecards = $this->service->storedScorecards();

        return view('ahg-library::odi.scorecard', [
            'scorecards' => $scorecards,
        ]);
    }

    /**
     * POST /library-manage/odi/scorecard/refresh
     */
    public function refresh(): RedirectResponse
    {
        $written = $this->service->refreshAll();

        return redirect()
            ->route('library.odi-scorecard')
            ->with('status', "ODI scorecards refreshed for {$written} collection(s).");
    }
}
