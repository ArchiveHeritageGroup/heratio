<?php

/**
 * ResearcherSubmissionController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgResearcherManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\AclService;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResearcherSubmissionController extends Controller
{
    /**
     * Researcher dashboard with stats and recent submissions.
     */
    public function dashboard()
    {
        $user = auth()->user();
        $isAdmin = AclService::isAdministrator($user);

        $baseQuery = DB::table('researcher_submission');
        if (!$isAdmin) {
            $baseQuery->where('user_id', $user->id);
        }

        $stats = [
            'total'    => (clone $baseQuery)->count(),
            'draft'    => (clone $baseQuery)->where('status', 'draft')->count(),
            'pending'  => (clone $baseQuery)->whereIn('status', ['submitted', 'under_review'])->count(),
            'approved' => (clone $baseQuery)->where('status', 'approved')->count(),
            'published' => (clone $baseQuery)->where('status', 'published')->count(),
            'returned' => (clone $baseQuery)->where('status', 'returned')->count(),
            'rejected' => (clone $baseQuery)->where('status', 'rejected')->count(),
        ];

        $culture = app()->getLocale();

        $recentQuery = DB::table('researcher_submission')
            ->leftJoin('user', 'researcher_submission.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select([
                'researcher_submission.id',
                'researcher_submission.title',
                'researcher_submission.source_type',
                'researcher_submission.status',
                'researcher_submission.total_items',
                'researcher_submission.total_files',
                'researcher_submission.updated_at',
                'actor_i18n.authorized_form_of_name as user_name',
                'user.username',
            ])
            ->orderBy('researcher_submission.updated_at', 'desc')
            ->limit(10);

        if (!$isAdmin) {
            $recentQuery->where('researcher_submission.user_id', $user->id);
        }

        $recent = $recentQuery->get()->toArray();

        // Research integration — load projects, collections, annotations
        $hasResearch = Schema::hasTable('research_researcher');
        $researcherProfile = null;
        $projects = [];
        $collections = [];
        $annotations = [];

        if ($hasResearch) {
            $researcherProfile = $this->getResearcherProfile($user->id);
            if ($researcherProfile) {
                $rid = (int) $researcherProfile->id;
                $projects = $this->getResearchProjects($rid, 5);
                $collections = $this->getResearchCollections($rid, 5);
                $annotations = $this->getResearchAnnotations($rid, 5);
            }
        }

        return view('ahg-researcher-manage::dashboard', compact(
            'stats', 'recent', 'isAdmin', 'hasResearch',
            'researcherProfile', 'projects', 'collections', 'annotations'
        ));
    }

    /**
     * Paginated submissions list with optional status filter.
     */
    public function submissions(Request $request)
    {
        $user = auth()->user();
        $isAdmin = AclService::isAdministrator($user);
        $status = $request->get('status', '');
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));

        $query = DB::table('researcher_submission')
            ->leftJoin('user', 'researcher_submission.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', app()->getLocale());
            });

        if (!$isAdmin) {
            $query->where('researcher_submission.user_id', $user->id);
        }

        if ($status !== '') {
            $query->where('researcher_submission.status', $status);
        }

        $total = (clone $query)->count();

        $submissions = $query->select([
            'researcher_submission.id',
            'researcher_submission.title',
            'researcher_submission.source_type',
            'researcher_submission.status',
            'researcher_submission.total_items',
            'researcher_submission.total_files',
            'researcher_submission.created_at',
            'researcher_submission.updated_at',
            'actor_i18n.authorized_form_of_name as researcher_name',
            'user.username',
        ])
            ->orderBy('researcher_submission.updated_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits'  => $submissions,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        return view('ahg-researcher-manage::submissions', [
            'pager' => $pager,
            'currentStatus' => $status,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Alias: pending submissions (status=submitted + under_review).
     */
    public function pending(Request $request)
    {
        $user = auth()->user();
        $isAdmin = AclService::isAdministrator($user);
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));

        $query = DB::table('researcher_submission')
            ->leftJoin('user', 'researcher_submission.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', app()->getLocale());
            })
            ->whereIn('researcher_submission.status', ['submitted', 'under_review']);

        if (!$isAdmin) {
            $query->where('researcher_submission.user_id', $user->id);
        }

        $total = (clone $query)->count();

        $submissions = $query->select([
            'researcher_submission.id',
            'researcher_submission.title',
            'researcher_submission.source_type',
            'researcher_submission.status',
            'researcher_submission.total_items',
            'researcher_submission.total_files',
            'researcher_submission.created_at',
            'researcher_submission.updated_at',
            'actor_i18n.authorized_form_of_name as researcher_name',
            'user.username',
        ])
            ->orderBy('researcher_submission.updated_at', 'desc')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits'  => $submissions,
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        return view('ahg-researcher-manage::submissions', [
            'pager' => $pager,
            'currentStatus' => 'pending',
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * GET: Show new submission form.
     */
    public function newSubmission()
    {
        return view('ahg-researcher-manage::new-submission');
    }

    /**
     * GET: Show import exchange form.
     * POST is handled by importExchangeStore().
     */
    public function importExchange()
    {
        $culture = app()->getLocale();

        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name', 'asc')
            ->get()
            ->map(fn($row) => (array) $row)
            ->toArray();

        $result = session('import_result');

        return view('ahg-researcher-manage::import-exchange', [
            'repositories' => $repositories,
            'result' => $result,
        ]);
    }

    /**
     * POST: Handle import exchange file upload.
     */
    public function importExchangeStore(Request $request)
    {
        $request->validate([
            'exchange_file' => 'required|file|mimes:json,txt|max:51200',
            'repository_id' => 'nullable|integer',
        ]);

        $file = $request->file('exchange_file');
        $contents = file_get_contents($file->getRealPath());
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->route('researcher.import')
                ->withErrors(['exchange_file' => 'Invalid JSON file: ' . json_last_error_msg()]);
        }

        // Validate expected structure
        if (!is_array($data)) {
            return redirect()->route('researcher.import')
                ->withErrors(['exchange_file' => 'JSON file must contain a valid object.']);
        }

        $user = auth()->user();
        $repositoryId = $request->input('repository_id');

        // Count items from the exchange data
        $notes = is_array($data['notes'] ?? null) ? count($data['notes']) : 0;
        $files = is_array($data['files'] ?? null) ? count($data['files']) : 0;
        $items = is_array($data['new_items'] ?? null) ? count($data['new_items']) : 0;
        $creators = is_array($data['new_creators'] ?? null) ? count($data['new_creators']) : 0;
        $repos = is_array($data['new_repositories'] ?? null) ? count($data['new_repositories']) : 0;
        $collections = is_array($data['collections'] ?? null) ? count($data['collections']) : 0;

        $title = $data['title'] ?? ($data['source'] ?? 'Imported exchange');

        // Store the original file
        $storagePath = 'researcher-imports/' . $user->id;
        $filename = date('Y-m-d_His') . '_' . $file->getClientOriginalName();
        $file->storeAs($storagePath, $filename, 'local');

        // Create the draft submission
        $submissionId = DB::table('researcher_submission')->insertGetId([
            'user_id'          => $user->id,
            'title'            => $title,
            'description'      => $data['description'] ?? null,
            'repository_id'    => $repositoryId,
            'parent_object_id' => null,
            'project_id'       => null,
            'source_type'      => 'online',
            'source_file'      => $storagePath . '/' . $filename,
            'status'           => 'draft',
            'total_items'      => $items + $collections,
            'total_files'      => $files,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $result = [
            'submission_id' => $submissionId,
            'title'         => $title,
            'notes'         => $notes,
            'files'         => $files,
            'items'         => $items,
            'creators'      => $creators,
            'repos'         => $repos,
            'collections'   => $collections,
        ];

        return redirect()->route('researcher.import')
            ->with('import_result', $result)
            ->with('success', 'Exchange file imported successfully. A draft submission has been created.');
    }

    // =========================================================================
    // RESEARCH INTEGRATION HELPERS
    // =========================================================================

    /**
     * Get researcher profile for a user.
     */
    protected function getResearcherProfile(int $userId): ?object
    {
        try {
            return DB::table('research_researcher')
                ->where('user_id', $userId)
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get research projects for a researcher.
     */
    protected function getResearchProjects(int $researcherId, int $limit = 5): array
    {
        try {
            return DB::table('research_project')
                ->where('owner_id', $researcherId)
                ->whereIn('status', ['planning', 'active', 'on_hold', 'completed'])
                ->orderByRaw("FIELD(status, 'active', 'planning', 'on_hold', 'completed')")
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get research collections for a researcher with item counts.
     */
    protected function getResearchCollections(int $researcherId, int $limit = 5): array
    {
        try {
            $collections = DB::table('research_collection')
                ->where('researcher_id', $researcherId)
                ->orderBy('updated_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();

            foreach ($collections as &$col) {
                $col->item_count = DB::table('research_collection_item')
                    ->where('collection_id', $col->id)
                    ->count();
            }

            return $collections;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recent research annotations for a researcher.
     */
    protected function getResearchAnnotations(int $researcherId, int $limit = 5): array
    {
        try {
            return DB::table('research_annotation as a')
                ->leftJoin('information_object_i18n as io', function ($j) {
                    $j->on('a.object_id', '=', 'io.id')->where('io.culture', '=', app()->getLocale());
                })
                ->where('a.researcher_id', $researcherId)
                ->select('a.*', 'io.title as object_title')
                ->orderBy('a.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    public function researcherBrowse(Request $request) { return view('ahg-researcher-manage::researcher-browse', ['rows' => collect()]); }

    public function researcherAdd(Request $request) { return view('ahg-researcher-manage::researcher-add', ['record' => (object)[]]); }

    public function researcherEdit(Request $request, int $id) { return view('ahg-researcher-manage::researcher-edit', ['record' => (object)['id'=>$id]]); }

    public function researcherView(int $id) { return view('ahg-researcher-manage::researcher-view', ['record' => (object)['id'=>$id]]); }

    public function researcherDelete(int $id) { return view('ahg-researcher-manage::researcher-delete', ['record' => (object)['id'=>$id]]); }

    public function submissionView(int $id) { return view('ahg-researcher-manage::submission-view', ['record' => (object)['id'=>$id]]); }
}
