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
use AhgResearch\Services\ResearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    public function __construct(ResearchService $service)
    {
        $this->service = $service;
    }

    public function mobileHome(Request $request)
    {
        $researcher = Auth::check() ? $this->service->getResearcherByUserId(Auth::id()) : null;

        $readingList = [];
        if ($researcher) {
            try {
                $readingList = DB::table('research_collection_item as ci')
                    ->join('research_collection as c', 'ci.collection_id', '=', 'c.id')
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('ci.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                    })
                    ->leftJoin('slug', 'ci.object_id', '=', 'slug.object_id')
                    ->where('c.researcher_id', $researcher->id)
                    ->orderByDesc('ci.created_at')
                    ->limit(50)
                    ->select('ci.object_id', 'ioi.title', 'slug.slug', 'c.name as collection_name')
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {}
        }

        return view('research::research.mobile-home', compact('researcher', 'readingList'));
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
