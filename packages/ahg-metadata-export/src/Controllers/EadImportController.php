<?php

/**
 * EadImportController - admin UI for EAD2002 / EAD3 XML import (#657 Phase 1).
 *
 * GET  /admin/ead/import          - upload form
 * POST /admin/ead/import/preview  - upload + parse + preview (no writes)
 * POST /admin/ead/import/commit   - persist the previewed tree
 *
 * Mirrors the MARCXML import flow shape from #663 Phase 2 (upload -> preview
 * -> commit) so operator muscle memory carries across import types.
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

use AhgMetadataExport\Services\Importers\EadXmlImporter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EadImportController extends Controller
{
    public function form()
    {
        return view('ahg-metadata-export::ead-import', [
            'stage' => 'upload',
            'tree' => null,
            'errors' => [],
            'culture' => 'en',
            'variant' => null,
        ]);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'eadxml' => 'required|file|max:102400',
            'culture' => 'nullable|string|max:16',
        ]);
        $culture = $request->input('culture', 'en');
        $xml = file_get_contents($request->file('eadxml')->getRealPath());
        if ($xml === false || $xml === '') {
            return back()->withErrors(['eadxml' => 'Could not read uploaded file.']);
        }

        $importer = new EadXmlImporter();
        $variant = $importer->detectVariant($xml);
        [$valid, $schemaErrors] = $importer->validate($xml);

        $tree = $valid ? $importer->preview($xml, $culture) : [];
        $rootNode = $tree[0] ?? null;

        session()->flash('eadxml_payload', base64_encode($xml));
        session()->flash('eadxml_culture', $culture);

        return view('ahg-metadata-export::ead-import', [
            'stage' => 'preview',
            'tree' => $rootNode,
            'errors' => $schemaErrors,
            'culture' => $culture,
            'variant' => $variant,
            'valid' => $valid,
        ]);
    }

    public function commit(Request $request)
    {
        $payload = $request->input('eadxml_payload');
        $culture = $request->input('culture', 'en');
        if (! $payload) {
            return redirect()->route('ahgmetadataexport.ead.import')
                ->withErrors(['eadxml' => 'No upload in session - re-upload to commit.']);
        }
        $xml = base64_decode($payload, true);
        if ($xml === false) {
            return redirect()->route('ahgmetadataexport.ead.import')
                ->withErrors(['eadxml' => 'Session payload corrupted - re-upload.']);
        }

        $importer = new EadXmlImporter();
        $results = $importer->commit($xml, $culture);
        $rootNode = $results[0] ?? null;

        return view('ahg-metadata-export::ead-import', [
            'stage' => 'committed',
            'tree' => $rootNode,
            'errors' => [],
            'culture' => $culture,
            'variant' => $importer->detectVariant($xml),
            'valid' => true,
        ]);
    }
}
