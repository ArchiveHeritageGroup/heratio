<?php

/**
 * IcipLabelAssignmentService - attach TK/BC labels to an object (#1464).
 *
 * Assignments live in `icip_tk_label`, keyed by information object. That is the
 * table TermProtocolService reads to drive community-protocol enforcement, so a
 * label recorded anywhere else is decoration: it renders, and governs nothing.
 *
 * Two rights services need to write these, and duplicating the vocabulary
 * translation between them is exactly the kind of thing #1464 exists to remove,
 * so it lives here once. ahg-core already hosts ICIP-aware code
 * (TermProtocolService), and every package depends on core, so this is the
 * dependency direction that already holds.
 *
 * Labels are OBJECT-scoped, not rights-record-scoped. An object may carry more
 * than one rights record, and a community's label belongs to the material
 * rather than to whichever record happens to mention it - which is why removing
 * a rights record must not remove labels.
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

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IcipLabelAssignmentService
{
    /**
     * Attach the given labels to an object, leaving existing ones in place.
     *
     * Ids may be `icip_tk_label_type` ids or legacy `rights_tk_label` ids - the
     * rights edit form still offers the legacy vocabulary, so those are
     * translated by code ('BC-CB' -> 'bc_cb'). An unresolvable id is skipped
     * rather than guessed at: a wrong cultural label is worse than none.
     *
     * @param  array<int|string>  $labelIds
     * @return int number attached
     */
    public static function apply(int $objectId, array $labelIds): int
    {
        if (! $objectId || ! Schema::hasTable('icip_tk_label') || ! Schema::hasTable('icip_tk_label_type')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $attached = 0;

        foreach ($labelIds as $labelId) {
            $typeId = self::resolveTypeId((int) $labelId);
            if (! $typeId) {
                continue;
            }

            DB::table('icip_tk_label')->updateOrInsert(
                ['information_object_id' => $objectId, 'label_type_id' => $typeId],
                ['updated_at' => $now, 'created_at' => $now]
            );
            $attached++;
        }

        return $attached;
    }

    /**
     * Replace an object's labels with the given set.
     *
     * Used by the rights edit form, where the label picker shows the object's
     * current labels and submits the intended full set.
     */
    public static function replace(int $objectId, array $labelIds): int
    {
        if (! $objectId || ! Schema::hasTable('icip_tk_label')) {
            return 0;
        }

        DB::table('icip_tk_label')->where('information_object_id', $objectId)->delete();

        return self::apply($objectId, $labelIds);
    }

    /** An ICIP label-type id, or a legacy rights_tk_label id translated by code. */
    private static function resolveTypeId(int $labelId): ?int
    {
        if (! $labelId) {
            return null;
        }

        $id = DB::table('icip_tk_label_type')->where('id', $labelId)->value('id');
        if ($id) {
            return (int) $id;
        }

        if (! Schema::hasTable('rights_tk_label')) {
            return null;
        }

        $code = DB::table('rights_tk_label')->where('id', $labelId)->value('code');
        if (! $code) {
            return null;
        }

        $id = DB::table('icip_tk_label_type')
            ->where('code', strtolower(str_replace('-', '_', $code)))
            ->value('id');

        return $id ? (int) $id : null;
    }
}
