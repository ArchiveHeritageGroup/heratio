<?php

/**
 * IiifCollectionService - Service for Heratio
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
use Illuminate\Support\Facades\Log;

/**
 * Service for managing IIIF Collections (manifest groupings).
 * Migrated from AtoM\Framework\Services\IiifCollectionService.
 * Supports IIIF Presentation API 3.0 collection format.
 */
class IiifCollectionService
{
    private string $culture = 'en';

    /**
     * Get all collections (optionally filtered by parent).
     */
    public function getAllCollections(?int $parentId = null, bool $publicOnly = false): array
    {
        $query = DB::table('iiif_collection as c')
            ->leftJoin('iiif_collection_i18n as i18n', function ($join) {
                $join->on('c.id', '=', 'i18n.collection_id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->select(
                'c.*',
                'i18n.name as i18n_name',
                'i18n.description as i18n_description'
            );

        if ($parentId === null) {
            $query->whereNull('c.parent_id');
        } else {
            $query->where('c.parent_id', $parentId);
        }

        if ($publicOnly) {
            $query->where('c.is_public', 1);
        }

        return $query->orderBy('c.sort_order')->orderBy('c.name')->get()->map(function ($c) {
            $c->display_name = $c->i18n_name ?: $c->name;
            $c->display_description = $c->i18n_description ?: $c->description;
            $c->item_count = $this->getItemCount($c->id);
            return $c;
        })->all();
    }

    /**
     * Get a single collection by ID or slug.
     */
    public function getCollection($identifier): ?object
    {
        $query = DB::table('iiif_collection as c')
            ->leftJoin('iiif_collection_i18n as i18n', function ($join) {
                $join->on('c.id', '=', 'i18n.collection_id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->select(
                'c.*',
                'i18n.name as i18n_name',
                'i18n.description as i18n_description'
            );

        if (is_numeric($identifier)) {
            $query->where('c.id', $identifier);
        } else {
            $query->where('c.slug', $identifier);
        }

        $collection = $query->first();

        if ($collection) {
            $collection->display_name = $collection->i18n_name ?: $collection->name;
            $collection->display_description = $collection->i18n_description ?: $collection->description;
            $collection->items = $this->getCollectionItems($collection->id);
            $collection->subcollections = $this->getAllCollections($collection->id);
        }

        return $collection;
    }

    /**
     * Get items in a collection.
     */
    public function getCollectionItems(int $collectionId): array
    {
        return DB::table('iiif_collection_item as ci')
            ->leftJoin('information_object as io', 'ci.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('ci.collection_id', $collectionId)
            ->select(
                'ci.*',
                'io.identifier',
                'i18n.title as object_title',
                'slug.slug'
            )
            ->orderBy('ci.sort_order')
            ->get()
            ->all();
    }

    /**
     * Get item count for a collection.
     */
    public function getItemCount(int $collectionId): int
    {
        return DB::table('iiif_collection_item')
            ->where('collection_id', $collectionId)
            ->count();
    }

    /**
     * Create a new collection.
     */
    public function createCollection(array $data): int
    {
        $slug = $this->generateSlug($data['name']);

        $id = DB::table('iiif_collection')->insertGetId([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'attribution' => $data['attribution'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'viewing_hint' => $data['viewing_hint'] ?? 'individuals',
            'parent_id' => $data['parent_id'] ?? null,
            'is_public' => $data['is_public'] ?? 1,
            'created_by' => $data['created_by'] ?? null,
        ]);

        Log::info('IIIF Collection created', ['id' => $id, 'name' => $data['name']]);

        return $id;
    }

    /**
     * Update a collection.
     */
    public function updateCollection(int $id, array $data): bool
    {
        $update = [];

        foreach (['name', 'description', 'attribution', 'logo_url', 'thumbnail_url', 'viewing_hint', 'parent_id', 'is_public'] as $field) {
            if (isset($data[$field])) {
                $update[$field] = $data[$field];
            }
        }

        if (!empty($update)) {
            DB::table('iiif_collection')->where('id', $id)->update($update);
            Log::info('IIIF Collection updated', ['id' => $id]);
            return true;
        }

        return false;
    }

    /**
     * Delete a collection.
     */
    public function deleteCollection(int $id): bool
    {
        DB::table('iiif_collection_item')->where('collection_id', $id)->delete();
        DB::table('iiif_collection')->where('id', $id)->delete();
        Log::info('IIIF Collection deleted', ['id' => $id]);
        return true;
    }

    /**
     * Add an item (manifest) to a collection.
     */
    public function addItem(int $collectionId, array $data): int
    {
        $maxOrder = DB::table('iiif_collection_item')
            ->where('collection_id', $collectionId)
            ->max('sort_order') ?? 0;

        return DB::table('iiif_collection_item')->insertGetId([
            'collection_id' => $collectionId,
            'object_id' => $data['object_id'] ?? null,
            'manifest_uri' => $data['manifest_uri'] ?? null,
            'item_type' => $data['item_type'] ?? 'manifest',
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
        ]);
    }

    /**
     * Remove an item from a collection.
     */
    public function removeItem(int $itemId): bool
    {
        return DB::table('iiif_collection_item')->where('id', $itemId)->delete() > 0;
    }

    /**
     * Reorder items in a collection.
     */
    public function reorderItems(int $collectionId, array $itemIds): bool
    {
        foreach ($itemIds as $order => $itemId) {
            DB::table('iiif_collection_item')
                ->where('id', $itemId)
                ->where('collection_id', $collectionId)
                ->update(['sort_order' => $order]);
        }
        return true;
    }

    /**
     * Generate IIIF Collection JSON (Presentation API 3.0 by default;
     * legacy v2 shape via $version = 2).
     */
    public function generateCollectionJson(int $collectionId, int $version = 3): array
    {
        if ($version === 2) {
            return $this->generateCollectionJsonV2($collectionId);
        }

        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new \Exception('Collection not found');
        }

        $baseUrl = rtrim(config('app.url'), '/');

        $json = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $baseUrl . '/iiif/collection/' . $collection->slug,
            'type' => 'Collection',
            'label' => ['en' => [$collection->display_name]],
        ];

        if ($collection->display_description) {
            $json['summary'] = ['en' => [$collection->display_description]];
        }

        if ($collection->attribution) {
            $json['requiredStatement'] = [
                'label' => ['en' => ['Attribution']],
                'value' => ['en' => [$collection->attribution]],
            ];
        }

        if ($collection->logo_url) {
            $json['logo'] = [['id' => $collection->logo_url, 'type' => 'Image']];
        }

        if ($collection->thumbnail_url) {
            $json['thumbnail'] = [['id' => $collection->thumbnail_url, 'type' => 'Image']];
        }

        if ($collection->viewing_hint) {
            $json['behavior'] = [$collection->viewing_hint];
        }

        $json['items'] = [];

        // Subcollections first
        foreach ($collection->subcollections as $sub) {
            $json['items'][] = [
                'id' => $baseUrl . '/iiif/collection/' . $sub->slug,
                'type' => 'Collection',
                'label' => ['en' => [$sub->display_name]],
            ];
        }

        // Manifests
        foreach ($collection->items as $item) {
            if ($item->item_type === 'collection') {
                $json['items'][] = [
                    'id' => $item->manifest_uri,
                    'type' => 'Collection',
                    'label' => ['en' => [$item->label ?: 'Collection']],
                ];
            } else {
                $manifestUri = $item->manifest_uri;
                if (!$manifestUri && $item->slug) {
                    $manifestUri = $baseUrl . '/iiif-manifest/' . $item->slug;
                }

                $manifestItem = [
                    'id' => $manifestUri,
                    'type' => 'Manifest',
                    'label' => ['en' => [$item->label ?: $item->object_title ?: 'Untitled']],
                ];

                if ($item->thumbnail_url) {
                    $manifestItem['thumbnail'] = [['id' => $item->thumbnail_url, 'type' => 'Image']];
                }

                $json['items'][] = $manifestItem;
            }
        }

        return $json;
    }

    /**
     * Legacy IIIF Presentation API 2.x Collection JSON. Mirador 3 and
     * older OSD-based viewers consume this shape. New consumers should
     * use the default v3 output.
     */
    public function generateCollectionJsonV2(int $collectionId): array
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new \Exception('Collection not found');
        }

        $baseUrl = rtrim(config('app.url'), '/');

        $json = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@id' => $baseUrl . '/iiif/collection/' . $collection->slug,
            '@type' => 'sc:Collection',
            'label' => $collection->display_name,
        ];

        if ($collection->display_description) {
            $json['description'] = $collection->display_description;
        }
        if ($collection->attribution) {
            $json['attribution'] = $collection->attribution;
        }
        if ($collection->viewing_hint) {
            $json['viewingHint'] = $collection->viewing_hint;
        }

        $collections = [];
        foreach ($collection->subcollections as $sub) {
            $collections[] = [
                '@id' => $baseUrl . '/iiif/collection/' . $sub->slug,
                '@type' => 'sc:Collection',
                'label' => $sub->display_name,
            ];
        }
        $manifests = [];
        foreach ($collection->items as $item) {
            if ($item->item_type === 'collection') {
                $collections[] = [
                    '@id' => $item->manifest_uri,
                    '@type' => 'sc:Collection',
                    'label' => $item->label ?: 'Collection',
                ];
            } else {
                $manifestUri = $item->manifest_uri ?:
                    ($item->slug ? $baseUrl . '/iiif-manifest/' . $item->slug . '?version=2' : null);
                if ($manifestUri) {
                    $manifests[] = [
                        '@id' => $manifestUri,
                        '@type' => 'sc:Manifest',
                        'label' => $item->label ?: $item->object_title ?: 'Untitled',
                    ];
                }
            }
        }
        if (!empty($collections)) {
            $json['collections'] = $collections;
        }
        if (!empty($manifests)) {
            $json['manifests'] = $manifests;
        }

        return $json;
    }

    /**
     * Get breadcrumb trail for a collection.
     */
    public function getBreadcrumbs(object $collection): array
    {
        $breadcrumbs = [];
        $current = $collection;

        while ($current) {
            array_unshift($breadcrumbs, $current);
            if ($current->parent_id) {
                $current = $this->getCollection($current->parent_id);
            } else {
                break;
            }
        }

        return $breadcrumbs;
    }

    /**
     * Search objects for adding to collection.
     */
    public function searchObjects(string $query): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
            ->where(function ($q) use ($query) {
                $q->where('i18n.title', 'LIKE', "%{$query}%")
                    ->orWhere('io.identifier', 'LIKE', "%{$query}%");
            })
            ->whereNotNull('do.id')
            ->select('io.id', 'io.identifier', 'i18n.title', 'slug.slug')
            ->limit(50)
            ->get()
            ->all();
    }

