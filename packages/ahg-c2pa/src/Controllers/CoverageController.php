<?php
/**
 * Heratio - C2PA "authenticity coverage" admin dashboard (deepens #1201 / #1209).
 *
 * The operator's view of the content-credentials layer. Where the public
 * /verify front door (AuthenticityController) tells the world how much of the
 * collection is verifiable, this admin-gated page tells the institution where
 * its gaps are: the headline coverage %, the verified / invalid / unsigned
 * split, and a per-holding-repository table so a collections manager can see
 * which holdings still need signing and close the gap. Read-only.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Controllers;

use AhgC2pa\Services\CoverageReportService;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class CoverageController extends Controller
{
    public function __construct(private CoverageReportService $coverage)
    {
    }

    /**
     * Admin authenticity-coverage dashboard.
     */
    public function index(): View
    {
        return view('ahg-c2pa::coverage', [
            'report' => $this->coverage->report(),
        ]);
    }
}
