<?php

/**
 * ScanDashboardController — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ScanDashboardController extends Controller
{
    public function index()
    {
        $base = DB::table('ingest_file as f')
            ->join('ingest_session as s', 'f.session_id', '=', 's.id')
            ->where('s.session_kind', '!=', 'wizard');

        $counts = [
            'pending' => (clone $base)->where('f.status', 'pending')->count(),
            'processing' => (clone $base)->where('f.status', 'processing')->count(),
            'done' => (clone $base)->where('f.status', 'done')->count(),
            'failed' => (clone $base)->where('f.status', 'failed')->count(),
            'duplicate' => (clone $base)->where('f.status', 'duplicate')->count(),
            'quarantined' => (clone $base)->where('f.status', 'quarantined')->count(),
        ];

        $last24h = (clone $base)
            ->where('f.created_at', '>=', now()->subDay())
            ->count();

        $doneLast24h = (clone $base)
            ->where('f.status', 'done')
            ->where('f.completed_at', '>=', now()->subDay())
            ->count();

        $folders = DB::table('scan_folder as sf')
            ->leftJoin('ingest_file as f', 'f.session_id', '=', 'sf.ingest_session_id')
            ->select(
                'sf.id', 'sf.code', 'sf.label', 'sf.enabled', 'sf.last_scanned_at',
                DB::raw('SUM(CASE WHEN f.status = "pending" THEN 1 ELSE 0 END) AS pending'),
                DB::raw('SUM(CASE WHEN f.status = "failed" THEN 1 ELSE 0 END) AS failed'),
                DB::raw('SUM(CASE WHEN f.status = "done" THEN 1 ELSE 0 END) AS done'),
                DB::raw('MAX(f.completed_at) AS last_done')
            )
            ->groupBy('sf.id', 'sf.code', 'sf.label', 'sf.enabled', 'sf.last_scanned_at')
            ->orderBy('sf.label')
            ->get();

        $recent = DB::table('ingest_file as f')
            ->join('ingest_session as s', 'f.session_id', '=', 's.id')
            ->leftJoin('scan_folder as sf', 'sf.ingest_session_id', '=', 's.id')
            ->where('s.session_kind', '!=', 'wizard')
            ->select(
                'f.id', 'f.original_name', 'f.status', 'f.stage',
                'f.resolved_io_id', 'f.created_at', 'f.completed_at',
                'f.error_message', 'sf.code as folder_code'
            )
            ->orderByDesc('f.id')
            ->limit(20)
            ->get();

        return view('ahg-scan::admin.scan.dashboard', compact('counts', 'last24h', 'doneLast24h', 'folders', 'recent'));
    }
}
