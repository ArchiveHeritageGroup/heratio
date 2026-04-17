<?php

/**
 * JobsController - Jobs management controller
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

namespace Ahg\Jobs\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Ahg\Jobs\Services\JobsService;

class JobsController extends Controller
{
    protected JobsService $jobsService;

    public function __construct(JobsService $jobsService)
    {
        $this->jobsService = $jobsService;
    }

    /**
     * Browse jobs
     */
    public function browse(Request $request)
    {
        $filters = $request->only(['status', 'job_type', 'sort']);
        $result = $this->jobsService->browse([
            'status' => $filters['status'] ?? null,
            'job_type' => $filters['job_type'] ?? null,
            'sort' => $filters['sort'] ?? 'date',
            'page' => $request->get('page', 1),
            'limit' => 25,
        ]);

        $stats = $this->jobsService->getStats();

        return view('ahg-jobs::browse', [
            'pager' => (object) [
                'items' => fn() => collect($result['data']),
                'hasPages' => fn() => $result['total_pages'] > 1,
                'links' => fn() => '',
            ],
            'stats' => $stats,
            'filters' => $filters,
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'total_pages' => $result['total_pages'],
        ]);
    }

    /**
     * Show job detail
     */
    public function show(int $id)
    {
        $job = $this->jobsService->find($id);

        if (!$job) {
            abort(404);
        }

        return view('ahg-jobs::show', [
            'job' => (object) $job,
        ]);
    }

    /**
     * Clear inactive jobs
     */
    public function clearInactive(Request $request)
    {
        $daysOld = (int) $request->input('days', 30);
        $count = $this->jobsService->clearInactive($daysOld);

        return redirect()->route('jobs.browse')
            ->with('success', "Cleared {$count} inactive job(s).");
    }

    /**
     * Export jobs to CSV
     */
    public function exportCsv(): StreamedResponse
    {
        $jobs = $this->jobsService->browse(['limit' => 10000]);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="jobs-export.csv"',
        ];

        return new StreamedResponse(function () use ($jobs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Type', 'Status', 'Created', 'Completed', 'Duration']);

            foreach ($jobs['data'] as $job) {
                fputcsv($handle, [
                    $job['id'] ?? '',
                    $job['name'] ?? '',
                    $job['type'] ?? '',
                    $job['status_id'] ?? '',
                    $job['created_at'] ?? '',
                    $job['completed_at'] ?? '',
                    $job['duration'] ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
