<?php

namespace AhgSharePoint\Services;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointBrowserService.
 *
 * Pure Graph wrapper used by:
 *   - the ingest wizard "From SharePoint" picker (AJAX)
 *   - SharePointAutoIngestService (cron-driven scanner)
 *
 * @phase 2 (v2 ingest plan, step 2)
 */
class SharePointBrowserService
{
    private const MAX_PAGES = 20;

    public function __construct(
        private GraphClientService $graph,
    ) {
    }

    /**
     * @return array<int, array{id:string,displayName:string,name:?string,webUrl:string,description:?string}>
     */
    public function listSites(int $tenantId, ?string $search = null): array
    {
        $query = $search !== null && $search !== ''
            ? '/sites?search=' . rawurlencode($search) . '&$top=200'
            : '/sites?search=*&$top=200';

        return $this->collect($tenantId, $query, fn (array $row) => [
            'id' => (string) ($row['id'] ?? ''),
            'displayName' => (string) ($row['displayName'] ?? ($row['name'] ?? '')),
            'name' => $row['name'] ?? null,
            'webUrl' => (string) ($row['webUrl'] ?? ''),
            'description' => $row['description'] ?? null,
        ]);
    }

    /**
     * @return array<int, array{id:string,name:string,driveType:?string,webUrl:string}>
     */
    public function listDrives(int $tenantId, string $siteId): array
    {
        $siteId = rawurlencode($siteId);
        return $this->collect($tenantId, "/sites/{$siteId}/drives?\$top=200", fn (array $row) => [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'driveType' => $row['driveType'] ?? null,
            'webUrl' => (string) ($row['webUrl'] ?? ''),
        ]);
    }

    /**
     * @return array<int, array>
     */
    public function listChildren(int $tenantId, string $driveId, string $itemId = 'root'): array
    {
        $driveId = rawurlencode($driveId);
        $itemId = rawurlencode($itemId);
        $select = 'id,name,size,file,folder,eTag,cTag,webUrl,lastModifiedDateTime,createdDateTime,parentReference,retentionLabel';
        return $this->collect(
            $tenantId,
            "/drives/{$driveId}/items/{$itemId}/children?\$top=500&\$select=" . rawurlencode($select),
            fn (array $row) => $this->mapDriveItem($row),
        );
    }

    public function downloadItem(int $tenantId, string $driveId, string $itemId, string $destPath): string
    {
        $this->graph->downloadDriveItemByDriveId($tenantId, $driveId, $itemId, $destPath);
        return $destPath;
    }

    private const SP_SYSTEM_COLUMNS = [
        'ID', 'ContentType', 'DocIcon', 'FileLeafRef',
        '_ColorTag', 'ComplianceAssetId',
        'LinkFilename', 'LinkFilenameNoMenu', 'LinkFilename2',
        'LinkTitle', 'LinkTitleNoMenu',
        '_CopySource', '_CheckinComment',
        'FileSizeDisplay', 'ItemChildCount', 'FolderChildCount',
        '_ComplianceFlags', '_ComplianceTag', '_ComplianceTagWrittenTime',
        '_ComplianceTagUserId', '_IsRecord',
        '_CommentCount', '_LikeCount', '_DisplayName',
        'AppAuthor', 'AppEditor', 'Edit',
        '_UIVersionString', 'ParentVersionString', 'ParentLeafName',
        'SelectTitle', 'Order', 'GUID', 'WorkflowVersion',
        '_HasCopyDestinations', '_ModerationStatus', '_ModerationComments',
        '_Level', '_IsCurrentVersion',
    ];

