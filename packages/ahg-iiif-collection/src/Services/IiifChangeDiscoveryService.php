<?php

/**
 * IiifChangeDiscoveryService - Service for Heratio
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

namespace AhgIiifCollection\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IIIF Change Discovery API 1.0 emitter (issue #695).
 *
 * Spec: https://iiif.io/api/discovery/1.0/
 *
 * Emits an Activity Streams 2.0 OrderedCollection at
 * /iiif/discovery/changes, plus paginated OrderedCollectionPage
 * documents under /iiif/discovery/changes?page=N. Each Activity carries
 * an `object` block with the manifest IRI + type, and a `type` of
 * Create / Update / Delete per the IIIF Change Discovery profile.
 *
 * Backed by ahg_iiif_collection.iiif_manifest_change which is
 * populated by the recordChange() hook called from
 * IiifCollectionService::generateObjectManifest (Update on read-as-cache)
 * and from explicit operator-triggered Create / Delete events.
 *
 * Cross-references issue #670 federation Phase 4 - the same Activity
 * Streams 2.0 pattern; once both ship, harvesters can choose either
 * surface without re-implementing the consumer.
 */
class IiifChangeDiscoveryService
{
    /**
     * Page size for OrderedCollectionPage. 100 matches the recommended
     * IIIF Change Discovery example pagination and keeps each page
     * small enough to render without backend pressure.
     */
    public const PAGE_SIZE = 100;

    /**
     * Record a manifest lifecycle change. Idempotent against repeated
     * Update events fired within a short window (we don't dedupe
     * intentionally - a busy curation session legitimately produces a
     * stream of Updates, and the timestamp resolution is enough to
     * order them).
     *
     * @param string $changeType Create | Update | Delete
     * @param int    $objectId   information_object.id
     * @param string $slug       manifest slug
     * @param string|null $actor optional actor IRI / username
     */
    public function recordChange(string $changeType, int $objectId, string $slug, ?string $actor = null): void
    {
        if (!in_array($changeType, ['Create', 'Update', 'Delete'], true)) {
            return;
        }
        if (!Schema::hasTable('iiif_manifest_change')) {
            return;
        }
        $baseUrl = rtrim(config('app.url'), '/');
        DB::table('iiif_manifest_change')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
            'manifest_uri' => $baseUrl . '/iiif-manifest/' . $slug,
            'change_type' => $changeType,
            'actor' => $actor,
            'created_at' => now(),
        ]);
    }

    /**
     * Build the root OrderedCollection document. Lists `first` + `last`
     * page IRIs and the total item count. Harvesters typically start
     * here and follow `last` -> `prev` until they catch up to their
     * last-known cursor.
     */
    public function buildOrderedCollection(): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $rootId = $baseUrl . '/iiif/discovery/changes';

        $total = 0;
        if (Schema::hasTable('iiif_manifest_change')) {
            $total = (int) DB::table('iiif_manifest_change')->count();
        }

        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));

        return [
            '@context' => [
                'http://iiif.io/api/discovery/1/context.json',
                'http://www.w3.org/ns/activitystreams',
            ],
            'id' => $rootId,
            'type' => 'OrderedCollection',
            'totalItems' => $total,
            'first' => [
                'id' => $rootId . '?page=1',
                'type' => 'OrderedCollectionPage',
            ],
            'last' => [
                'id' => $rootId . '?page=' . $lastPage,
                'type' => 'OrderedCollectionPage',
            ],
        ];
    }

    /**
     * Build a single OrderedCollectionPage for the given page number.
     * Returns null when the page is out of range so the controller can
     * 404.
     */
    public function buildOrderedCollectionPage(int $page): ?array
    {
        if ($page < 1) {
            return null;
        }
        if (!Schema::hasTable('iiif_manifest_change')) {
            return null;
        }
        $baseUrl = rtrim(config('app.url'), '/');
        $rootId = $baseUrl . '/iiif/discovery/changes';
        $offset = ($page - 1) * self::PAGE_SIZE;

        $rows = DB::table('iiif_manifest_change')
            ->orderBy('id')
            ->offset($offset)
            ->limit(self::PAGE_SIZE)
            ->get();

        if ($rows->isEmpty() && $page > 1) {
            return null;
        }

        $total = (int) DB::table('iiif_manifest_change')->count();
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => $rootId . '/activity/' . $row->id,
                'type' => $row->change_type,
                'object' => [
                    'id' => $row->manifest_uri,
                    'type' => 'Manifest',
                ],
                'endTime' => (string) $row->created_at,
            ];
        }

        $doc = [
            '@context' => [
                'http://iiif.io/api/discovery/1/context.json',
                'http://www.w3.org/ns/activitystreams',
            ],
            'id' => $rootId . '?page=' . $page,
            'type' => 'OrderedCollectionPage',
            'partOf' => [
                'id' => $rootId,
                'type' => 'OrderedCollection',
                'totalItems' => $total,
            ],
            'orderedItems' => $items,
        ];

        if ($page > 1) {
            $doc['prev'] = [
                'id' => $rootId . '?page=' . ($page - 1),
                'type' => 'OrderedCollectionPage',
            ];
        }
        if ($page < $lastPage) {
            $doc['next'] = [
                'id' => $rootId . '?page=' . ($page + 1),
                'type' => 'OrderedCollectionPage',
            ];
        }

        return $doc;
    }
}