    /**
     * AJAX autocomplete for objects.
     */
    public function autocompleteObjects(string $query): array
    {
        $results = [];

        if (strlen($query) < 2) {
            return $results;
        }

        $objects = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('digital_object as do', 'io.id', '=', 'do.object_id')
            ->where(function ($q) use ($query) {
                $q->where('i18n.title', 'LIKE', "%{$query}%")
                    ->orWhere('io.identifier', 'LIKE', "%{$query}%");
            })
            ->select('io.id', 'io.identifier', 'i18n.title', 'slug.slug', 'io.lft', 'io.rgt', 'do.id as has_digital')
            ->orderByRaw('`do`.id IS NOT NULL DESC')
            ->limit(30)
            ->get();

        foreach ($objects as $obj) {
            $childCount = 0;
            if ($obj->rgt - $obj->lft > 1) {
                $childCount = DB::table('information_object as io')
                    ->join('digital_object as do', 'io.id', '=', 'do.object_id')
                    ->where('io.lft', '>', $obj->lft)
                    ->where('io.rgt', '<', $obj->rgt)
                    ->count();
            }

            $results[] = [
                'id' => $obj->id,
                'text' => ($obj->identifier ? "[{$obj->identifier}] " : '') . ($obj->title ?: 'Untitled'),
                'identifier' => $obj->identifier,
                'title' => $obj->title,
                'slug' => $obj->slug,
                'hasDigital' => !empty($obj->has_digital),
                'hasChildren' => $childCount > 0,
                'childCount' => $childCount,
            ];
        }

        return $results;
    }