    public function listColumns(int $tenantId, string $driveId): array
    {
        $driveId = rawurlencode($driveId);
        $resp = $this->graph->get($tenantId, "/drives/{$driveId}/list/columns?\$top=200");
        $systemLookup = array_flip(self::SP_SYSTEM_COLUMNS);
        $out = [];
        foreach (($resp['value'] ?? []) as $col) {
            if (!is_array($col)) {
                continue;
            }
            $type = 'text';
            foreach (['text', 'number', 'dateTime', 'boolean', 'choice', 'lookup', 'personOrGroup', 'hyperlinkOrPicture', 'currency', 'note'] as $t) {
                if (isset($col[$t])) {
                    $type = $t;
                    break;
                }
            }
            $name = (string) ($col['name'] ?? '');
            $columnGroup = (string) ($col['columnGroup'] ?? '');
            $hidden = (bool) ($col['hidden'] ?? false);
            $isSystem = $hidden || isset($systemLookup[$name]) || $columnGroup === '_Hidden';
            $out[] = [
                'name' => $name,
                'displayName' => (string) ($col['displayName'] ?? $name),
                'type' => $type,
                'indexed' => (bool) ($col['indexed'] ?? false),
                'readOnly' => (bool) ($col['readOnly'] ?? false),
                'hidden' => $hidden,
                'isSystem' => $isSystem,
                'columnGroup' => $columnGroup,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(int $tenantId, string $driveId, string $itemId, bool $expandListItem = false): array
    {
        $driveIdEnc = rawurlencode($driveId);
        $itemIdEnc = rawurlencode($itemId);
        $path = "/drives/{$driveIdEnc}/items/{$itemIdEnc}";
        if ($expandListItem) {
            $path .= '?$expand=listItem(expand=fields)';
        }
        $raw = $this->graph->get($tenantId, $path);
        return $this->mapDriveItem($raw) + ['_raw' => $raw];
    }

    /**
     * @param callable(array):array $mapper
     * @return array<int, array>
     */
    private function collect(int $tenantId, string $initialPath, callable $mapper): array
    {
        $out = [];
        $path = $initialPath;
        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $resp = $this->graph->get($tenantId, $path);
            foreach (($resp['value'] ?? []) as $row) {
                if (is_array($row)) {
                    $out[] = $mapper($row);
                }
            }
            $next = $resp['@odata.nextLink'] ?? null;
            if (!is_string($next) || $next === '') {
                break;
            }
            $path = $this->stripGraphBase($next);
        }
        return $out;
    }

    private function stripGraphBase(string $absoluteUrl): string
    {
        $parsed = parse_url($absoluteUrl);
        $relative = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
        $relative = preg_replace('#^/(v1\.0|beta)#', '', $relative);
        return $relative === '' ? '/' : $relative;
    }

    private function mapDriveItem(array $row): array
    {
        $isFolder = isset($row['folder']);
        $isFile = isset($row['file']);
        $retentionLabel = null;
        $retentionLabelAppliedAt = null;
        if (isset($row['retentionLabel']) && is_array($row['retentionLabel'])) {
            $retentionLabel = isset($row['retentionLabel']['name']) ? (string) $row['retentionLabel']['name'] : null;
            $retentionLabelAppliedAt = $row['retentionLabel']['labelAppliedDateTime'] ?? null;
        }
        return [
            'id' => (string) ($row['id'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'isFolder' => $isFolder,
            'isFile' => $isFile,
            'size' => (int) ($row['size'] ?? 0),
            'mimeType' => $row['file']['mimeType'] ?? null,
            'webUrl' => (string) ($row['webUrl'] ?? ''),
            'etag' => $row['eTag'] ?? ($row['cTag'] ?? null),
            'lastModifiedDateTime' => $row['lastModifiedDateTime'] ?? null,
            'createdDateTime' => $row['createdDateTime'] ?? null,
            'parentReference' => $row['parentReference'] ?? null,
            'childCount' => $isFolder ? (int) ($row['folder']['childCount'] ?? 0) : null,
            'retentionLabel' => $retentionLabel,
            'retentionLabelAppliedAt' => $retentionLabelAppliedAt,
        ];
    }
}
