<?php

/**
 * ExportController - Controller for Heratio
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



namespace AhgExport\Controllers;

use AhgExport\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Export dashboard — index page showing all export options.
     */
    public function index()
    {
        $repositories = $this->exportService->getRepositories();
        $formats = $this->exportService->getExportFormats();

        return view('ahg-export::index', compact('repositories', 'formats'));
    }

    /**
     * CSV export page — information object CSV export.
     */
    public function csv(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->exportService->streamInformationObjectCsv($request->all());
        }
        $repositories = $this->exportService->getRepositories();
        $levels = $this->exportService->getLevelsOfDescription();
        $ioCount = $this->exportService->getInformationObjectCount();

        return view('ahg-export::csv', compact('repositories', 'levels', 'ioCount'));
    }

    /**
     * EAD export — form (GET) or EAD 2002 XML download (POST). Reuses the
     * working ahg-metadata-export serializer rather than re-implementing EAD.
     */
    public function ead(Request $request)
    {
        if ($request->isMethod('post')) {
            $objectId = (int) $request->input('object_id');
            if ($objectId <= 0) {
                return back()->with('error', __('Please choose a record to export.'));
            }
            $cls = \AhgMetadataExport\Services\Exporters\Ead2002Serializer::class;
            if (! class_exists($cls)) {
                return back()->with('error', __('The EAD exporter is unavailable.'));
            }
            $xml = (new $cls())->serializeRecord($objectId, 'en', (bool) $request->input('include_descendants', false));
            if (trim($xml) === '') {
                return back()->with('error', __('No EAD could be generated for that record.'));
            }

            return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xml, 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="ead-' . $objectId . '.xml"',
            ]);
        }
        $repositories = $this->exportService->getRepositories();
        $fonds = $this->exportService->getTopLevelFonds();

        return view('ahg-export::ead', compact('repositories', 'fonds'));
    }

    /**
     * Archival description export — form (GET) or CSV download (POST). The
     * single-record EAD/DC formats are handled on the dedicated EAD page.
     */
    public function archival(Request $request)
    {
        if ($request->isMethod('post')) {
            $format = (string) $request->input('format', 'csv');
            if ($format === 'ead' || $format === 'dc') {
                return redirect()->route('export.ead')
                    ->with('info', __('EAD / Dublin Core export is per-record — choose a record below.'));
            }

            return $this->exportService->streamInformationObjectCsv([
                'repository_id' => $request->input('repository_id'),
                'limit' => $request->input('limit'),
            ]);
        }
        $repositories = $this->exportService->getRepositories();

        return view('ahg-export::archival', compact('repositories'));
    }

    /**
     * Authority record export — form (GET) or CSV download (POST).
     */
    public function authority(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->exportService->streamActorCsv((int) $request->input('limit', 0));
        }
        $authorityCount = $this->exportService->getAuthorityCount();

        return view('ahg-export::authority', compact('authorityCount'));
    }

    /**
     * Repository export — form (GET) or CSV download (POST).
     */
    public function repository(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->exportService->streamRepositoryCsv((int) $request->input('limit', 0));
        }
        $repositoryCount = $this->exportService->getRepositoryCount();
        $repositories = $this->exportService->getRepositories();

        return view('ahg-export::repository', compact('repositoryCount', 'repositories'));
    }

    /**
     * Accession CSV export — form (GET) or CSV download (POST).
     */
    public function accessionCsv(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->exportService->streamAccessionCsv($request->all());
        }
        $repositories = $this->exportService->getRepositories();
        $accessionCount = $this->exportService->getAccessionCount();

        return view('ahg-export::accession-csv', compact('repositories', 'accessionCount'));
    }
}
