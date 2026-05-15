<?php

/**
 * IngestController - Controller for Heratio
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



namespace AhgIngest\Controllers;

use AhgIngest\Services\IngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class IngestController extends Controller
{
    protected IngestService $service;

    public function __construct()
    {
        $this->service = new IngestService();
    }

    public function index()
    {
        $isAdmin = auth()->check() && \AhgCore\Services\AclService::canAdmin(auth()->id());

        // Admins see all sessions, regular users see only their own
        $sessions = $isAdmin
            ? $this->service->getSessions()
            : $this->service->getSessions(auth()->id());

        return view('ahg-ingest::index', compact('sessions', 'isAdmin'));
    }

    /**
     * Download CSV template for a sector.
     * Migrated from AtoM ahgIngestPlugin downloadTemplate action.
     */
    public function downloadTemplate(string $sector = 'archive')
    {
        $fields = [
            'legacyId', 'parentId', 'identifier', 'title', 'levelOfDescription',
            'extentAndMedium', 'repository', 'archivalHistory', 'acquisition',
            'scopeAndContent', 'appraisal', 'accruals', 'arrangement',
            'accessConditions', 'reproductionConditions', 'language',
            'physicalCharacteristics', 'findingAids', 'locationOfOriginals',
            'locationOfCopies', 'relatedUnitsOfDescription', 'publicationNote',
            'digitalObjectPath', 'digitalObjectURI', 'generalNote',
            'subjectAccessPoints', 'placeAccessPoints', 'nameAccessPoints',
            'genreAccessPoints', 'descriptionIdentifier', 'institutionIdentifier',
            'rules', 'descriptionStatus', 'levelOfDetail', 'revisionHistory',
            'languageOfDescription', 'scriptOfDescription', 'sources',
            'archivistNote', 'publicationStatus', 'physicalObjectName',
            'physicalObjectLocation', 'physicalObjectType',
            'alternativeIdentifiers', 'alternativeIdentifierLabels',
            'eventDates', 'eventTypes', 'eventStartDates', 'eventEndDates', 'eventActors',
            'culture',
        ];

        $sectorFields = match ($sector) {
            'museum', 'gallery' => ['objectNumber', 'objectName', 'artist', 'medium', 'dimensions'],
            'library' => ['isbn', 'author', 'publisher', 'callNumber'],
            'dam' => ['assetId', 'assetType', 'resolution', 'colorSpace'],
            default => [],
        };

        $allFields = array_unique(array_merge($sectorFields, $fields));

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $allFields);
        fputcsv($handle, array_fill(0, count($allFields), ''));
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ingest_template_' . $sector . '.csv"',
        ]);
    }

    public function configure(Request $request, ?int $id = null)
    {
        // For new sessions present a synthetic $session pre-populated from the
        // ingest_* settings so the form's @checked / value="..." patterns
        // pull operator-configured defaults rather than the schema's bare
        // column defaults.
        $session = $id
            ? $this->service->getSession($id)
            : $this->service->configureDefaults();

        if ($request->isMethod('post')) {
            // Expanded `only()` list mirrors the columns now persisted by
            // IngestService::buildSessionRow - previously the form had
            // checkboxes for output_*_path / process_translate / process_format_id /
            // process_face_detect that never reached createSession().
            $config = $request->only([
                'title', 'entity_type', 'sector', 'standard',
                'repository_id', 'parent_id', 'parent_placement',
                'new_parent_title', 'new_parent_level',
                'output_create_records', 'output_generate_sip',
                'output_generate_aip', 'output_generate_dip',
                'output_sip_path', 'output_aip_path', 'output_dip_path',
                'derivative_thumbnails', 'derivative_reference',
                'process_ner', 'process_ocr', 'process_virus_scan',
                'process_summarize', 'process_spellcheck',
                'process_translate', 'process_translate_lang',
                'process_format_id', 'process_face_detect',
            ]);

            if ($id) {
                $this->service->updateSession($id, $config);
                $this->service->updateSessionStatus($id, 'upload');
                $sessionId = $id;
            } else {
                $sessionId = $this->service->createSession(auth()->id(), $config);
                $this->service->updateSessionStatus($sessionId, 'upload');
            }

            return redirect()->route('ingest.upload', $sessionId);
        }

        $repositories = $this->service->getRepositories();

        return view('ahg-ingest::configure', compact('session', 'repositories'));
    }

    public function upload(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        $files = $this->service->getFiles($id);

        $spTenants = [];
        if (class_exists(\AhgSharePoint\Services\SharePointBrowserService::class)) {
            try {
                $spTenants = \Illuminate\Support\Facades\DB::table('sharepoint_tenant')
                    ->where('status', '!=', 'disabled')
                    ->orderBy('name')
                    ->get()
                    ->all();
            } catch (\Throwable $e) {
                $spTenants = [];
            }
        }

        return view('ahg-ingest::upload', compact('session', 'files', 'spTenants'));
    }

    /**
     * SharePoint AJAX browse endpoint — proxies to SharePointBrowserService.
     */
    public function browseSharePoint(Request $request, int $id)
    {
        abort_unless($this->service->getSession($id), 404);
        if (!class_exists(\AhgSharePoint\Services\SharePointBrowserService::class)) {
            return response()->json(['error' => 'SharePoint package not installed'], 500);
        }
        $browser = app(\AhgSharePoint\Services\SharePointBrowserService::class);
        $tenantId = (int) $request->query('tenant_id');
        $op = (string) $request->query('op');

        try {
            switch ($op) {
                case 'sites':
                    return response()->json(['op' => 'sites', 'sites' => $browser->listSites($tenantId, $request->query('search'))]);
                case 'drives':
                    return response()->json(['op' => 'drives', 'drives' => $browser->listDrives($tenantId, (string) $request->query('site_id'))]);
                case 'children':
                    return response()->json([
                        'op' => 'children',
                        'items' => $browser->listChildren(
                            $tenantId,
                            (string) $request->query('drive_id'),
                            (string) ($request->query('item_id') ?: 'root'),
                        ),
                    ]);
                default:
                    return response()->json(['error' => 'unknown op'], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Import selected SharePoint items into a session, then redirect to Map step.
     */
    public function importFromSharePoint(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);
        abort_unless(class_exists(\AhgSharePoint\Services\SharePointBrowserService::class), 500, 'SharePoint package not installed');

        $tenantId = (int) $request->input('sp_tenant_id');
        $driveGraphId = (string) $request->input('sp_drive_id');
        $driveName = (string) $request->input('sp_drive_name');
        $siteId = (string) $request->input('sp_site_id');
        $itemIds = (array) $request->input('sp_item_ids', []);

        if ($tenantId <= 0 || $driveGraphId === '' || empty($itemIds)) {
            return back()->with('error', __('SharePoint import requires tenant, drive, and at least one item.'));
        }

        $drivePk = (int) \Illuminate\Support\Facades\DB::table('sharepoint_drive')
            ->where('drive_id', $driveGraphId)
            ->value('id');
        if (!$drivePk) {
            $drivePk = (int) \Illuminate\Support\Facades\DB::table('sharepoint_drive')->insertGetId([
                'tenant_id' => $tenantId,
                'site_id' => $siteId,
                'site_url' => '',
                'drive_id' => $driveGraphId,
                'drive_name' => $driveName,
                'ingest_enabled' => 0,
                'sector' => $session->sector ?? 'archive',
                'default_parent_placement' => 'top_level',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $browser = app(\AhgSharePoint\Services\SharePointBrowserService::class);
        $uploadDir = config('ahg-ingest.upload_dir', storage_path('app/ingest')) . '/' . $id;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $imported = 0;
        $errors = [];
        foreach ($itemIds as $itemId) {
            try {
                $meta = $browser->getMetadata($tenantId, $driveGraphId, $itemId, true);
                $name = $meta['name'] ?: $itemId;
                $itemDir = $uploadDir . '/' . $itemId;
                if (!is_dir($itemDir)) {
                    mkdir($itemDir, 0775, true);
                }
                $safeName = preg_replace('#[^A-Za-z0-9._ \-]#', '_', $name);
                $destPath = $itemDir . '/' . $safeName;
                $browser->downloadItem($tenantId, $driveGraphId, $itemId, $destPath);

                $checksum = is_file($destPath) ? hash_file('sha256', $destPath) : null;
                $listFields = $meta['_raw']['listItem']['fields'] ?? [];

                \Illuminate\Support\Facades\DB::table('ingest_file')->insert([
                    'session_id' => $id,
                    'file_type' => 'sharepoint',
                    'original_name' => $name,
                    'stored_path' => $destPath,
                    'file_size' => $meta['size'] ?? (filesize($destPath) ?: 0),
                    'mime_type' => $meta['mimeType'] ?? null,
                    'status' => 'pending',
                    'source_hash' => $checksum,
                    'sidecar_json' => json_encode([
                        'sp_drive_id' => $drivePk,
                        'sp_drive_graph_id' => $driveGraphId,
                        'sp_item_id' => $itemId,
                        'sp_etag' => $meta['etag'] ?? null,
                        'sp_web_url' => $meta['webUrl'] ?? null,
                        'sp_list_item_fields' => $listFields,
                        'sp_last_modified' => $meta['lastModifiedDateTime'] ?? null,
                        'sp_created' => $meta['createdDateTime'] ?? null,
                    ]),
                    'created_at' => now(),
                ]);
                ++$imported;
            } catch (\Throwable $e) {
                $errors[] = "{$itemId}: " . $e->getMessage();
            }
        }

        $this->service->updateSession($id, [
            'source' => 'sharepoint_manual',
            'source_id' => $drivePk,
            'source_metadata' => json_encode([
                'sp_drive_id' => $driveGraphId,
                'sp_drive_pk' => $drivePk,
                'sp_drive_name' => $driveName,
                'sp_site_id' => $siteId,
                'sp_item_ids' => $itemIds,
                'imported_at' => now()->toIso8601String(),
            ]),
        ]);

        if ($imported === 0) {
            $msg = __('No SharePoint items could be imported.');
            if (!empty($errors)) {
                $msg .= ' ' . implode('; ', array_slice($errors, 0, 3));
            }
            return back()->with('error', $msg);
        }

        $this->service->parseRows($id);
        $this->service->updateSessionStatus($id, 'map');

        $flash = sprintf(__('Imported %d of %d items.'), $imported, count($itemIds));
        return redirect()->route('ingest.map', ['id' => $id])->with('notice', $flash);
    }

    public function map(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        if ($request->isMethod('post')) {
            $action = $request->input('form_action', 'save');
            if ($action === 'save') {
                $payload    = (array) $request->input('target_field', []);
                $ignored    = (array) $request->input('is_ignored', []);
                $defaults   = (array) $request->input('default_value', []);
                $transforms = (array) $request->input('transform', []);
                $mappings   = [];
                foreach ($payload as $mapId => $targetField) {
                    $mappings[] = [
                        'id'            => (int) $mapId,
                        'target_field'  => $targetField !== '' ? $targetField : null,
                        'is_ignored'    => isset($ignored[$mapId]) ? 1 : 0,
                        'default_value' => $defaults[$mapId]   ?? null,
                        'transform'     => $transforms[$mapId] ?? null,
                    ];
                }
                if (!empty($mappings)) {
                    $this->service->saveMappings($id, $mappings);
                }
                if ($request->input('save_as_template') && ($session->source ?? '') === 'sharepoint_manual') {
                    $this->saveSharePointMappingTemplate($id, $session);
                }
                $this->service->enrichRows($id);
                $this->service->updateSessionStatus($id, 'validate');
                return redirect()->route('ingest.validate', ['id' => $id]);
            }
        }

        $mappings     = $this->service->getMappings($id);
        $targetFields = $this->targetFieldsFor((string) ($session->standard ?? 'isadg'));
        $sampleRows   = \Illuminate\Support\Facades\DB::table('ingest_row')
            ->where('session_id', $id)
            ->orderBy('row_number')
            ->limit(5)
            ->get();

        return view('ahg-ingest::map', compact('session', 'mappings', 'targetFields', 'sampleRows'));
    }

    /**
     * Target-field vocabulary by archival standard. Drives the dropdown in
     * the Map step. ISAD-G is the default fallback; per-standard subsets
     * exist for DACS / RAD / MODS / Spectrum so operators only see fields
     * their selected standard understands.
     *
     * @return array<int,string>
     */
    private function targetFieldsFor(string $standard): array
    {
        $isadg = [
            'identifier', 'title', 'levelOfDescription', 'scopeAndContent',
            'extentAndMedium', 'archivalHistory', 'acquisition',
            'appraisal', 'accruals', 'arrangement', 'accessConditions',
            'reproductionConditions', 'language', 'physicalCharacteristics',
            'findingAids', 'locationOfOriginals', 'locationOfCopies',
            'relatedUnitsOfDescription', 'publicationNote',
            'creators', 'subjects', 'places',
            'eventDates', 'eventStartDate', 'eventEndDate',
            'legacyId', 'parentId', 'repository',
        ];
        $dacs  = array_merge($isadg, ['biographicalHistory', 'preferredCitation']);
        $rad   = array_merge($isadg, ['editionStatement', 'standardNumber']);
        $mods  = ['identifier', 'title', 'subTitle', 'language', 'genre', 'subject',
                  'publisher', 'placeOfPublication', 'dateIssued', 'abstract',
                  'physicalDescription', 'tableOfContents', 'note', 'classification'];
        $spectrum = ['objectNumber', 'objectName', 'briefDescription', 'materials',
                     'techniques', 'measurements', 'condition', 'location',
                     'creator', 'placeOfOrigin', 'dateMade', 'subjectMatter'];

        return match (strtolower($standard)) {
            'dacs'     => $dacs,
            'rad'      => $rad,
            'mods'     => $mods,
            'spectrum' => $spectrum,
            default    => $isadg,
        };
    }

    /**
     * Persist current session's ingest_mapping rows as the default sharepoint_mapping
     * template for the session's source drive.
     */
    private function saveSharePointMappingTemplate(int $sessionId, object $session): void
    {
        $meta = $session->source_metadata ? @json_decode((string) $session->source_metadata, true) : [];
        $drivePk = (int) ($meta['sp_drive_pk'] ?? 0);
        if ($drivePk <= 0 && !empty($meta['sp_drive_id'])) {
            $drivePk = (int) \Illuminate\Support\Facades\DB::table('sharepoint_drive')
                ->where('drive_id', $meta['sp_drive_id'])
                ->value('id');
        }
        if ($drivePk <= 0) {
            session()->flash('error', 'Could not resolve SharePoint drive; mapping template NOT saved.');
            return;
        }
        \Illuminate\Support\Facades\DB::table('sharepoint_mapping')->where('drive_id', $drivePk)->delete();
        $rows = \Illuminate\Support\Facades\DB::table('ingest_mapping')
            ->where('session_id', $sessionId)
            ->where('is_ignored', 0)
            ->get();
        foreach ($rows as $i => $r) {
            \Illuminate\Support\Facades\DB::table('sharepoint_mapping')->insert([
                'drive_id' => $drivePk,
                'content_type_id' => null,
                'source_field' => $r->source_column,
                'target_field' => $r->target_field,
                'target_standard' => $session->standard ?? 'isadg',
                'transform' => $r->transform,
                'default_value' => $r->default_value,
                'is_required' => 0,
                'sort_order' => (int) ($r->sort_order ?? $i),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function validate(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        if ($request->isMethod('post')) {
            $action = $request->get('form_action');

            if ($action === 'proceed') {
                $this->service->updateSessionStatus($id, 'preview');

                return redirect()->route('ingest.preview', $id);
            }
        }

        $stats = $this->service->validateSession($id);
        $errors = $this->service->getValidationErrors($id);
        $rowCount = $this->service->getRowCount($id);

        return view('ahg-ingest::validate', compact('session', 'stats', 'errors', 'rowCount'));
    }

    public function preview(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        if ($request->isMethod('post')) {
            $action = $request->get('form_action');

            if ($action === 'approve') {
                $this->service->updateSessionStatus($id, 'commit');

                return redirect()->route('ingest.commit', $id);
            }
        }

        $rowCount = $this->service->getRowCount($id);

        return view('ahg-ingest::preview', compact('session', 'rowCount'));
    }

    public function commit(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        // Any POST to this route triggers the commit runner. The existing
        // commit view's "Start Commit" button posts without a form_action
        // field — treat that as the default start action. `form_action=status`
        // is reserved for the AJAX progress poll.
        if ($request->isMethod('post') && $request->input('form_action') !== 'status') {
            try {
                // Large batches run on the queue so the web request doesn't
                // time out. Threshold is configurable; sync remains an option
                // for small batches + sites without a queue worker.
                $threshold = (int) config('heratio.ingest.queue_threshold', 500);
                $rowCount = \Illuminate\Support\Facades\DB::table('ingest_row')
                    ->where('session_id', $id)
                    ->where('is_valid', 1)
                    ->where('is_excluded', 0)
                    ->count();

                if ($threshold > 0 && $rowCount >= $threshold) {
                    // Seed an ingest_job row so the UI polling sees "running"
                    // immediately while the queue worker picks up the job.
                    \Illuminate\Support\Facades\DB::table('ingest_job')->insert([
                        'session_id' => $id,
                        'status' => 'queued',
                        'total_rows' => $rowCount,
                        'processed_rows' => 0,
                        'created_records' => 0,
                        'created_dos' => 0,
                        'error_count' => 0,
                        'created_at' => now(),
                    ]);
                    \AhgIngest\Jobs\IngestCommitJob::dispatch($id);
                    return redirect()->route('ingest.commit', $id)
                        ->with('notice', "Commit dispatched to queue ({$rowCount} rows) — this page will auto-refresh as progress lands.");
                }

                $runner = app(\AhgIngest\Services\IngestCommitRunner::class);
                $result = $runner->run($id);
                return redirect()->route('ingest.commit', $id)
                    ->with('notice', "Commit complete: {$result['created']} IO(s), {$result['errors']} error(s).");
            } catch (\Throwable $e) {
                return redirect()->route('ingest.commit', $id)
                    ->with('error', 'Commit failed: ' . $e->getMessage());
            }
        }

        $job = $this->service->getJobBySession($id);

        return view('ahg-ingest::commit', compact('session', 'job'));
    }

    /**
     * Handle POST actions for ingest sessions.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');
        $id = (int) $request->get('id');

        if ($action === 'delete' && $id) {
            $this->service->deleteSession($id);

            return redirect()->route('ingest.index')->with('notice', 'Ingest session deleted.');
        }

        if ($action === 'cancel' && $id) {
            $this->service->updateSessionStatus($id, 'cancelled');

            return redirect()->route('ingest.index')->with('notice', 'Ingest session cancelled.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }
}
