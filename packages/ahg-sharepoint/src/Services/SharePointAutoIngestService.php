<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointDriveRepository;
use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointAutoIngestService.
 *
 * Cron-driven SharePoint→Heratio ingest. Same algorithm; Laravel-flavored.
 *
 * @phase 2 (v2 ingest plan, steps 3 + 4 + C.5)
 */
class SharePointAutoIngestService
{
    private const LOCK_DIR = '/tmp';
    private const MAX_ITEMS_PER_RUN = 500;
    private const DEFAULT_MAPPINGS = [
        ['source' => 'Title', 'target' => 'title'],
        ['source' => 'Name', 'target' => 'title'],
        ['source' => 'Modified', 'target' => 'dates', 'transform' => 'date_iso'],
        ['source' => 'Created', 'target' => 'dates', 'transform' => 'date_iso'],
        ['source' => 'Author', 'target' => 'creator'],
        ['source' => 'CreatedBy', 'target' => 'creator'],
        ['source' => 'Description', 'target' => 'scopeAndContent'],
    ];

    public function __construct(
        private SharePointBrowserService $browser,
        private SharePointDriveRepository $drives,
    ) {
    }

    /**
     * @return array<int, array>
     */
    public function runDueRules(bool $force = false, bool $dryRun = false): array
    {
        $rules = $this->loadDueRules($force);
        $results = [];
        foreach ($rules as $rule) {
            $results[] = $this->runRule((int) $rule->id, $dryRun);
        }
        return $results;
    }

    public function runRule(int $ruleId, bool $dryRun = false): array
    {
        $result = ['rule_id' => $ruleId, 'status' => 'pending', 'items_new' => 0, 'items_skipped' => 0];

        $rule = DB::table('sharepoint_ingest_rule')->where('id', $ruleId)->first();
        if (!$rule) {
            return $result + ['status' => 'error', 'error' => "Rule {$ruleId} not found"];
        }
        $drive = $this->drives->find((int) $rule->drive_id);
        if (!$drive) {
            $this->updateRuleStatus($ruleId, 'error');
            return $result + ['status' => 'error', 'error' => "Drive {$rule->drive_id} not found"];
        }

        $lock = $this->acquireLock($ruleId);
        if ($lock === false) {
            return $result + ['status' => 'skipped', 'error' => 'Previous run still active'];
        }

        try {
            $items = $this->walkDrive((int) $drive->tenant_id, $drive->drive_id, $rule);
            $newItems = [];
            foreach ($items as $item) {
                if ($this->isAlreadyIngested((int) $rule->drive_id, $item['id'], (string) ($item['etag'] ?? ''))) {
                    ++$result['items_skipped'];
                    continue;
                }
                $newItems[] = $item;
            }
            $result['items_new'] = count($newItems);

            if ($dryRun) {
                $this->updateRuleStatus($ruleId, 'dry_run');
                return $result + ['status' => 'dry_run'];
            }
            if (empty($newItems)) {
                $this->updateRuleStatus($ruleId, 'ok');
                return $result + ['status' => 'no_new_items'];
            }

            if (count($newItems) > self::MAX_ITEMS_PER_RUN) {
                $newItems = array_slice($newItems, 0, self::MAX_ITEMS_PER_RUN);
                $result['items_new'] = count($newItems);
            }

            $sessionId = $this->createSession((int) $rule->id, $rule, $drive);
            $this->materializeMappings($sessionId, (int) $drive->id, $rule->template_id ?? null);
            $this->downloadAndRegister($sessionId, (int) $drive->tenant_id, $drive->drive_id, $newItems);

            $jobId = $this->dispatchCommit($sessionId);

            DB::table('sharepoint_ingest_rule')->where('id', $ruleId)->update([
                'items_ingested' => DB::raw('items_ingested + ' . count($newItems)),
                'last_run_at' => now(),
                'last_run_status' => 'ok',
            ]);

            return $result + ['status' => 'ok', 'session_id' => $sessionId, 'job_id' => $jobId];
        } catch (\Throwable $e) {
            $this->updateRuleStatus($ruleId, 'error');
            $this->logError($ruleId, $e);
            return $result + ['status' => 'error', 'error' => $e->getMessage()];
        } finally {
            $this->releaseLock($lock);
        }
    }

