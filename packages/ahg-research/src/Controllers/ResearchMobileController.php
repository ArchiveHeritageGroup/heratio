<?php

/**
 * ResearchMobileController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Controllers\Concerns\ResearchControllerHelpers;
use AhgResearch\Services\OfflineSyncService;
use AhgResearch\Services\ResearchOfflineService;
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ResearchMobileController - Mobile / PWA home + offline sync.
 *
 * Extracted from ResearchController as part of the monolith decomposition
 * (issue #1269). Both endpoints sit in the auth-gated `research` route group:
 *   GET  /research/mobile        -> mobileHome   (renders the PWA home view)
 *   POST /research/sync/offline  -> offlineSync   (JSON offline-queue replay)
 *
 * No cross-calls to other ResearchController methods existed - the methods used
 * only the injected ResearchService (getResearcherByUserId) and framework
 * facades (Auth, DB), so the move is a verbatim lift. No private helpers were
 * exclusive to these methods.
 */
class ResearchMobileController extends Controller
{
    use ResearchControllerHelpers;

    protected ResearchService $service;

    protected ResearchOfflineService $offline;

    protected OfflineSyncService $sync;

    public function __construct(ResearchService $service, ResearchOfflineService $offline, OfflineSyncService $sync)
    {
        $this->service = $service;
        $this->offline = $offline;
        $this->sync = $sync;
    }

    /**
     * The "Work Offline" home: pick your own Collections / Projects / Favourites
     * folders to take offline as a self-contained package, see your recent
     * packages, and upload a researcher-sync.json to bring offline work back.
     */
    public function mobileHome(Request $request)
    {
        $researcher = Auth::check() ? $this->service->getResearcherByUserId(Auth::id()) : null;

        $collections = [];
        $projects = [];
        $folders = [];
        $packages = [];

        if ($researcher) {
            $userId = (int) Auth::id();
            try {
                $collections = DB::table('research_collection as c')
                    ->leftJoin('research_collection_item as ci', 'ci.collection_id', '=', 'c.id')
                    ->where('c.researcher_id', $researcher->id)
                    ->groupBy('c.id', 'c.name')
                    ->orderBy('c.name')
                    ->select('c.id', 'c.name', DB::raw('COUNT(ci.id) as item_count'))
                    ->get()->toArray();

                $projects = DB::table('research_project as p')
                    ->leftJoin('research_project_resource as pr', 'pr.project_id', '=', 'p.id')
                    ->where('p.owner_id', $researcher->id)
                    ->groupBy('p.id', 'p.title')
                    ->orderBy('p.title')
                    ->select('p.id', 'p.title as name', DB::raw('COUNT(pr.id) as item_count'))
                    ->get()->toArray();

                $folders = DB::table('favorites_folder as f')
                    ->leftJoin('favorites as fav', function ($j) {
                        $j->on('fav.folder_id', '=', 'f.id')->where('fav.object_type', '=', 'information_object');
                    })
                    ->where('f.user_id', $userId)
                    ->groupBy('f.id', 'f.name')
                    ->orderBy('f.name')
                    ->select('f.id', 'f.name', DB::raw('COUNT(fav.id) as item_count'))
                    ->get()->toArray();

                $packages = $this->offline->listForUser($userId, 20);
            } catch (\Throwable $e) {
            }
        }

        return view('research::research.mobile-home', compact('researcher', 'collections', 'projects', 'folders', 'packages'));
    }

    /**
     * Build a self-contained offline package from the selected Collections /
     * Projects / Favourites folders. The bundle worker ACL-gates the content to
     * exactly what THIS researcher may see (keyed on the export's user_id), so
     * restricted/embargoed/unpublished records are dropped automatically.
     */
    public function buildOfflinePackage(Request $request)
    {
        if (! Auth::check()) {
            abort(401);
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('research.mobileHome')->with('error', __('A researcher profile is required to build an offline package.'));
        }
        $userId = (int) Auth::id();

        $selected = [];
        foreach (['collection' => 'collection_ids', 'project' => 'project_ids', 'favorites' => 'folder_ids'] as $source => $field) {
            foreach ((array) $request->input($field, []) as $id) {
                if ((int) $id > 0) {
                    $selected[] = [$source, (int) $id];
                }
            }
        }
        if (empty($selected)) {
            return redirect()->route('research.mobileHome')->with('error', __('Select at least one collection, project or favourites folder to take offline.'));
        }

        $slugs = [];
        $titles = [];
        foreach ($selected as [$source, $id]) {
            $group = $this->offline->resolveGroup($source, $id, $researcher, $userId);
            if ($group && ! empty($group['slugs'])) {
                $slugs = array_merge($slugs, $group['slugs']);
                $titles[] = $group['title'];
            }
        }
        $slugs = array_values(array_unique(array_filter($slugs)));
        if (empty($slugs)) {
            return redirect()->route('research.mobileHome')->with('error', __('The selected items contain no records you can take offline.'));
        }

        $title = count($titles) === 1 ? $titles[0] : (__('Offline package').' ('.count($slugs).' '.__('records').')');
        // Combined multi-group package -> group_source='mixed'. The service
        // records a sync_token that we verify on the way back in syncUpload().
        $this->offline->createPackage($researcher, $userId, 'mixed', 0, $title, $slugs);

        return redirect()->route('research.mobileHome')
            ->with('success', __('Your offline package is being prepared — it will appear below to download in a moment.'));
    }

