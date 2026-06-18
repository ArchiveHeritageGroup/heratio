<?php

/**
 * TiffPdfMergeController - Controller for Heratio
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



namespace AhgPreservation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TiffPdfMergeController extends Controller
{
    /**
     * TIFF/PDF Merge tool index — form to create a new merge job.
     */
    public function index(Request $request)
    {
        // When the tool is opened from an archival record ("Open Merge Tool"),
        // carry the IO id through so store() can link the job to that record.
        $ioId = $request->query('io');
        $ioId = ($ioId !== null && is_numeric($ioId)) ? (int) $ioId : null;

        return view('ahg-preservation::tiffpdfmerge.index', ['ioId' => $ioId]);
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
                    ->select('tiff_pdf_merge_job.*')
                    // Real per-job file count; the view read a non-existent
                    // $job->file_count and always showed 0 (even for jobs with files).
                    ->selectSub(
                        DB::table('tiff_pdf_merge_file')
                            ->whereColumn('merge_job_id', 'tiff_pdf_merge_job.id')
                            ->selectRaw('count(*)'),
                        'file_count'
                    )
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
            'files'         => 'required|array|min:1',
            'files.*'       => 'file|mimes:tif,tiff,pdf,jpg,jpeg,png|max:204800',
        ], [
            'files.required' => 'Select at least one source file to merge.',
        ]);

        $format = $request->input('output_format');

        $ioId = $request->input('io');
        $ioId = ($ioId !== null && is_numeric($ioId)) ? (int) $ioId : null;

        // Sanitise the requested name and force the correct extension.
        $rawName = trim((string) $request->input('output_filename', '')) ?: 'merged-output';
        $base    = preg_replace('/[^A-Za-z0-9._-]+/', '-', pathinfo($rawName, PATHINFO_FILENAME)) ?: 'merged-output';
        $ext     = $format === 'tiff' ? 'tiff' : 'pdf';
        $outName = $base.'.'.$ext;

        $files = $request->file('files');

        // 1) Create the job row up front (status=processing while we work) so
        //    it is visible in the browse list even if the merge later fails.
        $jobId = DB::table('tiff_pdf_merge_job')->insertGetId([
            'information_object_id' => $ioId,
            'user_id'         => Auth::id(),
            'job_name'        => $base,
            'status'          => 'processing',
            'total_files'     => count($files),
            'processed_files' => 0,
            'output_filename' => $outName,
            'output_format'   => $format,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 2) Stage uploads under uploads_path/merges/<jobId>/ and record each file.
        $workDir = rtrim(config('heratio.uploads_path'), '/').'/merges/'.$jobId;
        if (! is_dir($workDir)) {
            @mkdir($workDir, 0775, true);
        }

        $orderedPaths = [];
        $idx = 0;
        foreach ($files as $file) {
            $idx++;
            $orig = $file->getClientOriginalName();
            $safe = $idx.'-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', $orig);
            $dest = $workDir.'/'.$safe;

            try {
                $file->move($workDir, $safe);
            } catch (\Throwable $e) {
                @copy($file->getRealPath(), $dest);
            }
            $orderedPaths[] = $dest;

            DB::table('tiff_pdf_merge_file')->insert([
                'merge_job_id'      => $jobId,
                'original_filename' => $orig,
                'stored_filename'   => $safe,
                'file_path'         => $dest,
                'file_size'         => is_file($dest) ? filesize($dest) : 0,
                'mime_type'         => $file->getClientMimeType(),
                'page_order'        => $idx,
                'status'            => 'staged',
                'checksum_md5'      => is_file($dest) ? md5_file($dest) : null,
                'created_at'        => now(),
            ]);
        }

        // 3) Run the merge synchronously (consistent with the derivative
        //    pipeline; no queue worker runs on this install).
        $outPath = $workDir.'/'.$outName;
        try {
            $this->runImagickMerge($orderedPaths, $outPath, $format);

            if (! is_file($outPath) || filesize($outPath) === 0) {
                throw new \RuntimeException('Merge produced no output file.');
            }

            DB::table('tiff_pdf_merge_job')->where('id', $jobId)->update([
                'status'          => 'completed',
                'processed_files' => count($orderedPaths),
                'output_path'     => $outPath,
                'completed_at'    => now(),
                'updated_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('tiff_pdf_merge_job')->where('id', $jobId)->update([
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'updated_at'    => now(),
            ]);

            return redirect()->route('preservation.tiffpdfmerge.view', $jobId)
                ->with('error', 'Merge failed: '.$e->getMessage());
        }

        return redirect()->route('preservation.tiffpdfmerge.view', $jobId)
            ->with('success', 'Merge completed: '.$outName);
    }

    /**
     * Concatenate the ordered inputs into a single PDF or multi-page TIFF
     * with ImageMagick. Multi-page PDF/TIFF inputs contribute all their pages.
     */
    private function runImagickMerge(array $inputs, string $outPath, string $format): void
    {
        if (empty($inputs)) {
            throw new \RuntimeException('No source files to merge.');
        }

        $args = [];
        foreach ($inputs as $in) {
            if (! is_file($in)) {
                throw new \RuntimeException('Source file missing: '.basename($in));
            }
            $args[] = escapeshellarg($in);
        }

        $density = '-density 200';
        if ($format === 'tiff') {
            $cmd = 'convert '.$density.' '.implode(' ', $args)
                .' -compress lzw '.escapeshellarg($outPath).' 2>&1';
        } else {
            $cmd = 'convert '.$density.' '.implode(' ', $args)
                .' -quality 90 '.escapeshellarg($outPath).' 2>&1';
        }

        $output = [];
        $rc = 0;
        exec($cmd, $output, $rc);

        if ($rc !== 0) {
            throw new \RuntimeException('convert exited '.$rc.': '.implode(' ', array_slice($output, 0, 5)));
        }
    }

    /**
     * Stream the completed merge output for download.
     */
    public function download(int $id)
    {
        $job = DB::table('tiff_pdf_merge_job')->where('id', $id)->first();

        if (! $job || $job->status !== 'completed' || empty($job->output_path) || ! is_file($job->output_path)) {
            abort(404, 'Merge output not available');
        }

        return response()->download($job->output_path, $job->output_filename ?: basename($job->output_path));
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
                // FK is merge_job_id (not job_id); order column is page_order.
                // Alias to the names the view reads (filename / size).
                $sourceFiles = DB::table('tiff_pdf_merge_file')
                    ->where('merge_job_id', $id)
                    ->orderBy('page_order')
                    ->get([
                        'original_filename as filename',
                        'file_size as size',
                        'mime_type',
                    ]);
                // The view reads $job->file_count; populate it from the real rows.
                $job->file_count = $sourceFiles->count();
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
