<?php

/**
 * MarcImportController - admin UI for MARCXML import (#663 Phase 2).
 *
 * GET  /admin/marc/import          - upload form
 * POST /admin/marc/import/preview  - upload + preview (no writes)
 * POST /admin/marc/import/commit   - upload + commit (writes + audit rows)
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
 */

namespace AhgMetadataExport\Controllers;

use AhgMetadataExport\Services\Importers\MarcXmlImporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\MessageBag;

class MarcImportController extends Controller
{
    public function form()
    {
        // Leave $errors as Laravel's shared ViewErrorBag so validation errors
        // flashed by validate() (which redirects back to this GET form) render
        // in the blade's @if ($errors->any()) block. No schema errors at upload.
        return view('ahg-metadata-export::marc-import', [
            'stage' => 'upload',
            'records' => [],
            'culture' => 'en',
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'marcxml' => 'required|file|max:51200',
            'culture' => 'nullable|string|max:16',
        ]);
        $culture = $request->input('culture', 'en');
        $xml = file_get_contents($request->file('marcxml')->getRealPath());
        if ($xml === false || $xml === '') {
            return back()->withErrors(['marcxml' => 'Could not read uploaded file.']);
        }

        $importer = new MarcXmlImporter();
        [$valid, $schemaErrors] = $importer->validate($xml);

        $records = $valid ? $importer->preview($xml, $culture) : [];

        // Stash the raw XML in the session so the commit step can re-parse
        // without re-uploading. Keeps preview/commit a single user flow.
        session()->flash('marcxml_payload', base64_encode($xml));
        session()->flash('marcxml_culture', $culture);

        // Surface schema-validation notes through Laravel's $errors bag so the
        // blade's @if ($errors->any()) / $errors->all() block lists them. The
        // value MUST be a MessageBag (not a plain array) or ->any() fatals.
        return view('ahg-metadata-export::marc-import', [
            'stage' => 'preview',
            'records' => $records,
            'errors' => new MessageBag(['schema' => array_values((array) $schemaErrors)]),
            'culture' => $culture,
            'valid' => $valid,
        ]);
    }

    public function commit(Request $request)
    {
        $payload = $request->input('marcxml_payload');
        $culture = $request->input('culture', 'en');
        if (! $payload) {
            return redirect()->route('ahgmetadataexport.marc.import')
                ->withErrors(['marcxml' => 'No upload in session - re-upload to commit.']);
        }
        $xml = base64_decode($payload, true);
        if ($xml === false) {
            return redirect()->route('ahgmetadataexport.marc.import')
                ->withErrors(['marcxml' => 'Session payload corrupted - re-upload.']);
        }

        $importer = new MarcXmlImporter();
        $results = $importer->commit($xml, $culture);

        return view('ahg-metadata-export::marc-import', [
            'stage' => 'committed',
            'records' => $results,
            'errors' => new MessageBag(),
            'culture' => $culture,
            'valid' => true,
        ]);
    }
}
