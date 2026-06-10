<?php

/**
 * CataloguerController - Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgScan\Controllers;

use AhgScan\Services\CataloguerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * heratio#1196 - AI Cataloguer. Upload one scan, get a review-ready draft archival
 * description (title + scope-and-content + extracted people/places/dates) built from
 * HTR -> NER -> LLM. Nothing is saved; the archivist reviews the draft.
 */
class CataloguerController extends Controller
{
    public function __construct(private CataloguerService $service) {}

    public function index()
    {
        return view('ahg-scan::admin.scan.cataloguer');
    }

    /** Accept one uploaded scan image, return the draft record as JSON. */
    public function draftAjax(Request $request)
    {
        $request->validate([
            'scan' => 'required|file|mimes:jpeg,jpg,png,tif,tiff,webp,bmp|max:51200',
        ]);

        $draft = $this->service->draftFromImage($request->file('scan')->getRealPath());

        return response()->json($draft);
    }
}
