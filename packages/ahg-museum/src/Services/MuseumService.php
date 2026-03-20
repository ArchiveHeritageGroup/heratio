<?php

namespace AhgMuseum\Services;

use AhgCore\Services\SettingHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MuseumService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Get a museum object by its slug, joining information_object + i18n + slug + museum_metadata + display_object_config.
     */
    public function getBySlug(string $slug): ?object
    {
        $record = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('object', 'information_object.id', '=', 'object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->join('museum_metadata', 'information_object.id', '=', 'museum_metadata.object_id')
            ->leftJoin('display_object_config', function ($join) {
                $join->on('information_object.id', '=', 'display_object_config.object_id')
                    ->where('display_object_config.object_type', '=', 'museum');
            })
            ->where('slug.slug', $slug)
            ->where('information_object_i18n.culture', $this->culture)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object.level_of_description_id',
                'information_object.repository_id',
                'information_object.parent_id',
                'information_object.lft',
                'information_object.rgt',
                'information_object.source_culture',
                'information_object_i18n.title',
                'information_object_i18n.alternate_title',
                'information_object_i18n.scope_and_content',
                'information_object_i18n.extent_and_medium',
                'information_object_i18n.access_conditions',
                'information_object_i18n.reproduction_conditions',
                'information_object_i18n.physical_characteristics',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
                // museum_metadata fields
                'museum_metadata.id as museum_metadata_id',
                'museum_metadata.work_type',
                'museum_metadata.object_type',
                'museum_metadata.classification',
                'museum_metadata.materials',
                'museum_metadata.techniques',
                'museum_metadata.measurements',
                'museum_metadata.dimensions',
                'museum_metadata.creation_date_earliest',
                'museum_metadata.creation_date_latest',
                'museum_metadata.inscription',
                'museum_metadata.inscriptions',
                'museum_metadata.condition_notes',
                'museum_metadata.provenance',
                'museum_metadata.style_period',
                'museum_metadata.cultural_context',
                'museum_metadata.current_location',
                'museum_metadata.edition_description',
                'museum_metadata.state_description',
                'museum_metadata.state_identification',
                'museum_metadata.facture_description',
                'museum_metadata.technique_cco',
                'museum_metadata.technique_qualifier',
                'museum_metadata.orientation',
                'museum_metadata.physical_appearance',
                'museum_metadata.color',
                'museum_metadata.shape',
                'museum_metadata.condition_term',
                'museum_metadata.condition_date',
                'museum_metadata.condition_description',
                'museum_metadata.condition_agent',
                'museum_metadata.treatment_type',
                'museum_metadata.treatment_date',
                'museum_metadata.treatment_agent',
                'museum_metadata.treatment_description',
                'museum_metadata.inscription_transcription',
                'museum_metadata.inscription_type',
                'museum_metadata.inscription_location',
                'museum_metadata.inscription_language',
                'museum_metadata.inscription_translation',
                'museum_metadata.mark_type',
                'museum_metadata.mark_description',
                'museum_metadata.mark_location',
                'museum_metadata.related_work_type',
                'museum_metadata.related_work_relationship',
                'museum_metadata.related_work_label',
                'museum_metadata.related_work_id',
                'museum_metadata.current_location_repository',
                'museum_metadata.current_location_geography',
                'museum_metadata.current_location_coordinates',
                'museum_metadata.current_location_ref_number',
                'museum_metadata.creation_place',
                'museum_metadata.creation_place_type',
                'museum_metadata.discovery_place',
                'museum_metadata.discovery_place_type',
                'museum_metadata.provenance_text',
                'museum_metadata.ownership_history',
                'museum_metadata.legal_status',
                'museum_metadata.rights_type',
                'museum_metadata.rights_holder',
                'museum_metadata.rights_date',
                'museum_metadata.rights_remarks',
                'museum_metadata.cataloger_name',
                'museum_metadata.cataloging_date',
                'museum_metadata.cataloging_institution',
                'museum_metadata.cataloging_remarks',
                'museum_metadata.record_type',
                'museum_metadata.record_level',
                'museum_metadata.creator_identity',
                'museum_metadata.creator_role',
                'museum_metadata.creator_extent',
                'museum_metadata.creator_qualifier',
                'museum_metadata.creator_attribution',
                'museum_metadata.creation_date_display',
                'museum_metadata.creation_date_qualifier',
                'museum_metadata.style',
                'museum_metadata.period',
                'museum_metadata.cultural_group',
                'museum_metadata.movement',
                'museum_metadata.school',
                'museum_metadata.dynasty',
                'museum_metadata.subject_indexing_type',
                'museum_metadata.subject_display',
                'museum_metadata.subject_extent',
                'museum_metadata.historical_context',
                'museum_metadata.architectural_context',
                'museum_metadata.archaeological_context',
                'museum_metadata.object_class',
                'museum_metadata.object_category',
                'museum_metadata.object_sub_category',
                'museum_metadata.edition_number',
                'museum_metadata.edition_size',
                'display_object_config.primary_profile_id',
            ])
            ->first();

        return $record;
    }

    /**
     * Browse museum objects with pagination, filters, and search.
     */
    public function browse(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? SettingHelper::hitsPerPage())));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'alphabetic';
        $sortDir = !empty($params['sortDir']) ? $params['sortDir'] : (($sort === 'lastUpdated') ? 'desc' : 'asc');
        $subquery = trim($params['subquery'] ?? '');

        try {
            $query = DB::table('information_object')
                ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
                ->join('object', 'information_object.id', '=', 'object.id')
                ->join('slug', 'information_object.id', '=', 'slug.object_id')
                ->join('museum_metadata', 'information_object.id', '=', 'museum_metadata.object_id')
                ->where('information_object_i18n.culture', $this->culture);

            $query->select([
                'information_object.id',
                'information_object.identifier',
                'information_object_i18n.title as name',
                'museum_metadata.work_type',
                'museum_metadata.creator_identity',
                'museum_metadata.creation_date_display',
                'museum_metadata.current_location',
                'museum_metadata.classification',
                'object.updated_at',
                'slug.slug',
            ]);

            // Filter by work_type
            if (!empty($params['filters']['work_type'])) {
                $query->where('museum_metadata.work_type', $params['filters']['work_type']);
            }

            // Filter by classification
            if (!empty($params['filters']['classification'])) {
                $query->where('museum_metadata.classification', $params['filters']['classification']);
            }

            // Search on title, creator, identifier
            if ($subquery !== '') {
                $query->where(function ($q) use ($subquery) {
                    $q->where('information_object_i18n.title', 'LIKE', "%{$subquery}%")
                      ->orWhere('museum_metadata.creator_identity', 'LIKE', "%{$subquery}%")
                      ->orWhere('information_object.identifier', 'LIKE', "%{$subquery}%");
                });
            }

            $total = $query->count();

            // Apply sorting
            switch ($sort) {
                case 'alphabetic':
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'identifier':
                    $query->orderBy('information_object.identifier', $sortDir);
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'workType':
                    $query->orderBy('museum_metadata.work_type', $sortDir);
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'creator':
                    $query->orderBy('museum_metadata.creator_identity', $sortDir);
                    $query->orderBy('information_object_i18n.title', $sortDir);
                    break;
                case 'lastUpdated':
                default:
                    $query->orderBy('object.updated_at', $sortDir);
                    break;
            }

            $rows = $query->skip($skip)->take($limit)->get();

            $hits = [];
            foreach ($rows as $row) {
                $hits[] = [
                    'id' => $row->id,
                    'name' => $row->name ?? '',
                    'identifier' => $row->identifier ?? '',
                    'work_type' => $row->work_type ?? '',
                    'creator_identity' => $row->creator_identity ?? '',
                    'creation_date_display' => $row->creation_date_display ?? '',
                    'current_location' => $row->current_location ?? '',
                    'classification' => $row->classification ?? '',
                    'updated_at' => $row->updated_at ?? '',
                    'slug' => $row->slug ?? '',
                ];
            }

            // Get distinct work types for filter dropdown
            $workTypes = DB::table('museum_metadata')
                ->whereNotNull('work_type')
                ->where('work_type', '!=', '')
                ->distinct()
                ->orderBy('work_type')
                ->pluck('work_type')
                ->toArray();

            // Get distinct classifications for filter dropdown
            $classifications = DB::table('museum_metadata')
                ->whereNotNull('classification')
                ->where('classification', '!=', '')
                ->distinct()
                ->orderBy('classification')
                ->pluck('classification')
                ->toArray();

            return [
                'hits' => $hits,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'workTypes' => $workTypes,
                'classifications' => $classifications,
            ];
        } catch (\Exception $e) {
            \Log::error('MuseumService browse error: ' . $e->getMessage());

            return [
                'hits' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'workTypes' => [],
                'classifications' => [],
            ];
        }
    }

    /**
     * Create a new museum object (information_object + i18n + slug + museum_metadata + display_object_config).
     */
    public function create(array $data): string
    {
        return DB::transaction(function () use ($data) {
            $parentId = $data['parent_id'] ?? 1;

            // Determine lft/rgt position: place as last child of parent
            $parent = DB::table('information_object')
                ->where('id', $parentId)
                ->select('lft', 'rgt')
                ->first();

            if (!$parent) {
                throw new \RuntimeException('Invalid parent information object.');
            }

            $newLft = $parent->rgt;
            $newRgt = $parent->rgt + 1;

            // Shift existing nested set values to make room
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

            // Insert into information_object table
            DB::table('information_object')->insert([
                'id' => $objectId,
                'identifier' => $data['identifier'] ?? null,
                'level_of_description_id' => $data['level_of_description_id'] ?: null,
                'collection_type_id' => null,
                'repository_id' => $data['repository_id'] ?: null,
                'parent_id' => $parentId,
                'lft' => $newLft,
                'rgt' => $newRgt,
                'source_culture' => $this->culture,
            ]);

            // Insert into information_object_i18n table
            DB::table('information_object_i18n')->insert([
                'id' => $objectId,
                'culture' => $this->culture,
                'title' => $data['title'],
                'alternate_title' => $data['alternate_title'] ?? null,
                'scope_and_content' => $data['scope_and_content'] ?? null,
                'extent_and_medium' => $data['extent_and_medium'] ?? null,
                'access_conditions' => $data['access_conditions'] ?? null,
                'reproduction_conditions' => $data['reproduction_conditions'] ?? null,
                'physical_characteristics' => $data['physical_characteristics'] ?? null,
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

            // Insert museum_metadata
            DB::table('museum_metadata')->insert([
                'object_id' => $objectId,
                'work_type' => $data['work_type'] ?? null,
                'object_type' => $data['object_type'] ?? null,
                'classification' => $data['classification'] ?? null,
                'materials' => $data['materials'] ?? null,
                'techniques' => $data['techniques'] ?? null,
                'measurements' => $data['measurements'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'creation_date_earliest' => $data['creation_date_earliest'] ?: null,
                'creation_date_latest' => $data['creation_date_latest'] ?: null,
                'inscription' => $data['inscription'] ?? null,
                'inscriptions' => $data['inscriptions'] ?? null,
                'condition_notes' => $data['condition_notes'] ?? null,
                'provenance' => $data['provenance'] ?? null,
                'style_period' => $data['style_period'] ?? null,
                'cultural_context' => $data['cultural_context'] ?? null,
                'current_location' => $data['current_location'] ?? null,
                'edition_description' => $data['edition_description'] ?? null,
                'state_description' => $data['state_description'] ?? null,
                'state_identification' => $data['state_identification'] ?? null,
                'facture_description' => $data['facture_description'] ?? null,
                'technique_cco' => $data['technique_cco'] ?? null,
                'technique_qualifier' => $data['technique_qualifier'] ?? null,
                'orientation' => $data['orientation'] ?? null,
                'physical_appearance' => $data['physical_appearance'] ?? null,
                'color' => $data['color'] ?? null,
                'shape' => $data['shape'] ?? null,
                'condition_term' => $data['condition_term'] ?? null,
                'condition_date' => $data['condition_date'] ?: null,
                'condition_description' => $data['condition_description'] ?? null,
                'condition_agent' => $data['condition_agent'] ?? null,
                'treatment_type' => $data['treatment_type'] ?? null,
                'treatment_date' => $data['treatment_date'] ?: null,
                'treatment_agent' => $data['treatment_agent'] ?? null,
                'treatment_description' => $data['treatment_description'] ?? null,
                'inscription_transcription' => $data['inscription_transcription'] ?? null,
                'inscription_type' => $data['inscription_type'] ?? null,
                'inscription_location' => $data['inscription_location'] ?? null,
                'inscription_language' => $data['inscription_language'] ?? null,
                'inscription_translation' => $data['inscription_translation'] ?? null,
                'mark_type' => $data['mark_type'] ?? null,
                'mark_description' => $data['mark_description'] ?? null,
                'mark_location' => $data['mark_location'] ?? null,
                'related_work_type' => $data['related_work_type'] ?? null,
                'related_work_relationship' => $data['related_work_relationship'] ?? null,
                'related_work_label' => $data['related_work_label'] ?? null,
                'related_work_id' => $data['related_work_id'] ?? null,
                'current_location_repository' => $data['current_location_repository'] ?? null,
                'current_location_geography' => $data['current_location_geography'] ?? null,
                'current_location_coordinates' => $data['current_location_coordinates'] ?? null,
                'current_location_ref_number' => $data['current_location_ref_number'] ?? null,
                'creation_place' => $data['creation_place'] ?? null,
                'creation_place_type' => $data['creation_place_type'] ?? null,
                'discovery_place' => $data['discovery_place'] ?? null,
                'discovery_place_type' => $data['discovery_place_type'] ?? null,
                'provenance_text' => $data['provenance_text'] ?? null,
                'ownership_history' => $data['ownership_history'] ?? null,
                'legal_status' => $data['legal_status'] ?? null,
                'rights_type' => $data['rights_type'] ?? null,
                'rights_holder' => $data['rights_holder'] ?? null,
                'rights_date' => $data['rights_date'] ?? null,
                'rights_remarks' => $data['rights_remarks'] ?? null,
                'cataloger_name' => $data['cataloger_name'] ?? null,
                'cataloging_date' => $data['cataloging_date'] ?: null,
                'cataloging_institution' => $data['cataloging_institution'] ?? null,
                'cataloging_remarks' => $data['cataloging_remarks'] ?? null,
                'record_type' => $data['record_type'] ?? null,
                'record_level' => $data['record_level'] ?? null,
                'creator_identity' => $data['creator_identity'] ?? null,
                'creator_role' => $data['creator_role'] ?? null,
                'creator_extent' => $data['creator_extent'] ?? null,
                'creator_qualifier' => $data['creator_qualifier'] ?? null,
                'creator_attribution' => $data['creator_attribution'] ?? null,
                'creation_date_display' => $data['creation_date_display'] ?? null,
                'creation_date_qualifier' => $data['creation_date_qualifier'] ?? null,
                'style' => $data['style'] ?? null,
                'period' => $data['period'] ?? null,
                'cultural_group' => $data['cultural_group'] ?? null,
                'movement' => $data['movement'] ?? null,
                'school' => $data['school'] ?? null,
                'dynasty' => $data['dynasty'] ?? null,
                'subject_indexing_type' => $data['subject_indexing_type'] ?? null,
                'subject_display' => $data['subject_display'] ?? null,
                'subject_extent' => $data['subject_extent'] ?? null,
                'historical_context' => $data['historical_context'] ?? null,
                'architectural_context' => $data['architectural_context'] ?? null,
                'archaeological_context' => $data['archaeological_context'] ?? null,
                'object_class' => $data['object_class'] ?? null,
                'object_category' => $data['object_category'] ?? null,
                'object_sub_category' => $data['object_sub_category'] ?? null,
                'edition_number' => $data['edition_number'] ?? null,
                'edition_size' => $data['edition_size'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert display_object_config
            DB::table('display_object_config')->insert([
                'object_id' => $objectId,
                'object_type' => 'museum',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $slug;
        });
    }

    /**
     * Update an existing museum object by slug.
     */
    public function update(string $slug, array $data): void
    {
        DB::transaction(function () use ($slug, $data) {
            $record = DB::table('slug')
                ->join('information_object', 'slug.object_id', '=', 'information_object.id')
                ->where('slug.slug', $slug)
                ->select('information_object.id')
                ->first();

            if (!$record) {
                throw new \RuntimeException('Museum object not found.');
            }

            $ioId = $record->id;

            // Update information_object
            DB::table('information_object')
                ->where('id', $ioId)
                ->update([
                    'identifier' => $data['identifier'] ?? null,
                    'level_of_description_id' => $data['level_of_description_id'] ?: null,
                    'repository_id' => $data['repository_id'] ?: null,
                ]);

            // Update information_object_i18n
            DB::table('information_object_i18n')
                ->where('id', $ioId)
                ->where('culture', $this->culture)
                ->update([
                    'title' => $data['title'],
                    'alternate_title' => $data['alternate_title'] ?? null,
                    'scope_and_content' => $data['scope_and_content'] ?? null,
                    'extent_and_medium' => $data['extent_and_medium'] ?? null,
                    'access_conditions' => $data['access_conditions'] ?? null,
                    'reproduction_conditions' => $data['reproduction_conditions'] ?? null,
                    'physical_characteristics' => $data['physical_characteristics'] ?? null,
                ]);

            // Update museum_metadata
            DB::table('museum_metadata')
                ->where('object_id', $ioId)
                ->update([
                    'work_type' => $data['work_type'] ?? null,
                    'object_type' => $data['object_type'] ?? null,
                    'classification' => $data['classification'] ?? null,
                    'materials' => $data['materials'] ?? null,
                    'techniques' => $data['techniques'] ?? null,
                    'measurements' => $data['measurements'] ?? null,
                    'dimensions' => $data['dimensions'] ?? null,
                    'creation_date_earliest' => $data['creation_date_earliest'] ?: null,
                    'creation_date_latest' => $data['creation_date_latest'] ?: null,
                    'inscription' => $data['inscription'] ?? null,
                    'inscriptions' => $data['inscriptions'] ?? null,
                    'condition_notes' => $data['condition_notes'] ?? null,
                    'provenance' => $data['provenance'] ?? null,
                    'style_period' => $data['style_period'] ?? null,
                    'cultural_context' => $data['cultural_context'] ?? null,
                    'current_location' => $data['current_location'] ?? null,
                    'edition_description' => $data['edition_description'] ?? null,
                    'state_description' => $data['state_description'] ?? null,
                    'state_identification' => $data['state_identification'] ?? null,
                    'facture_description' => $data['facture_description'] ?? null,
                    'technique_cco' => $data['technique_cco'] ?? null,
                    'technique_qualifier' => $data['technique_qualifier'] ?? null,
                    'orientation' => $data['orientation'] ?? null,
                    'physical_appearance' => $data['physical_appearance'] ?? null,
                    'color' => $data['color'] ?? null,
                    'shape' => $data['shape'] ?? null,
                    'condition_term' => $data['condition_term'] ?? null,
                    'condition_date' => $data['condition_date'] ?: null,
                    'condition_description' => $data['condition_description'] ?? null,
                    'condition_agent' => $data['condition_agent'] ?? null,
                    'treatment_type' => $data['treatment_type'] ?? null,
                    'treatment_date' => $data['treatment_date'] ?: null,
                    'treatment_agent' => $data['treatment_agent'] ?? null,
                    'treatment_description' => $data['treatment_description'] ?? null,
                    'inscription_transcription' => $data['inscription_transcription'] ?? null,
                    'inscription_type' => $data['inscription_type'] ?? null,
                    'inscription_location' => $data['inscription_location'] ?? null,
                    'inscription_language' => $data['inscription_language'] ?? null,
                    'inscription_translation' => $data['inscription_translation'] ?? null,
                    'mark_type' => $data['mark_type'] ?? null,
                    'mark_description' => $data['mark_description'] ?? null,
                    'mark_location' => $data['mark_location'] ?? null,
                    'related_work_type' => $data['related_work_type'] ?? null,
                    'related_work_relationship' => $data['related_work_relationship'] ?? null,
                    'related_work_label' => $data['related_work_label'] ?? null,
                    'related_work_id' => $data['related_work_id'] ?? null,
                    'current_location_repository' => $data['current_location_repository'] ?? null,
                    'current_location_geography' => $data['current_location_geography'] ?? null,
                    'current_location_coordinates' => $data['current_location_coordinates'] ?? null,
                    'current_location_ref_number' => $data['current_location_ref_number'] ?? null,
                    'creation_place' => $data['creation_place'] ?? null,
                    'creation_place_type' => $data['creation_place_type'] ?? null,
                    'discovery_place' => $data['discovery_place'] ?? null,
                    'discovery_place_type' => $data['discovery_place_type'] ?? null,
                    'provenance_text' => $data['provenance_text'] ?? null,
                    'ownership_history' => $data['ownership_history'] ?? null,
                    'legal_status' => $data['legal_status'] ?? null,
                    'rights_type' => $data['rights_type'] ?? null,
                    'rights_holder' => $data['rights_holder'] ?? null,
                    'rights_date' => $data['rights_date'] ?? null,
                    'rights_remarks' => $data['rights_remarks'] ?? null,
                    'cataloger_name' => $data['cataloger_name'] ?? null,
                    'cataloging_date' => $data['cataloging_date'] ?: null,
                    'cataloging_institution' => $data['cataloging_institution'] ?? null,
                    'cataloging_remarks' => $data['cataloging_remarks'] ?? null,
                    'record_type' => $data['record_type'] ?? null,
                    'record_level' => $data['record_level'] ?? null,
                    'creator_identity' => $data['creator_identity'] ?? null,
                    'creator_role' => $data['creator_role'] ?? null,
                    'creator_extent' => $data['creator_extent'] ?? null,
                    'creator_qualifier' => $data['creator_qualifier'] ?? null,
                    'creator_attribution' => $data['creator_attribution'] ?? null,
                    'creation_date_display' => $data['creation_date_display'] ?? null,
                    'creation_date_qualifier' => $data['creation_date_qualifier'] ?? null,
                    'style' => $data['style'] ?? null,
                    'period' => $data['period'] ?? null,
                    'cultural_group' => $data['cultural_group'] ?? null,
                    'movement' => $data['movement'] ?? null,
                    'school' => $data['school'] ?? null,
                    'dynasty' => $data['dynasty'] ?? null,
                    'subject_indexing_type' => $data['subject_indexing_type'] ?? null,
                    'subject_display' => $data['subject_display'] ?? null,
                    'subject_extent' => $data['subject_extent'] ?? null,
                    'historical_context' => $data['historical_context'] ?? null,
                    'architectural_context' => $data['architectural_context'] ?? null,
                    'archaeological_context' => $data['archaeological_context'] ?? null,
                    'object_class' => $data['object_class'] ?? null,
                    'object_category' => $data['object_category'] ?? null,
                    'object_sub_category' => $data['object_sub_category'] ?? null,
                    'edition_number' => $data['edition_number'] ?? null,
                    'edition_size' => $data['edition_size'] ?? null,
                    'updated_at' => now(),
                ]);

            // Update object.updated_at
            DB::table('object')
                ->where('id', $ioId)
                ->update(['updated_at' => now()]);
        });
    }

    /**
     * Delete a museum object and all related records.
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
                throw new \RuntimeException('Museum object not found.');
            }

            $ioId = $record->id;
            $width = $record->rgt - $record->lft + 1;

            // Collect all descendant IDs (nested set)
            $descendantIds = DB::table('information_object')
                ->whereBetween('lft', [$record->lft, $record->rgt])
                ->pluck('id')
                ->toArray();

            // Delete museum_metadata for this object
            DB::table('museum_metadata')
                ->where('object_id', $ioId)
                ->delete();

            // Delete display_object_config for this object
            DB::table('display_object_config')
                ->where('object_id', $ioId)
                ->where('object_type', 'museum')
                ->delete();

            // Delete i18n rows for all descendants
            DB::table('information_object_i18n')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Delete information_object rows for all descendants
            DB::table('information_object')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Delete slug rows for all descendants
            DB::table('slug')
                ->whereIn('object_id', $descendantIds)
                ->delete();

            // Delete object rows for all descendants
            DB::table('object')
                ->whereIn('id', $descendantIds)
                ->delete();

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
     * Get form choices for dropdowns.
     */
    public function getFormChoices(string $culture): array
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

        // Work type choices from existing data
        $workTypes = [
            'Painting',
            'Sculpture',
            'Drawing',
            'Print',
            'Photograph',
            'Textile',
            'Ceramic',
            'Furniture',
            'Metalwork',
            'Glass',
            'Mixed Media',
            'Installation',
            'Other',
        ];

        return compact('levels', 'repositories', 'workTypes');
    }
}
