<?php

/**
 * ReconstructionController - heratio#1206 "walk through what no longer exists".
 *
 * Public:
 *   gallery()  - "Reconstructions: walk through what no longer exists": every
 *                catalogue record (a lost / destroyed / no-longer-extant place)
 *                that has been linked to a walkable exhibition-space twin, each
 *                with a "Walk the reconstruction" link into the existing public
 *                walkthrough route.
 *
 * Admin (acl-gated at the route, like the other exhibition admin actions):
 *   manage()   - form to link a record to a reconstruction space + a list of
 *                existing links to remove.
 *   store()    - persist a new link.
 *   destroy()  - remove a link.
 *
 * A reconstruction is INTERPRETIVE - a virtual reconstruction for interpretation,
 * not a claim about the original's exact appearance. The views say so plainly.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Controllers;

use AhgExhibition\Services\ExhibitionSpaceService;
use AhgExhibition\Services\ReconstructionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconstructionController extends Controller
{
    protected ReconstructionService $reconstructions;

    protected ExhibitionSpaceService $spaces;

    public function __construct()
    {
        $this->reconstructions = new ReconstructionService;
        $this->spaces = new ExhibitionSpaceService;
    }

    /** Public gallery of all reconstructions. */
    public function gallery()
    {
        return view('ahg-exhibition::reconstruction.gallery', [
            'reconstructions' => $this->reconstructions->all(),
        ]);
    }

    /** Admin: link a record to a reconstruction space + manage existing links. */
    public function manage()
    {
        // Reconstruction spaces to choose from (every exhibition space is walkable).
        $spaces = DB::table('ahg_exhibition_space')
            ->orderBy('name')
            ->select('id', 'slug', 'name')
            ->get();

        return view('ahg-exhibition::reconstruction.manage', [
            'spaces' => $spaces,
            'reconstructions' => $this->reconstructions->all(),
        ]);
    }

    /** Admin: persist a new lost-place -> reconstruction link. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'information_object_id' => ['required', 'integer', 'min:1'],
            'exhibition_space_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        // Guard: both ends must exist before we link them.
        $ioExists = DB::table('information_object')->where('id', $data['information_object_id'])->exists();
        $spaceExists = DB::table('ahg_exhibition_space')->where('id', $data['exhibition_space_id'])->exists();
        if (! $ioExists || ! $spaceExists) {
            return back()
                ->withInput()
                ->withErrors(['information_object_id' => __('That record or reconstruction space could not be found.')]);
        }

        $this->reconstructions->link(
            (int) $data['information_object_id'],
            (int) $data['exhibition_space_id'],
            $data['note'] ?? null
        );

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Reconstruction linked.'));
    }

    /** Admin: remove a reconstruction link. */
    public function destroy(int $id)
    {
        $this->reconstructions->unlink($id);

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Reconstruction link removed.'));
    }
}
