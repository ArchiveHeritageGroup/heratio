<?php

/**
 * BrowseCounter - the GLAM browse result total for a set of browse parameters,
 * worked out by the browse page's own filters under the current viewer's rights
 * (heratio#1585). Extends the browse controller only to reach its filters, so a
 * citation counts exactly what the page shows.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSearch\Services;

use AhgDisplay\Controllers\DisplayController;
use Illuminate\Http\Request;

class BrowseCounter extends DisplayController
{
    public function total(array $params): int
    {
        $this->loadFilters(Request::create('/glam/browse', 'GET', $params));
        [, $total] = $this->filteredIdPage('date', 'asc', 1);

        return (int) $total;
    }
}
