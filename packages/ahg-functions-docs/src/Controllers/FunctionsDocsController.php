<?php

/**
 * FunctionsDocsController - Controller for Heratio
 *
 * Renders /docs/functions/ (index) and /docs/functions/{kind} (catalogue
 * for one of php, js, blade, py, routes).
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

namespace AhgFunctionsDocs\Controllers;

use AhgFunctionsDocs\Services\FunctionsDocsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FunctionsDocsController extends Controller
{
    public function __construct(protected FunctionsDocsService $service) {}

    /** Index: list the 5 catalogues with link counts and source-file mtime. */
    public function index()
    {
        $catalogues = $this->service->index();
        return view('ahg-functions-docs::index', [
            'catalogues' => $catalogues,
        ]);
    }

    /** Show one catalogue, paginated and filterable. */
    public function show(Request $request, string $kind)
    {
        if (!array_key_exists($kind, FunctionsDocsService::FILES)) {
            abort(404);
        }

        $summary = $this->service->summary($kind);
        if (empty($summary['available'])) {
            return view('ahg-functions-docs::missing', [
                'kind'    => $kind,
                'summary' => $summary,
            ]);
        }

        $page   = max(1, (int) $request->query('page', 1));
        $filter = trim((string) $request->query('q', ''));

        $rendered = $this->service->render($kind, $page, $filter !== '' ? $filter : null);

        return view('ahg-functions-docs::show', [
            'kind'     => $kind,
            'summary'  => $summary,
            'rendered' => $rendered,
            'filter'   => $filter,
        ]);
    }
}
