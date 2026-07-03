<?php

namespace AhgResearch\Controllers;

use AhgResearch\Services\ResearchOfflineService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Researcher-facing offline packages (Phase 1–2).
 *
 * Lets a researcher take one of their own groups (project / collection /
 * workspace / favourites folder) offline as an editable portable bundle, track
 * its build, and download it. Everything is scoped to the logged-in researcher
 * and — via the underlying portable-export ACL/disclosure gate — to the records
 * they are permitted to see.
 */
class ResearchOfflineController extends Controller
{
    public function __construct(
        private ResearchService $research,
        private ResearchOfflineService $offline
    ) {
    }

    /** List the researcher's offline packages. */
    public function index(Request $request)
    {
        $researcher = $this->research->getResearcherByUserId((int) Auth::id());
        if (! $researcher) {
            return redirect()->route('researcher.register');
        }

        $packages = $this->offline->listForUser((int) Auth::id());

        return view('research::research.offline', [
            'packages' => $packages,
            'researcher' => $researcher,
        ]);
    }

    /**
     * Create an offline package from a group. POST /research/offline/{source}/{id}.
     */
    public function take(Request $request, string $source, int $id)
    {
        $researcher = $this->research->getResearcherByUserId((int) Auth::id());
        if (! $researcher) {
            return $this->fail($request, 'You need a researcher profile first.', 'researcher.register');
        }

        if (! in_array($source, ResearchOfflineService::SOURCES, true)) {
            return $this->fail($request, 'Unknown group type.');
        }

        $resolved = $this->offline->resolveGroup($source, $id, $researcher, (int) Auth::id());
        if ($resolved === null) {
            return $this->fail($request, 'That group was not found, or it is not yours to export.');
        }

        if (empty($resolved['slugs'])) {
            return $this->fail($request, 'There are no catalogue records in that group to take offline yet.');
        }

        $exportId = $this->offline->createPackage(
            $researcher,
            (int) Auth::id(),
            $source,
            $id,
            $resolved['title'],
            $resolved['slugs']
        );

        $msg = sprintf('Building your offline package (%d record(s)). It will appear below when ready.', count($resolved['slugs']));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'export_id' => $exportId, 'message' => $msg]);
        }

        return redirect()->route('research.offline.index')->with('success', $msg);
    }

    /** Build progress for one of the researcher's packages (AJAX poll). */
    public function status(Request $request, int $id): JsonResponse
    {
        $pkg = $this->offline->ownedPackage($id, (int) Auth::id());
        if (! $pkg) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'status' => $pkg->status,
            'progress' => (int) $pkg->progress,
            'total_descriptions' => (int) $pkg->total_descriptions,
            'total_objects' => (int) $pkg->total_objects,
            'output_size' => (int) $pkg->output_size,
            'error' => $pkg->error_message,
        ]);
    }

    /** Download one of the researcher's completed packages. */
    public function download(Request $request, int $id)
    {
        $pkg = $this->offline->ownedPackage($id, (int) Auth::id());
        if (! $pkg || $pkg->status !== 'complete' || ! $pkg->output_path) {
            abort(404, 'Package not found or not ready.');
        }
        if (! is_file($pkg->output_path)) {
            abort(404, 'Package file is no longer on disk.');
        }

        $name = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $pkg->title).'-'.$pkg->id.'.zip';

        return response()->download($pkg->output_path, $name);
    }

    private function fail(Request $request, string $message, ?string $route = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'error' => $message], 422);
        }

        return redirect()->to($route ? route($route) : url()->previous())->with('error', $message);
    }
}
