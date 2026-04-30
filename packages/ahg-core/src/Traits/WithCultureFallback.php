<?php

/**
 * WithCultureFallback — encapsulates the LEFT-JOIN-current + LEFT-JOIN-fallback
 * + COALESCE-per-column pattern used to read translated content from `*_i18n`
 * tables without 404'ing on records that only have a single culture's row.
 *
 * Mirrors AtoM's `cultureFallback => true` option on Propel `__get($name, $opts)`
 * — when the requested culture has no row for a given record, the column value
 * falls back to the source/default culture (`config('app.fallback_locale', 'en')`).
 *
 * Usage in a service:
 *
 *   use AhgCore\Traits\WithCultureFallback;
 *
 *   class WhateverService {
 *       use WithCultureFallback;
 *       protected string $culture;
 *
 *       public function getBySlug(string $slug): ?object
 *       {
 *           $q = DB::table('information_object')
 *               ->join('object', 'information_object.id', '=', 'object.id')
 *               ->join('slug', 'information_object.id', '=', 'slug.object_id')
 *               ->where('slug.slug', $slug);
 *
 *           [$cur, $fb] = $this->joinI18nWithFallback(
 *               $q, 'information_object_i18n', 'information_object'
 *           );
 *
 *           return $q->select(array_merge(
 *               ['information_object.id', 'information_object.identifier', ...],
 *               $this->coalesceI18n($cur, $fb, ['title', 'scope_and_content', ...])
 *           ))->first();
 *       }
 *   }
 *
 * The trait expects the using class to expose a `protected string $culture`
 * property representing the current request culture (typically passed in via
 * the constructor as `app()->getLocale()`). The fallback culture comes from
 * `config('app.fallback_locale')` and defaults to `'en'`.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait WithCultureFallback
{
    /**
     * The fallback (source) culture to read from when the current culture has
     * no row for a given record. Override in the using class if you need a
     * different fallback than the app default.
     */
    protected function fallbackCulture(): string
    {
        return (string) config('app.fallback_locale', 'en');
    }

    /**
     * Add LEFT JOINs onto $query for current + fallback culture against the
     * given i18n table. Returns the two aliases as a [current, fallback]
     * tuple — pass them into coalesceI18n() below.
     *
     * @param  Builder  $query        The query being built (mutated in place).
     * @param  string   $i18nTable    e.g. 'information_object_i18n'
     * @param  string   $parentTable  e.g. 'information_object'
     * @param  string   $parentColumn e.g. 'id' (the join column on the parent)
     * @param  string   $i18nForeign  e.g. 'id' (the join column on the i18n table)
     * @param  string|null $aliasPrefix  Override the auto-generated alias prefix
     *                                    (default: i18n table name minus '_i18n')
     * @return array{0: string, 1: string} [current alias, fallback alias]
     */
    protected function joinI18nWithFallback(
        Builder $query,
        string $i18nTable,
        string $parentTable,
        string $parentColumn = 'id',
        string $i18nForeign = 'id',
        ?string $aliasPrefix = null,
        ?string $culture = null,
        ?string $fallback = null
    ): array {
        $prefix = $aliasPrefix ?? str_replace('_i18n', '', $i18nTable);
        $cur = "{$prefix}_cur";
        $fb = "{$prefix}_fb";

        // Resolution order: explicit arg > instance $culture > current locale.
        $culture = $culture ?? ($this->culture ?? (string) app()->getLocale());
        $fallback = $fallback ?? $this->fallbackCulture();

        $query
            ->leftJoin("{$i18nTable} as {$cur}", function ($j) use ($cur, $parentTable, $parentColumn, $i18nForeign, $culture) {
                $j->on("{$cur}.{$i18nForeign}", '=', "{$parentTable}.{$parentColumn}")
                    ->where("{$cur}.culture", '=', $culture);
            })
            ->leftJoin("{$i18nTable} as {$fb}", function ($j) use ($fb, $parentTable, $parentColumn, $i18nForeign, $fallback) {
                $j->on("{$fb}.{$i18nForeign}", '=', "{$parentTable}.{$parentColumn}")
                    ->where("{$fb}.culture", '=', $fallback);
            });

        return [$cur, $fb];
    }

    /**
     * Build COALESCE(cur.col, fb.col) AS col expressions for each given column.
     * Returns an array suitable for splatting into ->select([...]).
     *
     * @param  string  $cur      Current-culture alias (from joinI18nWithFallback)
     * @param  string  $fb       Fallback-culture alias (from joinI18nWithFallback)
     * @param  string[]  $columns  i18n column names to fall back
     * @return array<int, \Illuminate\Database\Query\Expression>
     */
    protected function coalesceI18n(string $cur, string $fb, array $columns): array
    {
        return array_map(
            fn ($c) => DB::raw("COALESCE({$cur}.{$c}, {$fb}.{$c}) AS {$c}"),
            $columns
        );
    }
}
