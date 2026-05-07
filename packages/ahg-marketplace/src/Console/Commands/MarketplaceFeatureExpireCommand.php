<?php

/**
 * MarketplaceFeatureExpireCommand - Heratio
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

namespace AhgMarketplace\Console\Commands;

use AhgMarketplace\Services\MarketplaceService;
use Illuminate\Console\Command;

/**
 * Reset is_featured=0 for marketplace_listing rows whose featured_until
 * window has closed. Closes #84.
 *
 * Idempotent: a listing with is_featured=0 already / featured_until=NULL /
 * featured_until in the future is left alone. The promoted-display queries
 * (getFeaturedListings, browse with orderBy is_featured DESC) read the
 * column directly, so flipping it here is enough to demote the listing
 * from prominent positioning on the next page render.
 */
class MarketplaceFeatureExpireCommand extends Command
{
    protected $signature = 'ahg:marketplace-feature-expire';
    protected $description = 'Demote marketplace listings whose featured_until window has closed.';

    public function handle(MarketplaceService $svc): int
    {
        $count = $svc->expireFeaturedListings();
        $this->line('[marketplace-feature-expire] demoted=' . $count);
        return self::SUCCESS;
    }
}
