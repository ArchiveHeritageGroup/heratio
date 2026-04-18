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
        $session = $id ? $this->service->getSession($id) : null;

        if ($request->isMethod('post')) {
            $config = $request->only([
                'title', 'entity_type', 'sector', 'standard',
                'repository_id', 'parent_id', 'parent_placement',
                'new_parent_title', 'new_parent_level',
                'output_create_records', 'output_generate_sip',
                'output_generate_aip', 'output_generate_dip',
                'derivative_thumbnails', 'derivative_reference',
                'process_ner', 'process_ocr', 'process_virus_scan',
                'process_summarize', 'process_spellcheck',
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

        return view('ahg-ingest::upload', compact('session', 'files'));
    }

    public function map(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        $mappings = $this->service->getMappings($id);

        return view('ahg-ingest::map', compact('session', 'mappings'));
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
