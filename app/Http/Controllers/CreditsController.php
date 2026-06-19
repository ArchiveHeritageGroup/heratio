<?php

/**
 * CreditsController - Heratio
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

/**
 * Public credits / open-source licenses page.
 *
 * This is the AGPL "Appropriate Legal Notices" surface (sec. 5d) for the
 * interactive UI: it credits the upstream work Heratio derives from (the
 * AtoM / Qubit data model by Artefactual Systems Inc., the ICA standards),
 * states Heratio's own AGPL licensing, and offers the corresponding source
 * (sec. 13 network-use clause).
 */
class CreditsController extends Controller
{
    public function show()
    {
        return view('credits', [
            'sourceUrl' => 'https://github.com/ArchiveHeritageGroup/heratio',
        ]);
    }
}
