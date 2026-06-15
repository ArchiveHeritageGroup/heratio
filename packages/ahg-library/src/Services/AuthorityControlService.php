<?php

/**
 * AuthorityControlService — Subject authority record management for Heratio.
 *
 * Provides CRUD operations for library_subject_authority records and the
 * library_item_authority_link pivot. Used by the MARC editor (6XX fields)
 * to offer link-based subject heading validation.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthorityControlService
{
    /**
     * List authority records with optional search, paginated.
     *
     * @param array $params  page|limit|search|subject_type|source
     * @return array{hits:array,total:int,page:int,limit:int}
     */
    public function index(array $params = []): array
    {
        $page  = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $skip  = ($page - 1) * $limit;
        $search = trim($params['search'] ?? '');
        $type   = trim($params['subject_type'] ?? '');
        $source = trim($params['source'] ?? '');

        $query = DB::table('library_subject_authority');

        if ($search !== '') {
            $query->where('heading', 'LIKE', "%{$search}%");
        }
        if ($type !== '') {
            $query->where('subject_type', $type);
        }
        if ($source !== '') {
            $query->where('source', $source);
        }

        $total = $query->count();

        $rows = $query
            ->orderByDesc('linked_count')
            ->orderBy('heading')
            ->skip($skip)
            ->take($limit)
            ->get();

        return [
            'hits'  => $rows->values()->all(),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Find a single authority record by ID, with linked_items count.
     *
     * @param int $id
     * @return object|null
     */
    public function find(int $id): ?object
    {
        $auth = DB::table('library_subject_authority')->where('id', $id)->first();

        if ($auth) {
            // Refresh linked_count from pivot
            $count = DB::table('library_item_authority_link')
                ->where('authority_id', $id)
                ->count();
            $auth = (object) array_merge((array) $auth, ['linked_count' => $count]);
        }

        return $auth;
    }

    /**
     * Create a new authority record.
     *
     * @param array $data  heading|subject_type|source|uri
     * @return int  authority record ID
     */
    public function create(array $data): int
    {
        $id = DB::table('library_subject_authority')->insertGetId([
            'heading'      => $data['heading'] ?? '',
            'subject_type' => $data['subject_type'] ?? 'topic',
            'source'      => $data['source'] ?? 'local',
            'uri'         => $data['uri'] ?? null,
            'linked_count'=> 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return (int) $id;
    }

    /**
     * Update an existing authority record.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $upd = array_filter([
            'heading'      => $data['heading'] ?? null,
            'subject_type' => $data['subject_type'] ?? null,
            'source'      => $data['source'] ?? null,
            'uri'         => array_key_exists('uri', $data) ? $data['uri'] : null,
        ], fn($v) => $v !== null);

        if (empty($upd)) {
            return false;
        }

        $upd['updated_at'] = now();

        return (bool) DB::table('library_subject_authority')
            ->where('id', $id)
            ->update($upd);
    }

    /**
     * Delete an authority record (cascade removes links).
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return (bool) DB::table('library_subject_authority')
            ->where('id', $id)
            ->delete();
    }

    /**
     * Link an authority record to a library item via the pivot table.
     * Updates linked_count on library_subject_authority.
     *
     * @param int    $authorityId
     * @param int    $libraryItemId
     * @param string $tag       MARC tag to record as source (default 650)
     */
    public function linkToItem(int $authorityId, int $libraryItemId, string $tag = '650'): void
    {
        $exists = DB::table('library_item_authority_link')
            ->where('library_item_id', $authorityId)
            ->where('authority_id', $libraryItemId)
            ->exists();

        if (! $exists) {
            DB::table('library_item_authority_link')->insert([
                'library_item_id' => $libraryItemId,
                'authority_id'   => $authorityId,
                'source_tag'     => $tag,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            DB::table('library_subject_authority')
                ->where('id', $authorityId)
                ->increment('linked_count');
        }
    }

    /**
     * Remove a specific authority-to-item link.
     * Decrements linked_count on library_subject_authority.
     *
     * @param int $linkId  library_item_authority_link.id
     */
    public function unlinkFromItem(int $linkId): void
    {
        $link = DB::table('library_item_authority_link')->where('id', $linkId)->first();
        if ($link) {
            DB::table('library_item_authority_link')->where('id', $linkId)->delete();
            DB::table('library_subject_authority')
                ->where('id', $link->authority_id)
                ->decrement('linked_count');
        }
    }

    /**
     * Search authority headings by prefix/minimum match for typeahead.
     *
     * @param string $term
     * @param int    $max
     * @return array[{id, heading, subject_type, source}]
     */
    public function search(string $term, int $max = 20): array
    {
        return DB::table('library_subject_authority')
            ->where('heading', 'LIKE', "%{$term}%")
            ->orderByDesc('linked_count')
            ->take($max)
            ->get(['id', 'heading', 'subject_type', 'source'])
            ->toArray();
    }

    /**
     * Get all library items linked to an authority record.
     *
     * @param int $authorityId
     * @return array[{link_id, library_item_id, title, source_tag}]
     */
    public function linkedItems(int $authorityId): array
    {
        return DB::table('library_item_authority_link as link')
            ->join('library_item as li', 'li.id', '=', 'link.library_item_id')
            ->join('information_object as io', 'io.id', '=', 'li.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                  ->where('ioi.culture', '=', app()->getLocale());
            })
            ->where('link.authority_id', $authorityId)
            ->get([
                'link.id as link_id',
                'link.library_item_id',
                'link.source_tag',
                'ioi.title as title',
            ])
            ->toArray();
    }
}
