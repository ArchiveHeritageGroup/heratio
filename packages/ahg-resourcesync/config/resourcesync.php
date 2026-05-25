<?php

/**
 * resourcesync.php - ResourceSync configuration for Heratio
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

return [

    /*
    |--------------------------------------------------------------------------
    | ChangeList horizon (days)
    |--------------------------------------------------------------------------
    |
    | The ChangeList document only reports records whose updated_at (or
    | deleted_at for tombstones) falls within the last N days. Aggregators
    | that poll on a faster cadence than this horizon will never miss a
    | change. 30 days is the ResourceSync community-recommended default;
    | sites with very high churn can lower it, sites that aggregate
    | infrequently can raise it.
    |
    */
    'changelist_days' => (int) env('RESOURCESYNC_CHANGELIST_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Page size for ResourceList / ChangeList pagination
    |--------------------------------------------------------------------------
    |
    | Matches the OAI-PMH resumption_token_limit shape. A ResourceList /
    | ChangeList with more entries than this gets split into pages
    | accessed via ?page=N; we emit a sitemap-style <rs:ln rel="next">
    | link so harvesters can walk the chain. Default 1000 keeps each
    | document under the ResourceSync 50,000-line soft cap with plenty
    | of headroom.
    |
    */
    'page_size' => (int) env('RESOURCESYNC_PAGE_SIZE', 1000),

];