    /**
     * Add all children/descendants of an object to a collection.
     */
    public function addChildrenToCollection(int $collectionId, int $parentObjectId): void
    {
        $parent = DB::table('information_object')
            ->where('id', $parentObjectId)
            ->select('lft', 'rgt')
            ->first();

        if (!$parent) {
            return;
        }

        $children = DB::table('information_object as io')
            ->join('digital_object as do', 'io.id', '=', 'do.object_id')
            ->where('io.lft', '>', $parent->lft)
            ->where('io.rgt', '<', $parent->rgt)
            ->select('io.id')
            ->orderBy('io.lft')
            ->get();

        foreach ($children as $child) {
            $this->addItem($collectionId, ['object_id' => $child->id]);
        }
    }

    /**
     * Get collections containing a specific object.
     */
    public function getCollectionsForObject(int $objectId): array
    {
        return DB::table('iiif_collection as c')
            ->join('iiif_collection_item as ci', 'c.id', '=', 'ci.collection_id')
            ->where('ci.object_id', $objectId)
            ->select('c.*')
            ->distinct()
            ->get()
            ->all();
    }

    /**
     * Generate IIIF Manifest for an individual information object.
     *
     * Default output is Presentation API 3.0 (https://iiif.io/api/presentation/3.0/).
     * Legacy callers can pass $version = 2 to receive the previous
     * Presentation API 2.1 shape (sequences + canvases + images) which
     * Mirador 3 and older OSD-based viewers still consume. Issue #698.
     */
    public function generateObjectManifest(string $slug, int $version = 3): ?array
    {
        if ($version === 2) {
            return $this->generateObjectManifestV2($slug);
        }
        return $this->generateObjectManifestV3($slug);
    }

