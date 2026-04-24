<?php

/**
 * ScanInboxController — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Controllers;

use AhgScan\Jobs\ProcessScanFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ScanInboxController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        $folder = $request->get('folder');
        $q = $request->get('q');

        $query = DB::table('ingest_file as f')
            ->join('ingest_session as s', 'f.session_id', '=', 's.id')
            ->leftJoin('scan_folder as sf', 'sf.ingest_session_id', '=', 's.id')
            ->where('s.session_kind', '!=', 'wizard')
            ->select(
                'f.id', 'f.original_name', 'f.status', 'f.stage',
                'f.resolved_io_id', 'f.resolved_do_id',
                'f.created_at', 'f.completed_at', 'f.attempts',
                'f.error_message', 'f.file_size',
                'sf.code as folder_code', 'sf.label as folder_label'
            );

        if ($status) { $query->where('f.status', $status); }
        if ($folder) { $query->where('sf.code', $folder); }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('f.original_name', 'like', "%{$q}%")
                  ->orWhere('f.stored_path', 'like', "%{$q}%");
            });
        }

        $files = $query->orderByDesc('f.id')->paginate(50)->appends($request->query());

        $folders = DB::table('scan_folder')->orderBy('label')->get(['code', 'label']);

        $statuses = ['pending', 'processing', 'done', 'failed', 'duplicate', 'quarantined', 'awaiting_rights'];

        return view('ahg-scan::admin.scan.inbox.index', compact('files', 'folders', 'statuses', 'status', 'folder', 'q'));
    }

    public function show(int $id)
    {
        $file = DB::table('ingest_file as f')
            ->join('ingest_session as s', 'f.session_id', '=', 's.id')
            ->leftJoin('scan_folder as sf', 'sf.ingest_session_id', '=', 's.id')
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'f.resolved_io_id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'f.resolved_io_id')->where('i18n.culture', '=', 'en');
            })
            ->where('f.id', $id)
            ->select(
                'f.*',
                's.sector', 's.standard', 's.title as session_title',
                'sf.code as folder_code', 'sf.label as folder_label',
                'sl.slug as io_slug',
                'i18n.title as io_title'
            )
            ->first();
        abort_unless($file, 404);

        return view('ahg-scan::admin.scan.inbox.show', compact('file'));
    }

    public function retry(int $id)
    {
        $file = DB::table('ingest_file')->where('id', $id)->first();
        abort_unless($file, 404);

        DB::table('ingest_file')->where('id', $id)->update([
            'status' => 'pending',
            'stage' => null,
            'error_message' => null,
        ]);

        ProcessScanFile::dispatchSync($id);

        return redirect()->route('scan.inbox.show', $id)->with('notice', 'Retry attempted.');
    }

    public function discard(int $id)
    {
        $file = DB::table('ingest_file')->where('id', $id)->first();
        abort_unless($file, 404);

        DB::table('ingest_file')->where('id', $id)->update([
            'status' => 'quarantined',
            'stage' => null,
            'error_message' => 'Discarded by admin',
            'completed_at' => now(),
        ]);

        return redirect()->route('scan.inbox.index')->with('notice', 'File discarded.');
    }

    /**
     * Release rights hold — resume the pipeline from the point it stopped
     * (deriving + indexing only, skipping re-resolve which would dedupe).
     */
    public function releaseRights(int $id)
    {
        $file = DB::table('ingest_file')->where('id', $id)->first();
        abort_unless($file, 404);
        if ($file->status !== 'awaiting_rights') {
            return redirect()->back()->with('error', 'File is not in awaiting_rights state.');
        }

        DB::table('ingest_file')->where('id', $id)->update([
            'status' => 'processing',
            'completed_at' => null,
        ]);

        try {
            \AhgScan\Jobs\ProcessScanFile::resumeFromDeriving($id);
            DB::table('ingest_file')->where('id', $id)->update([
                'status' => 'done',
                'stage' => null,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            DB::table('ingest_file')->where('id', $id)->update([
                'status' => 'failed',
                'error_message' => 'Resume failed: ' . $e->getMessage(),
            ]);
            return redirect()->route('scan.inbox.show', $id)->with('error', 'Resume failed: ' . $e->getMessage());
        }

        return redirect()->route('scan.inbox.show', $id)->with('notice', 'Rights released; pipeline resumed.');
    }
}
