<?php

namespace AhgResearch\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Researcher offline packages (Phase 1–2).
 *
 * Resolves one of a researcher's *groups* (research project / collection /
 * workspace / favourites folder) to the set of catalogue records it contains,
 * then creates an editable portable-export bundle scoped to exactly those
 * records. The existing portable-export worker + per-user ACL / disclosure gate
 * do the heavy lifting, so a researcher can only ever take offline what they are
 * permitted to see. The bundle carries a sync_token so the changes the
 * researcher makes offline can be verified when they sync back (Phase 3).
 */
class ResearchOfflineService
{
    /** Valid group sources. */
    public const SOURCES = ['project', 'collection', 'workspace', 'favorites'];

    /**
     * Resolve a group to the slugs it contains, enforcing ownership. Returns
     * null when the group does not exist or is not owned by this researcher.
     *
     * @return array{title:string,slugs:string[]}|null
     */
    public function resolveGroup(string $source, int $refId, object $researcher, int $userId): ?array
    {
        switch ($source) {
            case 'project':     return $this->fromProject($refId, $researcher);
            case 'collection':  return $this->fromCollection($refId, $researcher);
            case 'workspace':   return $this->fromWorkspace($refId, $researcher);
            case 'favorites':   return $this->fromFavorites($refId, $userId);
            default:            return null;
        }
    }

    private function fromProject(int $id, object $researcher): ?array
    {
        $project = DB::table('research_project')->where('id', $id)->first();
        if (! $project || (int) $project->owner_id !== (int) $researcher->id) {
            return null;
        }

        // research_project_resource holds catalogue records as resource_type
        // 'object'/'archive_record'; object_id (fallback resource_id) is the IO id.
        $ioIds = DB::table('research_project_resource')
            ->where('project_id', $id)
            ->whereIn('resource_type', ['object', 'archive_record'])
            ->get()
            ->map(fn ($r) => (int) ($r->object_id ?: $r->resource_id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['title' => (string) $project->title, 'slugs' => $this->idsToSlugs($ioIds)];
    }

    private function fromCollection(int $id, object $researcher): ?array
    {
        $collection = DB::table('research_collection')->where('id', $id)->first();
        if (! $collection || (int) $collection->researcher_id !== (int) $researcher->id) {
            return null;
        }

        $ioIds = DB::table('research_collection_item')
            ->where('collection_id', $id)
            ->where('object_type', 'information_object')
            ->pluck('object_id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['title' => (string) $collection->name, 'slugs' => $this->idsToSlugs($ioIds)];
    }

    private function fromWorkspace(int $id, object $researcher): ?array
    {
        $workspace = DB::table('research_workspace')->where('id', $id)->first();
        if (! $workspace || (int) $workspace->owner_id !== (int) $researcher->id) {
            return null;
        }

        // resource_type 'object' -> resource_id is the IO id. 'collection' rows
        // point at a nested research_collection; expand those to their items too.
        $rows = DB::table('research_workspace_resource')->where('workspace_id', $id)->get();

        $ioIds = [];
        $collectionIds = [];
        foreach ($rows as $r) {
            if ($r->resource_type === 'object' && $r->resource_id) {
                $ioIds[] = (int) $r->resource_id;
            } elseif ($r->resource_type === 'collection' && $r->resource_id) {
                $collectionIds[] = (int) $r->resource_id;
            }
        }
        if ($collectionIds) {
            $ioIds = array_merge($ioIds, DB::table('research_collection_item')
                ->whereIn('collection_id', $collectionIds)
                ->where('object_type', 'information_object')
                ->pluck('object_id')->map(fn ($v) => (int) $v)->all());
        }

        $ioIds = array_values(array_unique(array_filter($ioIds)));

        return ['title' => (string) $workspace->name, 'slugs' => $this->idsToSlugs($ioIds)];
    }

    private function fromFavorites(int $folderId, int $userId): ?array
    {
        $folder = DB::table('favorites_folder')->where('id', $folderId)->first();
        if (! $folder || (int) $folder->user_id !== $userId) {
            return null;
        }

        $ioIds = DB::table('favorites')
            ->where('folder_id', $folderId)
            ->where('user_id', $userId)
            ->where('object_type', 'information_object')
            ->pluck('archival_description_id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return ['title' => (string) $folder->name, 'slugs' => $this->idsToSlugs($ioIds)];
    }

    /** Map information-object ids to their public slugs (the worker's clipboard scope keys on slugs). */
    private function idsToSlugs(array $ioIds): array
    {
        if (empty($ioIds)) {
            return [];
        }

        return DB::table('slug')
            ->whereIn('object_id', $ioIds)
            ->pluck('slug')
            ->map(fn ($v) => (string) $v)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Create an editable offline package for a resolved group and queue the
     * bundler. Returns the portable_export id.
     */
    public function createPackage(
        object $researcher,
        int $userId,
        string $source,
        int $refId,
        string $groupTitle,
        array $slugs
    ): int {
        $title = trim($groupTitle) !== '' ? $groupTitle : ucfirst($source).' package';
        $researcherName = trim(($researcher->first_name ?? '').' '.($researcher->last_name ?? '')) ?: 'Researcher';

        $id = DB::table('portable_export')->insertGetId([
            'user_id' => $userId,
            'researcher_user_id' => $userId,
            'group_source' => $source,
            'group_ref' => $refId,
            'sync_token' => bin2hex(random_bytes(16)),
            'title' => $title,
            'scope_type' => 'clipboard',
            'scope_items' => json_encode(['items' => array_values($slugs)]),
            'mode' => 'editable',
            'destination' => 'zip',
            'culture' => app()->getLocale(),
            'include_objects' => 1,
            'include_thumbnails' => 1,
            'include_references' => 1,
            'include_masters' => 0,
            'branding' => json_encode([
                'title' => $title,
                'subtitle' => 'Offline research package',
                'footer' => 'Prepared for '.$researcherName.'. Work offline, then use "Save for sync" to bring your changes back.',
            ]),
            'status' => 'pending',
            'progress' => 0,
            'created_at' => now(),
        ]);

        try {
            Artisan::queue('ahg:portable-export-worker', ['--id' => $id]);
        } catch (\Throwable $e) {
            Log::warning('research offline: could not queue worker: '.$e->getMessage(), ['export_id' => $id]);
        }

        return $id;
    }

    /** The researcher's own offline packages, newest first. */
    public function listForUser(int $userId, int $limit = 30)
    {
        return DB::table('portable_export')
            ->where('researcher_user_id', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** Fetch a package owned by this user, or null. */
    public function ownedPackage(int $id, int $userId): ?object
    {
        return DB::table('portable_export')
            ->where('id', $id)
            ->where('researcher_user_id', $userId)
            ->first();
    }
}
