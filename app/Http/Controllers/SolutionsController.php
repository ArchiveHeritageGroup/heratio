<?php

/**
 * SolutionsController - the buyer pages on heratio.org (H03): one page per
 * kind of institution plus migration from AtoM. The pages are views in the
 * repository, not database content, so they are versioned with the code they
 * describe and the nightly demo reset cannot change them.
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

namespace App\Http\Controllers;

class SolutionsController extends Controller
{
    /** slug => [title, one-line summary]; the order is the order on the index. */
    public const PAGES = [
        'archives' => ['Heratio for archives', 'Arrangement and description, authority records, preservation and public access.'],
        'museums' => ['Heratio for museums', 'Object cataloguing, collections procedures, condition and loans, 3D and exhibitions.'],
        'libraries' => ['Heratio for libraries', 'Cataloguing, copy cataloguing, circulation and acquisitions.'],
        'integrated-glam' => ['Integrated GLAM', 'Archives, museum, library and gallery collections in one system.'],
        'migrating-from-atom' => ['Migrating from AtoM', 'Run Heratio over an existing AtoM database, or move into a fresh install.'],
    ];

    public function index()
    {
        return view('solutions.index', ['pages' => self::PAGES]);
    }

    public function show(string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);

        return view('solutions.'.$page, ['page' => $page, 'meta' => self::PAGES[$page], 'pages' => self::PAGES]);
    }
}