    public function materializeMappings(int $sessionId, int $drivePk, ?int $templateId = null): int
    {
        $chosenTemplate = null;
        if ($templateId !== null && $templateId > 0) {
            $chosenTemplate = DB::table('sharepoint_mapping_template')
                ->where('id', $templateId)
                ->where('drive_id', $drivePk)
                ->first();
        }
        if (!$chosenTemplate) {
            $chosenTemplate = DB::table('sharepoint_mapping_template')
                ->where('drive_id', $drivePk)
                ->where('is_default', 1)
                ->first();
        }
        if (!$chosenTemplate) {
            $chosenTemplate = DB::table('sharepoint_mapping_template')
                ->where('drive_id', $drivePk)
                ->orderBy('id')
                ->first();
        }
        if ($chosenTemplate) {
            $templates = DB::table('sharepoint_mapping')
                ->where('template_id', $chosenTemplate->id)
                ->orderBy('sort_order')
                ->get()
                ->all();
        } else {
            $templates = DB::table('sharepoint_mapping')
                ->where('drive_id', $drivePk)
                ->whereNull('template_id')
                ->orderBy('sort_order')
                ->get()
                ->all();
        }

        if (empty($templates)) {
            \Log::warning("SharePointAutoIngestService: no mapping template for drive {$drivePk}, falling back to defaults");
            foreach (self::DEFAULT_MAPPINGS as $i => $m) {
                DB::table('ingest_mapping')->insert([
                    'session_id' => $sessionId,
                    'source_column' => $m['source'],
                    'target_field' => $m['target'],
                    'transform' => $m['transform'] ?? null,
                    'is_ignored' => 0,
                    'sort_order' => $i,
                ]);
            }
            return count(self::DEFAULT_MAPPINGS);
        }

        foreach ($templates as $i => $t) {
            DB::table('ingest_mapping')->insert([
                'session_id' => $sessionId,
                'source_column' => $t->source_field,
                'target_field' => $t->target_field,
                'default_value' => $t->default_value,
                'transform' => $t->transform,
                'is_ignored' => 0,
                'sort_order' => (int) ($t->sort_order ?? $i),
            ]);
        }
        return count($templates);
    }

    private function loadDueRules(bool $force): array
    {
        $rules = DB::table('sharepoint_ingest_rule')->where('is_enabled', 1)->get()->all();
        if ($force) {
            return $rules;
        }
        return array_values(array_filter($rules, fn ($r) => $this->isCronDue($r->schedule_cron ?? '*/15 * * * *', $r->last_run_at)));
    }

    private function isCronDue(string $cron, ?string $lastRunAt): bool
    {
        if (!$lastRunAt) {
            return true;
        }
        $lastTs = strtotime($lastRunAt);
        return $lastTs === false || (time() - $lastTs) >= ($this->inferIntervalMinutes($cron) * 60);
    }

    private function inferIntervalMinutes(string $cron): int
    {
        if (preg_match('#^\*/(\d+)\s+\*\s+\*\s+\*\s+\*$#', trim($cron), $m)) {
            return max(1, (int) $m[1]);
        }
        if (preg_match('#^0\s+\*/(\d+)\s+\*\s+\*\s+\*$#', trim($cron), $m)) {
            return max(60, (int) $m[1] * 60);
        }
        return 15;
    }

    private function walkDrive(int $tenantId, string $driveId, object $rule): array
    {
        $startItemId = $this->resolveStartItemId($rule->folder_path ?? null);
        $patterns = $this->parsePatterns($rule->file_pattern ?? null);
        $requiredLabels = $this->parseLabels($rule->retention_label ?? null);
        $collected = [];
        $stack = [$startItemId];
        $visited = 0;
        $maxNodes = 5000;

        while (!empty($stack) && $visited < $maxNodes) {
            $itemId = array_pop($stack);
            ++$visited;
            $children = $this->browser->listChildren($tenantId, $driveId, $itemId);
            foreach ($children as $child) {
                if ($child['isFolder']) {
                    $stack[] = $child['id'];
                    continue;
                }
                if (!$this->matchesPattern($child['name'], $patterns)) {
                    continue;
                }
                if (!$this->matchesRetentionLabel($child['retentionLabel'] ?? null, $requiredLabels)) {
                    continue;
                }
                $collected[] = $child;
                if (count($collected) >= 10000) {
                    return $collected;
                }
            }
        }
        return $collected;
    }

