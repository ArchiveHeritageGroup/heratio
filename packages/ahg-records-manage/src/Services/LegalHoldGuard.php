<?php

/**
 * LegalHoldGuard - heratio#1740: one answer to "is this record held?".
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A legal hold placed on an aggregation (fonds, series, file) holds every
 * record inside it, not only the row it was placed on. The disposal code used
 * to look at the record's own id alone, so an item in a held file could still
 * be destroyed. This walks the parent chain - not the nested-set bounds, which
 * some records do not have - and returns the first active hold found.
 */
class LegalHoldGuard
{
    /** Root of the description tree; never carries a hold. */
    private const ROOT_ID = 1;

    /** Guard against a corrupt parent loop. Deeper trees than this are not real. */
    private const MAX_DEPTH = 200;

    /**
     * The active hold on the record or on any of its ancestors, or null.
     * Its information_object_id is the record the hold sits on.
     */
    public static function activeHoldFor(int $ioId): ?object
    {
        if ($ioId <= 0 || ! Schema::hasTable('integrity_legal_hold')) {
            return null;
        }

        $chain = [];
        $current = $ioId;
        while ($current && $current !== self::ROOT_ID && ! isset($chain[$current]) && count($chain) < self::MAX_DEPTH) {
            $chain[$current] = true;
            $current = (int) DB::table('information_object')->where('id', $current)->value('parent_id');
        }

        return DB::table('integrity_legal_hold')
            ->whereIn('information_object_id', array_keys($chain))
            ->where('status', 'active')
            ->orderBy('placed_at')
            ->first();
    }

    public static function isHeld(int $ioId): bool
    {
        return self::activeHoldFor($ioId) !== null;
    }
}
