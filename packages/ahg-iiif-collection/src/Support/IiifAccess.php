<?php

/**
 * IiifAccess - Heratio
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

namespace AhgIiifCollection\Support;

use AhgCore\Services\AclService;
use AhgCore\Services\DisclosureGate;
use Illuminate\Support\Facades\DB;

/**
 * Who may read a record through the IIIF routes (manifest, viewer, content
 * search, annotations) - GHSA-pfhq-87fh-gqfp. Staff who already see draft
 * descriptions (administrators, editors, contributors) see every record;
 * everyone else only records that are published and not under an embargo,
 * a security classification, an ICIP/TK protocol or an ODRL prohibition,
 * the same rule as DisclosureGate everywhere else. These routes used to
 * check publication only, and let any signed-in user see drafts.
 */
class IiifAccess
{
    public static function viewerSeesAll(): bool
    {
        $u = auth()->user();

        return $u !== null && (AclService::isAdministrator($u) || AclService::isEditor($u) || AclService::isContributor($u));
    }

    /** Limit a query on information_object (aliased by $idColumn) to what the viewer may read. */
    public static function scope($query, string $idColumn)
    {
        if (self::viewerSeesAll()) {
            return $query;
        }
        $query->whereExists(function ($s) use ($idColumn) {
            $s->select(DB::raw(1))->from('status as iiif_pub')
                ->whereColumn('iiif_pub.object_id', $idColumn)
                ->where('iiif_pub.type_id', DisclosureGate::STATUS_TYPE_PUBLICATION)
                ->where('iiif_pub.status_id', DisclosureGate::STATUS_PUBLISHED);
        });

        return app(DisclosureGate::class)->excludeRestricted($query, $idColumn);
    }

    /** May the viewer read the record with this slug? Unknown slugs answer false. */
    public static function allowsSlug(string $slug): bool
    {
        $id = (int) DB::table('slug')->where('slug', $slug)->value('object_id');

        return $id > 1 && (self::viewerSeesAll() || app(DisclosureGate::class)->allows($id));
    }
}
