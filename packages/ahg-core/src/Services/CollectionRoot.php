<?php

/**
 * CollectionRoot - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

/**
 * The top-level collection a record sits in (its ancestor directly under the
 * root), for the record page's "Explore this collection" link.
 */
class CollectionRoot
{
    /** @return object{id:int, slug:string, title:?string}|null  null when the record is not inside a collection with other records */
    public static function of(int $ioId): ?object
    {
        static $memo = [];
        if (array_key_exists($ioId, $memo)) {
            return $memo[$ioId];
        }
        $io = DB::table('information_object')->where('id', $ioId)->first(['lft', 'rgt', 'parent_id']);
        if (! $io) {
            return $memo[$ioId] = null;
        }
        $root = DB::table('information_object as a')
            ->join('slug', 'slug.object_id', '=', 'a.id')
            ->where('a.parent_id', 1)
            ->where('a.lft', '<=', $io->lft)->where('a.rgt', '>=', $io->rgt)
            ->first(['a.id', 'a.lft', 'a.rgt', 'slug.slug']);

        return $memo[$ioId] = ($root && $root->rgt - $root->lft > 1) ? $root : null;
    }
}
