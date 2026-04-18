<?php

/**
 * PreservationController - Controller for Heratio
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

use AhgPreservation\Services\PreservationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PreservationController extends Controller
{
    public function __construct(protected PreservationService $service)
    {
    }

    /**
     * Dashboard with stats cards, quick actions, recent fixity, events, at-risk formats.
     */
    public function index()
    {
        $stats = $this->service->getStatistics();
        $recentEvents = $this->service->getEvents(10);
        $atRiskFormats = $this->service->getAtRiskFormats();
        $recentFixity = $this->service->getFixityLog(10);

        return view('ahg-preservation::index', compact('stats', 'recentEvents', 'atRiskFormats', 'recentFixity'));
    }

    /**
     * Fixity check log with status filter.
     */
    public function fixityLog(Request $request)
    {
        $status = $request->input('status');
        $logs = $this->service->getFixityLog(50, $status);

        return view('ahg-preservation::fixity-log', compact('logs', 'status'));
    }

    /**
     * PREMIS events list.
     */
    public function events(Request $request)
    {
        $digitalObjectId = $request->input('digital_object_id') ? (int) $request->input('digital_object_id') : null;
        $currentType     = $request->input('type');
        $events          = $this->service->getEvents(50, $digitalObjectId, $currentType);

        $eventTypes = [];
        try {
            $eventTypes = \Illuminate\Support\Facades\DB::table('preservation_event')
                ->select('event_type', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
                ->groupBy('event_type')
                ->orderBy('event_type')
                ->get();
        } catch (\Throwable $e) {
            $eventTypes = collect();
        }

        return view('ahg-preservation::events', compact('events', 'digitalObjectId', 'eventTypes', 'currentType'));
    }

    /**
     * Format registry browser.
     */
    public function formats()
    {
        $formats = $this->service->getFormats();

        return view('ahg-preservation::formats', compact('formats'));
    }

    /**
     * Virus scan dashboard with ClamAV status, stats, and recent scans.
     */
    public function virusScan()
    {
        $scans = $this->service->getVirusScans(50);

        // ClamAV availability check
        $clamAvAvailable = false;
        $clamAvVersion = null;
        try {
            $output = @shell_exec('clamscan --version 2>&1');
            if ($output && str_contains($output, 'ClamAV')) {
                $clamAvAvailable = true;
                $parts = explode('/', trim($output));
                $clamAvVersion = [
                    'scanner' => 'ClamAV',
                    'version' => trim($parts[0] ?? ''),
                    'database' => trim($parts[1] ?? 'unknown'),
                ];
            }
        } catch (\Exception $e) {
            // ClamAV not available
        }

        // Scan stats
        $scanStats = ['clean' => 0, 'infected' => 0, 'error' => 0];
        try {
            $rows = \Illuminate\Support\Facades\DB::table('preservation_virus_scan')
                ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
                ->groupBy('status')
                ->get();
            foreach ($rows as $row) {
                $scanStats[$row->status] = $row->cnt;
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Unscanned objects
        $unscannedObjects = 0;
        try {
            $totalObjects = \Illuminate\Support\Facades\DB::table('digital_object')->count();
            $scannedObjects = \Illuminate\Support\Facades\DB::table('preservation_virus_scan')
                ->distinct('digital_object_id')
                ->count('digital_object_id');
            $unscannedObjects = $totalObjects - $scannedObjects;
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-preservation::virus-scan', compact(
            'scans', 'clamAvAvailable', 'clamAvVersion', 'scanStats', 'unscannedObjects'
        ));
    }

    /**
     * Preservation policies list with CLI commands.
     */
    public function policies()
    {
        $policies = $this->service->getPolicies();

        return view('ahg-preservation::policies', compact('policies'));
    }

    /**
     * OAIS package browser with type filter (SIP/AIP/DIP) and stats.
     */
    public function packages(Request $request)
    {
        $type = $request->input('type');
        $packages = $this->service->getPackages(50, $type);

        return view('ahg-preservation::packages', compact('packages', 'type'));
    }

    /**
     * Package detail with objects, events, timeline, related packages.
     */
    public function packageView(int $id)
    {
        $package = $this->service->getPackage($id);
        if (!$package) {
            abort(404, 'Package not found');
        }

        // Related packages
        $parentPackage = null;
        $childPackages = [];
        try {
            if ($package->parent_id ?? null) {
                $parentPackage = \Illuminate\Support\Facades\DB::table('preservation_package')
                    ->where('id', $package->parent_id)
                    ->first();
            }
            $childPackages = \Illuminate\Support\Facades\DB::table('preservation_package')
                ->where('parent_id', $id)
                ->get();
        } catch (\Exception $e) {
            // Column may not exist
        }

        return view('ahg-preservation::package-view', compact('package', 'parentPackage', 'childPackages'));
    }

    /**
     * Workflow schedules list with stats, recent runs, sidebar.
     */
    public function scheduler()
    {
        $schedules = $this->service->getSchedules();

        // Recent runs across all schedules
        $recentRuns = collect();
        try {
            $recentRuns = \Illuminate\Support\Facades\DB::table('preservation_workflow_run as wr')
                ->leftJoin('preservation_workflow_schedule as ws', 'ws.id', '=', 'wr.schedule_id')
                ->select('wr.*', 'ws.name as schedule_name')
                ->orderByDesc('wr.started_at')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-preservation::scheduler', compact('schedules', 'recentRuns'));
    }

    /**
     * Backup verification dashboard: replication targets, logs, verifications.
     */
    public function backup()
    {
        $targets = $this->service->getReplicationTargets();
        $verifications = $this->service->getBackupVerifications(20);
        $replicationLogs = $this->service->getReplicationLogs(20);

        return view('ahg-preservation::backup', compact('targets', 'verifications', 'replicationLogs'));
    }

    /**
     * Preservation reports: objects without checksums, stale fixity, high-risk formats, summary stats.
     */
    public function reports()
    {
        $noChecksums = $this->service->getObjectsWithoutChecksums(50);
        $staleFixity = $this->service->getStaleFixityObjects(90, 50);
        $highRisk = $this->service->getHighRiskFormatObjects(50);

        return view('ahg-preservation::reports', compact('noChecksums', 'staleFixity', 'highRisk'));
    }

    /**
     * JSON: generate checksum for a digital object.
     */
    public function apiGenerateChecksum(Request $request, int $id)
    {
        $algorithm = $request->input('algorithm', 'sha256');
        $result = $this->service->generateChecksum($id, $algorithm);

        if (!$result) {
            return response()->json(['error' => 'Digital object not found or file missing'], 404);
        }

        return response()->json(['success' => true, 'checksum' => $result]);
    }

    /**
     * JSON: verify fixity for a digital object.
     */
    public function apiVerifyFixity(Request $request, int $id)
    {
        $result = $this->service->verifyFixity($id);

        if (!$result) {
            return response()->json(['error' => 'Digital object not found or no checksum exists'], 404);
        }

        return response()->json(['success' => true, 'fixity_check' => $result]);
    }

    /**
     * JSON: preservation stats.
     */
    public function apiStats()
    {
        $stats = $this->service->getStatistics();
        $dailyStats = $this->service->getDailyStats(30);

        return response()->json([
            'stats' => $stats,
            'daily' => $dailyStats,
        ]);
    }

    /**
     * Format conversion dashboard with tool status, stats, supported conversions, recent.
     */
    public function conversion()
    {
        $tools = $this->service->getConversionTools();
        $conversionStats = $this->service->getConversionStats();
        $recentConversions = $this->service->getRecentConversions(20);
        $pendingConversions = $conversionStats['pending'] ?? 0;

        return view('ahg-preservation::conversion', compact('tools', 'conversionStats', 'recentConversions', 'pendingConversions'));
    }

    /**
     * Format identification dashboard with Siegfried status, confidence, risk, top formats, CLI.
     */
    public function identification()
    {
        $stats = $this->service->getIdentificationStats();
        $identifications = $this->service->getRecentIdentifications(20);

        // Siegfried availability
        $siegfriedAvailable = false;
        $siegfriedVersion = [];
        try {
            $sfOutput = @shell_exec('sf -version 2>&1');
            if ($sfOutput && (str_contains($sfOutput, 'siegfried') || str_contains($sfOutput, 'sf'))) {
                $siegfriedAvailable = true;
                $siegfriedVersion = [
                    'version' => trim($sfOutput),
                    'signature_date' => 'Unknown',
                ];
            }
        } catch (\Exception $e) {
            // Not available
        }

        // Confidence distribution
        $byConfidence = ['certain' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        try {
            $confRows = \Illuminate\Support\Facades\DB::table('preservation_identification')
                ->select('confidence', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
                ->groupBy('confidence')
                ->get();
            foreach ($confRows as $row) {
                if (isset($byConfidence[$row->confidence])) {
                    $byConfidence[$row->confidence] = $row->cnt;
                }
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Formats by risk level
        $formatsByRisk = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
        try {
            $riskRows = \Illuminate\Support\Facades\DB::table('preservation_format')
                ->select('risk_level', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
                ->groupBy('risk_level')
                ->get();
            foreach ($riskRows as $row) {
                if (isset($formatsByRisk[$row->risk_level])) {
                    $formatsByRisk[$row->risk_level] = $row->cnt;
                }
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Warnings count
        $withWarnings = 0;
        $identificationsWithWarnings = [];
        try {
            $withWarnings = \Illuminate\Support\Facades\DB::table('preservation_identification')
                ->whereNotNull('warning')
                ->where('warning', '!=', '')
                ->count();
            $identificationsWithWarnings = \Illuminate\Support\Facades\DB::table('preservation_identification as pi')
                ->leftJoin('digital_object as do', 'do.id', '=', 'pi.digital_object_id')
                ->select('pi.*', 'do.name as object_name')
                ->whereNotNull('pi.warning')
                ->where('pi.warning', '!=', '')
                ->orderByDesc('pi.created_at')
                ->limit(5)
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            // Column/table may not exist
        }

        // Top formats
        $topFormats = [];
        try {
            $topFormats = \Illuminate\Support\Facades\DB::table('preservation_identification')
                ->select('puid', 'format_name', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
                ->groupBy('puid', 'format_name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-preservation::identification', compact(
            'stats', 'identifications', 'siegfriedAvailable', 'siegfriedVersion',
            'byConfidence', 'formatsByRisk', 'withWarnings', 'identificationsWithWarnings', 'topFormats'
        ));
    }

    /**
     * Preservation object detail -- checksums, fixity history, events, format info.
     */
    public function object(int $id)
    {
        $digitalObject = $this->service->getDigitalObject($id);
        if (!$digitalObject) {
            abort(404, 'Digital object not found');
        }

        $formatInfo = $this->service->getFormatInfo($digitalObject->mime_type ?? '');
        $checksums = $this->service->getObjectChecksums($id);
        $events = $this->service->getObjectEvents($id, 20);

        // Fixity history
        $fixityHistory = collect();
        try {
            $fixityHistory = \Illuminate\Support\Facades\DB::table('preservation_fixity_check')
                ->where('digital_object_id', $id)
                ->orderByDesc('checked_at')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            // Table may not exist
        }

        return view('ahg-preservation::object', compact('digitalObject', 'formatInfo', 'checksums', 'events', 'fixityHistory'));
    }

    /**
     * Edit OAIS package form.
     */
    public function packageEdit(int $id)
    {
        $package = null;
        $formAction = route('preservation.packages');

        if ($id > 0) {
            $package = $this->service->getPackage($id);
            if (!$package) {
                abort(404, 'Package not found');
            }
            $formAction = route('preservation.package-view', $id);
        }

        return view('ahg-preservation::package-edit', compact('package', 'formAction'));
    }

    /**
     * Edit schedule form with cron presets, execution settings, notifications, runs.
     */
    public function scheduleEdit(int $id)
    {
        $schedule = null;
        $runs = [];
        $formAction = route('preservation.scheduler');

        if ($id > 0) {
            try {
                $schedule = \Illuminate\Support\Facades\DB::table('preservation_workflow_schedule')
                    ->where('id', $id)
                    ->first();
                if ($schedule) {
                    $runs = $this->service->getScheduleRuns($id, 10);
                    $formAction = route('preservation.schedule-edit', $id);
                }
            } catch (\Exception $e) {
                // Table may not exist
            }
        }

        return view('ahg-preservation::schedule-edit', compact('schedule', 'runs', 'formAction'));
    }
}
