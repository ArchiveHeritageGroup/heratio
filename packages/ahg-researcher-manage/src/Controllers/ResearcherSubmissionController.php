<?php

/**
 * ResearcherSubmissionController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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
            'returned_rejected' => (clone $baseQuery)->whereIn('status', ['returned', 'rejected'])->count(),
        ];

        $recentQuery = DB::table('researcher_submission')
            ->leftJoin('user', 'researcher_submission.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', app()->getLocale());
            })
            ->select([
                'researcher_submission.id',
                'researcher_submission.title',
                'researcher_submission.source_type',
                'researcher_submission.status',
                'researcher_submission.total_items',
                'researcher_submission.total_files',
                'researcher_submission.updated_at',
                'actor_i18n.authorized_form_of_name as researcher_name',
                'user.username',
            ])
            ->orderBy('researcher_submission.updated_at', 'desc')
            ->limit(10);

        if (!$isAdmin) {
            $recentQuery->where('researcher_submission.user_id', $user->id);
        }

        $recentSubmissions = $recentQuery->get()->map(fn($row) => (array) $row)->toArray();

        return view('ahg-researcher-manage::dashboard', [
            'stats' => $stats,
            'recentSubmissions' => $recentSubmissions,
            'isAdmin' => $isAdmin,
        ]);
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
     * Alias: pending submissions (status=submitted).
     */
    public function pending(Request $request)
    {
        $request->merge(['status' => 'submitted']);
        return $this->submissions($request);
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

    public function researcherBrowse(Request $request) { return view('ahg-researcher-manage::researcher-browse', ['rows' => collect()]); }

    public function researcherAdd(Request $request) { return view('ahg-researcher-manage::researcher-add', ['record' => (object)[]]); }

    public function researcherEdit(Request $request, int $id) { return view('ahg-researcher-manage::researcher-edit', ['record' => (object)['id'=>$id]]); }

    public function researcherView(int $id) { return view('ahg-researcher-manage::researcher-view', ['record' => (object)['id'=>$id]]); }

    public function researcherDelete(int $id) { return view('ahg-researcher-manage::researcher-delete', ['record' => (object)['id'=>$id]]); }

    public function submissionView(int $id) { return view('ahg-researcher-manage::submission-view', ['record' => (object)['id'=>$id]]); }
}
