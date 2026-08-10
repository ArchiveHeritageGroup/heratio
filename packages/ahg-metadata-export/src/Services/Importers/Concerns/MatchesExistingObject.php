<?php

/**
 * MatchesExistingObject - shared identifier->information_object.id lookup for XML importers.
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

namespace AhgMetadataExport\Services\Importers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve an existing information_object id by identifier (falling back to a
 * numeric id match). Was byte-identical in EadXmlImporter and MarcXmlImporter.
 */
trait MatchesExistingObject
{
    private function matchExisting(string $identifier): ?int
    {
        try {
            if (! Schema::hasTable('information_object')) {
                return null;
            }
            $id = DB::table('information_object')
                ->where('identifier', $identifier)
                ->value('id');
            if ($id) {
                return (int) $id;
            }
            if (ctype_digit($identifier)) {
                $hit = DB::table('information_object')->where('id', (int) $identifier)->value('id');
                if ($hit) {
                    return (int) $hit;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return null;
    }
}
