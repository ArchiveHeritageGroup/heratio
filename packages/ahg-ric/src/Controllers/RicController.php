<?php

/**
 * RicController - Controller for Heratio
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



namespace AhgRic\Controllers;

use AhgRic\Services\RelationshipService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RicController extends Controller
{
    /**
     * Check whether all required RiC tables exist.
     */
    private function tablesExist(): bool
    {
        return Schema::hasTable('ric_sync_status')
            && Schema::hasTable('ric_sync_queue')
            && Schema::hasTable('ric_orphan_tracking')
            && Schema::hasTable('ric_sync_log');
    }

    /**
     * Get related entities for an entity via RelationshipService.
     */
    public function getRelations(Request $request, int $id)
    {
        $service = app(RelationshipService::class);
        $type = $request->input('type');

        return response()->json([
            'success' => true,
            'relations' => $service->getRelatedEntities($id, $type),
        ]);
    }

    /**
     * Get graph summary for an entity.
     */
    public function getGraphSummary(int $id)
    {
        $service = app(RelationshipService::class);

        return response()->json([
            'success' => true,
            'graph' => $service->getGraphSummary($id),
        ]);
    }

    /**
     * Get timeline context for an entity.
     */
    public function getTimeline(int $id)
    {
        $service = app(RelationshipService::class);

        return response()->json([
            'success' => true,
            'events' => $service->getTimelineContext($id),
        ]);
    }

    /**
     * Explain why two entities are related.
     */
    public function explainRelation(int $sourceId, int $targetId)
    {
        $service = app(RelationshipService::class);

        return response()->json([
            'success' => true,
            'explanations' => $service->explainRelationship($sourceId, $targetId),
        ]);
    }

    /**
     * Set view mode (heratio/ric) in session.
     * Handles both AJAX (JSON response) and regular form POST (redirect back).
     */
    public function setViewMode(Request $request)
    {
        $mode = $request->input('mode', 'heratio');
        if (in_array($mode, ['heratio', 'ric'])) {
            session(['ric_view_mode' => $mode]);
        }

        // If it's an AJAX request, return JSON
        if ($request->wantsJson() || $request->hasHeader('X-Requested-With')) {
            return response()->json(['success' => true, 'mode' => session('ric_view_mode', 'heratio')]);
        }

        // For regular form POST, redirect back to previous page
        return redirect()->back();
    }

    /**
     * RiC Dashboard — index page.
     */
    public function index()
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        // Fuseki live status check
        $fusekiStatus = $this->checkFusekiStatusQuick();

        // Config settings (sync_enabled etc.)
        $configSettings = [];
        if (Schema::hasTable('ahg_settings')) {
            $configSettings = DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        }

        // Sync summary: counts by sync_status
        $syncSummary = DB::table('ric_sync_status')
            ->selectRaw("sync_status, COUNT(*) as cnt")
            ->groupBy('sync_status')
            ->pluck('cnt', 'sync_status')
            ->toArray();

        // Queue status: counts by status
        $queueStatus = DB::table('ric_sync_queue')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // Orphan count (detected only)
        $orphanCount = DB::table('ric_orphan_tracking')
            ->where('status', 'detected')
            ->count();

        // Recent 10 operations
        $recentOps = DB::table('ric_sync_log')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // 7-day sync trend
        $sevenDaysAgo = Carbon::now()->subDays(7)->startOfDay();
        $syncTrend = DB::table('ric_sync_log')
            ->selectRaw("DATE(created_at) as log_date, COUNT(*) as cnt")
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupByRaw("DATE(created_at)")
            ->orderBy('log_date')
            ->get();

        // Entity sync breakdown
        $entitySync = DB::table('ric_sync_status')
            ->selectRaw("entity_type,
                SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sync_status IN ('failed','error') THEN 1 ELSE 0 END) as failed")
            ->groupBy('entity_type')
            ->orderBy('entity_type')
            ->get();

        return view('ahg-ric::index', compact(
            'fusekiStatus',
            'configSettings',
            'syncSummary',
            'queueStatus',
            'orphanCount',
            'recentOps',
            'syncTrend',
            'entitySync'
        ));
    }

    /**
     * Sync Status — paginated list with filters.
     */
    public function syncStatus(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $entityType = $request->input('entity_type', '');
        $status = $request->input('status', '');

        $query = DB::table('ric_sync_status')->orderByDesc('updated_at');

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($status !== '') {
            $query->where('sync_status', $status);
        }

        $items = $query->paginate(25)->withQueryString();

        // Get distinct entity types for filter dropdown
        $entityTypes = DB::table('ric_sync_status')
            ->distinct()
            ->pluck('entity_type')
            ->sort()
            ->values();

        $statuses = ['synced', 'pending', 'failed', 'error'];

        return view('ahg-ric::sync-status', compact('items', 'entityTypes', 'statuses', 'entityType', 'status'));
    }

    /**
     * Orphans — list with status tabs.
     */
    public function orphans(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $tab = $request->input('status', 'all');

        $query = DB::table('ric_orphan_tracking')->orderByDesc('detected_at');

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $items = $query->paginate(25)->withQueryString();

        // Badge counts per status
        $counts = DB::table('ric_orphan_tracking')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
        $counts['all'] = array_sum($counts);

        return view('ahg-ric::orphans', compact('items', 'counts', 'tab'));
    }

    /**
     * Queue — list with status tabs.
     */
    public function queue(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $tab = $request->input('status', 'all');

        $query = DB::table('ric_sync_queue')->orderByDesc('created_at');

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $items = $query->paginate(25)->withQueryString();

        // Badge counts per status
        $counts = DB::table('ric_sync_queue')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();
        $counts['all'] = array_sum($counts);

        return view('ahg-ric::queue', compact('items', 'counts', 'tab'));
    }

    /**
     * Logs — list with filters.
     */
    public function logs(Request $request)
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        $operation = $request->input('operation', '');
        $status = $request->input('status', '');
        $entityType = $request->input('entity_type', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');

        $query = DB::table('ric_sync_log')->orderByDesc('created_at');

        if ($operation !== '') {
            $query->where('operation', $operation);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $items = $query->paginate(25)->withQueryString();

        // Distinct values for filter dropdowns
        $operations = DB::table('ric_sync_log')->distinct()->pluck('operation')->sort()->values();
        $statuses = DB::table('ric_sync_log')->distinct()->pluck('status')->sort()->values();
        $entityTypes = DB::table('ric_sync_log')->distinct()->pluck('entity_type')->sort()->values();

        return view('ahg-ric::logs', compact(
            'items', 'operations', 'statuses', 'entityTypes',
            'operation', 'status', 'entityType', 'dateFrom', 'dateTo'
        ));
    }

    /**
     * Config — show/save Fuseki settings.
     */
    public function config(Request $request)
    {
        // Load current config from ahg_settings (setting_group = 'fuseki')
        $config = [];
        if (Schema::hasTable('ahg_settings')) {
            $config = DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        }

        // Handle POST — save settings
        if ($request->isMethod('post')) {
            $incoming = $request->input('config', []);

            // Checkboxes: if not present in POST, they are unchecked => '0'
            $allKeys = ['fuseki_endpoint', 'fuseki_username', 'fuseki_password', 'sync_enabled', 'queue_enabled', 'cascade_delete', 'batch_size'];
            $checkboxKeys = ['sync_enabled', 'queue_enabled', 'cascade_delete'];

            foreach ($allKeys as $key) {
                $value = $incoming[$key] ?? (in_array($key, $checkboxKeys) ? '0' : null);
                if ($value === null) {
                    continue;
                }

                $exists = DB::table('ahg_settings')
                    ->where('setting_group', 'fuseki')
                    ->where('setting_key', $key)
                    ->exists();

                if ($exists) {
                    DB::table('ahg_settings')
                        ->where('setting_group', 'fuseki')
                        ->where('setting_key', $key)
                        ->update(['setting_value' => $value, 'updated_at' => now()]);
                } else {
                    DB::table('ahg_settings')->insert([
                        'setting_group' => 'fuseki',
                        'setting_key'   => $key,
                        'setting_value' => $value,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            return redirect()->route('ric.config')->with('notice', 'Configuration saved successfully.');
        }

        return view('ahg-ric::config', compact('config'));
    }

    /**
     * AJAX: dashboard data (queue, orphans, entity status, recent ops, charts).
     */
    public function ajaxDashboard()
    {
        if (! $this->tablesExist()) {
            return response()->json(['error' => 'RIC tables not configured'], 500);
        }

        $queueStatus = DB::table('ric_sync_queue')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $orphanCount = DB::table('ric_orphan_tracking')
            ->where('status', 'detected')
            ->count();

        $entitySync = DB::table('ric_sync_status')
            ->selectRaw("entity_type,
                SUM(CASE WHEN sync_status = 'synced' THEN 1 ELSE 0 END) as synced,
                SUM(CASE WHEN sync_status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN sync_status IN ('failed','error') THEN 1 ELSE 0 END) as failed")
            ->groupBy('entity_type')
            ->orderBy('entity_type')
            ->get();

        $syncSummary = [];
        foreach ($entitySync as $row) {
            $syncSummary[$row->entity_type] = [
                ['sync_status' => 'synced', 'count' => (int)$row->synced],
                ['sync_status' => 'pending', 'count' => (int)$row->pending],
                ['sync_status' => 'failed', 'count' => (int)$row->failed],
            ];
        }

        $recentOps = DB::table('ric_sync_log')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $sevenDaysAgo = Carbon::now()->subDays(7)->startOfDay();
        $syncTrend = DB::table('ric_sync_log')
            ->selectRaw("DATE(created_at) as date, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as success, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failure")
            ->where('created_at', '>=', $sevenDaysAgo)
            ->groupByRaw("DATE(created_at)")
            ->orderBy('date')
            ->get();

        $opsByType = DB::table('ric_sync_log')
            ->where('created_at', '>=', $sevenDaysAgo)
            ->selectRaw("operation, COUNT(*) as cnt")
            ->groupBy('operation')
            ->pluck('cnt', 'operation')
            ->toArray();

        return response()->json([
            'queue_status' => $queueStatus,
            'orphan_count' => $orphanCount,
            'sync_summary' => $syncSummary,
            'recent_operations' => $recentOps,
            'sync_trend' => $syncTrend,
            'operations_by_type' => $opsByType,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Compute RiC sync readiness: tables, script, config keys, Fuseki ping.
     * Returns [$ok, $reasons[], $resolved[]] where $resolved holds the
     * concrete values that will be passed through to the shell runner.
     */
    private function ricSyncReadiness(): array
    {
        $reasons = [];

        if (! $this->tablesExist()) {
            $reasons[] = 'RiC tables not installed (run install.sql).';
        }

        $script = config('ahg-ric.sync_script') ?: base_path('packages/ahg-ric/bin/ric_sync.sh');
        if (!is_file($script) || !is_executable($script)) {
            $reasons[] = "Sync script missing or not executable: {$script}";
        }

        $fusekiUrl     = config('ahg-ric.fuseki.url');
        $fusekiDataset = config('ahg-ric.fuseki.dataset');
        $fusekiUser    = config('ahg-ric.fuseki.user');
        $fusekiPass    = config('ahg-ric.fuseki.pass');

        if (empty($fusekiUrl)) {
            $reasons[] = 'RIC_FUSEKI_URL is not set in .env.';
        }
        if (empty($fusekiDataset)) {
            $reasons[] = 'RIC_FUSEKI_DATASET is not set in .env.';
        }

        if (empty($reasons) && !empty($fusekiUrl)) {
            if (!$this->fusekiReachable($fusekiUrl)) {
                $reasons[] = "Fuseki not reachable at {$fusekiUrl}.";
            }
        }

        return [
            empty($reasons),
            $reasons,
            [
                'script'         => $script,
                'fuseki_url'     => $fusekiUrl,
                'fuseki_dataset' => $fusekiDataset,
                'fuseki_user'    => $fusekiUser,
                'fuseki_pass'    => $fusekiPass,
                'source_db_host' => config('ahg-ric.source_db.host') ?: config('database.connections.mysql.host'),
                'source_db_user' => config('ahg-ric.source_db.user') ?: config('database.connections.mysql.username'),
                'source_db_pass' => config('ahg-ric.source_db.password') ?: config('database.connections.mysql.password'),
                'source_db_name' => config('ahg-ric.source_db.name') ?: config('database.connections.mysql.database'),
                'base_uri'       => config('ahg-ric.base_uri') ?: config('app.url'),
                'instance_id'    => config('ahg-ric.instance_id', 'heratio'),
            ],
        ];
    }

    /**
     * Best-effort Fuseki ping — 1-second timeout, accept 200 or 401.
     */
    private function fusekiReachable(string $baseUrl): bool
    {
        $pingUrl = rtrim($baseUrl, '/') . '/$/ping';
        $ch = curl_init($pingUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 1,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($code, [200, 401], true);
    }

    /**
     * AJAX: report whether the Sync button should be active.
     */
    public function ajaxSyncReadiness()
    {
        [$ok, $reasons] = $this->ricSyncReadiness();
        return response()->json(['ready' => $ok, 'reasons' => $reasons]);
    }

    /**
     * AJAX: trigger manual sync.
     */
    public function ajaxSync()
    {
        [$ok, $reasons, $resolved] = $this->ricSyncReadiness();
        if (!$ok) {
            return response()->json([
                'success' => false,
                'error'   => 'RiC sync is not configured: ' . implode(' ', $reasons),
            ], 503);
        }

        $logFile = storage_path('logs/ric_sync_' . date('Ymd_His') . '.log');

        $envPairs = [
            'RIC_FUSEKI_URL'          => $resolved['fuseki_url'],
            'RIC_FUSEKI_DATASET'      => $resolved['fuseki_dataset'],
            'RIC_FUSEKI_USER'         => $resolved['fuseki_user'] ?? '',
            'RIC_FUSEKI_PASS'         => $resolved['fuseki_pass'] ?? '',
            'RIC_SOURCE_DB_HOST'      => $resolved['source_db_host'],
            'RIC_SOURCE_DB_USER'      => $resolved['source_db_user'],
            'RIC_SOURCE_DB_PASSWORD'  => $resolved['source_db_pass'] ?? '',
            'RIC_SOURCE_DB_NAME'      => $resolved['source_db_name'],
            'RIC_BASE_URI'            => $resolved['base_uri'],
            'RIC_INSTANCE_ID'         => $resolved['instance_id'],
        ];

        $envPrefix = '';
        foreach ($envPairs as $k => $v) {
            $envPrefix .= $k . '=' . escapeshellarg((string) $v) . ' ';
        }

        $cmd = $envPrefix . escapeshellcmd($resolved['script']) . ' --cron > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
        $pid = trim((string) shell_exec($cmd));

        if (!$pid || !is_numeric($pid)) {
            return response()->json(['success' => false, 'error' => 'Failed to start sync process']);
        }

        return response()->json([
            'success' => true,
            'pid' => (int)$pid,
            'log_file' => $logFile,
            'message' => "Sync started (PID: {$pid})",
        ]);
    }

    /**
     * AJAX: check sync progress.
     */
    public function ajaxSyncProgress(Request $request)
    {
        $logFile = $request->input('log_file', '');
        if (!$logFile || !file_exists($logFile)) {
            return response()->json(['running' => false, 'output' => 'Log file not found']);
        }

        $output = file_get_contents($logFile);
        $running = false;

        // Check if the process is still running
        $pids = shell_exec("pgrep -f 'ric_sync.sh' 2>/dev/null");
        if (trim($pids)) {
            $running = true;
        }

        return response()->json([
            'running' => $running,
            'output' => $output,
        ]);
    }

    /**
     * AJAX: integrity check.
     */
    public function ajaxIntegrityCheck()
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Tables not configured']);
        }

        $orphanedCount = DB::table('ric_orphan_tracking')->where('status', 'detected')->count();
        $missingCount = DB::table('ric_sync_status')->where('sync_status', 'failed')->count();
        $inconsistencyCount = DB::table('ric_sync_status')->where('sync_status', 'error')->count();

        return response()->json([
            'success' => true,
            'report' => [
                'summary' => [
                    'orphaned_count' => $orphanedCount,
                    'missing_count' => $missingCount,
                    'inconsistency_count' => $inconsistencyCount,
                ],
                'checked_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * AJAX: cleanup orphans.
     */
    public function ajaxCleanupOrphans(Request $request)
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Tables not configured']);
        }

        $dryRun = $request->input('dry_run', false);
        $orphans = DB::table('ric_orphan_tracking')->where('status', 'detected')->get();

        if ($dryRun) {
            return response()->json([
                'success' => true,
                'stats' => ['orphans_found' => $orphans->count()],
            ]);
        }

        $removed = DB::table('ric_orphan_tracking')
            ->where('status', 'detected')
            ->update(['status' => 'cleaned', 'cleaned_at' => now()]);

        return response()->json([
            'success' => true,
            'stats' => ['triples_removed' => $removed],
        ]);
    }

    /**
     * AJAX: re-sync a specific entity (re-queue for sync).
     */
    public function ajaxResync(Request $request)
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Tables not configured']);
        }

        $entityType = $request->input('entity_type');
        $entityId = (int) $request->input('entity_id');

        if (!$entityType || !$entityId) {
            return response()->json(['success' => false, 'error' => 'entity_type and entity_id are required']);
        }

        // Update sync status to pending
        DB::table('ric_sync_status')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->update(['sync_status' => 'pending', 'updated_at' => now()]);

        // Queue for re-sync
        DB::table('ric_sync_queue')->insert([
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'operation'    => 'resync',
            'status'       => 'queued',
            'priority'     => 1,
            'attempts'     => 0,
            'scheduled_at' => now(),
            'created_at'   => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: clear/retry/cancel a queue item.
     */
    public function ajaxClearQueueItem(Request $request)
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Tables not configured']);
        }

        $id = (int) $request->input('id');
        $action = $request->input('queue_action', 'cancel');

        if (!$id) {
            return response()->json(['success' => false, 'error' => 'Queue item id is required']);
        }

        if ($action === 'retry') {
            DB::table('ric_sync_queue')
                ->where('id', $id)
                ->update(['status' => 'queued', 'attempts' => 0]);
        } elseif ($action === 'cancel') {
            DB::table('ric_sync_queue')
                ->where('id', $id)
                ->update(['status' => 'cancelled']);
        } elseif ($action === 'delete') {
            DB::table('ric_sync_queue')->where('id', $id)->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: update orphan status (reviewed, retained, cleaned).
     */
    public function ajaxUpdateOrphan(Request $request)
    {
        if (! $this->tablesExist()) {
            return response()->json(['success' => false, 'error' => 'Tables not configured']);
        }

        $id = (int) $request->input('id');
        $status = $request->input('orphan_status');
        $validStatuses = ['reviewed', 'retained', 'cleaned'];

        if (!$id) {
            return response()->json(['success' => false, 'error' => 'Orphan id is required']);
        }

        if (!in_array($status, $validStatuses)) {
            return response()->json(['success' => false, 'error' => 'Invalid status. Valid: ' . implode(', ', $validStatuses)]);
        }

        DB::table('ric_orphan_tracking')
            ->where('id', $id)
            ->update([
                'status'      => $status,
                'resolved_at' => $status === 'cleaned' ? now() : null,
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: stats summary (sync, queue, orphans, fuseki status).
     */
    public function ajaxStats()
    {
        if (! $this->tablesExist()) {
            return response()->json(['error' => 'RIC tables not configured'], 500);
        }

        $syncSummary = DB::table('ric_sync_status')
            ->selectRaw("entity_type, sync_status, COUNT(*) as `count`")
            ->groupBy('entity_type', 'sync_status')
            ->get()
            ->groupBy('entity_type')
            ->toArray();

        $queueStatus = DB::table('ric_sync_queue')
            ->selectRaw("status, COUNT(*) as cnt")
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $orphanCount = DB::table('ric_orphan_tracking')
            ->where('status', 'detected')
            ->count();

        $recentOps = DB::table('ric_sync_log')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Fuseki status check
        $fusekiStatus = $this->checkFusekiStatusQuick();

        return response()->json([
            'sync_summary'      => $syncSummary,
            'queue_status'      => $queueStatus,
            'orphan_count'      => $orphanCount,
            'fuseki_status'     => $fusekiStatus,
            'recent_operations' => $recentOps,
            'timestamp'         => now()->toDateTimeString(),
        ]);
    }

    /**
     * Quick Fuseki connectivity check via ASK query.
     */
    protected function checkFusekiStatusQuick(): array
    {
        $config = $this->getFusekiConfig();
        $endpoint = ($config['fuseki_endpoint'] ?? config('services.ric.fuseki_endpoint', 'http://localhost:3030/ric')) . '/query';
        $username = $config['fuseki_username'] ?? config('services.ric.fuseki_username', 'admin');
        $password = $config['fuseki_password'] ?? config('services.ric.fuseki_password', '');

        try {
            $ch = curl_init($endpoint);
            $curlOpts = [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => 'ASK { ?s ?p ?o }',
                CURLOPT_HTTPHEADER     => ['Content-Type: application/sparql-query', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ];
            if (!empty($password)) {
                $curlOpts[CURLOPT_USERPWD] = "{$username}:{$password}";
            }
            curl_setopt_array($ch, $curlOpts);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return ['online' => true, 'has_data' => $data['boolean'] ?? false];
            }
            return ['online' => false, 'error' => 'HTTP ' . $httpCode];
        } catch (\Exception $e) {
            return ['online' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // RiC Explorer
    // =========================================================================

    /**
     * Load Fuseki config from ahg_settings table.
     */
    protected function getFusekiConfig(): array
    {
        try {
            return DB::table('ahg_settings')
                ->where('setting_group', 'fuseki')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * RiC Explorer — full-page graph visualization.
     */
    public function explorer()
    {
        return view('ahg-ric::explorer');
    }

    /**
     * Create a new RiC entity directly in the Fuseki graph store.
     * Standalone — does not create in AtoM/Heratio DB. Can be synced later.
     */
    public function createEntity(Request $request)
    {
        $type = $request->input('type', 'Record');
        $name = trim($request->input('name', ''));
        $description = trim($request->input('description', ''));
        $identifier = trim($request->input('identifier', ''));
        $parentUri = trim($request->input('parent_uri', ''));

        if (!$name) {
            return response()->json(['success' => false, 'error' => 'Name is required']);
        }

        $config = $this->getFusekiConfig();
        $fusekiEndpoint = $config['fuseki_endpoint'] ?? config('services.ric.fuseki_endpoint', 'http://localhost:3030/ric');
        $fusekiUsername = $config['fuseki_username'] ?? config('services.ric.fuseki_username', 'admin');
        $fusekiPassword = $config['fuseki_password'] ?? config('services.ric.fuseki_password', '');

        // RiC-O type mapping
        $ricTypes = [
            'Record'        => 'rico:Record',
            'RecordSet'     => 'rico:RecordSet',
            'RecordPart'    => 'rico:RecordPart',
            'Person'        => 'rico:Person',
            'CorporateBody' => 'rico:CorporateBody',
            'Family'        => 'rico:Family',
            'Place'         => 'rico:Place',
            'Activity'      => 'rico:Activity',
            'Event'         => 'rico:Event',
            'Concept'       => 'rico:Concept',
        ];

        $ricType = $ricTypes[$type] ?? 'rico:Record';

        // Generate a URI for the new entity
        $slug = \Illuminate\Support\Str::slug($name);
        $uid = substr(md5($name . microtime(true)), 0, 8);
        $entityUri = "https://heratio.theahg.co.za/ric/" . strtolower($type) . "/{$slug}-{$uid}";

        // Build SPARQL INSERT
        $escapeName = addslashes($name);
        $sparql = "PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n"
            . "PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>\n"
            . "PREFIX dcterms: <http://purl.org/dc/terms/>\n"
            . "PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\n\n"
            . "INSERT DATA {\n"
            . "  <{$entityUri}> a {$ricType} ;\n"
            . "    rdfs:label \"{$escapeName}\" ;\n"
            . "    rico:title \"{$escapeName}\" ;\n"
            . "    dcterms:created \"" . now()->toIso8601String() . "\"^^xsd:dateTime ;\n";

        if ($identifier) {
            $sparql .= "    rico:identifier \"" . addslashes($identifier) . "\" ;\n";
        }
        if ($description) {
            $sparql .= "    rico:scopeAndContent \"" . addslashes($description) . "\" ;\n";
        }
        if ($parentUri) {
            $sparql .= "    rico:isOrWasIncludedIn <{$parentUri}> ;\n";
        }

        // Remove trailing " ;\n" and close with " .\n}"
        $sparql = rtrim($sparql, " ;\n") . " .\n}";

        // Execute SPARQL UPDATE
        $updateUrl = rtrim($fusekiEndpoint, '/') . '/update';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $updateUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'update=' . urlencode($sparql),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($fusekiUsername) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$fusekiUsername}:{$fusekiPassword}");
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            \Log::info('[RiC] Entity created in graph store', ['uri' => $entityUri, 'type' => $type, 'name' => $name]);
            return response()->json([
                'success' => true,
                'uri' => $entityUri,
                'type' => $type,
                'name' => $name,
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => "Fuseki returned HTTP {$httpCode}",
            'response' => substr($response ?: '', 0, 500),
        ]);
    }

    /**
     * Autocomplete endpoint for information objects.
     * Returns JSON array of {id, title, lod, identifier, slug}.
     */
    public function autocomplete(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $culture = app()->getLocale() === 'en' ? 'en' : app()->getLocale();

        $results = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->leftJoin('term_i18n as lod', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'lod.id')->where('lod.culture', '=', $culture);
            })
            ->where('io.id', '>', 1)
            ->where(function ($query) use ($q) {
                $query->where('ioi.title', 'LIKE', '%' . $q . '%')
                      ->orWhere('io.identifier', 'LIKE', '%' . $q . '%')
                      ->orWhere('s.slug', 'LIKE', '%' . $q . '%');
            })
            ->select('io.id', 'ioi.title', 'io.identifier', 'lod.name as level_of_description', 's.slug')
            ->orderByRaw('CASE WHEN ioi.title LIKE ? THEN 0 ELSE 1 END', [$q . '%'])
            ->limit(15)
            ->get();

        $items = [];
        foreach ($results as $row) {
            $items[] = [
                'id'         => $row->id,
                'title'      => $row->title ?: 'Untitled',
                'identifier' => $row->identifier,
                'lod'        => $row->level_of_description,
                'slug'       => $row->slug,
            ];
        }

        return response()->json($items);
    }

    /**
     * Get graph data for a record (or overview).
     * Returns JSON {success, graphData: {nodes, edges}}.
     */
    public function getData(Request $request)
    {
        $recordId = $request->input('id');
        if (!$recordId) {
            return response()->json(['success' => false, 'error' => 'No ID provided']);
        }

        $config = $this->getFusekiConfig();
        $fusekiEndpoint = ($config['fuseki_endpoint'] ?? config('services.ric.fuseki_endpoint', 'http://localhost:3030/ric')) . '/query';
        $fusekiUsername  = $config['fuseki_username'] ?? config('services.ric.fuseki_username', 'admin');
        $fusekiPassword  = $config['fuseki_password'] ?? config('services.ric.fuseki_password', '');
        $baseUri         = $config['ric_base_uri'] ?? config('services.ric.base_uri', 'https://archives.theahg.co.za/ric');
        $instanceId      = $config['ric_instance_id'] ?? config('services.ric.instance_id', 'atom-psis');

        if ($recordId === 'overview') {
            $graphData = $this->buildOverviewGraph($fusekiEndpoint, $fusekiUsername, $fusekiPassword);
            // DB fallback if SPARQL returned nothing
            if (empty($graphData['nodes'])) {
                $graphData = $this->buildOverviewGraphFromDatabase($baseUri, $instanceId);
            }
        } else {
            $graphData = $this->buildGraphData(
                $recordId, $fusekiEndpoint, $fusekiUsername, $fusekiPassword, $baseUri, $instanceId
            );
        }

        return response()->json([
            'success'   => true,
            'graphData' => $graphData,
        ]);
    }

    /**
     * Timeline data for a record — events with dates for the explorer timeline view.
     */
    public function getTimelineData(Request $request)
    {
        $id = (int) $request->input('id');
        if (!$id) {
            return response()->json(['success' => false, 'error' => 'No ID provided']);
        }

        $culture = app()->getLocale();

        // Get the record info
        $io = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->where('io.id', $id)
            ->select('io.id', 'io.identifier', 'i18n.title')
            ->first();

        if (!$io) {
            return response()->json(['success' => false, 'error' => 'Record not found']);
        }

        // Get events with dates
        $events = DB::table('event')
            ->join('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')->where('event_i18n.culture', $culture);
            })
            ->leftJoin('actor_i18n', function ($j) use ($culture) {
                $j->on('event.actor_id', '=', 'actor_i18n.id')->where('actor_i18n.culture', $culture);
            })
            ->leftJoin('term_i18n as eti', function ($j) use ($culture) {
                $j->on('event.type_id', '=', 'eti.id')->where('eti.culture', $culture);
            })
            ->where('event.object_id', $id)
            ->select(
                'event.id', 'event.start_date', 'event.end_date', 'event.type_id',
                'event_i18n.date as date_display',
                'event_i18n.name as event_name',
                'actor_i18n.authorized_form_of_name as actor_name',
                'eti.name as event_type_name'
            )
            ->orderBy('event.start_date')
            ->get();

        // Get descendants with their events for a richer timeline
        $descendants = DB::table('information_object as child')
            ->join('information_object as parent', function ($j) use ($id) {
                $j->whereRaw('child.lft > parent.lft AND child.rgt < parent.rgt AND parent.id = ?', [$id]);
            })
            ->join('information_object_i18n as ci', function ($j) use ($culture) {
                $j->on('ci.id', '=', 'child.id')->where('ci.culture', $culture);
            })
            ->join('event', 'event.object_id', '=', 'child.id')
            ->join('event_i18n', function ($j) use ($culture) {
                $j->on('event.id', '=', 'event_i18n.id')->where('event_i18n.culture', $culture);
            })
            ->leftJoin('term_i18n as eti', function ($j) use ($culture) {
                $j->on('event.type_id', '=', 'eti.id')->where('eti.culture', $culture);
            })
            ->whereNotNull('event.start_date')
            ->select(
                'child.id as object_id', 'ci.title as object_title',
                'event.start_date', 'event.end_date',
                'event_i18n.date as date_display',
                'eti.name as event_type_name'
            )
            ->orderBy('event.start_date')
            ->limit(200)
            ->get();

        $items = [];

        // Add main record events
        foreach ($events as $e) {
            if (!$e->start_date) continue;
            $items[] = [
                'id' => 'event-' . $e->id,
                'label' => $e->event_type_name ?? $e->event_name ?? 'Event',
                'detail' => $e->actor_name ? ($e->actor_name . ' — ' . ($e->date_display ?? '')) : ($e->date_display ?? ''),
                'start' => $e->start_date,
                'end' => $e->end_date,
                'group' => $io->title ?? 'Record',
                'color' => '#4ecdc4',
            ];
        }

        // Add descendant events
        foreach ($descendants as $d) {
            $items[] = [
                'id' => 'desc-' . $d->object_id . '-' . $d->start_date,
                'label' => $d->object_title ?? 'Child',
                'detail' => $d->event_type_name ?? ($d->date_display ?? ''),
                'start' => $d->start_date,
                'end' => $d->end_date,
                'group' => $d->object_title ?? 'Descendant',
                'color' => '#45b7d1',
            ];
        }

        return response()->json([
            'success' => true,
            'record' => ['id' => $io->id, 'title' => $io->title, 'identifier' => $io->identifier],
            'items' => $items,
        ]);
    }

    /**
     * Execute a SPARQL query against Fuseki.
     */
    protected function executeSparql(string $query, string $endpoint, string $username, string $password): ?array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $query,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/sparql-query',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 3,
        ];
        if ($password) {
            $opts[CURLOPT_USERPWD] = "{$username}:{$password}";
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Build a URI for a RiC record.
     */
    protected function buildRecordUri(string $type, $id, string $baseUri, string $instanceId): string
    {
        return $baseUri . '/' . $instanceId . '/' . $type . '/' . $id;
    }

    /**
     * Derive a human-readable edge label from a canonical rico:* predicate.
     * Example: "rico:hasOrHadSubject" → "has or had subject".
     */
    protected function humanisePredicate(string $predicate): string
    {
        $local = preg_replace('/^[a-z]+:/', '', $predicate);
        $withSpaces = preg_replace('/([a-z])([A-Z])/', '$1 $2', $local);
        return strtolower($withSpaces);
    }

    /**
     * Build overview graph from SPARQL.
     */
    protected function buildOverviewGraph(string $endpoint, string $username, string $password): array
    {
        // Main query: RecordSets and their relations including new RiC-O predicates
        $query = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?s ?label ?type ?related ?relLabel ?relType ?pred WHERE {
  {
    ?s a rico:RecordSet .
    ?s rico:title ?label .
    BIND("RecordSet" AS ?type)
  }
  OPTIONAL {
    ?s ?pred ?related .
    FILTER(isURI(?related) && ?pred != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
    OPTIONAL { ?related rico:title ?relLabel }
    OPTIONAL { ?related a ?relType . FILTER(STRSTARTS(STR(?relType), "https://www.ica.org/standards/RiC/ontology#")) }
  }
} LIMIT 200
SPARQL;

        $result = $this->executeSparql($query, $endpoint, $username, $password);
        $nodes = [];
        $edges = [];
        $nodeIndex = [];

        if ($result && isset($result['results']['bindings'])) {
            foreach ($result['results']['bindings'] as $row) {
                $uri = $row['s']['value'];
                if (!isset($nodeIndex[$uri])) {
                    $nodeIndex[$uri] = true;
                    $nodes[] = [
                        'id'    => $uri,
                        'label' => $row['label']['value'] ?? $this->extractLabel($uri),
                        'type'  => 'RecordSet',
                    ];
                }
                if (isset($row['related'])) {
                    $relUri = $row['related']['value'];
                    if (!isset($nodeIndex[$relUri])) {
                        $nodeIndex[$relUri] = true;
                        $relType = isset($row['relType']) ? $this->extractType($row['relType']['value']) : $this->extractTypeFromUri($relUri);
                        $nodes[] = [
                            'id'    => $relUri,
                            'label' => isset($row['relLabel']) ? $row['relLabel']['value'] : $this->extractLabel($relUri),
                            'type'  => $relType,
                        ];
                    }
                    $predLabel = isset($row['pred']) ? $this->extractLabel($row['pred']['value']) : '';
                    $edges[] = ['source' => $uri, 'target' => $relUri, 'label' => $predLabel];
                }
            }
        }

        // Also query Mandate, Rule, Mechanism entities from triplestore
        $mandateQuery = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?s ?label ?type WHERE {
  { ?s a rico:Mandate . BIND("Mandate" AS ?type) }
  UNION { ?s a rico:Rule . BIND("Rule" AS ?type) }
  UNION { ?s a rico:Mechanism . BIND("Mechanism" AS ?type) }
  OPTIONAL { ?s rico:title ?label }
} LIMIT 50
SPARQL;

        $mandateResult = $this->executeSparql($mandateQuery, $endpoint, $username, $password);
        if ($mandateResult && isset($mandateResult['results']['bindings'])) {
            foreach ($mandateResult['results']['bindings'] as $row) {
                $uri = $row['s']['value'];
                if (!isset($nodeIndex[$uri])) {
                    $nodeIndex[$uri] = true;
                    $nodes[] = [
                        'id'    => $uri,
                        'label' => isset($row['label']) ? $row['label']['value'] : $this->extractLabel($uri),
                        'type'  => $row['type']['value'] ?? 'Mandate',
                    ];
                }
            }
        }

        // Query FindingAid and AuthorityRecord entities
        $docTypeQuery = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?s ?label ?type ?described WHERE {
  { ?s a rico:FindingAid . BIND("FindingAid" AS ?type) }
  UNION { ?s a rico:AuthorityRecord . BIND("AuthorityRecord" AS ?type) }
  OPTIONAL { ?s rico:title ?label }
  OPTIONAL { ?s rico:describesOrDescribed ?described }
} LIMIT 50
SPARQL;

        $docTypeResult = $this->executeSparql($docTypeQuery, $endpoint, $username, $password);
        if ($docTypeResult && isset($docTypeResult['results']['bindings'])) {
            foreach ($docTypeResult['results']['bindings'] as $row) {
                $uri = $row['s']['value'];
                if (!isset($nodeIndex[$uri])) {
                    $nodeIndex[$uri] = true;
                    $nodes[] = [
                        'id'    => $uri,
                        'label' => isset($row['label']) ? $row['label']['value'] : $this->extractLabel($uri),
                        'type'  => $row['type']['value'] ?? 'FindingAid',
                    ];
                }
                // Link finding aids to records they describe
                if (isset($row['described'])) {
                    $descUri = $row['described']['value'];
                    if (!isset($nodeIndex[$descUri])) {
                        $nodeIndex[$descUri] = true;
                        $nodes[] = [
                            'id'    => $descUri,
                            'label' => $this->extractLabel($descUri),
                            'type'  => $this->extractTypeFromUri($descUri),
                        ];
                    }
                    $edges[] = ['source' => $uri, 'target' => $descUri, 'label' => 'describes Or Described'];
                }
            }
        }

        $nodes = $this->enrichNodesWithSlugs($nodes);

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Fallback: build overview graph from database (top-level fonds/collections).
     */
    protected function buildOverviewGraphFromDatabase(string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() === 'en' ? 'en' : app()->getLocale();
        $nodes = [];
        $edges = [];
        $nodeIndex = [];

        // Top-level information objects (fonds/collections — parent_id = 1 is root)
        $topLevel = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', 1)
            ->whereNotNull('ioi.title')
            ->select('io.id', 'ioi.title', 'slug.slug', 'io.level_of_description_id')
            ->limit(50)
            ->get();

        foreach ($topLevel as $io) {
            $uri = $this->buildRecordUri('recordset', $io->id, $baseUri, $instanceId);
            $nodes[] = ['id' => $uri, 'label' => $io->title, 'type' => 'RecordSet', 'slug' => $io->slug];
            $nodeIndex[$uri] = true;

            // Get creators for each top-level record
            $creators = DB::table('event')
                ->join('actor_i18n', function ($j) use ($culture) {
                    $j->on('event.actor_id', '=', 'actor_i18n.id')->where('actor_i18n.culture', '=', $culture);
                })
                ->leftJoin('slug as s', 'event.actor_id', '=', 's.object_id')
                ->where('event.object_id', $io->id)
                ->whereNotNull('actor_i18n.authorized_form_of_name')
                ->select('event.actor_id', 'actor_i18n.authorized_form_of_name', 's.slug')
                ->limit(5)
                ->get();

            foreach ($creators as $c) {
                $actorUri = $this->buildRecordUri('person', $c->actor_id, $baseUri, $instanceId);
                if (!isset($nodeIndex[$actorUri])) {
                    $nodeIndex[$actorUri] = true;
                    $nodes[] = ['id' => $actorUri, 'label' => $c->authorized_form_of_name, 'type' => 'Person', 'slug' => $c->slug];
                }
                $edges[] = ['source' => $actorUri, 'target' => $uri, 'label' => 'created'];
            }

            // Get repository
            $repo = DB::table('information_object as io2')
                ->join('actor_i18n as ai', function ($j) use ($culture) {
                    $j->on('io2.repository_id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                })
                ->where('io2.id', $io->id)
                ->whereNotNull('io2.repository_id')
                ->value('ai.authorized_form_of_name');

            if ($repo) {
                $repoUri = $this->buildRecordUri('corporatebody', $io->id . '-repo', $baseUri, $instanceId);
                if (!isset($nodeIndex[$repoUri])) {
                    $nodeIndex[$repoUri] = true;
                    $nodes[] = ['id' => $repoUri, 'label' => $repo, 'type' => 'CorporateBody'];
                }
                $edges[] = ['source' => $uri, 'target' => $repoUri, 'label' => 'held by'];
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Build graph data for a specific record — tries SPARQL first, falls back to DB.
     */
    protected function buildGraphData($recordId, string $endpoint, string $username, string $password, string $baseUri, string $instanceId): array
    {
        $nodes = [];
        $edges = [];

        $recordUris = [
            $this->buildRecordUri('recordset', $recordId, $baseUri, $instanceId),
            $this->buildRecordUri('record', $recordId, $baseUri, $instanceId),
        ];
        $uriFilter = '<' . implode('>, <', $recordUris) . '>';

        $query = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?subject ?predicate ?object WHERE {
  {
    { ?subject ?predicate ?object . FILTER(?subject IN ({$uriFilter})) FILTER(isURI(?object)) }
    UNION
    { ?subject ?predicate ?object . FILTER(?object IN ({$uriFilter})) FILTER(isURI(?subject)) }
  }
  FILTER(?predicate != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
} LIMIT 150
SPARQL;

        // Always use DB fallback first (fast + reliable), then try SPARQL enrichment
        $dbGraph = $this->buildGraphFromDatabase($recordId, $baseUri, $instanceId);
        if (!empty($dbGraph['nodes'])) {
            return $dbGraph;
        }

        // DB returned nothing — try SPARQL
        $result = $this->executeSparql($query, $endpoint, $username, $password);

        if ($result && isset($result['results']['bindings']) && count($result['results']['bindings']) > 0) {
            $nodeIndex = [];
            $allUris = [];

            foreach ($result['results']['bindings'] as $row) {
                $subjectUri = $row['subject']['value'];
                $objectUri  = $row['object']['value'];
                $predLabel  = $this->extractLabel($row['predicate']['value']);

                if (!isset($nodeIndex[$subjectUri])) {
                    $nodeIndex[$subjectUri] = true;
                    $allUris[] = $subjectUri;
                }
                if (!isset($nodeIndex[$objectUri])) {
                    $nodeIndex[$objectUri] = true;
                    $allUris[] = $objectUri;
                }
                $edges[] = ['source' => $subjectUri, 'target' => $objectUri, 'label' => $predLabel];
            }

            // Batch-fetch labels and types
            $uriLabels = $this->fetchUriMetadata($allUris, $endpoint, $username, $password);

            foreach ($allUris as $uri) {
                $meta = $uriLabels[$uri] ?? [];
                $nodes[] = [
                    'id'    => $uri,
                    'label' => $meta['label'] ?? $this->extractLabel($uri),
                    'type'  => $meta['type'] ?? $this->extractTypeFromUri($uri),
                ];
            }
        } else {
            // Fallback to database
            return $this->buildGraphFromDatabase($recordId, $baseUri, $instanceId);
        }

        $nodes = $this->enrichNodesWithSlugs($nodes);

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Batch-fetch labels and types for URIs from SPARQL.
     */
    protected function fetchUriMetadata(array $uris, string $endpoint, string $username, string $password): array
    {
        if (empty($uris)) {
            return [];
        }

        $uriList = '<' . implode('>, <', $uris) . '>';
        $query = <<<SPARQL
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?uri ?label ?type WHERE {
  VALUES ?uri { {$uriList} }
  OPTIONAL { ?uri rico:title ?label }
  OPTIONAL { ?uri a ?type . FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#")) }
}
SPARQL;

        $result = $this->executeSparql($query, $endpoint, $username, $password);
        $metadata = [];

        if ($result && isset($result['results']['bindings'])) {
            foreach ($result['results']['bindings'] as $row) {
                $uri = $row['uri']['value'];
                if (!isset($metadata[$uri])) {
                    $metadata[$uri] = [];
                }
                if (isset($row['label'])) {
                    $metadata[$uri]['label'] = $row['label']['value'];
                }
                if (isset($row['type'])) {
                    $metadata[$uri]['type'] = $this->extractType($row['type']['value']);
                }
            }
        }

        return $metadata;
    }

    /**
     * Fallback: build graph from AtoM database tables.
     */
    protected function buildGraphFromDatabase($recordId, string $baseUri, string $instanceId): array
    {
        $culture = app()->getLocale() === 'en' ? 'en' : app()->getLocale();
        $nodes = [];
        $edges = [];
        $nodeIndex = [];

        $record = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->where('io.id', $recordId)
            ->select('io.*', 'ioi.title')
            ->first();

        if (!$record) {
            // Try digital_object (Instantiation)
            $digObj = DB::table('digital_object as d')
                ->leftJoin('information_object as io', 'd.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                })
                ->where('d.id', $recordId)
                ->select('d.*', 'ioi.title as parent_title', 'io.id as parent_id')
                ->first();

            if ($digObj) {
                // Build graph: instantiation → parent IO
                $instUri = $this->buildRecordUri('instantiation', $recordId, $baseUri, $instanceId);
                $nodes[] = ['id' => $instUri, 'label' => $digObj->name ?: 'Digital Object ' . $recordId, 'type' => 'Instantiation'];
                $nodeIndex[$instUri] = true;

                if ($digObj->parent_id) {
                    $parentUri = $this->buildRecordUri('recordset', $digObj->parent_id, $baseUri, $instanceId);
                    $nodes[] = ['id' => $parentUri, 'label' => $digObj->parent_title ?: 'Record ' . $digObj->parent_id, 'type' => 'RecordSet'];
                    $edges[] = ['source' => $parentUri, 'target' => $instUri, 'label' => 'has instantiation'];

                    // Now build the parent's full graph
                    $parentGraph = $this->buildGraphFromDatabase($digObj->parent_id, $baseUri, $instanceId);
                    foreach ($parentGraph['nodes'] as $n) {
                        if (!isset($nodeIndex[$n['id']])) { $nodes[] = $n; $nodeIndex[$n['id']] = true; }
                    }
                    $edges = array_merge($edges, $parentGraph['edges']);
                }

                $nodes = $this->enrichNodesWithSlugs($nodes);
                return ['nodes' => $nodes, 'edges' => $edges];
            }

            // Try actor
            $actor = DB::table('actor_i18n')->where('id', $recordId)->where('culture', $culture)->first();
            if ($actor) {
                $actorUri = $this->buildRecordUri('person', $recordId, $baseUri, $instanceId);
                $nodes[] = ['id' => $actorUri, 'label' => $actor->authorized_form_of_name ?: 'Actor ' . $recordId, 'type' => 'Person'];
                $nodeIndex[$actorUri] = true;

                // Find records linked to this actor via events
                $events = DB::table('event as e')
                    ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                        $j->on('e.object_id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                    })
                    ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                        $j->on('e.type_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                    })
                    ->where('e.actor_id', $recordId)
                    ->select('e.object_id', 'ioi.title', 'ti.name as event_type')
                    ->limit(20)
                    ->get();
                foreach ($events as $ev) {
                    $recUri = $this->buildRecordUri('recordset', $ev->object_id, $baseUri, $instanceId);
                    if (!isset($nodeIndex[$recUri])) {
                        $nodeIndex[$recUri] = true;
                        $nodes[] = ['id' => $recUri, 'label' => $ev->title ?: 'Record ' . $ev->object_id, 'type' => 'RecordSet'];
                    }
                    $edges[] = ['source' => $actorUri, 'target' => $recUri, 'label' => $ev->event_type ?: 'related'];
                }

                $nodes = $this->enrichNodesWithSlugs($nodes);
                return ['nodes' => $nodes, 'edges' => $edges];
            }

            // Try term/concept (taxonomy term)
            $term = DB::table('term_i18n')->where('id', $recordId)->where('culture', $culture)->first();
            if ($term) {
                $termUri = $this->buildRecordUri('term', $recordId, $baseUri, $instanceId);
                $nodes[] = ['id' => $termUri, 'label' => $term->name ?: 'Term ' . $recordId, 'type' => 'Concept'];
                $nodeIndex[$termUri] = true;

                // Find records linked to this term via object_term_relation
                $linked = DB::table('object_term_relation as otr')
                    ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                        $j->on('otr.object_id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                    })
                    ->where('otr.term_id', $recordId)
                    ->select('otr.object_id', 'ioi.title')
                    ->limit(20)
                    ->get();
                foreach ($linked as $link) {
                    $recUri = $this->buildRecordUri('recordset', $link->object_id, $baseUri, $instanceId);
                    if (!isset($nodeIndex[$recUri])) {
                        $nodeIndex[$recUri] = true;
                        $nodes[] = ['id' => $recUri, 'label' => $link->title ?: 'Record ' . $link->object_id, 'type' => 'RecordSet'];
                    }
                    $edges[] = ['source' => $recUri, 'target' => $termUri, 'label' => 'about'];
                }

                $nodes = $this->enrichNodesWithSlugs($nodes);
                return ['nodes' => $nodes, 'edges' => $edges];
            }

            return ['nodes' => $nodes, 'edges' => $edges];
        }

        $recordUri = $this->buildRecordUri('recordset', $recordId, $baseUri, $instanceId);
        $nodes[] = [
            'id'    => $recordUri,
            'label' => $record->title ?: 'Record ' . $recordId,
            'type'  => 'RecordSet',
        ];
        $nodeIndex[$recordUri] = true;

        // Creators via events
        $events = DB::table('event as e')
            ->leftJoin('actor as a', 'e.actor_id', '=', 'a.id')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('e.type_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('e.object_id', $recordId)
            ->select('a.id as actor_id', 'ai.authorized_form_of_name', 'e.type_id', 'ti.name as event_type')
            ->get();

        foreach ($events as $event) {
            if ($event->actor_id) {
                $actorUri = $this->buildRecordUri('person', $event->actor_id, $baseUri, $instanceId);
                if (!isset($nodeIndex[$actorUri])) {
                    $nodeIndex[$actorUri] = true;
                    $nodes[] = [
                        'id'    => $actorUri,
                        'label' => $event->authorized_form_of_name ?: 'Actor ' . $event->actor_id,
                        'type'  => 'Person',
                    ];
                }
                // Map AtoM event.type_id to canonical RiC creator predicate.
                // Creation / production / contribution → hasCreator;
                // accumulation / collection → hasAccumulator.
                $evTypeLower = strtolower($event->event_type ?: '');
                $evPredicate = match (true) {
                    str_contains($evTypeLower, 'creat'), str_contains($evTypeLower, 'product'),
                    str_contains($evTypeLower, 'contribut')
                        => 'rico:hasCreator',
                    str_contains($evTypeLower, 'accumulat'), str_contains($evTypeLower, 'collect')
                        => 'rico:hasAccumulator',
                    default => 'rico:isAssociatedWith',
                };
                $edges[] = [
                    'source' => $actorUri,
                    'target' => $recordUri,
                    'predicate' => $evPredicate,
                    'label'  => $event->event_type ?: 'related',
                ];
            }
        }

        // Subject access points
        $subjects = DB::table('object_term_relation as otr')
            ->join('term as t', 'otr.term_id', '=', 't.id')
            ->join('term_i18n as ti', function ($j) use ($culture) {
                $j->on('t.id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->join('taxonomy as tax', 't.taxonomy_id', '=', 'tax.id')
            ->where('otr.object_id', $recordId)
            ->whereIn('tax.id', [35, 42, 43]) // subjects, places, genres
            ->select('t.id as term_id', 'ti.name as term_name', 'tax.id as taxonomy_id')
            ->get();

        foreach ($subjects as $subject) {
            $termUri = $this->buildRecordUri('term', $subject->term_id, $baseUri, $instanceId);
            if (!isset($nodeIndex[$termUri])) {
                $nodeIndex[$termUri] = true;
                $type = 'Thing';
                if ($subject->taxonomy_id == 42) {
                    $type = 'Place';
                }
                $nodes[] = [
                    'id'    => $termUri,
                    'label' => $subject->term_name ?: 'Term ' . $subject->term_id,
                    'type'  => $type,
                ];
            }
            // Subject access points — taxonomy 42 is Places, others are Subjects
            $subjPredicate = ($subject->taxonomy_id == 42)
                ? 'rico:hasOrHadLocation'
                : 'rico:hasOrHadSubject';
            $edges[] = [
                'source' => $recordUri,
                'target' => $termUri,
                'predicate' => $subjPredicate,
                'label' => 'about',
            ];
        }

        // Parent/child relations
        if (isset($record->parent_id) && $record->parent_id && $record->parent_id > 1) {
            $parent = DB::table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                })
                ->where('io.id', $record->parent_id)
                ->select('io.id', 'ioi.title')
                ->first();

            if ($parent) {
                $parentUri = $this->buildRecordUri('recordset', $parent->id, $baseUri, $instanceId);
                if (!isset($nodeIndex[$parentUri])) {
                    $nodeIndex[$parentUri] = true;
                    $nodes[] = [
                        'id'    => $parentUri,
                        'label' => $parent->title ?: 'Record ' . $parent->id,
                        'type'  => 'RecordSet',
                    ];
                }
                $edges[] = [
                    'source' => $recordUri,
                    'target' => $parentUri,
                    'predicate' => 'rico:isOrWasPartOf',
                    'label' => 'part of',
                ];
            }
        }

        // RiC-O: hasCreationDate / hasAccumulationDate — temporal date modelling (rico:Date nodes)
        $dateEvents = DB::table('event as e')
            ->leftJoin('event_i18n as ei', function ($j) use ($culture) {
                $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                $j->on('e.type_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
            })
            ->where('e.object_id', $recordId)
            ->select('e.id as event_id', 'e.type_id', 'e.start_date', 'e.end_date', 'ei.date as date_display', 'ti.name as event_type_name')
            ->get();

        foreach ($dateEvents as $de) {
            $dateValue = $de->date_display ?: ($de->start_date ? substr($de->start_date, 0, 10) : null);
            if (!$dateValue) {
                continue;
            }
            $dateUri = $this->buildRecordUri('date', $de->event_id, $baseUri, $instanceId);
            if (!isset($nodeIndex[$dateUri])) {
                $nodeIndex[$dateUri] = true;
                $nodes[] = [
                    'id'    => $dateUri,
                    'label' => $dateValue,
                    'type'  => 'Date',
                ];
            }
            // Creation events => hasCreationDate; Accumulation => hasAccumulationDate; others => hasBeginningDate.
            $datePredicate = match (true) {
                $de->type_id == 112 => 'rico:hasAccumulationDate',
                $de->type_id == 111 => 'rico:hasCreationDate',
                default             => 'rico:hasBeginningDate',
            };
            $edges[] = [
                'source' => $recordUri,
                'target' => $dateUri,
                'predicate' => $datePredicate,
                'label' => $this->humanisePredicate($datePredicate),
            ];
        }

        // RiC-O: hasOrHadHolder — map from repository_id
        if (isset($record->repository_id) && $record->repository_id) {
            $repo = DB::table('repository as r')
                ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                    $j->on('r.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
                })
                ->where('r.id', $record->repository_id)
                ->select('r.id', 'ai.authorized_form_of_name')
                ->first();

            if ($repo) {
                $repoUri = $this->buildRecordUri('corporatebody', $repo->id, $baseUri, $instanceId);
                if (!isset($nodeIndex[$repoUri])) {
                    $nodeIndex[$repoUri] = true;
                    $nodes[] = [
                        'id'    => $repoUri,
                        'label' => $repo->authorized_form_of_name ?: 'Repository ' . $repo->id,
                        'type'  => 'CorporateBody',
                    ];
                }
                $edges[] = [
                    'source' => $recordUri,
                    'target' => $repoUri,
                    'predicate' => 'rico:hasOrHadHolder',
                    'label' => 'has or had holder',
                ];
            }
        }

        // RiC-O: relation-table links (only actors, IOs, repositories, terms).
        // Canonical RiC-O predicate sourced from ric_relation_meta when present;
        // falls back to a generic rico:isAssociatedWith when not.
        $relations = DB::table('relation as r')
            ->join('object as o_other', function ($j) use ($recordId) {
                $j->on(DB::raw("CASE WHEN r.subject_id = {$recordId} THEN r.object_id ELSE r.subject_id END"), '=', 'o_other.id');
            })
            ->leftJoin('ric_relation_meta as rm', 'r.id', '=', 'rm.relation_id')
            ->leftJoin('actor_i18n as ai_s', function ($j) use ($culture) {
                $j->on('r.subject_id', '=', 'ai_s.id')->where('ai_s.culture', '=', $culture);
            })
            ->leftJoin('actor_i18n as ai_o', function ($j) use ($culture) {
                $j->on('r.object_id', '=', 'ai_o.id')->where('ai_o.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as ioi_s', function ($j) use ($culture) {
                $j->on('r.subject_id', '=', 'ioi_s.id')->where('ioi_s.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as ioi_o', function ($j) use ($culture) {
                $j->on('r.object_id', '=', 'ioi_o.id')->where('ioi_o.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti_r', function ($j) use ($culture) {
                $j->on('r.type_id', '=', 'ti_r.id')->where('ti_r.culture', '=', $culture);
            })
            ->where(function ($q) use ($recordId) {
                $q->where('r.subject_id', $recordId)
                  ->orWhere('r.object_id', $recordId);
            })
            ->whereIn('o_other.class_name', ['QubitActor', 'QubitInformationObject', 'QubitRepository', 'QubitTerm'])
            ->select(
                'r.id as relation_id', 'r.subject_id', 'r.object_id', 'r.type_id',
                'rm.rico_predicate', 'rm.inverse_predicate',
                'o_other.class_name as other_class',
                'ai_s.authorized_form_of_name as subject_name',
                'ai_o.authorized_form_of_name as object_name',
                'ioi_s.title as subject_title',
                'ioi_o.title as object_title',
                'ti_r.name as relation_type'
            )
            ->limit(30)
            ->get();

        foreach ($relations as $rel) {
            $isSubject = ($rel->subject_id == $recordId);
            $otherId = $isSubject ? $rel->object_id : $rel->subject_id;
            $otherName = $isSubject
                ? ($rel->object_name ?: $rel->object_title ?: null)
                : ($rel->subject_name ?: $rel->subject_title ?: null);
            if (!$otherName) continue; // Skip unresolvable entities
            $type = str_contains($rel->other_class ?? '', 'Actor') ? 'Person' : 'RecordSet';
            $otherUri = $this->buildRecordUri(strtolower($type === 'Person' ? 'person' : 'recordset'), $otherId, $baseUri, $instanceId);

            if (!isset($nodeIndex[$otherUri])) {
                $nodeIndex[$otherUri] = true;
                $nodes[] = [
                    'id'    => $otherUri,
                    'label' => $otherName,
                    'type'  => 'Person',
                ];
            }

            // Direction: from the record's perspective, if we are the subject of
            // the relation the predicate applies as-is; otherwise use the inverse.
            $predicate = $isSubject
                ? ($rel->rico_predicate ?: 'rico:isAssociatedWith')
                : ($rel->inverse_predicate ?: $rel->rico_predicate ?: 'rico:isAssociatedWith');

            $edges[] = [
                'source' => $recordUri,
                'target' => $otherUri,
                'predicate' => $predicate,
                'label'  => $rel->relation_type ?: $this->humanisePredicate($predicate),
            ];
        }

        // RiC-native entities (Activities, Places, Rules, Instantiations)
        $ricEntityService = new \AhgRic\Services\RicEntityService($culture);
        $ricEntities = $ricEntityService->getEntitiesForRecord($recordId);

        // Activities
        foreach ($ricEntities['activities'] ?? [] as $act) {
            $actUri = $this->buildRecordUri('activity', $act->id, $baseUri, $instanceId);
            if (!isset($nodeIndex[$actUri])) {
                $nodeIndex[$actUri] = true;
                $nodes[] = [
                    'id'    => $actUri,
                    'label' => $act->name ?: ($act->date_display ? ucfirst($act->type_id ?? 'Activity') . ' (' . $act->date_display . ')' : 'Activity #' . $act->id),
                    'type'  => 'Activity',
                ];
            }
            $edges[] = [
                'source' => $actUri,
                'target' => $recordUri,
                'predicate' => 'rico:resultsOrResultedIn',
                'label' => 'results in',
            ];
        }

        // Places
        foreach ($ricEntities['places'] ?? [] as $place) {
            $placeUri = $this->buildRecordUri('place', $place->id, $baseUri, $instanceId);
            if (!isset($nodeIndex[$placeUri])) {
                $nodeIndex[$placeUri] = true;
                $nodes[] = [
                    'id'    => $placeUri,
                    'label' => $place->name ?: 'Place #' . $place->id,
                    'type'  => 'Place',
                ];
            }
            $edges[] = [
                'source' => $recordUri,
                'target' => $placeUri,
                'predicate' => 'rico:hasOrHadLocation',
                'label' => 'has or had location',
            ];
        }

        // Rules
        foreach ($ricEntities['rules'] ?? [] as $rule) {
            $ruleUri = $this->buildRecordUri('rule', $rule->id, $baseUri, $instanceId);
            if (!isset($nodeIndex[$ruleUri])) {
                $nodeIndex[$ruleUri] = true;
                $nodes[] = [
                    'id'    => $ruleUri,
                    'label' => $rule->title ?: 'Rule #' . $rule->id,
                    'type'  => 'Rule',
                ];
            }
            $edges[] = [
                'source' => $recordUri,
                'target' => $ruleUri,
                'predicate' => 'rico:isOrWasRegulatedBy',
                'label' => 'is or was regulated by',
            ];
        }

        // Instantiations
        foreach ($ricEntities['instantiations'] ?? [] as $inst) {
            $instUri = $this->buildRecordUri('instantiation', $inst->id, $baseUri, $instanceId);
            if (!isset($nodeIndex[$instUri])) {
                $nodeIndex[$instUri] = true;
                $nodes[] = [
                    'id'    => $instUri,
                    'label' => $inst->title ?: ($inst->mime_type ? 'Instantiation (' . $inst->mime_type . ')' : 'Instantiation #' . $inst->id),
                    'type'  => 'Instantiation',
                ];
            }
            $edges[] = [
                'source' => $recordUri,
                'target' => $instUri,
                'predicate' => 'rico:hasInstantiation',
                'label' => 'has instantiation',
            ];
        }

        // RiC-native relations (with ric_relation_meta) that link to non-RiC entities
        $ricRelations = DB::table('relation as r')
            ->join('ric_relation_meta as rm', 'r.id', '=', 'rm.relation_id')
            ->where(function ($q) use ($recordId) {
                $q->where('r.subject_id', $recordId)->orWhere('r.object_id', $recordId);
            })
            ->select('r.subject_id', 'r.object_id', 'rm.rico_predicate', 'rm.dropdown_code')
            ->limit(30)
            ->get();

        foreach ($ricRelations as $rr) {
            $otherId = ($rr->subject_id == $recordId) ? $rr->object_id : $rr->subject_id;
            $otherClassName = DB::table('object')->where('id', $otherId)->value('class_name');
            // Skip entities we already added above
            if (in_array($otherClassName, ['RicActivity', 'RicPlace', 'RicRule', 'RicInstantiation'])) continue;

            $otherName = $ricEntityService->resolveEntityName($otherId);
            $otherType = match ($otherClassName) {
                'QubitActor' => 'Person',
                'QubitInformationObject' => 'RecordSet',
                'QubitRepository' => 'CorporateBody',
                'QubitFunctionObject' => 'Function',
                default => $otherClassName ?? 'Entity',
            };
            $otherUri = $this->buildRecordUri(strtolower($otherType), $otherId, $baseUri, $instanceId);

            if (!isset($nodeIndex[$otherUri])) {
                $nodeIndex[$otherUri] = true;
                $nodes[] = ['id' => $otherUri, 'label' => $otherName, 'type' => $otherType];
            }
            $predicate = $rr->rico_predicate ?: 'rico:isAssociatedWith';
            $label = $this->humanisePredicate($predicate);
            if ($rr->subject_id == $recordId) {
                $edges[] = [
                    'source' => $recordUri,
                    'target' => $otherUri,
                    'predicate' => $predicate,
                    'label' => $label,
                ];
            } else {
                $edges[] = [
                    'source' => $otherUri,
                    'target' => $recordUri,
                    'predicate' => $predicate,
                    'label' => $label,
                ];
            }
        }

        // RiC-O: describesOrDescribed — finding aid references
        if (Schema::hasTable('finding_aid')) {
            $findingAids = DB::table('finding_aid')
                ->where('information_object_id', $recordId)
                ->select('id', 'name')
                ->limit(10)
                ->get();

            foreach ($findingAids as $fa) {
                $faUri = $this->buildRecordUri('findingaid', $fa->id, $baseUri, $instanceId);
                if (!isset($nodeIndex[$faUri])) {
                    $nodeIndex[$faUri] = true;
                    $nodes[] = [
                        'id'    => $faUri,
                        'label' => $fa->name ?: 'Finding Aid ' . $fa->id,
                        'type'  => 'FindingAid',
                    ];
                }
                $edges[] = ['source' => $faUri, 'target' => $recordUri, 'label' => 'describes Or Described'];
            }
        }

        $nodes = $this->enrichNodesWithSlugs($nodes);

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    /**
     * Extract a human-readable label from a URI.
     */
    protected function extractLabel(string $uri): string
    {
        $cultures = $this->getLabelCultures();

        // Ontology predicate URIs (e.g., https://...#hasOrHadSubject)
        if (preg_match('/#(\w+)$/', $uri, $m)) {
            return $this->camelToReadable($m[1]);
        }
        // Term-based URIs
        if (preg_match('/\/(place|term|concept|documentaryformtype|carriertype|contenttype|recordstate|language)\/(\d+)$/', $uri, $m)) {
            foreach ($cultures as $c) {
                $term = DB::table('term_i18n')->where('id', $m[2])->where('culture', $c)->value('name');
                if ($term) return $term;
            }
        }
        // Actor URIs
        if (preg_match('/\/(person|actor|corporatebody|family)\/(\d+)$/', $uri, $m)) {
            foreach ($cultures as $c) {
                $name = DB::table('actor_i18n')->where('id', $m[2])->where('culture', $c)->value('authorized_form_of_name');
                if ($name) return $name;
            }
        }
        // Record URIs
        if (preg_match('/\/(record|recordset)\/(\d+)$/', $uri, $m)) {
            foreach ($cultures as $c) {
                $title = DB::table('information_object_i18n')->where('id', $m[2])->where('culture', $c)->value('title');
                if ($title) return $title;
            }
        }
        // Event URIs
        if (preg_match('/\/(production|accumulation|activity|event)\/(\d+)$/', $uri, $m)) {
            $event = DB::table('event as e')
                ->leftJoin('event_i18n as ei', function ($j) use ($culture) {
                    $j->on('e.id', '=', 'ei.id')->where('ei.culture', '=', $culture);
                })
                ->leftJoin('term_i18n as ti', function ($j) use ($culture) {
                    $j->on('e.type_id', '=', 'ti.id')->where('ti.culture', '=', $culture);
                })
                ->where('e.id', $m[2])
                ->select('ti.name as type_name', 'ei.date as date_text')
                ->first();
            if ($event) {
                $label = $event->type_name ?: ucfirst($m[1]);
                if ($event->date_text) {
                    $label .= ': ' . $event->date_text;
                }
                return $label;
            }
        }
        // Instantiation URIs (digital objects)
        if (preg_match('/\/instantiation\/(\d+)$/', $uri, $m)) {
            $do = DB::table('digital_object as d')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('d.object_id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
                })
                ->where('d.id', $m[1])
                ->select('d.name', 'd.mime_type', 'ioi.title')
                ->first();
            if ($do) {
                $label = $do->name ?: ($do->title ? $do->title . ' (file)' : null);
                if ($label) {
                    $ext  = pathinfo($label, PATHINFO_EXTENSION);
                    $base = pathinfo($label, PATHINFO_FILENAME);
                    if (mb_strlen($base) > 40) {
                        $base = mb_substr($base, 0, 37) . '...';
                    }
                    return $ext ? $base . '.' . $ext : $base;
                }
            }
        }
        // Generic numeric URI
        if (preg_match('/\/(\w+)\/(\d+)$/', $uri, $m)) {
            return ucfirst($m[1]) . ' ' . $m[2];
        }

        return 'Unknown';
    }

    /**
     * Extract RiC type from an ontology URI.
     */
    protected function extractType(string $uri): string
    {
        if (preg_match('/#(\w+)$/', $uri, $m)) {
            return $m[1];
        }
        return 'Unknown';
    }

    /**
     * Infer RiC type from a resource URI path segment.
     */
    protected function extractTypeFromUri(string $uri): string
    {
        if (preg_match('/\/(\w+)\/\d+$/', $uri, $m)) {
            $map = [
                'recordset'          => 'RecordSet',
                'record'             => 'Record',
                'recordpart'         => 'RecordPart',
                'recordresource'     => 'RecordResource',
                'person'             => 'Person',
                'family'             => 'Family',
                'corporatebody'      => 'CorporateBody',
                'place'              => 'Place',
                'instantiation'      => 'Instantiation',
                'production'         => 'Production',
                'accumulation'       => 'Accumulation',
                'activity'           => 'Activity',
                'function'           => 'Function',
                'concept'            => 'Concept',
                'term'               => 'Concept',
                'documentaryformtype'=> 'DocumentaryFormType',
                'carriertype'        => 'CarrierType',
                'contenttype'        => 'ContentType',
                'recordstate'        => 'RecordState',
                'language'           => 'Language',
                'mandate'            => 'Mandate',
                'rule'               => 'Rule',
                'mechanism'          => 'Mechanism',
                'date'               => 'Date',
                'authorityrecord'    => 'AuthorityRecord',
                'findingaid'         => 'FindingAid',
            ];
            return $map[strtolower($m[1])] ?? ucfirst($m[1]);
        }
        return 'Unknown';
    }

    /**
     * Enrich graph nodes with AtoM slugs for clickable URLs.
     */
    protected function enrichNodesWithSlugs(array $nodes): array
    {
        $idMap = [];
        foreach ($nodes as $idx => $node) {
            if (preg_match('/\/(\d+)$/', $node['id'], $m)) {
                $id = (int) $m[1];
                $idMap[$id][] = $idx;
            }
        }

        if (empty($idMap)) {
            return $nodes;
        }

        try {
            $slugs = DB::table('slug')
                ->whereIn('object_id', array_keys($idMap))
                ->pluck('slug', 'object_id')
                ->toArray();
        } catch (\Exception $e) {
            return $nodes;
        }

        foreach ($idMap as $id => $indices) {
            $slug = $slugs[$id] ?? null;
            foreach ($indices as $idx) {
                $nodes[$idx]['atomId'] = $id;
                if ($slug) {
                    $nodes[$idx]['atomUrl'] = '/' . $slug;
                }
            }
        }

        return $nodes;
    }

    /**
     * Convert camelCase to readable text.
     */
    protected function camelToReadable(string $str): string
    {
        $result = preg_replace('/([a-z])([A-Z])/', '$1 $2', $str);
        return ucfirst($result);
    }

    // =========================================================================
    // RiC Semantic Search
    // =========================================================================

    /**
     * Semantic Search — full page.
     */
    public function semanticSearch()
    {
        $config = $this->getFusekiConfig();
        $searchApiUrl = $config['ric_search_api'] ?? config('services.ric.search_api', 'http://localhost:5001/api');

        return view('ahg-ric::semantic-search', compact('searchApiUrl'));
    }

    // =========================================================================
    // RiC-O Community Features: SHACL, JSON-LD, External Linking, Multilingual
    // =========================================================================

    /**
     * Get ordered list of cultures for multilingual label resolution.
     * Tries the current locale first, then English, then other available cultures.
     */
    protected function getLabelCultures(): array
    {
        $primary = app()->getLocale() ?: 'en';
        $cultures = [$primary];
        if ($primary !== 'en') {
            $cultures[] = 'en';
        }
        // Additional cultures commonly used in archives
        foreach (['af', 'fr', 'de', 'nl', 'pt', 'es', 'it'] as $c) {
            if (!in_array($c, $cultures)) {
                $cultures[] = $c;
            }
        }
        return $cultures;
    }

    /**
     * SHACL Validation — validates RiC data against SHACL shapes via Fuseki.
     * GET /admin/ric/shacl-validate
     */
    public function shaclValidate()
    {
        $config = $this->getFusekiConfig();
        $fusekiEndpoint = $config['fuseki_endpoint'] ?? config('services.ric.fuseki_endpoint', 'http://localhost:3030/ric');
        $fusekiUsername  = $config['fuseki_username'] ?? config('services.ric.fuseki_username', 'admin');
        $fusekiPassword  = $config['fuseki_password'] ?? config('services.ric.fuseki_password', '');

        // Read SHACL shapes file
        $shapesPath = base_path('packages/ahg-ric/tools/ric_shacl_shapes.ttl');
        if (!file_exists($shapesPath)) {
            return response()->json([
                'success' => false,
                'error'   => 'SHACL shapes file not found at ' . $shapesPath,
            ], 404);
        }

        $shapesData = file_get_contents($shapesPath);

        // First, try Fuseki's built-in SHACL validation endpoint
        $shaclEndpoint = rtrim($fusekiEndpoint, '/') . '/shacl?graph=default';

        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $shaclEndpoint,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $shapesData,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: text/turtle',
                'Accept: application/ld+json, application/json, text/turtle',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 5,
        ];
        if (!empty($fusekiPassword)) {
            $opts[CURLOPT_USERPWD] = "{$fusekiUsername}:{$fusekiPassword}";
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            // Try to parse as JSON
            $parsed = json_decode($response, true);
            if ($parsed !== null) {
                return response()->json([
                    'success' => true,
                    'method'  => 'fuseki-shacl-endpoint',
                    'results' => $parsed,
                ]);
            }

            // Return raw Turtle/text response
            return response()->json([
                'success'      => true,
                'method'       => 'fuseki-shacl-endpoint',
                'content_type' => $contentType,
                'results_raw'  => $response,
            ]);
        }

        // Fallback: use SPARQL-based lightweight validation
        $validationResults = $this->sparqlBasedValidation($fusekiEndpoint . '/query', $fusekiUsername, $fusekiPassword);

        return response()->json([
            'success' => true,
            'method'  => 'sparql-based',
            'note'    => 'Fuseki SHACL endpoint returned HTTP ' . $httpCode . ($curlError ? ' (' . $curlError . ')' : '') . '. Using SPARQL-based validation fallback.',
            'results' => $validationResults,
        ]);
    }

    /**
     * Lightweight SPARQL-based validation checking common SHACL-like constraints.
     */
    protected function sparqlBasedValidation(string $endpoint, string $username, string $password): array
    {
        $violations = [];

        // Check: RecordSets without title
        $q1 = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT (COUNT(?s) AS ?count) WHERE {
  ?s a rico:RecordSet .
  FILTER NOT EXISTS { ?s rico:title ?t }
}
SPARQL;
        $r1 = $this->executeSparql($q1, $endpoint, $username, $password);
        $count1 = (int)($r1['results']['bindings'][0]['count']['value'] ?? 0);
        if ($count1 > 0) {
            $violations[] = [
                'severity'    => 'Violation',
                'shape'       => 'RecordSetShape',
                'constraint'  => 'rico:title required',
                'count'       => $count1,
                'message'     => "{$count1} RecordSet(s) missing required rico:title",
            ];
        }

        // Check: Persons without title/name
        $q2 = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT (COUNT(?s) AS ?count) WHERE {
  ?s a rico:Person .
  FILTER NOT EXISTS { ?s rico:title ?t }
  FILTER NOT EXISTS { ?s rico:name ?n }
}
SPARQL;
        $r2 = $this->executeSparql($q2, $endpoint, $username, $password);
        $count2 = (int)($r2['results']['bindings'][0]['count']['value'] ?? 0);
        if ($count2 > 0) {
            $violations[] = [
                'severity'    => 'Warning',
                'shape'       => 'PersonShape',
                'constraint'  => 'rico:title or rico:name recommended',
                'count'       => $count2,
                'message'     => "{$count2} Person(s) missing title or name",
            ];
        }

        // Check: RecordSets without creator
        $q3 = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT (COUNT(?s) AS ?count) WHERE {
  ?s a rico:RecordSet .
  FILTER NOT EXISTS { ?s rico:hasCreator ?c }
  FILTER NOT EXISTS { ?s rico:hasOrHadHolder ?h }
}
SPARQL;
        $r3 = $this->executeSparql($q3, $endpoint, $username, $password);
        $count3 = (int)($r3['results']['bindings'][0]['count']['value'] ?? 0);
        if ($count3 > 0) {
            $violations[] = [
                'severity'    => 'Warning',
                'shape'       => 'RecordSetShape',
                'constraint'  => 'rico:hasCreator or rico:hasOrHadHolder recommended',
                'count'       => $count3,
                'message'     => "{$count3} RecordSet(s) without creator or holder",
            ];
        }

        // Check: Records without date
        $q4 = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT (COUNT(?s) AS ?count) WHERE {
  ?s a rico:RecordSet .
  FILTER NOT EXISTS { ?s rico:hasCreationDate ?d }
  FILTER NOT EXISTS { ?s rico:hasAccumulationDate ?d2 }
}
SPARQL;
        $r4 = $this->executeSparql($q4, $endpoint, $username, $password);
        $count4 = (int)($r4['results']['bindings'][0]['count']['value'] ?? 0);
        if ($count4 > 0) {
            $violations[] = [
                'severity'    => 'Info',
                'shape'       => 'RecordSetShape',
                'constraint'  => 'rico:hasCreationDate or rico:hasAccumulationDate recommended',
                'count'       => $count4,
                'message'     => "{$count4} RecordSet(s) without creation or accumulation date",
            ];
        }

        $totalViolations = array_sum(array_column(
            array_filter($violations, fn($v) => $v['severity'] === 'Violation'),
            'count'
        ));
        $totalWarnings = array_sum(array_column(
            array_filter($violations, fn($v) => $v['severity'] === 'Warning'),
            'count'
        ));

        return [
            'conforms'         => $totalViolations === 0,
            'total_violations' => $totalViolations,
            'total_warnings'   => $totalWarnings,
            'details'          => $violations,
            'validated_at'     => now()->toDateTimeString(),
        ];
    }

    /**
     * JSON-LD Export for a specific record.
     * GET /admin/ric/export/jsonld?id={recordId}
     */
    public function exportJsonLd(Request $request)
    {
        $recordId = $request->input('id');
        if (!$recordId) {
            return response()->json(['error' => 'Record ID is required'], 400);
        }

        $config = $this->getFusekiConfig();
        $fusekiEndpoint = ($config['fuseki_endpoint'] ?? config('services.ric.fuseki_endpoint', 'http://localhost:3030/ric')) . '/query';
        $fusekiUsername  = $config['fuseki_username'] ?? config('services.ric.fuseki_username', 'admin');
        $fusekiPassword  = $config['fuseki_password'] ?? config('services.ric.fuseki_password', '');
        $baseUri         = $config['ric_base_uri'] ?? config('services.ric.base_uri', 'https://archives.theahg.co.za/ric');
        $instanceId      = $config['ric_instance_id'] ?? config('services.ric.instance_id', 'atom-psis');

        // Build graph data
        $graphData = $this->buildGraphData(
            $recordId, $fusekiEndpoint, $fusekiUsername, $fusekiPassword, $baseUri, $instanceId
        );

        // Convert graph to JSON-LD structure
        $context = [
            'rico'   => 'https://www.ica.org/standards/RiC/ontology#',
            'rdf'    => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs'   => 'http://www.w3.org/2000/01/rdf-schema#',
            'owl'    => 'http://www.w3.org/2002/07/owl#',
            'xsd'    => 'http://www.w3.org/2001/XMLSchema#',
            'title'  => 'rico:title',
            'hasCreator'          => ['@id' => 'rico:hasCreator', '@type' => '@id'],
            'hasOrHadHolder'      => ['@id' => 'rico:hasOrHadHolder', '@type' => '@id'],
            'hasCreationDate'     => ['@id' => 'rico:hasCreationDate', '@type' => '@id'],
            'hasAccumulationDate' => ['@id' => 'rico:hasAccumulationDate', '@type' => '@id'],
            'describesOrDescribed'=> ['@id' => 'rico:describesOrDescribed', '@type' => '@id'],
            'isAssociatedWith'    => ['@id' => 'rico:isAssociatedWith', '@type' => '@id'],
            'hasProvenanceOf'     => ['@id' => 'rico:hasProvenanceOf', '@type' => '@id'],
            'isEquivalentTo'      => ['@id' => 'rico:isEquivalentTo', '@type' => '@id'],
            'resultsOrResultedFrom' => ['@id' => 'rico:resultsOrResultedFrom', '@type' => '@id'],
            'isPartOf'            => ['@id' => 'rico:isPartOf', '@type' => '@id'],
            'hasOrHadSubject'     => ['@id' => 'rico:hasOrHadSubject', '@type' => '@id'],
        ];

        // Build @graph array from nodes and edges
        $graph = [];
        $nodeMap = [];
        foreach ($graphData['nodes'] as $node) {
            $nodeMap[$node['id']] = $node;
            $entity = [
                '@id'   => $node['id'],
                '@type' => 'rico:' . ($node['type'] ?? 'RecordResource'),
                'title' => $node['label'] ?? null,
            ];
            $graph[] = $entity;
        }

        // Merge edge data into graph entities
        foreach ($graphData['edges'] as $edge) {
            $predicate = $this->labelToPredicate($edge['label'] ?? '');
            foreach ($graph as &$entity) {
                if ($entity['@id'] === $edge['source']) {
                    if (!isset($entity[$predicate])) {
                        $entity[$predicate] = [];
                    }
                    $entity[$predicate][] = ['@id' => $edge['target']];
                    break;
                }
            }
            unset($entity);
        }

        $jsonLd = [
            '@context' => $context,
            '@graph'   => $graph,
        ];

        $json = json_encode($jsonLd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($request->input('download')) {
            $filename = 'ric-' . $recordId . '.jsonld';
            return response($json, 200, [
                'Content-Type' => 'application/ld+json; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        return response($json, 200, [
            'Content-Type' => 'application/ld+json; charset=utf-8',
        ]);
    }

    /**
     * Convert a readable edge label back to a RiC-O predicate name.
     */
    protected function labelToPredicate(string $label): string
    {
        $map = [
            'has Creator'              => 'hasCreator',
            'has Or Had Holder'        => 'hasOrHadHolder',
            'has Creation Date'        => 'hasCreationDate',
            'has Accumulation Date'    => 'hasAccumulationDate',
            'describes Or Described'   => 'describesOrDescribed',
            'is Associated With'       => 'isAssociatedWith',
            'has Provenance Of'        => 'hasProvenanceOf',
            'is Equivalent To'         => 'isEquivalentTo',
            'results Or Resulted From' => 'resultsOrResultedFrom',
            'part of'                  => 'isPartOf',
            'about'                    => 'hasOrHadSubject',
        ];

        // Check exact match first
        if (isset($map[$label])) {
            return $map[$label];
        }
        // Check case-insensitive
        foreach ($map as $key => $value) {
            if (strcasecmp($key, $label) === 0) {
                return $value;
            }
        }
        // Convert readable label to camelCase predicate
        $words = explode(' ', $label);
        $camel = lcfirst(implode('', array_map('ucfirst', $words)));
        return $camel;
    }

    /**
     * Lookup external authority records from Wikidata and VIAF.
     * GET /admin/ric/lookup-external?name={name}&type={person|place|organization}
     */
    public function lookupExternal(Request $request)
    {
        $name = trim($request->input('name', ''));
        $type = $request->input('type', 'person');

        if (mb_strlen($name) < 2) {
            return response()->json(['error' => 'Name must be at least 2 characters'], 400);
        }

        $results = [
            'query'    => $name,
            'type'     => $type,
            'wikidata' => [],
            'viaf'     => [],
        ];

        // Wikidata search
        $wikidataResults = $this->searchWikidata($name, $type);
        $results['wikidata'] = $wikidataResults;

        // VIAF search
        $viafResults = $this->searchViaf($name, $type);
        $results['viaf'] = $viafResults;

        return response()->json($results);
    }

    /**
     * Search Wikidata API for matching entities.
     */
    protected function searchWikidata(string $name, string $type): array
    {
        $wdType = match ($type) {
            'person'       => 'Q5',            // human
            'place'        => 'Q515',          // city (broad match)
            'organization' => 'Q43229',        // organization
            default        => '',
        };

        $url = 'https://www.wikidata.org/w/api.php?' . http_build_query([
            'action'   => 'wbsearchentities',
            'search'   => $name,
            'language' => 'en',
            'limit'    => 10,
            'format'   => 'json',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Heratio/1.0 (https://archives.theahg.co.za; mailto:johan@theahg.co.za)',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [];
        }

        $data = json_decode($response, true);
        $items = [];

        foreach ($data['search'] ?? [] as $item) {
            $items[] = [
                'id'          => $item['id'] ?? '',
                'uri'         => $item['concepturi'] ?? ('https://www.wikidata.org/wiki/' . ($item['id'] ?? '')),
                'label'       => $item['label'] ?? '',
                'description' => $item['description'] ?? '',
                'source'      => 'wikidata',
            ];
        }

        return $items;
    }

    /**
     * Search VIAF API for authority records.
     */
    protected function searchViaf(string $name, string $type): array
    {
        $viafIndex = match ($type) {
            'person'       => 'local.personalNames',
            'organization' => 'local.corporateNames',
            'place'        => 'local.geographicNames',
            default        => 'local.names',
        };

        $url = 'https://www.viaf.org/viaf/AutoSuggest?' . http_build_query([
            'query' => $name,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'Heratio/1.0 (https://archives.theahg.co.za)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return [];
        }

        $data = json_decode($response, true);
        $items = [];

        foreach ($data['result'] ?? [] as $item) {
            $viafId = $item['viafid'] ?? '';
            $items[] = [
                'id'          => $viafId,
                'uri'         => $viafId ? 'https://viaf.org/viaf/' . $viafId : '',
                'label'       => $item['term'] ?? '',
                'description' => $item['nametype'] ?? '',
                'source'      => 'viaf',
            ];
        }

        return array_slice($items, 0, 10);
    }
}
