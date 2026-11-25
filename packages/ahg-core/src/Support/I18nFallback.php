<?php

/**
 * I18nFallback - the shared read-side culture fallback for `*_i18n` tables
 * (heratio#1608).
 *
 * An inner join on an i18n table filtered to the UI culture drops every
 * record that has no row in that culture: an English catalogue browsed in
 * Afrikaans loses its creators, subjects and children, and titles read
 * "Untitled". This helper replaces that inner join with the pattern
 * CONTRIBUTING.md requires - LEFT JOIN the current culture, LEFT JOIN
 * config('app.fallback_locale'), COALESCE per column - in a form that drops
 * into an existing fluent query without breaking the chain:
 *
 *   DB::table('event')
 *       ->join('actor', 'event.actor_id', '=', 'actor.id')
 *       ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'event.actor_id', $culture))
 *       ->select('event.actor_id as id', I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'name'))
 *
 * The current-culture join keeps the original alias (here `actor_i18n`), so
 * any reference to it elsewhere in the query still resolves; the fallback
 * join is the same alias with `_fb` appended. Two LEFT JOINs on the
 * (id, culture) primary key, so each joined row stays one row - no
 * duplication and no N+1.
 *
 * It is the static twin of AhgCore\Traits\WithCultureFallback, usable from
 * static methods and controllers that do not use the trait.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Support;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class I18nFallback
{
    /** The source culture read when the current one has no row. */
    public static function fallback(): string
    {
        return (string) config('app.fallback_locale', 'en');
    }

    /**
     * LEFT JOIN $i18nTable twice - current culture under its own alias, the
     * fallback culture under "{alias}_fb" - on {alias}.id = $parentKey.
     *
     * @param  string  $i18nTable  'actor_i18n' or 'actor_i18n as ai'
     * @param  string  $parentKey  qualified column the i18n id matches, e.g. 'event.actor_id'
     * @param  bool  $required  keep only rows that have a row in either culture
     *                          (the old inner-join semantics, satisfied by the fallback)
     */
    public static function join(
        Builder|EloquentBuilder $query,
        string $i18nTable,
        string $parentKey,
        ?string $culture = null,
        bool $required = false
    ): Builder|EloquentBuilder {
        [$table, $alias] = self::splitAlias($i18nTable);
        $fb = $alias . '_fb';
        $culture = $culture ?? (string) app()->getLocale();
        $fallback = self::fallback();

        $query
            ->leftJoin("{$table} as {$alias}", function ($j) use ($alias, $parentKey, $culture) {
                $j->on("{$alias}.id", '=', $parentKey)->where("{$alias}.culture", '=', $culture);
            })
            ->leftJoin("{$table} as {$fb}", function ($j) use ($fb, $parentKey, $fallback) {
                $j->on("{$fb}.id", '=', $parentKey)->where("{$fb}.culture", '=', $fallback);
            });

        if ($required) {
            $query->where(function ($w) use ($alias, $fb) {
                $w->whereNotNull("{$alias}.id")->orWhereNotNull("{$fb}.id");
            });
        }

        return $query;
    }

    /**
     * COALESCE({alias}.col, {alias}_fb.col) - aliased "AS $as" when $as is
     * given (for select, pluck, value), bare otherwise (for where, orderBy,
     * groupBy).
     */
    public static function col(string $alias, string $column, ?string $as = null): ExpressionContract
    {
        $sql = self::sql($alias, $column);

        return DB::raw($as === null ? $sql : "{$sql} AS {$as}");
    }

    /** The raw SQL string of col(), for use inside selectRaw / whereRaw. */
    public static function sql(string $alias, string $column): string
    {
        return "COALESCE({$alias}.{$column}, {$alias}_fb.{$column})";
    }

    /**
     * One i18n column for many ids, current culture first, else the fallback:
     * [id => value]. One query. Replaces
     *   DB::table('term_i18n')->whereIn('id', $ids)->where('culture', $c)->pluck('name', 'id')
     */
    public static function pluck(string $i18nTable, iterable $ids, string $column = 'name', ?string $culture = null): array
    {
        $ids = array_values(array_unique(array_filter(
            is_array($ids) ? $ids : iterator_to_array($ids, false),
            fn ($v) => $v !== null && $v !== '' && $v !== 0
        )));
        if ($ids === []) {
            return [];
        }
        $culture = $culture ?? (string) app()->getLocale();

        $rows = DB::table($i18nTable)
            ->whereIn('id', $ids)
            ->whereIn('culture', array_values(array_unique([$culture, self::fallback()])))
            ->get(['id', 'culture', $column]);

        $out = [];
        foreach ($rows as $r) {
            $v = $r->{$column};
            if ($v === null || $v === '') {
                continue;
            }
            if ($r->culture === $culture || ! isset($out[$r->id])) {
                $out[$r->id] = $v;
            }
        }

        return $out;
    }

    /** Single-id pluck(): the current-culture value, else the fallback, else null. */
    public static function value(string $i18nTable, $id, string $column = 'name', ?string $culture = null): ?string
    {
        if ($id === null || $id === '' || $id === 0) {
            return null;
        }
        $v = self::pluck($i18nTable, [$id], $column, $culture)[$id] ?? null;

        return $v === null ? null : (string) $v;
    }

    /** @return array{0: string, 1: string} [table, alias] */
    private static function splitAlias(string $i18nTable): array
    {
        if (preg_match('/^\s*(\S+)\s+as\s+(\S+)\s*$/i', $i18nTable, $m)) {
            return [$m[1], $m[2]];
        }

        return [trim($i18nTable), trim($i18nTable)];
    }
}
