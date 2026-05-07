<?php

/**
 * SearchCacheCleanCommand - prune expired federation search cache rows
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

namespace AhgFederation\Console;

use AhgFederation\Services\FederatedSearchService;
use Illuminate\Console\Command;

class SearchCacheCleanCommand extends Command
{
    protected $signature = 'ahg:federation-search-cache-clean';
    protected $description = 'Delete expired rows from federation_search_cache.';

    public function handle(FederatedSearchService $service): int
    {
        $deleted = $service->clearExpiredCache();
        $this->info(sprintf('Pruned %d expired cache row%s.', $deleted, $deleted === 1 ? '' : 's'));
        return self::SUCCESS;
    }
}
