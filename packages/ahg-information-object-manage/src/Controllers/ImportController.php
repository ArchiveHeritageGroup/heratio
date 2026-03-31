<?php

/**
 * ImportController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    /**
     * Show the XML import form (migrated from AtoM object/importSelectSuccess.php).
     */
    public function xml(string $slug = null)
    {
        $resource = null;
        if ($slug) {
            $resource = $this->getIO($slug);
        }

        return view('ahg-io-manage::import.select', [
            'type' => 'xml',
            'resource' => $resource,
            'title' => 'Import XML',
        ]);
    }

    /**
     * Show the CSV import form.
     */
    public function csv(string $slug = null)
    {
        $resource = null;
        if ($slug) {
            $resource = $this->getIO($slug);
        }

        return view('ahg-io-manage::import.select', [
            'type' => 'csv',
            'resource' => $resource,
            'title' => 'Import CSV',
        ]);
    }

    /**
     * Process the import upload.
     */
    public function process(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
            'importType' => 'required|in:xml,csv',
            'objectType' => 'required|string',
            'updateType' => 'required|string',
        ]);

        $type = $request->input('importType');
        $objectType = $request->input('objectType');
        $updateType = $request->input('updateType');
        $file = $request->file('file');

        // Store the uploaded file
        $filename = time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('imports', $filename, 'local');

        // Queue the import job
        $slug = $request->input('slug');
        ImportJob::dispatch($path, $type, $objectType, $updateType, $slug);

        return redirect()
            ->route($slug ? 'informationobject.show' : 'informationobject.browse', $slug ? ['slug' => $slug] : [])
            ->with('success', "Import queued: {$objectType} ({$type}) — file: {$file->getClientOriginalName()}. Processing will begin shortly.");
    }

    /**
     * Show the Validate CSV form (migrated from AtoM object/validateCsvSuccess.php).
     *
     * Menu path: object/validateCsv
     */
    public function validateCsv()
    {
        return view('ahg-io-manage::import.validate-csv', [
            'title' => 'Validate CSV',
            'results' => null,
        ]);
    }

    /**
     * Process CSV validation — read headers and validate against expected columns per type.
     */
    public function validateCsvProcess(Request $request)
    {
        $request->validate([
            'objectType' => 'required|in:informationObject,accession,authorityRecord,authorityRecordRelationship,event,repository',
            'file' => 'required|file|mimes:csv,txt|max:51200',
        ]);

        $objectType = $request->input('objectType');
        $file = $request->file('file');

        // Read CSV headers from first line
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return redirect()->route('object.validateCsv')
                ->withErrors(['file' => 'Unable to read the uploaded file.']);
        }

        $headerLine = fgets($handle);
        fclose($handle);

        if (empty($headerLine)) {
            return redirect()->route('object.validateCsv')
                ->withErrors(['file' => 'The CSV file appears to be empty.']);
        }

        // Parse headers — handle BOM and trim whitespace
        $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
        $headers = str_getcsv(trim($headerLine));
        $headers = array_map('trim', $headers);

        // Expected columns per type (matching AtoM CSV import specifications)
        $expectedColumns = $this->getExpectedColumns($objectType);
        $requiredColumns = $this->getRequiredColumns($objectType);

        // Validate each header
        $results = [];
        foreach ($headers as $header) {
            if (empty($header)) {
                continue;
            }
            if (in_array($header, $expectedColumns)) {
                $results[] = [
                    'column' => $header,
                    'status' => 'valid',
                    'message' => 'Valid column',
                ];
            } else {
                $results[] = [
                    'column' => $header,
                    'status' => 'invalid',
                    'message' => 'Unrecognized column — will be ignored during import',
                ];
            }
        }

        // Check for missing required columns
        $presentHeaders = array_column($results, 'column');
        foreach ($requiredColumns as $required) {
            if (!in_array($required, $presentHeaders)) {
                $results[] = [
                    'column' => $required,
                    'status' => 'missing',
                    'message' => 'Required column is missing from the CSV',
                ];
            }
        }

        // Summary counts
        $validCount = count(array_filter($results, fn($r) => $r['status'] === 'valid'));
        $invalidCount = count(array_filter($results, fn($r) => $r['status'] === 'invalid'));
        $missingCount = count(array_filter($results, fn($r) => $r['status'] === 'missing'));

        return view('ahg-io-manage::import.validate-csv', [
            'title' => 'Validate CSV',
            'results' => $results,
            'objectType' => $objectType,
            'fileName' => $file->getClientOriginalName(),
            'validCount' => $validCount,
            'invalidCount' => $invalidCount,
            'missingCount' => $missingCount,
        ]);
    }

    /**
     * Show the SKOS import form (migrated from AtoM sfSkosPlugin/importSuccess.php).
     *
     * Menu path: sfSkosPlugin/import
     */
    public function skosImport()
    {
        $culture = app()->getLocale();

        $taxonomies = DB::table('taxonomy')
            ->join('taxonomy_i18n', function ($j) use ($culture) {
                $j->on('taxonomy_i18n.id', '=', 'taxonomy.id')
                  ->where('taxonomy_i18n.culture', $culture);
            })
            ->orderBy('taxonomy_i18n.name')
            ->select('taxonomy.id', 'taxonomy_i18n.name')
            ->get();

        return view('ahg-io-manage::import.skos-import', [
            'title' => 'SKOS import',
            'taxonomies' => $taxonomies,
        ]);
    }

    /**
     * Process SKOS import — validate file and queue import job.
     */
    public function skosImportProcess(Request $request)
    {
        $request->validate([
            'taxonomy' => 'required|integer|exists:taxonomy,id',
            'file' => 'nullable|file|max:51200',
            'url' => 'nullable|url',
        ]);

        // Must have either file or URL
        if (!$request->hasFile('file') && empty($request->input('url'))) {
            return redirect()->route('sfSkosPlugin.import')
                ->withErrors(['file' => 'You must select a file or provide a URL to continue.']);
        }

        $taxonomyId = $request->input('taxonomy');

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());

            if (!in_array($ext, ['xml', 'rdf', 'skos'])) {
                return redirect()->route('sfSkosPlugin.import')
                    ->withErrors(['file' => 'The file must be an XML or RDF file (.xml, .rdf, .skos).']);
            }

            // Store the uploaded file
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('imports/skos', $filename, 'local');

            // Queue import job
            ImportJob::dispatch($path, 'skos', 'taxonomy', 'import-as-new', null, [
                'taxonomyId' => $taxonomyId,
                'location' => 'file://' . storage_path('app/' . $path),
            ]);

            return redirect()->route('sfSkosPlugin.import')
                ->with('success', 'SKOS import initiated. The file will be processed in the background. Check the Jobs page to view the status of the import.');
        }

        // URL-based import
        $url = $request->input('url');
        ImportJob::dispatch(null, 'skos', 'taxonomy', 'import-as-new', null, [
            'taxonomyId' => $taxonomyId,
            'location' => $url,
        ]);

        return redirect()->route('sfSkosPlugin.import')
            ->with('success', 'SKOS import initiated from URL. The file will be processed in the background. Check the Jobs page to view the status of the import.');
    }

    /**
     * Get expected columns for a given CSV import object type.
     */
    private function getExpectedColumns(string $objectType): array
    {
        return match ($objectType) {
            'informationObject' => [
                'legacyId', 'parentId', 'qubitParentSlug', 'accessionNumber',
                'identifier', 'title', 'levelOfDescription', 'extentAndMedium',
                'repository', 'archivalHistory', 'acquisition',
                'scopeAndContent', 'appraisal', 'accruals', 'arrangement',
                'accessConditions', 'reproductionConditions', 'language',
                'script', 'languageNote', 'physicalCharacteristics',
                'findingAids', 'relatedUnitsOfDescription', 'publicationNote',
                'digitalObjectPath', 'digitalObjectURI', 'generalNote',
                'subjectAccessPoints', 'placeAccessPoints', 'nameAccessPoints',
                'genreAccessPoints', 'descriptionIdentifier',
                'institutionIdentifier', 'rules', 'descriptionStatus',
                'levelOfDetail', 'revisionHistory', 'languageOfDescription',
                'scriptOfDescription', 'sources', 'archivistNote',
                'publicationStatus', 'physicalObjectName', 'physicalObjectLocation',
                'physicalObjectType', 'alternativeIdentifiers',
                'alternativeIdentifierLabels', 'eventDates', 'eventTypes',
                'eventStartDates', 'eventEndDates', 'eventActors',
                'eventActorHistories', 'culture',
            ],
            'accession' => [
                'accessionNumber', 'acquisitionDate', 'sourceOfAcquisition',
                'locationInformation', 'acquisitionType', 'resourceType',
                'title', 'archivalHistory', 'scopeAndContent',
                'appraisal', 'physicalCondition', 'receivedExtentUnits',
                'processingStatus', 'processingPriority', 'processingNotes',
                'donors', 'creators', 'culture',
            ],
            'authorityRecord' => [
                'authorizedFormOfName', 'entityType', 'corporateBodyIdentifiers',
                'datesOfExistence', 'history', 'places', 'legalStatus',
                'functions', 'mandates', 'internalStructures',
                'generalContext', 'descriptionIdentifier', 'institutionIdentifier',
                'rules', 'status', 'levelOfDetail', 'revisionHistory',
                'sources', 'maintenanceNotes', 'actorOccupations',
                'actorOccupationNotes', 'parallelFormsOfName',
                'standardizedFormsOfName', 'otherFormsOfName', 'culture',
            ],
            'authorityRecordRelationship' => [
                'sourceAuthorizedFormOfName', 'targetAuthorizedFormOfName',
                'category', 'description', 'date', 'startDate', 'endDate',
                'culture',
            ],
            'event' => [
                'legacyId', 'eventActorName', 'eventType', 'eventDate',
                'eventStartDate', 'eventEndDate', 'eventDescription', 'culture',
            ],
            'repository' => [
                'authorizedFormOfName', 'identifier', 'parallelFormsOfName',
                'otherFormsOfName', 'repositoryType', 'contactPerson',
                'streetAddress', 'city', 'region', 'countryCode',
                'postalCode', 'telephone', 'fax', 'email', 'website',
                'note', 'descriptionIdentifier', 'institutionIdentifier',
                'rules', 'status', 'levelOfDetail', 'revisionHistory',
                'sources', 'geographicSubregion', 'thematicArea',
                'history', 'geoculturalContext', 'mandates',
                'internalStructures', 'collectingPolicies', 'buildings',
                'holdings', 'findingAids', 'openingTimes',
                'accessConditions', 'disabledAccess', 'researchServices',
                'reproductionServices', 'publicFacilities',
                'descriptionStatus', 'culture',
            ],
            default => [],
        };
    }

    /**
     * Get required columns for a given CSV import object type.
     */
    private function getRequiredColumns(string $objectType): array
    {
        return match ($objectType) {
            'informationObject' => ['legacyId', 'parentId', 'identifier', 'title', 'levelOfDescription'],
            'accession' => ['accessionNumber', 'title', 'acquisitionDate'],
            'authorityRecord' => ['authorizedFormOfName', 'entityType'],
            'authorityRecordRelationship' => ['sourceAuthorizedFormOfName', 'targetAuthorizedFormOfName', 'category'],
            'event' => ['legacyId', 'eventActorName', 'eventType'],
            'repository' => ['authorizedFormOfName'],
            default => [],
        };
    }

    private function getIO(string $slug): ?object
    {
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return null;
        }

        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $slugRow->object_id)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
