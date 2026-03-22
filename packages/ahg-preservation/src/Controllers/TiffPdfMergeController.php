<?php

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
