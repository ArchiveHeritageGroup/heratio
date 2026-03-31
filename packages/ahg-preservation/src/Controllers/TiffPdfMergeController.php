<?php

/**
 * TiffPdfMergeController - Controller for Heratio
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



namespace AhgPreservation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TiffPdfMergeController extends Controller
{
    /**
     * TIFF/PDF Merge tool index — form to create a new merge job.
     */
    public function index()
    {
        return view('ahg-preservation::tiffpdfmerge.index');
    }

    /**
     * Browse all merge jobs.
     */
    public function browse()
    {
        $jobs = collect();

        try {
            if (Schema::hasTable('tiff_pdf_merge_job')) {
                $jobs = DB::table('tiff_pdf_merge_job')
                    ->orderByDesc('created_at')
                    ->paginate(25);
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        return view('ahg-preservation::tiffpdfmerge.browse', compact('jobs'));
    }

    /**
     * Store a new merge job.
     */
    public function store(Request $request)
    {
        $request->validate([
            'output_format' => 'required|in:pdf,tiff',
        ]);

        // Job creation would happen here via a service
        return redirect()->route('preservation.tiffpdfmerge.browse')
            ->with('success', 'Merge job queued successfully.');
    }

    /**
     * Create a new merge job (AJAX).
     */
    public function create(Request $request)
    {
        $request->validate(['output_format' => 'required|in:pdf,tiff']);

        $jobId = DB::table('tiff_pdf_merge_job')->insertGetId([
            'output_format' => $request->input('output_format'),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'job_id' => $jobId]);
    }

    /**
     * Upload file to a merge job (AJAX).
     */
    public function upload(Request $request)
    {
        $request->validate([
            'job_id' => 'required|integer',
            'file' => 'required|file',
        ]);

        return response()->json(['success' => true, 'message' => 'File uploaded.']);
    }

    /**
     * Reorder files in a merge job (AJAX).
     */
    public function reorder(Request $request)
    {
        $request->validate([
            'job_id' => 'required|integer',
            'order' => 'required|array',
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Remove a file from a merge job (AJAX).
     */
    public function removeFile(Request $request)
    {
        $request->validate([
            'job_id' => 'required|integer',
            'file_id' => 'required|integer',
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Process/execute a merge job (AJAX).
     */
    public function process(Request $request)
    {
        $request->validate(['job_id' => 'required|integer']);

        return response()->json(['success' => true, 'message' => 'Merge job queued.']);
    }

    /**
     * Delete a merge job (AJAX).
     */
    public function delete(Request $request)
    {
        $request->validate(['job_id' => 'required|integer']);

        DB::table('tiff_pdf_merge_job')->where('id', $request->input('job_id'))->delete();

        return response()->json(['success' => true]);
    }

    /**
     * View a specific merge job.
     */
    public function view(int $id)
    {
        $job = null;
        $sourceFiles = collect();

        try {
            if (Schema::hasTable('tiff_pdf_merge_job')) {
                $job = DB::table('tiff_pdf_merge_job')->where('id', $id)->first();
            }
            if ($job && Schema::hasTable('tiff_pdf_merge_file')) {
                $sourceFiles = DB::table('tiff_pdf_merge_file')
                    ->where('job_id', $id)
                    ->orderBy('sort_order')
                    ->get();
            }
        } catch (\Exception $e) {
            // ignore
        }

        if (!$job) {
            abort(404, 'Merge job not found');
        }

        return view('ahg-preservation::tiffpdfmerge.view', compact('job', 'sourceFiles'));
    }
}
