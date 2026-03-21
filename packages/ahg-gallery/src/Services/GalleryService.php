<?php

namespace AhgGallery\Services;

use AhgCore\Services\SettingHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GalleryService
{
    /**
     * Get a single gallery artwork by its slug.
     * Joins information_object + i18n + slug + museum_metadata + display_object_config (object_type='gallery').
     */
    public function getBySlug(string $slug, string $culture = 'en'): ?object
    {
        $artwork = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->join('object as obj', 'io.id', '=', 'obj.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->join('display_object_config as doc', function ($j) {
                $j->on('io.id', '=', 'doc.object_id')->where('doc.object_type', '=', 'gallery');
            })
            ->leftJoin('museum_metadata as mm', 'io.id', '=', 'mm.object_id')
            ->where('slug.slug', $slug)
            ->select([
                'io.id',
                'io.identifier',
                'io.level_of_description_id',
                'io.repository_id',
                'io.parent_id',
                'io.lft',
                'io.rgt',
                'io.description_status_id',
                'io.description_detail_id',
                'io.description_identifier',
                'io.source_standard',
                'io.display_standard_id',
                'io.collection_type_id',
                'io.source_culture',
                'i18n.title',
                'i18n.alternate_title',
                'i18n.extent_and_medium',
                'i18n.scope_and_content',
                'i18n.archival_history',
                'i18n.acquisition',
                'i18n.access_conditions',
                'i18n.reproduction_conditions',
                'i18n.physical_characteristics',
                'i18n.arrangement',
                'i18n.appraisal',
                'i18n.accruals',
                'i18n.finding_aids',
                'i18n.location_of_originals',
                'i18n.location_of_copies',
                'i18n.related_units_of_description',
                'i18n.rules',
                'i18n.sources',
                'i18n.revision_history',
                'i18n.institution_responsible_identifier',
                'obj.created_at',
                'obj.updated_at',
                'slug.slug',
                // Museum/Gallery CCO metadata
                'mm.id as metadata_id',
                'mm.object_id as mm_object_id',
                'mm.work_type',
                'mm.classification',
                'mm.creator_identity',
                'mm.creator_role',
                'mm.creation_date_display',
                'mm.creation_date_earliest',
                'mm.creation_date_latest',
                'mm.creation_place',
                'mm.style',
                'mm.period',
                'mm.movement',
                'mm.school',
                'mm.measurements',
                'mm.dimensions',
                'mm.materials',
                'mm.techniques',
                'mm.inscription',
                'mm.mark_description',
                'mm.condition_term',
                'mm.condition_description',
                'mm.provenance',
                'mm.current_location',
                'mm.rights_type',
                'mm.rights_holder',
                'mm.cataloger_name',
                'mm.cataloging_date',
            ])
            ->first();

        return $artwork;
    }

    /**
     * Browse gallery artworks with pagination.
     */
    public function browse(array $params, string $culture = 'en'): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? SettingHelper::hitsPerPage()));
        $sort = $params['sort'] ?? 'alphabetic';
        $subquery = $params['subquery'] ?? '';

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->join('object as obj', 'io.id', '=', 'obj.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->join('display_object_config as doc', function ($j) {
                $j->on('io.id', '=', 'doc.object_id')->where('doc.object_type', '=', 'gallery');
            })
            ->leftJoin('museum_metadata as mm', 'io.id', '=', 'mm.object_id');

        // Search filter
        if ($subquery !== '') {
            $like = '%' . $subquery . '%';
            $query->where(function ($q) use ($like) {
                $q->where('i18n.title', 'like', $like)
                    ->orWhere('io.identifier', 'like', $like)
                    ->orWhere('mm.creator_identity', 'like', $like)
                    ->orWhere('mm.materials', 'like', $like)
                    ->orWhere('mm.techniques', 'like', $like)
                    ->orWhere('mm.classification', 'like', $like)
                    ->orWhere('mm.work_type', 'like', $like);
            });
        }

        // Repository filter
        if (!empty($params['filters']['repository_id'])) {
            $query->where('io.repository_id', $params['filters']['repository_id']);
        }

        // Count
        $total = $query->count();

        // Sort
        switch ($sort) {
            case 'lastUpdated':
                $query->orderBy('obj.updated_at', 'desc');
                break;
            case 'identifier':
                $query->orderBy('io.identifier', 'asc');
                break;
            case 'artist':
                $query->orderBy('mm.creator_identity', 'asc');
                break;
            default: // alphabetic
                $query->orderBy('i18n.title', 'asc');
                break;
        }

        $offset = ($page - 1) * $limit;
        $rows = $query->select([
                'io.id',
                'io.identifier',
                'io.repository_id',
                'io.level_of_description_id',
                'i18n.title as name',
                'slug.slug',
                'obj.updated_at',
                'mm.creator_identity',
                'mm.work_type',
                'mm.materials',
                'mm.techniques',
                'mm.creation_date_display',
            ])
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Resolve repository names
        $repoIds = $rows->pluck('repository_id')->filter()->unique()->values()->toArray();
        $repositoryNames = [];
        if (!empty($repoIds)) {
            $repositoryNames = DB::table('repository')
                ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
                ->whereIn('repository.id', $repoIds)
                ->where('actor_i18n.culture', $culture)
                ->pluck('actor_i18n.authorized_form_of_name', 'repository.id')
                ->toArray();
        }

        // Fetch thumbnails for each artwork
        $objectIds = $rows->pluck('id')->toArray();
        $thumbnails = [];
        if (!empty($objectIds)) {
            $thumbRows = DB::table('digital_object')
                ->whereIn('object_id', $objectIds)
                ->select('object_id', 'path', 'name', 'mime_type')
                ->get();
            foreach ($thumbRows as $tr) {
                $thumbnails[$tr->object_id] = $tr;
            }
        }

        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => $row->id,
                'identifier' => $row->identifier,
                'name' => $row->name,
                'slug' => $row->slug,
                'updated_at' => $row->updated_at,
                'repository_id' => $row->repository_id,
                'creator_identity' => $row->creator_identity,
                'work_type' => $row->work_type,
                'materials' => $row->materials,
                'techniques' => $row->techniques,
                'creation_date_display' => $row->creation_date_display,
                'thumbnail' => $thumbnails[$row->id] ?? null,
            ];
        }

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'repositoryNames' => $repositoryNames,
        ];
    }

    /**
     * Create a new gallery artwork: IO + i18n + museum_metadata + display_object_config + slug.
     */
    public function create(array $data, string $culture = 'en'): string
    {
        return DB::transaction(function () use ($data, $culture) {
            $parentId = $data['parent_id'] ?? 1;

            // Determine lft/rgt position
            $parent = DB::table('information_object')
                ->where('id', $parentId)
                ->select('lft', 'rgt')
                ->first();

            if (!$parent) {
                abort(422, 'Invalid parent information object.');
            }

            $newLft = $parent->rgt;
            $newRgt = $parent->rgt + 1;

            // Shift nested set values
            DB::table('information_object')
                ->where('rgt', '>=', $parent->rgt)
                ->increment('rgt', 2);

            DB::table('information_object')
                ->where('lft', '>', $parent->rgt)
                ->increment('lft', 2);

            // Insert into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert information_object
            DB::table('information_object')->insert([
                'id' => $objectId,
                'identifier' => $data['identifier'] ?? null,
                'level_of_description_id' => !empty($data['level_of_description_id']) ? $data['level_of_description_id'] : null,
                'collection_type_id' => null,
                'repository_id' => !empty($data['repository_id']) ? $data['repository_id'] : null,
                'parent_id' => $parentId,
                'description_status_id' => !empty($data['description_status_id']) ? $data['description_status_id'] : null,
                'description_detail_id' => !empty($data['description_detail_id']) ? $data['description_detail_id'] : null,
                'description_identifier' => $data['description_identifier'] ?? null,
                'source_standard' => $data['source_standard'] ?? null,
                'display_standard_id' => !empty($data['display_standard_id']) ? $data['display_standard_id'] : null,
                'lft' => $newLft,
                'rgt' => $newRgt,
                'source_culture' => $culture,
            ]);

            // Insert information_object_i18n
            DB::table('information_object_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'title' => $data['title'],
                'alternate_title' => $data['alternate_title'] ?? null,
                'extent_and_medium' => $data['extent_and_medium'] ?? null,
                'scope_and_content' => $data['scope_and_content'] ?? null,
                'archival_history' => $data['archival_history'] ?? null,
                'acquisition' => $data['acquisition'] ?? null,
                'access_conditions' => $data['access_conditions'] ?? null,
                'reproduction_conditions' => $data['reproduction_conditions'] ?? null,
                'physical_characteristics' => $data['physical_characteristics'] ?? null,
                'arrangement' => $data['arrangement'] ?? null,
                'appraisal' => $data['appraisal'] ?? null,
                'accruals' => $data['accruals'] ?? null,
                'finding_aids' => $data['finding_aids'] ?? null,
                'location_of_originals' => $data['location_of_originals'] ?? null,
                'location_of_copies' => $data['location_of_copies'] ?? null,
                'related_units_of_description' => $data['related_units_of_description'] ?? null,
                'rules' => $data['rules'] ?? null,
                'sources' => $data['sources'] ?? null,
                'revision_history' => $data['revision_history'] ?? null,
                'institution_responsible_identifier' => $data['institution_responsible_identifier'] ?? null,
            ]);

            // Insert display_object_config for gallery type
            DB::table('display_object_config')->insert([
                'object_id' => $objectId,
                'object_type' => 'gallery',
            ]);

            // Insert museum_metadata (shared CCO fields)
            DB::table('museum_metadata')->insert([
                'object_id' => $objectId,
                'work_type' => $data['work_type'] ?? null,
                'classification' => $data['classification'] ?? null,
                'creator_identity' => $data['creator_identity'] ?? null,
                'creator_role' => $data['creator_role'] ?? null,
                'creation_date_display' => $data['creation_date_display'] ?? null,
                'creation_date_earliest' => $data['creation_date_earliest'] ?? null,
                'creation_date_latest' => $data['creation_date_latest'] ?? null,
                'creation_place' => $data['creation_place'] ?? null,
                'style' => $data['style'] ?? null,
                'period' => $data['period'] ?? null,
                'movement' => $data['movement'] ?? null,
                'school' => $data['school'] ?? null,
                'measurements' => $data['measurements'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'materials' => $data['materials'] ?? null,
                'techniques' => $data['techniques'] ?? null,
                'inscription' => $data['inscription'] ?? null,
                'mark_description' => $data['mark_description'] ?? null,
                'condition_term' => $data['condition_term'] ?? null,
                'condition_description' => $data['condition_description'] ?? null,
                'provenance' => $data['provenance'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'rights_type' => $data['rights_type'] ?? null,
                'rights_holder' => $data['rights_holder'] ?? null,
                'cataloger_name' => $data['cataloger_name'] ?? null,
                'cataloging_date' => $data['cataloging_date'] ?? null,
            ]);

            // Generate slug
            $baseSlug = Str::slug($data['title'] ?: 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            DB::table('slug')->insert([
                'object_id' => $objectId,
                'slug' => $slug,
            ]);

            // Set publication status (draft by default: 159)
            DB::table('status')->insert([
                'object_id' => $objectId,
                'type_id' => 158,
                'status_id' => 159,
            ]);

            return $slug;
        });
    }

    /**
     * Update a gallery artwork.
     */
    public function update(string $slug, array $data, string $culture = 'en'): void
    {
        DB::transaction(function () use ($slug, $data, $culture) {
            $io = DB::table('slug')
                ->join('information_object', 'slug.object_id', '=', 'information_object.id')
                ->where('slug.slug', $slug)
                ->select('information_object.id')
                ->first();

            if (!$io) {
                abort(404);
            }

            $ioId = $io->id;

            // Update information_object
            DB::table('information_object')
                ->where('id', $ioId)
                ->update([
                    'identifier' => $data['identifier'] ?? null,
                    'level_of_description_id' => !empty($data['level_of_description_id']) ? $data['level_of_description_id'] : null,
                    'repository_id' => !empty($data['repository_id']) ? $data['repository_id'] : null,
                    'description_status_id' => !empty($data['description_status_id']) ? $data['description_status_id'] : null,
                    'description_detail_id' => !empty($data['description_detail_id']) ? $data['description_detail_id'] : null,
                    'description_identifier' => $data['description_identifier'] ?? null,
                    'source_standard' => $data['source_standard'] ?? null,
                    'display_standard_id' => !empty($data['display_standard_id']) ? $data['display_standard_id'] : null,
                ]);

            // Update information_object_i18n
            DB::table('information_object_i18n')
                ->where('id', $ioId)
                ->where('culture', $culture)
                ->update([
                    'title' => $data['title'],
                    'alternate_title' => $data['alternate_title'] ?? null,
                    'extent_and_medium' => $data['extent_and_medium'] ?? null,
                    'scope_and_content' => $data['scope_and_content'] ?? null,
                    'archival_history' => $data['archival_history'] ?? null,
                    'acquisition' => $data['acquisition'] ?? null,
                    'access_conditions' => $data['access_conditions'] ?? null,
                    'reproduction_conditions' => $data['reproduction_conditions'] ?? null,
                    'physical_characteristics' => $data['physical_characteristics'] ?? null,
                    'arrangement' => $data['arrangement'] ?? null,
                    'appraisal' => $data['appraisal'] ?? null,
                    'accruals' => $data['accruals'] ?? null,
                    'finding_aids' => $data['finding_aids'] ?? null,
                    'location_of_originals' => $data['location_of_originals'] ?? null,
                    'location_of_copies' => $data['location_of_copies'] ?? null,
                    'related_units_of_description' => $data['related_units_of_description'] ?? null,
                    'rules' => $data['rules'] ?? null,
                    'sources' => $data['sources'] ?? null,
                    'revision_history' => $data['revision_history'] ?? null,
                    'institution_responsible_identifier' => $data['institution_responsible_identifier'] ?? null,
                ]);

            // Update museum_metadata
            $metadataExists = DB::table('museum_metadata')->where('object_id', $ioId)->exists();
            $metaFields = [
                'work_type' => $data['work_type'] ?? null,
                'classification' => $data['classification'] ?? null,
                'creator_identity' => $data['creator_identity'] ?? null,
                'creator_role' => $data['creator_role'] ?? null,
                'creation_date_display' => $data['creation_date_display'] ?? null,
                'creation_date_earliest' => $data['creation_date_earliest'] ?? null,
                'creation_date_latest' => $data['creation_date_latest'] ?? null,
                'creation_place' => $data['creation_place'] ?? null,
                'style' => $data['style'] ?? null,
                'period' => $data['period'] ?? null,
                'movement' => $data['movement'] ?? null,
                'school' => $data['school'] ?? null,
                'measurements' => $data['measurements'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'materials' => $data['materials'] ?? null,
                'techniques' => $data['techniques'] ?? null,
                'inscription' => $data['inscription'] ?? null,
                'mark_description' => $data['mark_description'] ?? null,
                'condition_term' => $data['condition_term'] ?? null,
                'condition_description' => $data['condition_description'] ?? null,
                'provenance' => $data['provenance'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'rights_type' => $data['rights_type'] ?? null,
                'rights_holder' => $data['rights_holder'] ?? null,
                'cataloger_name' => $data['cataloger_name'] ?? null,
                'cataloging_date' => $data['cataloging_date'] ?? null,
            ];

            if ($metadataExists) {
                DB::table('museum_metadata')->where('object_id', $ioId)->update($metaFields);
            } else {
                DB::table('museum_metadata')->insert(array_merge(['object_id' => $ioId], $metaFields));
            }

            // Update object.updated_at
            DB::table('object')->where('id', $ioId)->update(['updated_at' => now()]);
        });
    }

    /**
     * Delete a gallery artwork.
     */
    public function delete(string $slug): void
    {
        DB::transaction(function () use ($slug) {
            $record = DB::table('slug')
                ->join('information_object', 'slug.object_id', '=', 'information_object.id')
                ->where('slug.slug', $slug)
                ->select('information_object.id', 'information_object.lft', 'information_object.rgt')
                ->first();

            if (!$record) {
                abort(404);
            }

            $ioId = $record->id;
            $width = $record->rgt - $record->lft + 1;

            // Collect all descendant IDs
            $descendantIds = DB::table('information_object')
                ->whereBetween('lft', [$record->lft, $record->rgt])
                ->pluck('id')
                ->toArray();

            // Delete museum_metadata for all descendants
            DB::table('museum_metadata')->whereIn('object_id', $descendantIds)->delete();

            // Delete display_object_config for all descendants
            DB::table('display_object_config')->whereIn('object_id', $descendantIds)->delete();

            // Delete status rows
            DB::table('status')->whereIn('object_id', $descendantIds)->delete();

            // Delete i18n rows
            DB::table('information_object_i18n')->whereIn('id', $descendantIds)->delete();

            // Delete information_object rows
            DB::table('information_object')->whereIn('id', $descendantIds)->delete();

            // Delete slug rows
            DB::table('slug')->whereIn('object_id', $descendantIds)->delete();

            // Delete object rows
            DB::table('object')->whereIn('id', $descendantIds)->delete();

            // Close the gap in the nested set
            DB::table('information_object')
                ->where('lft', '>', $record->rgt)
                ->decrement('lft', $width);

            DB::table('information_object')
                ->where('rgt', '>', $record->rgt)
                ->decrement('rgt', $width);
        });
    }

    /**
     * Get all gallery artists.
     */
    public function getArtists(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, (int) ($params['limit'] ?? SettingHelper::hitsPerPage()));
        $sort = $params['sort'] ?? 'alphabetic';
        $subquery = $params['subquery'] ?? '';

        $query = DB::table('gallery_artist');

        if ($subquery !== '') {
            $like = '%' . $subquery . '%';
            $query->where(function ($q) use ($like) {
                $q->where('display_name', 'like', $like)
                    ->orWhere('nationality', 'like', $like)
                    ->orWhere('medium_specialty', 'like', $like)
                    ->orWhere('movement_style', 'like', $like)
                    ->orWhere('biography', 'like', $like);
            });
        }

        // Only active by default
        if (!isset($params['include_inactive'])) {
            $query->where('is_active', 1);
        }

        $total = $query->count();

        switch ($sort) {
            case 'lastUpdated':
                $query->orderBy('updated_at', 'desc');
                break;
            case 'nationality':
                $query->orderBy('nationality', 'asc')->orderBy('sort_name', 'asc');
                break;
            default: // alphabetic
                $query->orderBy('sort_name', 'asc');
                break;
        }

        $offset = ($page - 1) * $limit;
        $rows = $query->offset($offset)->limit($limit)->get();

        $results = [];
        foreach ($rows as $row) {
            $results[] = (array) $row;
        }

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Get a single artist with related exhibitions and bibliography.
     */
    public function getArtist(int $id): ?object
    {
        $artist = DB::table('gallery_artist')->where('id', $id)->first();

        if (!$artist) {
            return null;
        }

        // Get artworks by this artist (via museum_metadata.creator_identity matching display_name)
        $artist->artworks = DB::table('museum_metadata as mm')
            ->join('display_object_config as doc', function ($j) {
                $j->on('mm.object_id', '=', 'doc.object_id')->where('doc.object_type', '=', 'gallery');
            })
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('mm.object_id', '=', 'i18n.id')->where('i18n.culture', '=', app()->getLocale());
            })
            ->join('slug', 'mm.object_id', '=', 'slug.object_id')
            ->where('mm.creator_identity', 'like', '%' . $artist->display_name . '%')
            ->select('mm.object_id as id', 'i18n.title', 'slug.slug', 'mm.work_type', 'mm.creation_date_display', 'mm.materials')
            ->get();

        // If artist has an actor_id, get related information from actor_i18n
        if ($artist->actor_id) {
            $culture = app()->getLocale();
            $actorInfo = DB::table('actor_i18n')
                ->where('id', $artist->actor_id)
                ->where('culture', $culture)
                ->select('history', 'places', 'legal_status', 'functions', 'mandates', 'internal_structures', 'general_context')
                ->first();
            $artist->actor_info = $actorInfo;
        }

        return $artist;
    }

    /**
     * Create a new gallery artist.
     */
    public function createArtist(array $data): int
    {
        return DB::table('gallery_artist')->insertGetId([
            'actor_id' => !empty($data['actor_id']) ? $data['actor_id'] : null,
            'display_name' => $data['display_name'],
            'sort_name' => $data['sort_name'] ?? $data['display_name'],
            'birth_date' => $data['birth_date'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'death_date' => $data['death_date'] ?? null,
            'death_place' => $data['death_place'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'artist_type' => $data['artist_type'] ?? null,
            'medium_specialty' => $data['medium_specialty'] ?? null,
            'movement_style' => $data['movement_style'] ?? null,
            'active_period' => $data['active_period'] ?? null,
            'represented' => $data['represented'] ?? null,
            'biography' => $data['biography'] ?? null,
            'artist_statement' => $data['artist_statement'] ?? null,
            'cv' => $data['cv'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'website' => $data['website'] ?? null,
            'studio_address' => $data['studio_address'] ?? null,
            'instagram' => $data['instagram'] ?? null,
            'twitter' => $data['twitter'] ?? null,
            'facebook' => $data['facebook'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get dropdown choices for gallery forms.
     */
    public function getFormChoices(string $culture = 'en'): array
    {
        // Level of description options (taxonomy_id = 34)
        $levels = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Repositories
        $repositories = DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.culture', $culture)
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->get();

        // Work types for gallery
        $workTypes = [
            'Painting',
            'Sculpture',
            'Drawing',
            'Print',
            'Photograph',
            'Mixed Media',
            'Installation',
            'Video Art',
            'Performance',
            'Other',
        ];

        // Creator roles for gallery
        $creatorRoles = [
            'Artist',
            'Collaborator',
            'Workshop',
            'Studio',
            'School of',
            'Circle of',
            'Follower of',
            'Attributed to',
            'After',
            'Unknown',
        ];

        // Artist types
        $artistTypes = [
            'Painter',
            'Sculptor',
            'Printmaker',
            'Photographer',
            'Mixed Media Artist',
            'Installation Artist',
            'Video Artist',
            'Performance Artist',
            'Ceramicist',
            'Textile Artist',
            'Digital Artist',
            'Other',
        ];

        return compact('levels', 'repositories', 'workTypes', 'creatorRoles', 'artistTypes');
    }

    /**
     * Get extra data needed for edit form: physical location, admin area.
     */
    public function getEditExtras(?int $objectId, string $culture): array
    {
        // Physical objects for storage container dropdown
        $physicalObjects = [];
        try {
            $poResult = DB::table('physical_object as po')
                ->leftJoin('physical_object_i18n as poi', function ($join) use ($culture) {
                    $join->on('poi.id', '=', 'po.id')->where('poi.culture', '=', $culture);
                })
                ->select(['po.id', 'poi.name', 'poi.location'])
                ->orderBy('poi.name')
                ->get();
            foreach ($poResult as $po) {
                $physicalObjects[$po->id] = $po->name . ($po->location ? ' (' . $po->location . ')' : '');
            }
        } catch (\Exception $e) {
            // Table may not exist
        }

        // Item location data
        $itemLocation = [];
        if ($objectId) {
            try {
                $loc = DB::table('item_physical_location')->where('object_id', $objectId)->first();
                if ($loc) {
                    $itemLocation = (array) $loc;
                }
            } catch (\Exception $e) {
                // Table may not exist
            }
        }

        // Display standards
        $displayStandards = [];
        try {
            $terms = DB::table('term')
                ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', 53)
                ->where('term_i18n.culture', $culture)
                ->orderBy('term_i18n.name')
                ->select('term.id', 'term_i18n.name')
                ->get();
            foreach ($terms as $t) {
                $displayStandards[$t->id] = $t->name;
            }
        } catch (\Exception $e) {
            // Taxonomy may not exist
        }

        // Current display standard
        $currentDisplayStandard = null;
        if ($objectId) {
            $currentDisplayStandard = DB::table('information_object')
                ->where('id', $objectId)
                ->value('display_standard_id');
        }

        // Source culture
        $sourceCulture = 'English';
        if ($objectId) {
            $sc = DB::table('information_object')->where('id', $objectId)->value('source_culture');
            if ($sc) {
                $sourceCulture = locale_get_display_language($sc, 'en') ?: $sc;
            }
        }

        return compact(
            'physicalObjects',
            'itemLocation',
            'displayStandards',
            'currentDisplayStandard',
            'sourceCulture'
        );
    }
}
