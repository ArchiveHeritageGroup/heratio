<?php

/**
 * ComparisonController - serves the static SEO/sales comparison pages.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgMarketing\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ComparisonController extends Controller
{
    /** Heratio vs AtoM comparison page. */
    public function atom(): View
    {
        return view('marketing::compare.atom');
    }
}
