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
 *
 * Walks up parent_id rather than using lft/rgt: records placed through the
 * closure hierarchy (heratio#1541) can have no nested-set bounds, and a NULL
 * bound made the old range query throw on the record page.
 */
class CollectionRoot
{
    /** Deepest hierarchy walked before giving up. */
    private const MAX_DEPTH = 50;

    /** @return object{id:int, slug:string}|null  null when the record is not inside a collection with other records */
    public static function of(int $ioId): ?object
    {
        static $memo = [];
        if (array_key_exists($ioId, $memo)) {
            return $memo[$ioId];
        }
        $id = $ioId;
        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            $parent = DB::table('information_object')->where('id', $id)->value('parent_id');
            if ($parent === null) {
                return $memo[$ioId] = null; // not a record, or the root itself
            }
            if ((int) $parent === 1) {
                break;
            }
            $id = (int) $parent;
        }
        if ($i === self::MAX_DEPTH) {
            return $memo[$ioId] = null;
        }
        // A collection of one (a top-level record with nothing under it) has nothing to explore.
        if ($id === $ioId && ! DB::table('information_object')->where('parent_id', $id)->exists()) {
            return $memo[$ioId] = null;
        }
        $slug = DB::table('slug')->where('object_id', $id)->value('slug');

        return $memo[$ioId] = $slug ? (object) ['id' => $id, 'slug' => $slug] : null;
    }
}
