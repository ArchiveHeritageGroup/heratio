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
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $reconstructions = $this->reconstructions->all();

        // Attach the rebuild stages + current montage style to each reconstruction
        // so the admin can manage them inline. Style comes off the link row; stages
        // are presented (resolved image URLs) the same way the player sees them.
        $styleByRecon = [];
        if (Schema::hasColumn('ahg_lost_place_reconstruction', 'montage_style')) {
            $styleByRecon = DB::table('ahg_lost_place_reconstruction')
                ->pluck('montage_style', 'id')->all();
        }

        $stagesByRecon = [];
        foreach ($reconstructions as $r) {
            $stagesByRecon[$r->id] = $this->presentStages($this->reconstructions->stagesFor((int) $r->id));
        }

        return view('ahg-exhibition::reconstruction.manage', [
            'spaces' => $spaces,
            'reconstructions' => $reconstructions,
            'stagesByRecon' => $stagesByRecon,
            'styleByRecon' => $styleByRecon,
            'styleOptions' => $this->styleOptions(),
            // heratio#1206 - fixed picklists for the optional evidence-layer annotator.
            'metadataOptions' => app(\AhgExhibition\Services\ReconstructionMetadataService::class)->options(),
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

    // ------------------------------------------------------------------------
    // heratio#1219 - "reconstruction assembly montage": a lost structure
    // rebuilding itself on screen before the visitor walks into its 3D twin.
    // ------------------------------------------------------------------------

    /**
     * Public player: a DB-backed reconstruction with its rebuild stages, playing
     * either the Assembly stack or the Time-lapse cross-fade. Degrades gracefully
     * to an empty state when there are no stages, and never 500s on a missing
     * space / table.
     */
    public function show(int $id)
    {
        $reconstruction = $this->reconstructions->getById($id);
        if (! $reconstruction) {
            abort(404);
        }

        $stages = $this->presentStages($this->reconstructions->stagesFor($id));

        return view('ahg-exhibition::reconstruction.show', [
            'reconstruction' => $reconstruction,
            'stages' => $stages,
            'defaultStyle' => $reconstruction->montage_style ?? 'assembly',
            'spaceSlug' => $reconstruction->space_slug ?? null,
            'spaceName' => $reconstruction->space_name ?? null,
            'modes' => $this->montageModes(),
            'demo' => false,
        ]);
    }

    /**
     * Public self-contained demo: bundled SVG "evidence layers" seeded in-memory
     * (no DB, no auth) that play BOTH montage modes. The "Walk through it" CTA
     * points at any existing walkable space, else explains there is none.
     */
    public function demo()
    {
        $asset = fn (string $f) => route('reconstruction.demo.asset', ['file' => $f]);
        $stages = $this->presentStages([
            (object) ['id' => 1, 'caption' => __('Site survey: the footprint that remains'), 'body' => __('Faint ruin outline traced from the surviving foundations and a measured ground plan.'), 'date_display' => 'c. 1905', 'image_path' => null, 'image_url' => $asset('01-ruin'), 'opacity' => 1.0],
            (object) ['id' => 2, 'caption' => __('Walls raised from the evidence'), 'body' => __('Wall lines reconstructed from the footprint, archival elevations and comparable structures of the period.'), 'date_display' => 'c. 1912', 'image_path' => null, 'image_url' => $asset('02-walls'), 'opacity' => 1.0],
            (object) ['id' => 3, 'caption' => __('Openings and the upper storey'), 'body' => __('Doors, windows and the second storey placed from photographs and the surveyed wall heights.'), 'date_display' => 'c. 1920', 'image_path' => null, 'image_url' => $asset('03-openings'), 'opacity' => 1.0],
            (object) ['id' => 4, 'caption' => __('Roof and structure'), 'body' => __('The roofline and structural frame inferred from period building practice and one surviving gable photograph.'), 'date_display' => 'c. 1928', 'image_path' => null, 'image_url' => $asset('04-roof'), 'opacity' => 1.0],
            (object) ['id' => 5, 'caption' => __('Archival photograph: the building in use'), 'body' => __('A dated archival-style frame showing the structure as it stood, used to check the reconstruction.'), 'date_display' => '1931', 'image_path' => null, 'image_url' => $asset('05-photo'), 'opacity' => 1.0],
            (object) ['id' => 6, 'caption' => __('Full interpretive render'), 'body' => __('The completed virtual reconstruction, ready to walk through. One informed reading of the evidence.'), 'date_display' => __('Reconstruction'), 'image_path' => null, 'image_url' => $asset('06-render'), 'opacity' => 1.0],
        ]);

        // Point the CTA at any real walkable space if one exists; else explain.
        $spaceSlug = null;
        $spaceName = null;
        try {
            if (Schema::hasTable('ahg_exhibition_space')) {
                $space = DB::table('ahg_exhibition_space')->orderBy('id')->select('slug', 'name')->first();
                if ($space) {
                    $spaceSlug = $space->slug;
                    $spaceName = $space->name;
                }
            }
        } catch (\Throwable $e) {
            // No space available; the CTA renders in its explanatory state.
        }

        $reconstruction = (object) [
            'id' => 0,
            'record_title' => __('Demonstration: a lost building rebuilds itself'),
            'note' => __('A self-contained demonstration of the reconstruction montage. The layers below are illustrative, not a real record.'),
            'montage_style' => 'assembly',
            'space_slug' => $spaceSlug,
            'space_name' => $spaceName,
        ];

        return view('ahg-exhibition::reconstruction.show', [
            'reconstruction' => $reconstruction,
            'stages' => $stages,
            'defaultStyle' => 'assembly',
            'spaceSlug' => $spaceSlug,
            'spaceName' => $spaceName,
            'modes' => $this->montageModes(),
            'demo' => true,
        ]);
    }

    /**
     * Public: serve a bundled demo SVG "evidence layer" shipped inside the package
     * (self-hosted, no CDN). The {file} is constrained to a slug at the route and
     * re-validated here against the known demo basenames so it can never escape the
     * demo-assets directory.
     */
    public function demoAsset(string $file)
    {
        $allowed = ['01-ruin', '02-walls', '03-openings', '04-roof', '05-photo', '06-render'];
        if (! in_array($file, $allowed, true)) {
            abort(404);
        }

        $path = __DIR__.'/../../resources/demo-assets/'.$file.'.svg';
        if (! is_file($path)) {
            abort(404);
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'image/svg+xml');
        $response->headers->set('Cache-Control', 'public, max-age=86400');

        return $response;
    }

    /**
     * Public: stream a stage's uploaded evidence image from storage. 404 when the
     * stage, its image_path, or the file on disk is absent. (When a stage only has
     * an image_url, callers reference that URL directly and never hit this route.)
     */
    public function stageImage(int $id)
    {
        $stage = $this->reconstructions->getStage($id);
        if (! $stage || empty($stage->image_path)) {
            abort(404);
        }

        $abs = $this->reconstructions->absoluteStagePath($stage->image_path);
        if ($abs === null || ! is_file($abs)) {
            abort(404);
        }

        $response = new BinaryFileResponse($abs);
        $response->headers->set('Cache-Control', 'public, max-age=86400');

        return $response;
    }

    /** Admin: add a rebuild stage to a reconstruction. */
    public function addStage(Request $request, int $id)
    {
        if (! $this->reconstructions->getById($id)) {
            abort(404);
        }

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:65000'],
            'date_display' => ['nullable', 'string', 'max:64'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif,svg', 'max:51200'],
            'image_url' => ['nullable', 'string', 'max:1024', 'url'],
            'opacity' => ['nullable', 'numeric', 'between:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->reconstructions->addStage($id, $data, $request->file('image'));

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Rebuild stage added.'));
    }

    /** Admin: update a rebuild stage. */
    public function updateStage(Request $request, int $id, int $stageId)
    {
        $stage = $this->reconstructions->getStage($stageId);
        if (! $stage || (int) $stage->reconstruction_id !== $id) {
            abort(404);
        }

        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:65000'],
            'date_display' => ['nullable', 'string', 'max:64'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,gif,svg', 'max:51200'],
            'image_url' => ['nullable', 'string', 'max:1024', 'url'],
            'opacity' => ['nullable', 'numeric', 'between:0,1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->reconstructions->updateStage($stageId, $data, $request->file('image'));

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Rebuild stage updated.'));
    }

    /** Admin: delete a rebuild stage. */
    public function deleteStage(int $id, int $stageId)
    {
        $stage = $this->reconstructions->getStage($stageId);
        if (! $stage || (int) $stage->reconstruction_id !== $id) {
            abort(404);
        }

        $this->reconstructions->deleteStage($stageId);

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Rebuild stage removed.'));
    }

    /** Admin: reorder the rebuild stages of a reconstruction. */
    public function reorderStages(Request $request, int $id)
    {
        if (! $this->reconstructions->getById($id)) {
            abort(404);
        }

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'min:1'],
        ]);

        $this->reconstructions->reorderStages($id, $data['order']);

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Rebuild stages reordered.'));
    }

    /** Admin: set the default montage style for a reconstruction. */
    public function setStyle(Request $request, int $id)
    {
        if (! $this->reconstructions->getById($id)) {
            abort(404);
        }

        $data = $request->validate([
            'montage_style' => ['required', 'string', 'in:'.implode(',', ReconstructionService::STYLES)],
        ]);

        $this->reconstructions->setStyle($id, $data['montage_style']);

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', __('Montage style updated.'));
    }

    // ------------------------------------------------------------------------
    // heratio#1206 - optional AI "evidence layer" annotator. annotateStage()
    // returns an AI SUGGESTION (AJAX) the curator reviews; saveStageMetadata()
    // persists the curator-confirmed JSON. Both are admin-gated at the route.
    // The AI call routes through the AHG gateway (LlmService) and fails soft.
    // ------------------------------------------------------------------------

    /**
     * Admin (AJAX): ask the AI to suggest evidence-layer metadata for a stage from its
     * caption / body text. Always returns 200 JSON: {ok, metadata?} or {ok:false, error}
     * so the front end can show the suggestion or a friendly message - never a 500.
     */
    public function annotateStage(Request $request, int $id, int $stageId)
    {
        $stage = $this->reconstructions->getStage($stageId);
        if (! $stage || (int) $stage->reconstruction_id !== $id) {
            return response()->json(['ok' => false, 'error' => __('That rebuild stage could not be found.')], 404);
        }

        $result = app(\AhgExhibition\Services\ReconstructionMetadataService::class)->suggest($stage);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? __('No suggestion available.')]);
        }

        return response()->json(['ok' => true, 'metadata' => $result['metadata']]);
    }

    /**
     * Admin: persist the curator-confirmed evidence-layer metadata for a stage. The
     * submitted values are normalised + enum-constrained by the metadata service; an
     * empty set clears the metadata. Additive - never touches the montage behaviour.
     */
    public function saveStageMetadata(Request $request, int $id, int $stageId)
    {
        $stage = $this->reconstructions->getStage($stageId);
        if (! $stage || (int) $stage->reconstruction_id !== $id) {
            abort(404);
        }

        $data = $request->validate([
            'date_estimate' => ['nullable', 'string', 'max:120'],
            'evidence_type' => ['nullable', 'string', 'max:64'],
            'confidence' => ['nullable', 'string', 'max:32'],
            'source_credibility' => ['nullable', 'string', 'max:32'],
            'rationale' => ['nullable', 'string', 'max:1000'],
        ]);

        $metadata = app(\AhgExhibition\Services\ReconstructionMetadataService::class)->normalise($data);
        $this->reconstructions->saveStageMetadata($stageId, $metadata);

        return redirect()
            ->route('exhibition-space.reconstructions.manage')
            ->with('success', $metadata === null ? __('Evidence metadata cleared.') : __('Evidence metadata saved.'));
    }

    /**
     * Normalise stage rows for the player/admin: resolve each layer's display URL
     * (uploaded image streamed via the route; else the external image_url) and a
     * float opacity. Rows with no usable image are dropped from the player.
     */
    private function presentStages(array $stages): array
    {
        $out = [];
        foreach ($stages as $s) {
            $src = null;
            if (! empty($s->image_path)) {
                $src = route('reconstruction.stage.image', ['id' => $s->id]);
            } elseif (! empty($s->image_url)) {
                $src = $s->image_url;
            }

            // heratio#1206 - decode the optional curator-confirmed evidence-layer
            // metadata JSON so the admin form + the player can read it. Null / absent
            // / malformed -> null (the stage simply shows no evidence layer).
            $metadata = null;
            if (! empty($s->metadata)) {
                $decoded = is_array($s->metadata) ? $s->metadata : json_decode((string) $s->metadata, true);
                if (is_array($decoded) && $decoded !== []) {
                    $metadata = $decoded;
                }
            }

            $out[] = (object) [
                'id' => $s->id ?? null,
                'caption' => $s->caption ?? null,
                'body' => $s->body ?? null,
                'date_display' => $s->date_display ?? null,
                'src' => $src,
                'opacity' => isset($s->opacity) ? max(0.0, min(1.0, (float) $s->opacity)) : 1.0,
                'has_upload' => ! empty($s->image_path),
                'image_url' => $s->image_url ?? null,
                'metadata' => $metadata,
            ];
        }

        return $out;
    }

    /**
     * Montage-style options for the admin <select>, read from the
     * reconstruction_montage_style dropdown group (Dropdown Manager). Same source
     * as the visitor mode toggle, with the fuller admin labels.
     */
    private function styleOptions(): array
    {
        return $this->montageModes();
    }

    /**
     * The two montage modes, labelled from the reconstruction_montage_style
     * dropdown group so the toggle reads the Dropdown Manager. Falls back to
     * built-in labels if the dropdown rows are missing.
     */
    private function montageModes(): array
    {
        $fallback = [
            ['code' => 'assembly', 'label' => __('Assembly')],
            ['code' => 'timelapse', 'label' => __('Time-lapse')],
        ];

        try {
            if (! Schema::hasTable('ahg_dropdown')) {
                return $fallback;
            }
            $rows = DB::table('ahg_dropdown')
                ->where('taxonomy', 'reconstruction_montage_style')
                ->where('is_active', 1)
                ->orderBy('sort_order')
                ->get(['code', 'label']);
            if ($rows->isEmpty()) {
                return $fallback;
            }

            return $rows->map(fn ($r) => ['code' => $r->code, 'label' => $r->label])->all();
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
