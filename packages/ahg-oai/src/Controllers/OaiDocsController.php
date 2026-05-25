<?php

/**
 * OaiDocsController - human-friendly landing page for the OAI-PMH endpoint
 * served at /oai/docs. Lists supported verbs, metadata formats, and sample
 * queries operators can crib for testing or onboarding harvesters.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgOai\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OaiDocsController extends Controller
{
    public function index(Request $request)
    {
        $baseUrl = url('/oai');

        return view('ahg-oai::docs', compact('baseUrl'));
    }
}
