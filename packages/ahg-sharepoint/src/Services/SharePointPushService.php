<?php

namespace AhgSharePoint\Services;

use AhgSharePoint\Repositories\SharePointDriveRepository;
use AhgSharePoint\Repositories\SharePointTenantRepository;
use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointPushService.
 *
 * @phase 2.B
 */
class SharePointPushService
{
    public function __construct(
        private GraphClientService $graph,
        private SharePointTenantRepository $tenants,
        private SharePointDriveRepository $drives,
        private SharePointMappingService $mapping,
        private SharePointRetentionMapper $retention,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function project(array $request, array $userClaims): array
    {
        $tenantId = (int) $request['tenant_id'];
        $driveRow = $this->drives->find((int) $request['drive_id']);
        if ($driveRow === null) {
            throw new \InvalidArgumentException("Drive {$request['drive_id']} not found");
        }

        $userToken = (string) ($userClaims['_raw'] ?? '');
        $oboToken = $this->graph->acquireOboToken(
            $tenantId, $userToken, 'https://graph.microsoft.com/Files.Read.All',
        );

        $out = [];
        foreach ($request['items'] as $item) {
            $driveItem = $this->graph->get(
                $tenantId,
                "/sites/{$item['site_id']}/drives/{$item['drive_id']}/items/{$item['item_id']}",
                ['Authorization' => 'Bearer ' . $oboToken],
            );
            $fields = $this->graph->getListItemFields(
                $tenantId, $item['site_id'], $item['drive_id'], $item['item_id'],
            );
            $projected = $this->mapping->project((int) $driveRow->id, $driveItem, $fields);
            $disposition = $this->retention->resolve($fields);
            $out[] = [
                'sp_item_id' => $item['item_id'],
                'metadata' => $projected,
                'disposition' => $disposition,
                'name' => $driveItem['name'] ?? null,
                'mimeType' => $driveItem['file']['mimeType'] ?? null,
                'size' => $driveItem['size'] ?? null,
            ];
        }

        return $out;
    }

    public function commit(array $request, int $heratioUserId, array $userClaims): int
    {
        $tenantId = (int) $request['tenant_id'];
        $driveRow = $this->drives->find((int) $request['drive_id']);
        if ($driveRow === null) {
            throw new \InvalidArgumentException("Drive {$request['drive_id']} not found");
        }

        $userToken = (string) ($userClaims['_raw'] ?? '');
        $oboToken = $this->graph->acquireOboToken(
            $tenantId, $userToken, 'https://graph.microsoft.com/Files.Read.All',
        );

        $sessionId = (int) DB::table('ingest_session')->insertGetId([
            'user_id' => $heratioUserId,
            'title' => 'SharePoint manual push by user ' . $heratioUserId,
            'sector' => $driveRow->sector,
            'standard' => 'isadg',
            'source' => 'sharepoint_push',
            'source_id' => null,
            'repository_id' => $request['repository_id'] ?? $driveRow->default_repository_id,
            'parent_id' => $request['parent_id'] ?? $driveRow->default_parent_id,
            'parent_placement' => isset($request['parent_id']) ? 'existing' : ($driveRow->default_parent_placement ?? 'top_level'),
            'output_create_records' => 1,
            'output_generate_sip' => 0,
            'output_generate_aip' => 0,
            'output_generate_dip' => 0,
            'derivative_thumbnails' => 1,
            'derivative_reference' => 1,
            'process_virus_scan' => 1,
            'created_at' => now(),
        ]);

        foreach ($request['items'] as $idx => $item) {
            $driveItem = $this->graph->get(
                $tenantId,
                "/sites/{$item['site_id']}/drives/{$item['drive_id']}/items/{$item['item_id']}",
                ['Authorization' => 'Bearer ' . $oboToken],
            );

            $localPath = $this->downloadItemAsUser(
                $tenantId, $oboToken, $item['site_id'], $item['drive_id'], $item['item_id'],
                $sessionId, (string) ($driveItem['name'] ?? "item-{$item['item_id']}"),
            );

            $rowData = $item['metadata'];
            $rowData['_sharepoint_drive_id'] = (int) $driveRow->id;
            $rowData['_sharepoint_item_id'] = $item['item_id'];
            $rowData['_pushed_by_user_id'] = $heratioUserId;
            $rowData['_pushed_by_aad_oid'] = $userClaims['oid'] ?? null;

            DB::table('ingest_row')->insert([
                'session_id' => $sessionId,
                'row_index' => $idx,
                'data' => json_encode($rowData, JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
            DB::table('ingest_file')->insert([
                'session_id' => $sessionId,
                'filename' => basename($localPath),
                'path' => $localPath,
                'mime_type' => (string) ($driveItem['file']['mimeType'] ?? 'application/octet-stream'),
                'size' => is_file($localPath) ? filesize($localPath) : 0,
                'created_at' => now(),
            ]);
        }

        return $this->dispatchCommit($sessionId, $userClaims);
    }

    private function downloadItemAsUser(int $tenantId, string $oboToken, string $siteId, string $driveId, string $itemId, int $sessionId, string $name): string
    {
        $dir = storage_path("app/sharepoint/push/{$sessionId}");
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?? 'item';
        $absPath = $dir . '/' . substr($clean, 0, 200);
        $this->graph->downloadDriveItem($tenantId, $siteId, $driveId, $itemId, $absPath);
        return $absPath;
    }

    private function dispatchCommit(int $sessionId, array $userClaims): int
    {
        if (!class_exists('\\AhgIngest\\Services\\IngestCommitService')) {
            throw new \RuntimeException('AhgIngest\\Services\\IngestCommitService not registered.');
        }
        $svc = app(\AhgIngest\Services\IngestCommitService::class);
        $jobId = (int) $svc->startJob($sessionId);

        if (class_exists('\\AhgAuditTrail\\Services\\AuditService')) {
            try {
                \AhgAuditTrail\Services\AuditService::log(
                    'sharepoint.push',
                    'ingest_session',
                    $sessionId,
                    [
                        'job_id' => $jobId,
                        'aad_oid' => $userClaims['oid'] ?? null,
                        'aad_upn' => $userClaims['upn'] ?? null,
                    ],
                );
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        return $jobId;
    }
}
