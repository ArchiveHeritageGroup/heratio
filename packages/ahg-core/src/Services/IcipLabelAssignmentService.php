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
     * Attach labels identified by LEGACY rights_tk_label ids.
     *
     * This is what the rights edit forms submit. The id spaces of the two
     * vocabularies OVERLAP (legacy 1-19, ICIP 1-70), so an id alone cannot say
     * which vocabulary it belongs to - legacy 15 is BC-CB while ICIP 15 is
     * tk_mg. Guessing there would attach the wrong cultural label, so the
     * caller must state the vocabulary and translation always goes via the
     * code.
     *
     * @param  array<int|string>  $legacyIds
     * @return int number attached
     */
    public static function applyLegacyIds(int $objectId, array $legacyIds): int
    {
        if (! Schema::hasTable('rights_tk_label')) {
            return 0;
        }

        $codes = DB::table('rights_tk_label')
            ->whereIn('id', array_filter(array_map('intval', $legacyIds)))
            ->pluck('code')
            ->all();

        return self::applyCodes($objectId, $codes);
    }

    /**
     * Attach labels by Local Contexts code, in either casing ('BC-CB', 'bc_cb').
     *
     * @param  array<string>  $codes
     * @return int number attached
     */
    public static function applyCodes(int $objectId, array $codes): int
    {
        if (! $objectId || ! Schema::hasTable('icip_tk_label') || ! Schema::hasTable('icip_tk_label_type')) {
            return 0;
        }

        $typeIds = [];
        foreach ($codes as $code) {
            $id = DB::table('icip_tk_label_type')
                ->where('code', strtolower(str_replace('-', '_', (string) $code)))
                ->value('id');
            // An unresolvable code is skipped, never guessed at: a wrong
            // cultural label is worse than a missing one.
            if ($id) {
                $typeIds[] = (int) $id;
            }
        }

        return self::applyTypeIds($objectId, $typeIds);
    }

    /**
     * Attach labels by icip_tk_label_type id, for callers already holding one.
     *
     * @param  array<int>  $typeIds
     * @return int number attached
     */
    public static function applyTypeIds(int $objectId, array $typeIds): int
    {
        if (! $objectId || ! Schema::hasTable('icip_tk_label')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $attached = 0;

        foreach (array_unique(array_filter(array_map('intval', $typeIds))) as $typeId) {
            DB::table('icip_tk_label')->updateOrInsert(
                ['information_object_id' => $objectId, 'label_type_id' => $typeId],
                ['updated_at' => $now, 'created_at' => $now]
            );
            $attached++;
        }

        return $attached;
    }

    /**
     * Replace an object's labels with the given LEGACY-id set.
     *
     * Used by the rights edit form, whose picker shows the object's current
     * labels and submits the intended full set.
     */
    public static function replaceLegacyIds(int $objectId, array $legacyIds): int
    {
        if (! $objectId || ! Schema::hasTable('icip_tk_label')) {
            return 0;
        }

        DB::table('icip_tk_label')->where('information_object_id', $objectId)->delete();

        return self::applyLegacyIds($objectId, $legacyIds);
    }
}
