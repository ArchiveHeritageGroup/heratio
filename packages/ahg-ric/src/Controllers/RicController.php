<?php

namespace AhgRic\Controllers;

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
     * RiC Dashboard — index page.
     */
    public function index()
    {
        if (! $this->tablesExist()) {
            return view('ahg-ric::not-configured');
        }

        // Fuseki settings from ahg_settings
        $fusekiSettings = [];
        if (Schema::hasTable('ahg_settings')) {
            $fusekiSettings = DB::table('ahg_settings')
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
            'fusekiSettings',
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
     * AJAX: trigger manual sync.
     */
    public function ajaxSync()
    {
        $logFile = storage_path('logs/ric_sync_' . date('Ymd_His') . '.log');
        $cmd = 'cd ' . base_path() . ' && php artisan ric:sync > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
        $pid = trim(shell_exec($cmd));

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
        $pids = shell_exec("pgrep -f 'ric:sync' 2>/dev/null");
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
     * Build overview graph from SPARQL.
     */
    protected function buildOverviewGraph(string $endpoint, string $username, string $password): array
    {
        $query = <<<'SPARQL'
PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>
SELECT ?s ?label ?type ?related ?relLabel ?relType ?pred WHERE {
  ?s a rico:RecordSet .
  ?s rico:title ?label .
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

        $nodes = $this->enrichNodesWithSlugs($nodes);

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
SELECT ?subject ?predicate ?object WHERE {
  { ?subject ?predicate ?object . FILTER(?subject IN ({$uriFilter})) FILTER(isURI(?object)) }
  UNION
  { ?subject ?predicate ?object . FILTER(?object IN ({$uriFilter})) FILTER(isURI(?subject)) }
  FILTER(?predicate != <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>)
} LIMIT 100
SPARQL;

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
                $edges[] = [
                    'source' => $actorUri,
                    'target' => $recordUri,
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
            $edges[] = ['source' => $recordUri, 'target' => $termUri, 'label' => 'about'];
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
                $edges[] = ['source' => $recordUri, 'target' => $parentUri, 'label' => 'part of'];
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
        $culture = app()->getLocale() === 'en' ? 'en' : app()->getLocale();

        // Ontology predicate URIs (e.g., https://...#hasOrHadSubject)
        if (preg_match('/#(\w+)$/', $uri, $m)) {
            return $this->camelToReadable($m[1]);
        }
        // Term-based URIs
        if (preg_match('/\/(place|term|concept|documentaryformtype|carriertype|contenttype|recordstate|language)\/(\d+)$/', $uri, $m)) {
            $term = DB::table('term_i18n')->where('id', $m[2])->where('culture', $culture)->value('name');
            if ($term) return $term;
        }
        // Actor URIs
        if (preg_match('/\/(person|actor|corporatebody|family)\/(\d+)$/', $uri, $m)) {
            $name = DB::table('actor_i18n')->where('id', $m[2])->where('culture', $culture)->value('authorized_form_of_name');
            if ($name) return $name;
        }
        // Record URIs
        if (preg_match('/\/(record|recordset)\/(\d+)$/', $uri, $m)) {
            $title = DB::table('information_object_i18n')->where('id', $m[2])->where('culture', $culture)->value('title');
            if ($title) return $title;
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
}
