<?php

/**
 * EmbargoScope - the one rule for "this embargo applies today" (heratio#1588).
 *
 * The embargo table carries two flags written by different screens: `status`
 * (active / pending / lifted / expired) and `is_active`. Some screens set only
 * one of them: /embargo saved new embargoes with is_active=1 and status left at
 * 'pending', and lifted them by clearing is_active only; a future-dated embargo
 * is saved 'pending' and nothing promotes it when its start date arrives. Every
 * check that asked only `status = 'active'` therefore missed some embargoes and
 * kept others. An embargo is current when it is not lifted or expired by either
 * flag and today falls inside its dates.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Support;

use Illuminate\Database\Query\Builder;

final class EmbargoScope
{
    /** Restrict $q (on `embargo`, optionally aliased) to the embargoes in force today. */
    public static function current(Builder $q, string $alias = ''): Builder
    {
        $c = fn (string $col) => $alias !== '' ? "{$alias}.{$col}" : $col;
        $today = now()->toDateString();

        return $q->whereIn($c('status'), ['active', 'pending'])
            ->where(fn ($w) => $w->where($c('is_active'), 1)->orWhereNull($c('is_active')))
            ->whereDate($c('start_date'), '<=', $today)
            ->where(fn ($w) => $w->whereNull($c('end_date'))->orWhereDate($c('end_date'), '>=', $today));
    }
}
