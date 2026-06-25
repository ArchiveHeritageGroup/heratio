<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AclService;
use AhgCore\Services\SectorProfileService;
use AhgCore\Support\SectorProfiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Admin "Apply sector profile" UI (heratio#1331) for existing installs - pick a
 * sector, apply the same profile bin/install --sector would. Calls the shared
 * SectorProfileService (no logic duplicated). Admin-gated (auth route group +
 * canAdmin check).
 */
class SectorProfileController extends Controller
{
    public function __construct(private SectorProfileService $service)
    {
    }

    private function gate(): void
    {
        abort_unless(AclService::canAdmin(auth()->id()), 403);
    }

    public function index()
    {
        $this->gate();

        return view('ahg-core::sector-profile', [
            'profiles' => SectorProfiles::all(),
            'current'  => $this->service->current(),
        ]);
    }

    public function apply(Request $request)
    {
        $this->gate();

        $sector = strtolower(trim((string) $request->input('sector')));
        if (! SectorProfiles::has($sector)) {
            return redirect()->route('admin.sector-profile')->with('error', 'Unknown sector.');
        }

        try {
            $r = $this->service->apply($sector);
        } catch (\Throwable $e) {
            return redirect()->route('admin.sector-profile')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sector-profile')->with(
            'success',
            "Applied the {$r['label']} profile — {$r['theme_count']} theme settings + identifier mask {$r['mask']}. Re-run any time to switch sectors."
        );
    }
}