    /**
     * Presentation API 3.0 manifest for a single information object.
     *
     * Spec-correct shape:
     *   - @context = http://iiif.io/api/presentation/3/context.json
     *   - type = Manifest
     *   - label, summary, metadata = language-map { "en": ["..."] }
     *   - items = list of Canvas objects (no sequences wrapper)
     *   - each Canvas has items = [AnnotationPage{ items: [Annotation] }]
     *   - homepage, provider, requiredStatement, behavior populated where
     *     we know the values.
     *   - service block on the canvas resource carries PhysicalDimensions
     *     when the digital_object exposes a physical scale; downstream
     *     viewers (Mirador / OSD scalebar plugin) read it from there.
     */
    public function generateObjectManifestV3(string $slug): ?array
    {
        $ctx = $this->loadObjectAndDigitalObjects($slug);
        if (!$ctx) {
            return null;
        }
        $object = $ctx['object'];
        $digitalObjects = $ctx['digitalObjects'];

        $baseUrl = rtrim(config('app.url'), '/');
        $label = $object->title ?: $object->identifier ?: 'Untitled';
        $manifestId = $baseUrl . '/iiif-manifest/' . $object->slug;

        $canvases = $this->buildCanvasesV3($manifestId, $baseUrl, $digitalObjects);
        if (empty($canvases)) {
            return null;
        }

        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $manifestId,
            'type' => 'Manifest',
            'label' => ['en' => [$label]],
            'metadata' => [],
            'items' => $canvases,
        ];

        // homepage points back at the Heratio show page so a manifest
        // consumer (Mirador, Universal Viewer) has a deep link to the
        // source archival description.
        $manifest['homepage'] = [[
            'id' => $baseUrl . '/' . $object->slug,
            'type' => 'Text',
            'label' => ['en' => [$label]],
            'format' => 'text/html',
        ]];

        // provider block carries Heratio's organisation metadata. The
        // operator can override with config('heratio.iiif_provider').
        $providerName = config('heratio.iiif_provider_name', config('app.name', 'Heratio'));
        $providerHomepage = config('heratio.iiif_provider_homepage', $baseUrl);
        $manifest['provider'] = [[
            'id' => $providerHomepage,
            'type' => 'Agent',
            'label' => ['en' => [$providerName]],
            'homepage' => [[
                'id' => $providerHomepage,
                'type' => 'Text',
                'label' => ['en' => [$providerName]],
                'format' => 'text/html',
            ]],
        ]];

        if ($object->identifier) {
            $manifest['metadata'][] = [
                'label' => ['en' => ['Identifier']],
                'value' => ['en' => [$object->identifier]],
            ];
        }

        // Multi-canvas archival objects often represent paged content
        // (multi-page TIFFs, scanned books). "paged" tells the viewer
        // to render a two-page spread; "individuals" is the safer
        // default when we have a single canvas or unrelated images.
        $manifest['behavior'] = count($canvases) > 1 ? ['paged'] : ['individuals'];

        if (!empty($canvases)) {
            // Thumbnail is the first canvas's painting target at 200px
            // wide. Pres 3 thumbnails take id/type/format/width/height.
            $first = $canvases[0];
            $painting = $first['items'][0]['items'][0]['body'] ?? null;
            if ($painting && isset($painting['id'])) {
                $thumbId = str_replace('/full/max/', '/full/200,/', $painting['id']);
                $thumbId = str_replace('/full/full/', '/full/200,/', $thumbId);
                $thumb = [
                    'id' => $thumbId,
                    'type' => 'Image',
                    'format' => 'image/jpeg',
                ];
                if (isset($painting['service'])) {
                    $thumb['service'] = $painting['service'];
                }
                $manifest['thumbnail'] = [$thumb];
            }
        }

