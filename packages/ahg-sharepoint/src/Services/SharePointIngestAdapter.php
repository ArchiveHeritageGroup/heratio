<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointDriveRepository;
use AhgSharePoint\Repositories\SharePointEventRepository;
use AhgSharePoint\Repositories\SharePointTenantRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointIngestAdapter.
 *
 * @phase 2.A
 */
class SharePointIngestAdapter
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED_DUPLICATE = 'skipped_duplicate';
    public const STATUS_SKIPPED_NOT_ALLOWLISTED = 'skipped_not_allowlisted';

    public function __construct(
        private GraphClientService $graph,
        private SharePointTenantRepository $tenants,
        private SharePointDriveRepository $drives,
        private SharePointEventRepository $events,
        private SharePointMappingService $mapping,
        private SharePointRetentionMapper $retention,
    ) {
    }

    public function ingest(int $eventId): string
    {
        $event = $this->events->find($eventId);
        if ($event === null) {
            throw new \InvalidArgumentException("Event {$eventId} not found");
        }

        $this->events->markStatus($eventId, 'processing');
        $this->events->incrementAttempts($eventId);

        try {
            if ($this->events->isDuplicate(
                (int) $event->drive_id,
                $event->sp_item_id,
                $event->sp_etag,
                $eventId,
            )) {
                $this->events->markStatus($eventId, self::STATUS_SKIPPED_DUPLICATE);
                return self::STATUS_SKIPPED_DUPLICATE;
            }

            $drive = $this->drives->find((int) $event->drive_id);
            if ($drive === null) {
                throw new \RuntimeException("Drive {$event->drive_id} not found");
            }
            $tenant = $this->tenants->find((int) $drive->tenant_id);
            if ($tenant === null) {
                throw new \RuntimeException("Tenant {$drive->tenant_id} not found");
            }
            if ($event->sp_item_id === null) {
                throw new \RuntimeException("Event {$eventId} has no sp_item_id");
            }

            $driveItem = $this->graph->get(
                (int) $tenant->id,
                "/sites/{$drive->site_id}/drives/{$drive->drive_id}/items/{$event->sp_item_id}",
            );
            $listItemFields = $this->graph->getListItemFields(
                (int) $tenant->id,
                $drive->site_id,
                $drive->drive_id,
                $event->sp_item_id,
            );

            if (!$this->isAllowlisted($drive, $listItemFields)) {
                $this->events->markStatus($eventId, self::STATUS_SKIPPED_NOT_ALLOWLISTED);
                return self::STATUS_SKIPPED_NOT_ALLOWLISTED;
            }

            $projected = $this->mapping->project((int) $drive->id, $driveItem, $listItemFields);
            $disposition = $this->retention->resolve($listItemFields);
            $rowData = array_merge($projected, $this->dispositionToRowFields($disposition));

            $localPath = $this->downloadFile($tenant, $drive, $event, $driveItem, $eventId);

            $sessionId = $this->createIngestSession((int) $drive->id, $eventId, $rowData);
            $this->createIngestRow($sessionId, $rowData);
            $this->createIngestFile(
                $sessionId,
                $localPath,
                (string) ($driveItem['file']['mimeType'] ?? 'application/octet-stream'),
            );
            $jobId = $this->dispatchCommit($sessionId);

            $informationObjectId = $this->resolveInformationObjectId($jobId);

            $this->events->update($eventId, [
                'ingest_job_id' => $jobId,
                'information_object_id' => $informationObjectId,
            ]);
            $this->events->markStatus($eventId, self::STATUS_COMPLETED);
            $this->auditIngest($eventId, $informationObjectId, $event, $drive);

            return self::STATUS_COMPLETED;
        } catch (\Throwable $e) {
            $this->events->markStatus($eventId, self::STATUS_FAILED, $e->getMessage());
            throw $e;
        }
    }

    private function isAllowlisted(object $drive, array $listItemFields): bool
    {
        $raw = $drive->auto_ingest_labels ?? null;
        if (empty($raw)) {
            return false;
        }
        $allowed = json_decode($raw, true);
        if (!is_array($allowed) || count($allowed) === 0) {
            return false;
        }
        $tag = $listItemFields['_ComplianceTag'] ?? null;
        if ($tag === null || $tag === '') {
            return false;
        }
        return in_array($tag, $allowed, true);
    }

    private function dispositionToRowFields(array $disposition): array
    {
        $out = [];
        foreach ([
            'level_of_description_id' => 'levelOfDescriptionId',
            'parent_id' => 'parentId',
            'security_classification_id' => 'securityClassificationId',
            'embargo_until' => 'embargoUntil',
        ] as $src => $dst) {
            if (isset($disposition[$src])) {
                $out[$dst] = $disposition[$src];
            }
        }
        if (!empty($disposition['compliance_tag'])) {
            $out['_compliance_tag'] = $disposition['compliance_tag'];
        }
        if (!empty($disposition['is_record'])) {
            $out['_is_record'] = true;
        }
        return $out;
    }

    private function downloadFile(object $tenant, object $drive, object $event, array $driveItem, int $eventId): string
    {
        $name = $this->safeFileName((string) ($driveItem['name'] ?? "item-{$event->sp_item_id}"));
        $relPath = "sharepoint/{$eventId}/{$name}";
        $absPath = storage_path("app/{$relPath}");
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->graph->downloadDriveItem(
            (int) $tenant->id,
            $drive->site_id,
            $drive->drive_id,
            $event->sp_item_id,
            $absPath,
        );
        return $absPath;
    }

    private function safeFileName(string $raw): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', $raw) ?? 'item';
        return substr($clean, 0, 200);
    }

    private function createIngestSession(int $driveId, int $eventId, array $rowData): int
    {
        return (int) DB::table('ingest_session')->insertGetId([
            'user_id' => 1,
            'title' => 'SharePoint auto-ingest event ' . $eventId,
            'sector' => 'archive',
            'standard' => 'isadg',
            'source' => 'sharepoint_auto',
            'source_id' => $eventId,
            'parent_id' => $rowData['parentId'] ?? null,
            'parent_placement' => isset($rowData['parentId']) ? 'existing' : 'top_level',
            'output_create_records' => 1,
            'output_generate_sip' => 0,
            'output_generate_aip' => 0,
            'output_generate_dip' => 0,
            'derivative_thumbnails' => 1,
            'derivative_reference' => 1,
            'process_virus_scan' => 1,
            'created_at' => now(),
        ]);
    }

    private function createIngestRow(int $sessionId, array $rowData): void
    {
        DB::table('ingest_row')->insert([
            'session_id' => $sessionId,
            'row_index' => 0,
            'data' => json_encode($rowData, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
        ]);
    }

    private function createIngestFile(int $sessionId, string $localPath, string $mimeType): void
    {
        DB::table('ingest_file')->insert([
            'session_id' => $sessionId,
            'filename' => basename($localPath),
            'path' => $localPath,
            'mime_type' => $mimeType,
            'size' => is_file($localPath) ? filesize($localPath) : 0,
            'created_at' => now(),
        ]);
    }

    private function dispatchCommit(int $sessionId): int
    {
        // Heratio's ahg-ingest package exposes its commit service via the
        // service container. The exact service name is confirmed during
        // integration; this is the documented hand-off point.
        if (!class_exists('\\AhgIngest\\Services\\IngestCommitService')) {
            throw new \RuntimeException('AhgIngest IngestCommitService not registered. Verify ahg/ingest is installed.');
        }
        $svc = app(\AhgIngest\Services\IngestCommitService::class);
        return (int) $svc->startJob($sessionId);
    }

    private function resolveInformationObjectId(int $ingestJobId): ?int
    {
        $row = DB::table('ingest_job')->where('id', $ingestJobId)->first();
        if ($row === null) {
            return null;
        }
        return isset($row->primary_object_id) ? (int) $row->primary_object_id : null;
    }

    private function auditIngest(int $eventId, ?int $ioId, object $event, object $drive): void
    {
        if (!class_exists('\\AhgAuditTrail\\Services\\AuditService')) {
            return;
        }
        try {
            \AhgAuditTrail\Services\AuditService::log(
                'sharepoint.ingest',
                'informationobject',
                $ioId,
                [
                    'source' => 'sharepoint_auto',
                    'sp_drive_id' => (int) $drive->id,
                    'sp_item_id' => $event->sp_item_id,
                    'sp_etag' => $event->sp_etag,
                    'event_id' => $eventId,
                ],
            );
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