    /**
     * @return array<int, string>
     */
    private function parseLabels(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }
        $parts = array_map(fn ($s) => strtolower(trim((string) $s)), explode(',', $csv));
        return array_values(array_filter($parts, fn ($s) => $s !== ''));
    }

    private function matchesRetentionLabel(?string $itemLabel, array $requiredLabels): bool
    {
        if (empty($requiredLabels)) {
            return true;
        }
        if ($itemLabel === null || $itemLabel === '') {
            return false;
        }
        return in_array(strtolower($itemLabel), $requiredLabels, true);
    }

    private function resolveStartItemId(?string $folderPath): string
    {
        if ($folderPath === null || $folderPath === '' || $folderPath === '/') {
            return 'root';
        }
        return 'root:/' . trim($folderPath, '/') . ':';
    }

    private function parsePatterns(?string $patternCsv): array
    {
        if ($patternCsv === null || trim($patternCsv) === '') {
            return ['*'];
        }
        return array_values(array_filter(array_map('trim', explode(',', $patternCsv))));
    }

    private function matchesPattern(string $name, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (fnmatch($p, $name, FNM_CASEFOLD)) {
                return true;
            }
        }
        return false;
    }

    private function isAlreadyIngested(int $driveId, string $itemId, string $etag): bool
    {
        $q = DB::table('ingest_file')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(sidecar_json, '$.sp_drive_id')) = ?", [$driveId])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(sidecar_json, '$.sp_item_id')) = ?", [$itemId])
            ->whereIn('status', ['completed', 'imported', 'pending']);
        if ($etag !== '') {
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(sidecar_json, '$.sp_etag')) = ?", [$etag]);
        }
        return $q->exists();
    }

    private function createSession(int $ruleId, object $rule, object $drive): int
    {
        $title = sprintf('SharePoint auto-ingest: %s [%s]', $rule->name, now()->format('Y-m-d H:i'));
        return DB::table('ingest_session')->insertGetId([
            'user_id' => $this->systemUserId(),
            'title' => $title,
            'entity_type' => 'description',
            'sector' => $rule->sector ?? 'archive',
            'standard' => $rule->standard ?? 'isadg',
            'repository_id' => $rule->repository_id,
            'parent_id' => $rule->parent_id,
            'parent_placement' => $rule->parent_placement ?? 'top_level',
            'output_create_records' => 1,
            'derivative_thumbnails' => 1,
            'derivative_reference' => 1,
            'process_ner' => $this->flag($rule->process_flags, 'ner'),
            'process_ocr' => $this->flag($rule->process_flags, 'ocr'),
            'process_virus_scan' => $this->flag($rule->process_flags, 'virus_scan', 1),
            'process_summarize' => $this->flag($rule->process_flags, 'summarize'),
            'process_spellcheck' => $this->flag($rule->process_flags, 'spellcheck'),
            'process_translate' => $this->flag($rule->process_flags, 'translate'),
            'process_format_id' => $this->flag($rule->process_flags, 'format_id'),
            'process_face_detect' => $this->flag($rule->process_flags, 'face_detect'),
            'status' => 'configure',
            'session_kind' => 'auto',
            'auto_commit' => 1,
            'source' => 'sharepoint_auto',
            'source_id' => $ruleId,
            'source_metadata' => json_encode([
                'sp_drive_id' => $drive->drive_id,
                'sp_drive_pk' => (int) $drive->id,
                'sp_drive_name' => $drive->drive_name,
                'sp_site_id' => $drive->site_id,
                'sp_site_title' => $drive->site_title,
                'sp_folder_path' => $rule->folder_path,
                'sp_file_pattern' => $rule->file_pattern,
                'rule_id' => $ruleId,
                'rule_name' => $rule->name,
                'run_started_at' => now()->toIso8601String(),
            ]),
            'config' => json_encode(['source' => 'sharepoint_auto', 'rule_id' => $ruleId]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function downloadAndRegister(int $sessionId, int $tenantId, string $driveId, array $items): void
    {
        $baseDir = $this->sessionDownloadDir($sessionId);
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $rowNum = 0;
        foreach ($items as $item) {
            ++$rowNum;
            $safeName = $this->sanitizeFilename($item['name'] ?: $item['id']);
            $itemDir = $baseDir . '/' . $item['id'];
            if (!is_dir($itemDir)) {
                mkdir($itemDir, 0775, true);
            }
            $destPath = $itemDir . '/' . $safeName;
            $this->browser->downloadItem($tenantId, $driveId, $item['id'], $destPath);

            $listFields = [];
            try {
                $meta = $this->browser->getMetadata($tenantId, $driveId, $item['id'], true);
                $listFields = $meta['_raw']['listItem']['fields'] ?? [];
            } catch (\Throwable $e) {
                $listFields = [];
            }

            $checksum = is_file($destPath) ? hash_file('sha256', $destPath) : null;

            DB::table('ingest_file')->insert([
                'session_id' => $sessionId,
                'file_type' => 'sharepoint',
                'original_name' => $item['name'],
                'stored_path' => $destPath,
                'file_size' => $item['size'] ?? (filesize($destPath) ?: 0),
                'mime_type' => $item['mimeType'] ?? null,
                'status' => 'pending',
                'source_hash' => $checksum,
                'sidecar_json' => json_encode([
                    'sp_drive_id' => DB::table('sharepoint_drive')->where('drive_id', $driveId)->value('id'),
                    'sp_drive_graph_id' => $driveId,
                    'sp_item_id' => $item['id'],
                    'sp_etag' => $item['etag'] ?? null,
                    'sp_web_url' => $item['webUrl'] ?? null,
                    'sp_list_item_fields' => $listFields,
                    'sp_last_modified' => $item['lastModifiedDateTime'] ?? null,
                    'sp_created' => $item['createdDateTime'] ?? null,
                    'sp_retention_label' => $item['retentionLabel'] ?? null,
                    'sp_retention_label_applied_at' => $item['retentionLabelAppliedAt'] ?? null,
                ]),
                'created_at' => now(),
            ]);

        }
    }

    private function dispatchCommit(int $sessionId): int
    {
        // Use AhgIngest service if installed; otherwise direct queue dispatch.
        if (class_exists('\\AhgIngest\\Services\\IngestService')
            && class_exists('\\AhgIngest\\Services\\IngestCommitService')) {
            $ingest = app(\AhgIngest\Services\IngestService::class);
            $commit = app(\AhgIngest\Services\IngestCommitService::class);
            $ingest->parseRows($sessionId);
            $ingest->enrichRows($sessionId);
            $ingest->validateSession($sessionId);
            $ingest->updateSessionStatus($sessionId, 'commit');
            $jobId = $commit->startJob($sessionId);
            $commit->executeJob($jobId);
            return $jobId;
        }

        // Fallback: just create the job row and let a queue worker pick it up.
        $jobId = DB::table('ingest_job')->insertGetId([
            'session_id' => $sessionId,
            'status' => 'queued',
            'total_rows' => DB::table('ingest_row')->where('session_id', $sessionId)->count(),
            'processed_rows' => 0,
            'created_at' => now(),
        ]);
        \Log::info("SharePoint auto-ingest queued ingest_job id={$jobId} (no IngestService bound)");
        return $jobId;
    }

    private function flag(?string $jsonFlags, string $key, int $default = 0): int
    {
        if ($jsonFlags === null) {
            return $default;
        }
        $decoded = json_decode($jsonFlags, true);
        if (!is_array($decoded)) {
            return $default;
        }
        return !empty($decoded[$key]) ? 1 : $default;
    }

    private function sessionDownloadDir(int $sessionId): string
    {
        $base = config('ahg-ingest.upload_dir', storage_path('app/ingest'));
        return rtrim($base, '/') . '/' . $sessionId;
    }

    private function sanitizeFilename(string $name): string
    {
        $name = preg_replace('#[\\\\/]+#', '_', $name);
        $name = preg_replace('#[^A-Za-z0-9._ \-]#', '_', $name);
        return substr($name, 0, 200);
    }

    private function systemUserId(): int
    {
        $u = DB::table('users')->orderBy('id')->first();
        return (int) ($u->id ?? 1);
    }

    private function updateRuleStatus(int $ruleId, string $status): void
    {
        DB::table('sharepoint_ingest_rule')->where('id', $ruleId)->update([
            'last_run_at' => now(),
            'last_run_status' => $status,
        ]);
    }

    private function logError(int $ruleId, \Throwable $e): void
    {
        \Log::error("SharePoint auto-ingest rule={$ruleId} failed: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
    }

    private function acquireLock(int $ruleId)
    {
        $path = self::LOCK_DIR . "/sp-rule-{$ruleId}.lock";
        $fh = fopen($path, 'c');
        if (!$fh) {
            return false;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return false;
        }
        return $fh;
    }

    private function releaseLock($handle): void
    {
        if ($handle === false) {
            return;
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
