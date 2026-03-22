<?php

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
     * Dashboard with stats cards, recent events, at-risk formats.
     */
    public function index()
    {
        $stats = $this->service->getStatistics();
        $recentEvents = $this->service->getEvents(10);
        $atRiskFormats = $this->service->getAtRiskFormats();

        return view('ahg-preservation::index', compact('stats', 'recentEvents', 'atRiskFormats'));
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
        $events = $this->service->getEvents(50, $digitalObjectId);

        return view('ahg-preservation::events', compact('events', 'digitalObjectId'));
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
     * Virus scan dashboard.
     */
    public function virusScan()
    {
        $scans = $this->service->getVirusScans(50);

        return view('ahg-preservation::virus-scan', compact('scans'));
    }

    /**
     * Preservation policies list.
     */
    public function policies()
    {
        $policies = $this->service->getPolicies();

        return view('ahg-preservation::policies', compact('policies'));
    }

    /**
     * OAIS package browser with type filter (SIP/AIP/DIP).
     */
    public function packages(Request $request)
    {
        $type = $request->input('type');
        $packages = $this->service->getPackages(50, $type);

        return view('ahg-preservation::packages', compact('packages', 'type'));
    }

    /**
     * Package detail with objects.
     */
    public function packageView(int $id)
    {
        $package = $this->service->getPackage($id);
        if (!$package) {
            abort(404, 'Package not found');
        }

        return view('ahg-preservation::package-view', compact('package'));
    }

    /**
     * Workflow schedules list.
     */
    public function scheduler()
    {
        $schedules = $this->service->getSchedules();

        return view('ahg-preservation::scheduler', compact('schedules'));
    }

    /**
     * Backup verification dashboard: replication targets + recent verifications.
     */
    public function backup()
    {
        $targets = $this->service->getReplicationTargets();
        $verifications = $this->service->getBackupVerifications(20);
        $replicationLogs = $this->service->getReplicationLogs(20);

        return view('ahg-preservation::backup', compact('targets', 'verifications', 'replicationLogs'));
    }

    /**
     * Preservation reports: objects without checksums, stale fixity, high-risk formats.
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
     * Format conversion dashboard.
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
     * Format identification dashboard.
     */
    public function identification()
    {
        $stats = $this->service->getIdentificationStats();
        $identifications = $this->service->getRecentIdentifications(20);

        return view('ahg-preservation::identification', compact('stats', 'identifications'));
    }

    /**
     * Preservation object detail — checksums, events, format info.
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

        return view('ahg-preservation::object', compact('digitalObject', 'formatInfo', 'checksums', 'events'));
    }

    /**
     * Edit OAIS package form.
     */
    public function packageEdit(int $id)
    {
        $package = $this->service->getPackage($id);
        if (!$package) {
            abort(404, 'Package not found');
        }

        $formAction = route('preservation.package-view', $id);

        return view('ahg-preservation::package-edit', compact('package', 'formAction'));
    }

    /**
     * Edit schedule form.
     */
    public function scheduleEdit(int $id)
    {
        $schedule = null;
        $formAction = route('preservation.scheduler');

        return view('ahg-preservation::schedule-edit', compact('schedule', 'formAction'));
    }
}
