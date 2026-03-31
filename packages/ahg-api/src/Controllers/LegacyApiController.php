<?php

/**
 * LegacyApiController - Controller for Heratio
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


use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * GET/POST /api/search/io — Legacy IO search.
     */
    public function searchIo(Request $request): JsonResponse
    {
        $queryStr = $request->get('query', $request->get('q', ''));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $skip = max(0, (int) $request->get('skip', 0));

        if (empty($queryStr)) {
            return response()->json(['error' => 'Query parameter required.'], 400);
        }

        $searchTerm = '%' . $queryStr . '%';

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('ioi.culture', $this->culture)
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 160)
            ->where(function ($q) use ($searchTerm) {
                $q->where('ioi.title', 'LIKE', $searchTerm)
                    ->orWhere('io.identifier', 'LIKE', $searchTerm)
                    ->orWhere('ioi.scope_and_content', 'LIKE', $searchTerm);
            });

        $total = $query->count();

        $results = $query
            ->select('io.id', 'ioi.title', 'io.identifier', 'slug.slug',
                'io.level_of_description_id', 'io.repository_id')
            ->orderByRaw("CASE WHEN ioi.title LIKE ? THEN 0 ELSE 1 END", [$searchTerm])
            ->offset($skip)
            ->limit($limit)
            ->get();

        return response()->json([
            'total' => $total,
            'limit' => $limit,
            'skip' => $skip,
            'results' => $results,
        ]);
    }

    /**
     * GET/POST /api/autocomplete/glam — GLAM autocomplete for search boxes.
     */
    public function autocompleteGlam(Request $request): JsonResponse
    {
        $queryStr = $request->get('query', $request->get('q', ''));
        $limit = min(20, max(1, (int) $request->get('limit', 10)));

        if (strlen($queryStr) < 2) {
            return response()->json(['results' => []]);
        }

        $searchTerm = $queryStr . '%';

        // Search titles
        $titles = DB::table('information_object_i18n as ioi')
            ->join('information_object as io', 'ioi.id', '=', 'io.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('ioi.culture', $this->culture)
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 160)
            ->where('ioi.title', 'LIKE', $searchTerm)
            ->select('io.id', 'ioi.title as label', 'slug.slug')
            ->selectRaw("'description' as type")
            ->limit($limit)
            ->get();

        // Search actor names
        $actors = DB::table('actor_i18n as ai')
            ->join('actor', 'ai.id', '=', 'actor.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('ai.culture', $this->culture)
            ->where('object.class_name', 'QubitActor')
            ->where('actor.parent_id', '!=', 0)
            ->where('ai.authorized_form_of_name', 'LIKE', $searchTerm)
            ->select('actor.id', 'ai.authorized_form_of_name as label', 'slug.slug')
            ->selectRaw("'authority' as type")
            ->limit($limit)
            ->get();

        // Search repository names
        $repos = DB::table('actor_i18n as ai')
            ->join('repository', 'ai.id', '=', 'repository.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('ai.culture', $this->culture)
            ->where('object.class_name', 'QubitRepository')
            ->where('ai.authorized_form_of_name', 'LIKE', $searchTerm)
            ->select('repository.id', 'ai.authorized_form_of_name as label', 'slug.slug')
            ->selectRaw("'repository' as type")
            ->limit($limit)
            ->get();

        $results = $titles->merge($actors)->merge($repos)->take($limit);

        return response()->json(['results' => $results->values()]);
    }

    /**
     * GET /api/export-preview — Export statistics.
     *
     * If ?collection= is provided, returns collection-specific stats + hierarchy.
     * Otherwise returns global counts.
     */
    public function exportPreview(Request $request): JsonResponse
    {
        $collectionId = $request->get('collection');

        if ($collectionId) {
            return $this->collectionExportPreview((int) $collectionId);
        }

        $ioCount = DB::table('information_object')->where('id', '!=', 1)->count();
        $actorCount = DB::table('actor')->join('object', 'actor.id', '=', 'object.id')
            ->where('object.class_name', 'QubitActor')->where('actor.parent_id', '!=', 0)->count();
        $repoCount = DB::table('repository')->join('object', 'repository.id', '=', 'object.id')
            ->where('object.class_name', 'QubitRepository')->count();

        $publishedCount = DB::table('information_object as io')
            ->join('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 160)
            ->count();

        return response()->json([
            'descriptions' => $ioCount,
            'published_descriptions' => $publishedCount,
            'authority_records' => $actorCount,
            'repositories' => $repoCount,
        ]);
    }

    /**
     * Collection-specific export preview with hierarchy.
     */
    protected function collectionExportPreview(int $collectionId): JsonResponse
    {
        $collection = DB::table('information_object')->where('id', $collectionId)->first();
        if (!$collection) {
            return response()->json(['error' => 'Collection not found'], 404);
        }

        // Count descendants
        $totalDescriptions = DB::table('information_object')
            ->where('lft', '>=', $collection->lft)
            ->where('rgt', '<=', $collection->rgt)
            ->count();

        // Count digital objects
        $digitalObjects = DB::table('digital_object')
            ->join('information_object as io', 'digital_object.object_id', '=', 'io.id')
            ->where('io.lft', '>=', $collection->lft)
            ->where('io.rgt', '<=', $collection->rgt)
            ->count();

        // Estimate sizes
        $totalSize = DB::table('digital_object')
            ->join('information_object as io', 'digital_object.object_id', '=', 'io.id')
            ->where('io.lft', '>=', $collection->lft)
            ->where('io.rgt', '<=', $collection->rgt)
            ->sum('digital_object.byte_size') ?? 0;

        $csvSize = $totalDescriptions * 500;
        $estimatedSize = $totalSize + $csvSize;

        // Get hierarchy (limited depth for preview)
        $hierarchy = $this->getHierarchy($collectionId, $collection->lft, $collection->rgt, 3);

        return response()->json([
            'totalDescriptions' => number_format($totalDescriptions),
            'digitalObjects' => number_format($digitalObjects),
            'estimatedSize' => $this->formatBytes($estimatedSize),
            'hierarchy' => $hierarchy,
        ]);
    }

    /**
     * Build hierarchy tree for preview.
     */
    protected function getHierarchy(int $parentId, int $parentLft, int $parentRgt, int $maxDepth, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return [];
        }

        $children = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', $this->culture);
            })
            ->leftJoin('term_i18n as lod', function ($j) {
                $j->on('io.level_of_description_id', '=', 'lod.id')->where('lod.culture', $this->culture);
            })
            ->where('io.parent_id', $parentId)
            ->select('io.id', 'io.identifier', 'io.lft', 'io.rgt', 'ioi.title', 'lod.name as level')
            ->orderBy('io.lft')
            ->limit(11) // Fetch one extra to detect truncation
            ->get();

        $hierarchy = [];
        $count = 0;

        foreach ($children as $child) {
            if ($count >= 10) {
                $remaining = DB::table('information_object')->where('parent_id', $parentId)->count() - 10;
                if ($remaining > 0) {
                    $hierarchy[] = [
                        'title' => sprintf('... and %d more', $remaining),
                        'count' => null,
                        'children' => [],
                    ];
                }
                break;
            }

            $childCount = (int) (($child->rgt - $child->lft - 1) / 2);

            $hierarchy[] = [
                'title' => $child->title,
                'identifier' => $child->identifier,
                'level' => $child->level,
                'count' => $childCount > 0 ? $childCount : null,
                'children' => $childCount > 0
                    ? $this->getHierarchy($child->id, $child->lft, $child->rgt, $maxDepth, $currentDepth + 1)
                    : [],
            ];

            $count++;
        }

        return $hierarchy;
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * GET /api/reports/pending-counts — Counts for UI badges.
     *
     * Ported from AtoM apiReportsPendingCountsAction — returns counts for
     * access requests, loans, condition alerts, valuation alerts, pending
     * approvals, clearance expiry, draft descriptions, and failed jobs.
     */
    public function pendingCounts(): JsonResponse
    {
        // Draft descriptions
        $draftCount = DB::table('information_object as io')
            ->join('status', function ($j) {
                $j->on('io.id', '=', 'status.object_id')->where('status.type_id', 158);
            })
            ->where('io.id', '!=', 1)
            ->where('status.status_id', 159)
            ->count();

        // Failed jobs (if table exists)
        $failedJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Pending access requests
        $accessRequests = 0;
        try {
            $accessRequests = (int) DB::table('access_request')
                ->where('status', 'pending')
                ->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Pending/overdue loans (due within 30 days)
        $pendingLoans = 0;
        try {
            $pendingLoans = (int) DB::table('spectrum_loan_out')
                ->whereIn('status', ['active', 'overdue'])
                ->whereRaw('loan_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                ->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Condition alerts (items with poor condition or needing check)
        $conditionAlerts = 0;
        try {
            $conditionAlerts = (int) DB::table('spectrum_condition_check')
                ->where(function ($query) {
                    $query->where('overall_condition', '>=', 4)
                        ->orWhereRaw('(next_check_date IS NOT NULL AND next_check_date <= CURDATE())');
                })
                ->distinct()
                ->count('information_object_id');
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Valuation alerts (valuations due for renewal within 60 days)
        $valuationAlerts = 0;
        try {
            $valuationAlerts = (int) DB::table('spectrum_valuation')
                ->where('is_current', 1)
                ->whereRaw('next_valuation_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)')
                ->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Pending workflow approvals
        $pendingApprovals = 0;
        try {
            $pendingApprovals = (int) DB::table('workflow_state')
                ->whereIn('current_state', ['pending_approval', 'under_review', 'submitted'])
                ->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        // Clearance expiry warnings (within 60 days)
        $clearanceExpiry = 0;
        try {
            $clearanceExpiry = (int) DB::table('user_security_clearance')
                ->where('is_active', 1)
                ->whereRaw('expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)')
                ->count();
        } catch (\Throwable $e) {
            // Table may not exist
        }

        return response()->json([
            'draft_descriptions' => $draftCount,
            'failed_jobs' => $failedJobs,
            'accessRequests' => $accessRequests,
            'pendingLoans' => $pendingLoans,
            'conditionAlerts' => $conditionAlerts,
            'valuationAlerts' => $valuationAlerts,
            'pendingApprovals' => $pendingApprovals,
            'clearanceExpiry' => $clearanceExpiry,
        ]);
    }
}