    /**
     * Bring offline work back: parse the uploaded researcher-sync.json and apply
     * it. Ownership is resolved server-side from the session; the package is
     * verified against its sync_token before anything is written.
     */
    public function syncUpload(Request $request)
    {
        if (! Auth::check()) {
            abort(401);
        }
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (! $researcher) {
            return redirect()->route('research.mobileHome')->with('error', __('A researcher profile is required.'));
        }
        $userId = (int) Auth::id();

        $file = $request->file('sync_file');
        if (! $file || ! $file->isValid()) {
            return redirect()->route('research.mobileHome')->with('error', __('Please choose the researcher-sync.json file from your offline package.'));
        }
        if ($file->getSize() > 25 * 1024 * 1024) {
            return redirect()->route('research.mobileHome')->with('error', __('That sync file is too large (25 MB maximum).'));
        }

        $payload = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (! is_array($payload) || (int) ($payload['heratio_sync'] ?? 0) !== 1) {
            return redirect()->route('research.mobileHome')->with('error', __('That does not look like a Heratio researcher-sync.json file.'));
        }

        // Verify the package is one of THIS researcher's, by token + ownership.
        $packageId = (int) ($payload['package_id'] ?? 0);
        $token = (string) ($payload['sync_token'] ?? '');
        if ($packageId > 0 && Schema::hasColumn('portable_export', 'sync_token')) {
            $pkg = DB::table('portable_export')->where('id', $packageId)->first();
            if ($pkg && (
                (int) ($pkg->researcher_user_id ?? $pkg->user_id ?? 0) !== $userId
                || ($token !== '' && (string) ($pkg->sync_token ?? '') !== $token)
            )) {
                return redirect()->route('research.mobileHome')->with('error', __('That sync file does not match one of your offline packages.'));
            }
        }

        $r = $this->sync->applyBundle((int) $researcher->id, $payload);

        if ($r['applied'] === 0) {
            return redirect()->route('research.mobileHome')->with('info', __('No changes were found in that sync file.'));
        }

        $detail = [];
        if ($r['notes']) {
            $detail[] = $r['notes'].' '.__('notes');
        }
        if ($r['sources']) {
            $detail[] = $r['sources'].' '.__('sources');
        }
        if ($r['suggestions']) {
            $detail[] = $r['suggestions'].' '.__('suggestions (queued for curator review)');
        }
        if ($r['files']) {
            $detail[] = $r['files'].' '.__('files');
        }
        $msg = __('Synced :n change(s) back into your research.', ['n' => $r['applied']]);
        if ($detail) {
            $msg .= ' — '.implode(', ', $detail);
        }
        if ($r['conflicts']) {
            $msg .= ' ('.$r['conflicts'].' '.__('could not be applied').')';
        }

        return redirect()->route('research.mobileHome')->with('success', $msg);
    }

    public function offlineSync(Request $request)
    {
        if (!Auth::check()) abort(401);
        $researcher = $this->service->getResearcherByUserId(Auth::id());
        if (!$researcher) abort(403);

        $payload = $request->json()->all();
        if (!is_array($payload) || empty($payload['queue'])) {
            return response()->json(['applied' => 0, 'conflicts' => 0]);
        }

        $applied = 0;
        $conflicts = 0;
        $logId = DB::table('research_offline_sync_log')->insertGetId([
            'researcher_id'   => (int) $researcher->id,
            'sync_started_at' => date('Y-m-d H:i:s'),
            'queued_count'    => count($payload['queue']),
            'payload_hash'    => hash('sha256', json_encode($payload['queue'])),
        ]);

        foreach ($payload['queue'] as $item) {
            $kind = $item['kind'] ?? null;
            try {
                if ($kind === 'journal_entry') {
                    DB::table('research_journal_entry')->insert([
                        'researcher_id' => (int) $researcher->id,
                        'project_id'    => $item['project_id'] ?? null,
                        'entry_type'    => $item['entry_type'] ?? 'note',
                        'entry_date'    => $item['entry_date'] ?? date('Y-m-d'),
                        'title'         => mb_substr((string) ($item['title'] ?? ''), 0, 255),
                        'content'       => (string) ($item['content'] ?? ''),
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);
                    $applied++;
                } elseif ($kind === 'annotation') {
                    DB::table('ahg_iiif_annotation')->insert([
                        'uuid'                  => $item['uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                        'target_iri'            => (string) ($item['target_iri'] ?? ''),
                        'information_object_id' => $item['information_object_id'] ?? null,
                        'project_id'            => $item['project_id'] ?? null,
                        'visibility'            => $item['visibility'] ?? 'private',
                        'body_json'             => json_encode($item['body'] ?? []),
                        'created_by'            => Auth::id(),
                        'updated_by'            => Auth::id(),
                        'created_at'            => date('Y-m-d H:i:s'),
                        'updated_at'            => date('Y-m-d H:i:s'),
                    ]);
                    $applied++;
                } else {
                    $conflicts++;
                }
            } catch (\Throwable $e) {
                $conflicts++;
            }
        }

        DB::table('research_offline_sync_log')->where('id', $logId)->update([
            'sync_completed_at' => date('Y-m-d H:i:s'),
            'applied_count'     => $applied,
            'conflict_count'    => $conflicts,
        ]);

        return response()->json([
            'applied'   => $applied,
            'conflicts' => $conflicts,
            'log_id'    => $logId,
        ]);
    }
}