        return $manifest;
    }

    /**
     * Build the Pres 3 canvas list for an object's digital objects.
     * Each canvas carries one AnnotationPage with one painting
     * Annotation pointing at a IIIF Image API service. PhysicalDimensions
     * arrives via the service array on the painting body when the
     * digital_object exposes a physical scale.
     *
     * Issue #695 widens the emitter to audio + video. A/V canvases carry
     * a `duration` (seconds), the painting body has `type` = Sound or
     * Video, the body's `format` is the source mime type, and a service
     * block advertises MediaFragmentSelector so consumers know they can
     * request `#t=ss,ee` ranges. A poster-frame thumbnail is emitted on
     * the canvas when one is available.
     */
    private function buildCanvasesV3(string $manifestId, string $baseUrl, $digitalObjects): array
    {
        $cantaloupeBaseUrl = 'http://127.0.0.1:8182';
        $canvases = [];
        $canvasIndex = 1;

        foreach ($digitalObjects as $do) {
            $mimeType = strtolower($do->mime_type ?? '');
            $fileName = strtolower($do->name ?? '');

            // A/V branch (issue #695). Audio + Video digital objects don't
            // route through Cantaloupe; the painting body points at the
            // direct media URL with the correct Pres 3 type. We still emit
            // one canvas per digital object so multi-track works.
            if ($this->isAudioMime($mimeType, $fileName) || $this->isVideoMime($mimeType, $fileName)) {
                $canvases[] = $this->buildAvCanvasV3(
                    $manifestId,
                    $baseUrl,
                    $canvasIndex,
                    $do,
                    $this->isVideoMime($mimeType, $fileName) ? 'Video' : 'Sound'
                );
                $canvasIndex++;
                continue;
            }

            $imagePath = ltrim($do->path, '/');
            $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $do->name;

            $isMultiPageTiff = false;
            $pageCount = 1;

            if ($mimeType === 'image/tiff' || preg_match('/\.tiff?$/i', $fileName)) {
                $page2InfoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$cantaloupeId};2/info.json";
                $page2Info = @file_get_contents($page2InfoUrl);
                if ($page2Info !== false) {
                    $isMultiPageTiff = true;
                    $pageCount = 2;
                    for ($i = 3; $i <= 100; $i++) {
                        $pageInfoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$cantaloupeId};{$i}/info.json";
                        $ctx = stream_context_create(['http' => ['timeout' => 1]]);
                        $pageInfo = @file_get_contents($pageInfoUrl, false, $ctx);
                        if ($pageInfo === false) {
                            break;
                        }
                        $pageCount = $i;
                    }
                }
            }

            if ($isMultiPageTiff) {
                for ($pageNum = 1; $pageNum <= $pageCount; $pageNum++) {
                    $pageCantaloupeId = "{$cantaloupeId};{$pageNum}";
                    $canvases[] = $this->buildSingleCanvasV3(
                        $manifestId,
                        $baseUrl,
                        $canvasIndex,
                        $pageCantaloupeId,
                        ($do->name ?: 'Image') . " - Page {$pageNum}",
                        $cantaloupeBaseUrl,
                        $do
                    );
                    $canvasIndex++;
                }
            } else {
                $canvases[] = $this->buildSingleCanvasV3(
                    $manifestId,
                    $baseUrl,
                    $canvasIndex,
                    $cantaloupeId,
                    $do->name ?: "Image {$canvasIndex}",
                    $cantaloupeBaseUrl,
                    $do
                );
                $canvasIndex++;
            }
        }

        return $canvases;
    }

    /**
     * Build a single Pres 3 Canvas + AnnotationPage + Annotation.
     */
    private function buildSingleCanvasV3(
        string $manifestId,
        string $baseUrl,
        int $canvasIndex,
        string $imageApiId,
        string $label,
        string $cantaloupeBaseUrl,
        $digitalObject
    ): array {
        // Heratio's Cantaloupe is wired on IIIF Image API 2 today
        // (see public/vendor/openseadragon/...). We still emit a Pres 3
        // manifest because the manifest spec doesn't constrain the
        // image-api version of its painting service.
        $imageApiBase = "{$baseUrl}/iiif/2/{$imageApiId}";
        $infoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$imageApiId}/info.json";
        $infoJson = @file_get_contents($infoUrl);
        $width = 1000;
        $height = 1000;
        if ($infoJson) {
            $info = json_decode($infoJson, true);
            $width = $info['width'] ?? 1000;
            $height = $info['height'] ?? 1000;
        }

        $canvasId = "{$manifestId}/canvas/{$canvasIndex}";
        $pageId = "{$canvasId}/page/1";
        $annId = "{$canvasId}/annotation/1";

        $body = [
            'id' => "{$imageApiBase}/full/max/0/default.jpg",
            'type' => 'Image',
            'format' => 'image/jpeg',
            'width' => $width,
            'height' => $height,
            'service' => [[
                'id' => $imageApiBase,
                'type' => 'ImageService2',
                'profile' => 'http://iiif.io/api/image/2/level2.json',
            ]],
        ];

        // PhysicalDimensions service block - emitted whenever the
        // digital_object (or its derived metadata) exposes a physical
        // scale. We pull it from the digital_object_property table if
        // present, falling back to nothing rather than guessing.
        $physdim = $this->resolvePhysDim($digitalObject);
        if ($physdim) {
            $body['service'][] = [
                '@context' => 'http://iiif.io/api/annex/services/physdim/1/context.json',
                'profile' => 'http://iiif.io/api/annex/services/physdim',
                'type' => 'PhysicalDimensions',
                'physicalScale' => (float) $physdim['physicalScale'],
                'physicalUnits' => $physdim['physicalUnits'],
            ];
        }

        return [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => ['en' => [$label]],
            'width' => $width,
            'height' => $height,
            'items' => [[
                'id' => $pageId,
                'type' => 'AnnotationPage',
                'items' => [[
                    'id' => $annId,
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'body' => $body,
                    'target' => $canvasId,
                ]],
            ]],
        ];
    }

    /**
     * Resolve physical-dimensions metadata for a digital object.
     *
     * Lookup order:
     *   1. digital_object_property where name='physicalScale' or 'physical_scale'
     *   2. ahg_settings (iiif_default_physical_scale + iiif_default_physical_units)
     *   3. null - no scalebar emitted
     */
    private function resolvePhysDim($digitalObject): ?array
    {
        $scale = null;
        $units = null;

        try {
            if (\Schema::hasTable('digital_object_property')) {
                $props = DB::table('digital_object_property')
                    ->where('object_id', $digitalObject->id ?? 0)
                    ->whereIn('name', ['physicalScale', 'physical_scale', 'physicalUnits', 'physical_units'])
                    ->pluck('value', 'name');
                if (isset($props['physicalScale']) || isset($props['physical_scale'])) {
                    $scale = $props['physicalScale'] ?? $props['physical_scale'];
                }
                if (isset($props['physicalUnits']) || isset($props['physical_units'])) {
                    $units = $props['physicalUnits'] ?? $props['physical_units'];
                }
            }
        } catch (\Throwable $e) {
            // best-effort - no scalebar rather than crash the manifest
        }

        if (!$scale) {
            try {
                if (\Schema::hasTable('ahg_settings')) {
                    $rows = DB::table('ahg_settings')
                        ->whereIn('setting_key', ['iiif_default_physical_scale', 'iiif_default_physical_units'])
                        ->pluck('setting_value', 'setting_key');
                    if (!empty($rows['iiif_default_physical_scale'])) {
                        $scale = $rows['iiif_default_physical_scale'];
                        $units = $rows['iiif_default_physical_units'] ?? 'mm';
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        if ($scale === null || $scale === '' || !is_numeric($scale)) {
            return null;
        }
        return [
            'physicalScale' => (float) $scale,
            'physicalUnits' => $units ?: 'mm',
        ];
    }

    /**
     * Common loader for object + digital objects. Used by both v2 and
     * v3 manifest generators.
     */
    private function loadObjectAndDigitalObjects(string $slug): ?array
    {
        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('s.slug', $slug)
            ->select('io.id', 'io.identifier', 'i18n.title', 's.slug')
            ->first();

        if (!$object) {
            return null;
        }

        $digitalObjects = DB::table('digital_object as do')
            ->where('do.object_id', $object->id)
            ->orderBy('do.id')
            ->select('do.id', 'do.name', 'do.path', 'do.mime_type', 'do.byte_size')
            ->get();

        if ($digitalObjects->isEmpty()) {
            return null;
        }

        return ['object' => $object, 'digitalObjects' => $digitalObjects];
    }

    /**
     * Legacy IIIF Presentation API 2.1 manifest. Kept for any consumer
     * that explicitly requests ?version=2. New consumers should use
     * the default v3 output.
     */
    public function generateObjectManifestV2(string $slug): ?array
    {
        $ctx = $this->loadObjectAndDigitalObjects($slug);
        if (!$ctx) {
            return null;
        }
        $object = $ctx['object'];
        $digitalObjects = $ctx['digitalObjects'];

        $baseUrl = rtrim(config('app.url'), '/');
        $label = $object->title ?: $object->identifier ?: 'Untitled';
        $manifestId = $baseUrl . '/iiif-manifest/' . $object->slug;
        $cantaloupeBaseUrl = 'http://127.0.0.1:8182';

        $canvases = [];
        $canvasIndex = 1;

        foreach ($digitalObjects as $do) {
            $imagePath = ltrim($do->path, '/');
            $cantaloupeId = str_replace('/', '_SL_', $imagePath) . $do->name;
            $isMultiPageTiff = false;
            $pageCount = 1;
            $mimeType = strtolower($do->mime_type ?? '');
            $fileName = strtolower($do->name ?? '');

            if ($mimeType === 'image/tiff' || preg_match('/\.tiff?$/i', $fileName)) {
                $page2InfoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$cantaloupeId};2/info.json";
                $page2Info = @file_get_contents($page2InfoUrl);
                if ($page2Info !== false) {
                    $isMultiPageTiff = true;
                    $pageCount = 2;
                    for ($i = 3; $i <= 100; $i++) {
                        $pageInfoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$cantaloupeId};{$i}/info.json";
                        $ctx2 = stream_context_create(['http' => ['timeout' => 1]]);
                        $pageInfo = @file_get_contents($pageInfoUrl, false, $ctx2);
                        if ($pageInfo === false) {
                            break;
                        }
                        $pageCount = $i;
                    }
                }
            }

            $loopMax = $isMultiPageTiff ? $pageCount : 1;
            for ($p = 1; $p <= $loopMax; $p++) {
                $imageId = $isMultiPageTiff ? "{$cantaloupeId};{$p}" : $cantaloupeId;
                $imageApiBase = "{$baseUrl}/iiif/2/{$imageId}";
                $infoUrl = "{$cantaloupeBaseUrl}/iiif/2/{$imageId}/info.json";
                $infoJson = @file_get_contents($infoUrl);
                $width = 1000;
                $height = 1000;
                if ($infoJson) {
                    $info = json_decode($infoJson, true);
                    $width = $info['width'] ?? 1000;
                    $height = $info['height'] ?? 1000;
                }

                $canvasId = "{$manifestId}/canvas/{$canvasIndex}";
                $canvasLabel = $isMultiPageTiff
                    ? (($do->name ?: 'Image') . " - Page {$p}")
                    : ($do->name ?: "Image {$canvasIndex}");

                $canvases[] = [
                    '@type' => 'sc:Canvas',
                    '@id' => $canvasId,
                    'label' => $canvasLabel,
                    'width' => $width,
                    'height' => $height,
                    'images' => [[
                        '@type' => 'oa:Annotation',
                        'motivation' => 'sc:painting',
                        'resource' => [
                            '@id' => "{$imageApiBase}/full/full/0/default.jpg",
                            '@type' => 'dctypes:Image',
                            'format' => 'image/jpeg',
                            'width' => $width,
                            'height' => $height,
                            'service' => [
                                '@context' => 'http://iiif.io/api/image/2/context.json',
                                '@id' => $imageApiBase,
                                'profile' => 'http://iiif.io/api/image/2/level2.json',
                            ],
                        ],
                        'on' => $canvasId,
                    ]],
                ];
                $canvasIndex++;
            }
        }

        $manifest = [
            '@context' => 'http://iiif.io/api/presentation/2/context.json',
            '@type' => 'sc:Manifest',
            '@id' => $manifestId,
            'label' => $label,
            'metadata' => [],
            'sequences' => [[
                '@type' => 'sc:Sequence',
                '@id' => "{$manifestId}/sequence/normal",
                'label' => 'Normal Order',
                'canvases' => $canvases,
            ]],
        ];

        if ($object->identifier) {
            $manifest['metadata'][] = [
                'label' => 'Identifier',
                'value' => $object->identifier,
            ];
        }

        if (!empty($canvases)) {
            $firstCanvas = $canvases[0];
            $manifest['thumbnail'] = [
                '@id' => str_replace('/full/full/', '/full/200,/', $firstCanvas['images'][0]['resource']['@id']),
                'service' => $firstCanvas['images'][0]['resource']['service'],
            ];
        }

        // Issue #694 - advertise Content Search 2.0 + AutoComplete 2 so
        // Mirador's search box discovers the endpoints from the manifest.
        // Kept in a helper method so the in-flight Presentation 3 emitter
        // (#698) can call the same hook without re-implementing the block.
        self::appendSearchService($manifest, $object->slug);

        return $manifest;
    }

    /**
     * Attach the IIIF Content Search 2.0 service block (and its nested
     * AutoCompleteService2) to the supplied manifest array in-place. Safe
     * to call on either a Presentation 2 manifest (sc:Manifest, uses the
     * `service` key as an array) or a Presentation 3 manifest (Manifest,
     * uses `service`). The block delegate is owned by
     * IiifContentSearchService::buildServiceBlock() so the URL layout is
     * defined in one place.
     *
     * @param array<string,mixed> $manifest passed by reference
     */
    public static function appendSearchService(array &$manifest, string $slug): void
    {
        $svc = (new IiifContentSearchService())->buildServiceBlock($slug);
        if (!isset($manifest['service']) || !is_array($manifest['service'])) {
            $manifest['service'] = [];
        }
        foreach ($svc as $entry) {
            $manifest['service'][] = $entry;
        }
    }

    /**
     * Detect audio media. Mime types are authoritative when present;
     * the filename fallback covers digital_object rows imported before
     * mime sniffing was wired into the ingest pipeline.
     */
    private function isAudioMime(string $mime, string $fileName): bool
    {
        if (str_starts_with($mime, 'audio/')) {
            return true;
        }
        return (bool) preg_match('/\.(mp3|wav|ogg|oga|flac|m4a|aac|opus)$/i', $fileName);
    }

    /**
     * Detect video media (same fallback rules as isAudioMime()).
     */
    private function isVideoMime(string $mime, string $fileName): bool
    {
        if (str_starts_with($mime, 'video/')) {
            return true;
        }
        return (bool) preg_match('/\.(mp4|webm|mov|m4v|mkv|avi|ogv)$/i', $fileName);
    }

    /**
     * Build a Pres 3 Canvas for an audio or video digital object (#695).
     *
     * The painting body's `type` field is Sound for audio, Video for
     * video. Canvas `duration` is in seconds; we read it from
     * digital_object_property when populated, fall back to a sensible
     * default rather than crashing the manifest. The MediaFragmentSelector
     * service block tells consumers they can request `#t=start,end`
     * ranges - that is the W3C-spec way to address a temporal slice on
     * a IIIF A/V canvas, and Mirador 4 / UV 4 honour it.
     *
     * Poster frame: digital_object_property name='poster_url' takes
     * precedence; absent that, we leave the thumbnail empty rather than
     * fabricate one.
     */
    private function buildAvCanvasV3(
        string $manifestId,
        string $baseUrl,
        int $canvasIndex,
        $digitalObject,
        string $type
    ): array {
        $imagePath = ltrim($digitalObject->path ?? '', '/');
        $mediaUrl = $baseUrl . '/' . $imagePath . $digitalObject->name;
        $format = $digitalObject->mime_type ?: ($type === 'Video' ? 'video/mp4' : 'audio/mpeg');

        // Look up duration + poster, best-effort. digital_object_property
        // is the canonical sidecar table for AV metadata in Heratio.
        $duration = 0.0;
        $posterUrl = null;
        try {
            if (\Schema::hasTable('digital_object_property')) {
                $props = DB::table('digital_object_property')
                    ->where('object_id', $digitalObject->id ?? 0)
                    ->whereIn('name', ['duration', 'duration_seconds', 'poster_url', 'poster_frame'])
                    ->pluck('value', 'name');
                if (!empty($props['duration'])) {
                    $duration = (float) $props['duration'];
                } elseif (!empty($props['duration_seconds'])) {
                    $duration = (float) $props['duration_seconds'];
                }
                if (!empty($props['poster_url'])) {
                    $posterUrl = (string) $props['poster_url'];
                } elseif (!empty($props['poster_frame'])) {
                    $posterUrl = (string) $props['poster_frame'];
                }
            }
        } catch (\Throwable $e) {
            // best-effort - emit a canvas with duration=0 rather than 500
        }
        if ($duration <= 0) {
            // Pres 3 requires duration on a temporal Canvas. We emit a
            // placeholder of 1.0s so the manifest still validates;
            // operators should populate digital_object_property.duration
            // during ingest for accurate ranges.
            $duration = 1.0;
        }

        $canvasId = "{$manifestId}/canvas/{$canvasIndex}";
        $pageId = "{$canvasId}/page/1";
        $annId = "{$canvasId}/annotation/1";

        $body = [
            'id' => $mediaUrl,
            'type' => $type,
            'format' => $format,
            'duration' => $duration,
            'service' => [[
                '@context' => 'http://www.w3.org/ns/anno.jsonld',
                'type' => 'MediaFragmentSelector',
                'conformsTo' => 'http://www.w3.org/TR/media-frags/',
            ]],
        ];
        if ($type === 'Video') {
            // Video canvases also carry width/height. We don't probe the
            // actual stream (no ffprobe round-trip in the manifest hot
            // path); operators populate it via digital_object_property
            // when accuracy matters. 1920x1080 is the spec-validator-safe
            // default for an unknown video.
            $w = 1920;
            $h = 1080;
            try {
                if (\Schema::hasTable('digital_object_property')) {
                    $dims = DB::table('digital_object_property')
                        ->where('object_id', $digitalObject->id ?? 0)
                        ->whereIn('name', ['width', 'height'])
                        ->pluck('value', 'name');
                    if (!empty($dims['width'])) {
                        $w = (int) $dims['width'];
                    }
                    if (!empty($dims['height'])) {
                        $h = (int) $dims['height'];
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }
            $body['width'] = $w;
            $body['height'] = $h;
        }

        $canvas = [
            'id' => $canvasId,
            'type' => 'Canvas',
            'label' => ['en' => [$digitalObject->name ?: "Track {$canvasIndex}"]],
            'duration' => $duration,
            'items' => [[
                'id' => $pageId,
                'type' => 'AnnotationPage',
                'items' => [[
                    'id' => $annId,
                    'type' => 'Annotation',
                    'motivation' => 'painting',
                    'body' => $body,
                    'target' => $canvasId,
                ]],
            ]],
        ];
        if ($type === 'Video') {
            $canvas['width'] = $body['width'];
            $canvas['height'] = $body['height'];
        }
        if ($posterUrl) {
            $canvas['thumbnail'] = [[
                'id' => $posterUrl,
                'type' => 'Image',
                'format' => 'image/jpeg',
            ]];
            // Place the poster frame at the natural start of the timeline.
            // Mirador 4 reads `placeholderCanvas` for the still that appears
            // before the user hits play.
            $canvas['placeholderCanvas'] = [
                'id' => $canvasId . '/placeholder',
                'type' => 'Canvas',
                'label' => ['en' => ['Poster frame']],
                'items' => [[
                    'id' => $canvasId . '/placeholder/page/1',
                    'type' => 'AnnotationPage',
                    'items' => [[
                        'id' => $canvasId . '/placeholder/annotation/1',
                        'type' => 'Annotation',
                        'motivation' => 'painting',
                        'body' => [
                            'id' => $posterUrl,
                            'type' => 'Image',
                            'format' => 'image/jpeg',
                        ],
                        'target' => $canvasId . '/placeholder',
                    ]],
                ]],
            ];
        }

        return $canvas;
    }

    /**
     * Generate unique slug.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $baseSlug = $slug;
        $counter = 1;

        while (DB::table('iiif_collection')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
