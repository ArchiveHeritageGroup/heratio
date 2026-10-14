<?php

/**
 * MarketingSite - is this request for the vendor's own demo site (heratio.org)?
 * Demo-only features (the guided tour H06, acquisition tracking H09) switch on
 * here and nowhere else, so client installs never carry them.
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

namespace App\Support;

use App\Http\Controllers\HomeController;

class MarketingSite
{
    public static function is(): bool
    {
        return config('heratio.homepage_mode') === 'marketing'
            || in_array(request()->getHost(), HomeController::MARKETING_HOSTS, true);
    }
}
