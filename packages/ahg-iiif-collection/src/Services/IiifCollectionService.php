<?php

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
     * Generate IIIF Collection JSON (Presentation API 3.0).
     */
    public function generateCollectionJson(int $collectionId): array
    {
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
                    $manifestUri = $baseUrl . '/iiif-manifest.php?slug=' . $item->slug;
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
