<?php

/**
 * DamService - Service for Heratio
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



namespace AhgDam\Services;

use AhgCore\Traits\WithCultureFallback;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DamService
{
    use WithCultureFallback;

    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Get a DAM asset by slug with all related data.
     */
    public function getBySlug(string $slug): ?object
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return null;
        }

        return $this->getById($objectId);
    }

    /**
     * Get a DAM asset by ID with all fields joined.
     */
    public function getById(int $id): ?object
    {
        // Culture-fallback i18n joins via WithCultureFallback trait.
        return DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->join('display_object_config as doc', function ($j) {
                $j->on('io.id', '=', 'doc.object_id')
                    ->where('doc.object_type', '=', 'dam');
            })
            ->tap(fn ($q) => $this->joinI18nWithFallback($q, 'information_object_i18n', 'io', aliasPrefix: 'i18n'))
            ->leftJoin('dam_iptc_metadata as iptc', 'io.id', '=', 'iptc.object_id')
            ->where('io.id', $id)
            ->select([
                'io.id',
                'io.identifier',
                'io.parent_id',
                'io.repository_id',
                'io.level_of_description_id',
                'io.source_culture',
                'io.icip_sensitivity',
                DB::raw('COALESCE(i18n_cur.title, i18n_fb.title) AS title'),
                DB::raw('COALESCE(i18n_cur.scope_and_content, i18n_fb.scope_and_content) AS scope_and_content'),
                DB::raw('COALESCE(i18n_cur.extent_and_medium, i18n_fb.extent_and_medium) AS extent_and_medium'),
                'slug.slug',
                'o.created_at',
                'o.updated_at',
                // IPTC metadata
                'iptc.creator',
                'iptc.creator_job_title',
                'iptc.creator_address',
                'iptc.creator_city',
                'iptc.creator_state',
                'iptc.creator_postal_code',
                'iptc.creator_country',
                'iptc.creator_phone',
                'iptc.creator_email',
                'iptc.creator_website',
                'iptc.headline',
                'iptc.duration_minutes',
                'iptc.caption',
                'iptc.keywords',
                'iptc.iptc_subject_code',
                'iptc.intellectual_genre',
                'iptc.asset_type',
                'iptc.genre',
                'iptc.contributors_json',
                'iptc.color_type',
                'iptc.audio_language',
                'iptc.subtitle_language',
                'iptc.production_company',
                'iptc.distributor',
                'iptc.broadcast_date',
                'iptc.awards',
                'iptc.series_title',
                'iptc.episode_number',
                'iptc.season_number',
                'iptc.iptc_scene',
                'iptc.date_created as iptc_date_created',
                'iptc.city',
                'iptc.state_province',
                'iptc.country',
                'iptc.country_code',
                'iptc.production_country',
                'iptc.production_country_code',
                'iptc.sublocation',
                'iptc.title as iptc_title',
                'iptc.job_id',
                'iptc.instructions',
                'iptc.credit_line',
                'iptc.source',
                'iptc.copyright_notice',
                'iptc.rights_usage_terms',
                'iptc.license_type',
                'iptc.license_url',
                'iptc.license_expiry',
                'iptc.model_release_status',
                'iptc.model_release_id',
                'iptc.property_release_status',
                'iptc.property_release_id',
                'iptc.artwork_title',
                'iptc.artwork_creator',
                'iptc.artwork_date',
                'iptc.artwork_source',
                'iptc.artwork_copyright',
                'iptc.persons_shown',
            ])
            ->first();
    }

    /**
     * Browse DAM assets with pagination, filtering, and search.
     */
    public function browse(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 10)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'lastUpdated';
        $sortDir = strtolower($params['sortDir'] ?? '') === 'asc' ? 'asc' : 'desc';
        $subquery = trim($params['subquery'] ?? '');
        $assetType = trim($params['asset_type'] ?? '');

        $baseQuery = DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->join('display_object_config as doc', function ($j) {
                $j->on('io.id', '=', 'doc.object_id')
                    ->where('doc.object_type', '=', 'dam');
            })
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('dam_iptc_metadata as iptc', 'io.id', '=', 'iptc.object_id');

        // Filter by asset type
        if ($assetType !== '') {
            $baseQuery->where('iptc.asset_type', $assetType);
        }

        // Search
        if ($subquery !== '') {
            $baseQuery->where(function ($q) use ($subquery) {
                $q->where('i18n.title', 'like', "%{$subquery}%")
                    ->orWhere('iptc.keywords', 'like', "%{$subquery}%")
                    ->orWhere('iptc.creator', 'like', "%{$subquery}%")
                    ->orWhere('iptc.headline', 'like', "%{$subquery}%")
                    ->orWhere('io.identifier', 'like', "%{$subquery}%");
            });
        }

        // Count
        $total = $baseQuery->count();

        // Sort
        switch ($sort) {
            case 'alphabetic':
                $baseQuery->orderBy('i18n.title', $sortDir);
                break;
            case 'identifier':
                $baseQuery->orderBy('io.identifier', $sortDir);
                break;
            case 'date':
                $baseQuery->orderBy('iptc.date_created', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $baseQuery->orderBy('o.updated_at', $sortDir);
                break;
        }

        $hits = $baseQuery
            ->select([
                'io.id',
                'io.identifier',
                'i18n.title as name',
                'iptc.asset_type',
                'iptc.creator',
                'iptc.date_created',
                'iptc.keywords',
                'o.updated_at',
                'slug.slug',
            ])
            ->skip($skip)
            ->take($limit)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->toArray();

        return [
            'hits' => $hits,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Create a new DAM asset with information_object + i18n + slug + IPTC metadata + display config.
     *
     * @return int The new object ID
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            // 1. Create object record
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // 2. Create information_object record
            $ioInsert = [
                'id' => $id,
                'identifier' => $data['identifier'] ?? null,
                'parent_id' => $data['parent_id'] ?? 1,
                'repository_id' => $data['repository_id'] ?? null,
                'level_of_description_id' => $data['level_of_description_id'] ?? null,
                'source_standard' => 'dam',
                'source_culture' => $this->culture,
            ];
            if (array_key_exists('icip_sensitivity', $data) && $data['icip_sensitivity'] !== '') {
                $ioInsert['icip_sensitivity'] = $data['icip_sensitivity'];
            }
            DB::table('information_object')->insert($ioInsert);

            // 3. Create information_object_i18n record
            DB::table('information_object_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'title' => $data['title'] ?? null,
                'scope_and_content' => $data['scope_and_content'] ?? null,
                'extent_and_medium' => $data['extent_and_medium'] ?? null,
            ]);

            // 4. Generate and insert slug
            $baseSlug = Str::slug($data['title'] ?? $data['identifier'] ?? 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            DB::table('slug')->insert([
                'object_id' => $id,
                'slug' => $slug,
            ]);

            // 5. Create display_object_config record
            DB::table('display_object_config')->updateOrInsert(
                ['object_id' => $id],
                ['object_type' => 'dam', 'updated_at' => now()]
            );

            // 6. Create dam_iptc_metadata record
            DB::table('dam_iptc_metadata')->insert([
                'object_id' => $id,
                'creator' => $data['creator'] ?? null,
                'creator_job_title' => $data['creator_job_title'] ?? null,
                'creator_address' => $data['creator_address'] ?? null,
                'creator_city' => $data['creator_city'] ?? null,
                'creator_state' => $data['creator_state'] ?? null,
                'creator_postal_code' => $data['creator_postal_code'] ?? null,
                'creator_country' => $data['creator_country'] ?? null,
                'creator_phone' => $data['creator_phone'] ?? null,
                'creator_email' => $data['creator_email'] ?? null,
                'creator_website' => $data['creator_website'] ?? null,
                'headline' => $data['headline'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'caption' => $data['caption'] ?? null,
                'keywords' => $data['keywords'] ?? null,
                'iptc_subject_code' => $data['iptc_subject_code'] ?? null,
                'intellectual_genre' => $data['intellectual_genre'] ?? null,
                'asset_type' => $data['asset_type'] ?? null,
                'genre' => $data['genre'] ?? null,
                'color_type' => $data['color_type'] ?? null,
                'audio_language' => $data['audio_language'] ?? null,
                'subtitle_language' => $data['subtitle_language'] ?? null,
                'production_company' => $data['production_company'] ?? null,
                'distributor' => $data['distributor'] ?? null,
                'broadcast_date' => $data['broadcast_date'] ?? null,
                'awards' => $data['awards'] ?? null,
                'series_title' => $data['series_title'] ?? null,
                'season_number' => $data['season_number'] ?? null,
                'episode_number' => $data['episode_number'] ?? null,
                'persons_shown' => $data['persons_shown'] ?? null,
                'date_created' => $data['date_created'] ?? null,
                'city' => $data['city'] ?? null,
                'state_province' => $data['state_province'] ?? null,
                'country' => $data['country'] ?? null,
                'country_code' => $data['country_code'] ?? null,
                'production_country' => $data['production_country'] ?? null,
                'production_country_code' => $data['production_country_code'] ?? null,
                'sublocation' => $data['sublocation'] ?? null,
                'title' => $data['iptc_title'] ?? null,
                'job_id' => $data['job_id'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'credit_line' => $data['credit_line'] ?? null,
                'source' => $data['source'] ?? null,
                'copyright_notice' => $data['copyright_notice'] ?? null,
                'rights_usage_terms' => $data['rights_usage_terms'] ?? null,
                'license_type' => $data['license_type'] ?? null,
                'license_url' => $data['license_url'] ?? null,
                'license_expiry' => $data['license_expiry'] ?? null,
                'model_release_status' => $data['model_release_status'] ?? null,
                'model_release_id' => $data['model_release_id'] ?? null,
                'property_release_status' => $data['property_release_status'] ?? null,
                'property_release_id' => $data['property_release_id'] ?? null,
                'artwork_title' => $data['artwork_title'] ?? null,
                'artwork_creator' => $data['artwork_creator'] ?? null,
                'artwork_date' => $data['artwork_date'] ?? null,
                'artwork_source' => $data['artwork_source'] ?? null,
                'artwork_copyright' => $data['artwork_copyright'] ?? null,
                // Production credits (JSON of {role, name} pairs).
                'contributors_json' => $data['contributors_json'] ?? null,
                'created_at' => now(),
            ]);

            // 7. Set publication status (published = 160)
            DB::table('status')->insert([
                'object_id' => $id,
                'type_id' => 158,
                'status_id' => 160,
            ]);

            return $id;
        });
    }

    /**
     * Update an existing DAM asset.
     */
    public function update(string $slug, array $data): void
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return;
        }

        DB::transaction(function () use ($objectId, $data) {
            // 1. Update information_object
            $ioUpdate = [];
            foreach (['identifier', 'parent_id', 'repository_id', 'level_of_description_id', 'icip_sensitivity'] as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    if ($field === 'icip_sensitivity' && $value === '') {
                        $value = null;
                    }
                    $ioUpdate[$field] = $value;
                }
            }
            if (!empty($ioUpdate)) {
                DB::table('information_object')->where('id', $objectId)->update($ioUpdate);
            }

            // 2. Update information_object_i18n
            // Issue #61 Phase 3c: snapshot before, run update, detect overrides.
            $i18nUpdate = [];
            foreach (['title', 'scope_and_content', 'extent_and_medium'] as $field) {
                if (array_key_exists($field, $data)) {
                    $i18nUpdate[$field] = $data[$field];
                }
            }
            if (!empty($i18nUpdate)) {
                $beforeI18n = (array) (DB::table('information_object_i18n')
                    ->where('id', $objectId)->where('culture', $this->culture)
                    ->first(array_keys($i18nUpdate)) ?? []);
                $exists = DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', $this->culture)
                    ->exists();
                if ($exists) {
                    DB::table('information_object_i18n')
                        ->where('id', $objectId)
                        ->where('culture', $this->culture)
                        ->update($i18nUpdate);
                } else {
                    DB::table('information_object_i18n')->insert(array_merge(
                        ['id' => $objectId, 'culture' => $this->culture],
                        $i18nUpdate
                    ));
                }
                try {
                    app(\AhgProvenanceAi\Services\OverrideService::class)
                        ->detectOverridesFromForm('information_object', (int) $objectId, $beforeI18n, $i18nUpdate, (int) (auth()->id() ?? 0));
                } catch (\Throwable $e) { \Log::warning('DamService update: override detection failed: ' . $e->getMessage()); }
            }

            // 3. Update dam_iptc_metadata
            $iptcFields = [
                'creator', 'creator_job_title', 'creator_address', 'creator_city',
                'creator_state', 'creator_postal_code', 'creator_country',
                'creator_phone', 'creator_email', 'creator_website',
                'headline', 'duration_minutes', 'caption', 'keywords',
                'iptc_subject_code', 'intellectual_genre', 'asset_type', 'genre',
                'color_type', 'audio_language', 'subtitle_language',
                'production_company', 'distributor', 'broadcast_date', 'awards',
                'series_title', 'season_number', 'episode_number',
                'persons_shown', 'date_created', 'city', 'state_province',
                'country', 'country_code', 'production_country', 'production_country_code',
                'sublocation', 'job_id', 'instructions',
                'credit_line', 'source', 'copyright_notice', 'rights_usage_terms',
                'license_type', 'license_url', 'license_expiry',
                'model_release_status', 'model_release_id',
                'property_release_status', 'property_release_id',
                'artwork_title', 'artwork_creator', 'artwork_date',
                'artwork_source', 'artwork_copyright',
                // Production credits — JSON array of {role, name} pairs.
                'contributors_json',
            ];

            $iptcUpdate = [];
            foreach ($iptcFields as $field) {
                if (array_key_exists($field, $data)) {
                    $iptcUpdate[$field] = $data[$field];
                }
            }
            // Handle iptc_title mapped to 'title' column in dam_iptc_metadata
            if (array_key_exists('iptc_title', $data)) {
                $iptcUpdate['title'] = $data['iptc_title'];
            }

            if (!empty($iptcUpdate)) {
                $iptcUpdate['updated_at'] = now();
                $exists = DB::table('dam_iptc_metadata')
                    ->where('object_id', $objectId)
                    ->exists();
                if ($exists) {
                    DB::table('dam_iptc_metadata')
                        ->where('object_id', $objectId)
                        ->update($iptcUpdate);
                } else {
                    DB::table('dam_iptc_metadata')->insert(array_merge(
                        ['object_id' => $objectId, 'created_at' => now()],
                        $iptcUpdate
                    ));
                }
            }

            // 4. Touch the object record
            DB::table('object')->where('id', $objectId)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Delete a DAM asset and all related records.
     */
    public function delete(string $slug): void
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return;
        }

        DB::transaction(function () use ($objectId) {
            // 1. Delete IPTC metadata
            DB::table('dam_iptc_metadata')->where('object_id', $objectId)->delete();

            // 2. Delete display_object_config
            DB::table('display_object_config')->where('object_id', $objectId)->delete();

            // 3. Delete version links
            DB::table('dam_version_links')->where('object_id', $objectId)->delete();

            // 4. Delete format holdings
            DB::table('dam_format_holdings')->where('object_id', $objectId)->delete();

            // 5. Delete external links
            DB::table('dam_external_links')->where('object_id', $objectId)->delete();

            // 6. Delete status records
            DB::table('status')->where('object_id', $objectId)->delete();

            // 7. Delete information_object_i18n
            DB::table('information_object_i18n')->where('id', $objectId)->delete();

            // 8. Delete information_object
            DB::table('information_object')->where('id', $objectId)->delete();

            // 9. Delete slug + object
            DB::table('slug')->where('object_id', $objectId)->delete();
            DB::table('object')->where('id', $objectId)->delete();
        });
    }

    /**
     * Get dashboard statistics.
     */
    public function getDashboardStats(): array
    {
        $totalAssets = DB::table('display_object_config')
            ->where('object_type', 'dam')
            ->count();

        $withDigitalObjects = DB::table('display_object_config as doc')
            ->join('digital_object as do', 'doc.object_id', '=', 'do.object_id')
            ->where('doc.object_type', 'dam')
            ->whereNull('do.parent_id')
            ->count();

        $withIptcMetadata = DB::table('display_object_config as doc')
            ->join('dam_iptc_metadata as iptc', 'doc.object_id', '=', 'iptc.object_id')
            ->where('doc.object_type', 'dam')
            ->count();

        // Counts by asset_type
        $byAssetType = DB::table('display_object_config as doc')
            ->join('dam_iptc_metadata as iptc', 'doc.object_id', '=', 'iptc.object_id')
            ->where('doc.object_type', 'dam')
            ->whereNotNull('iptc.asset_type')
            ->select('iptc.asset_type', DB::raw('COUNT(*) as count'))
            ->groupBy('iptc.asset_type')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        // License breakdown
        $licenseTypes = DB::table('display_object_config as doc')
            ->join('dam_iptc_metadata as iptc', 'doc.object_id', '=', 'iptc.object_id')
            ->where('doc.object_type', 'dam')
            ->whereNotNull('iptc.license_type')
            ->where('iptc.license_type', '!=', '')
            ->select('iptc.license_type', DB::raw('COUNT(*) as count'))
            ->groupBy('iptc.license_type')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();

        return [
            'totalAssets' => $totalAssets,
            'withDigitalObjects' => $withDigitalObjects,
            'withIptcMetadata' => $withIptcMetadata,
            'byAssetType' => $byAssetType,
            'licenseTypes' => $licenseTypes,
        ];
    }

    /**
     * Get recent DAM assets for dashboard.
     */
    public function getRecentAssets(int $limit = 10): array
    {
        return DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('display_object_config as doc', function ($j) {
                $j->on('io.id', '=', 'doc.object_id')
                    ->where('doc.object_type', '=', 'dam');
            })
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->leftJoin('dam_iptc_metadata as iptc', 'io.id', '=', 'iptc.object_id')
            ->select([
                'io.id',
                'io.identifier',
                'i18n.title',
                'slug.slug',
                'o.created_at',
                'o.updated_at',
                'iptc.creator',
                'iptc.asset_type',
                'iptc.headline',
            ])
            ->orderBy('o.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return (array) $row;
            })
            ->toArray();
    }

    /**
     * Get form choices for create/edit forms.
     */
    public function getFormChoices(): array
    {
        // Repositories
        $repositories = DB::table('repository as r')
            ->join('actor_i18n as ai', function ($j) {
                $j->on('r.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $this->culture);
            })
            ->select('r.id', 'ai.authorized_form_of_name as name')
            ->whereNotNull('ai.authorized_form_of_name')
            ->orderBy('ai.authorized_form_of_name')
            ->get();

        // Levels of description
        $levels = DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $this->culture);
            })
            ->where('term.taxonomy_id', 34)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term.lft')
            ->get();

        // Parent DAM collections (top-level)
        $parents = DB::table('information_object as io')
            ->join('display_object_config as doc', function ($j) {
                $j->on('io.id', '=', 'doc.object_id')
                    ->where('doc.object_type', '=', 'dam');
            })
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->where('io.parent_id', 1)
            ->select('io.id', 'i18n.title', 'io.identifier')
            ->orderBy('i18n.title')
            ->get();

        return [
            'repositories' => $repositories,
            'levels' => $levels,
            'parents' => $parents,
        ];
    }

    /**
     * Get the slug for an object ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    /**
     * Get digital objects for an information_object.
     */
    public function getDigitalObjects(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('digital_object')
            ->where('object_id', $objectId)
            ->whereNull('parent_id')
            ->select('id', 'object_id', 'mime_type', 'byte_size', 'name', 'path')
            ->get();
    }

    /**
     * Get related information objects (children) for sidebar.
     */
    public function getRelatedItems(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', $objectId)
            ->select('io.id', 'i18n.title', 'slug.slug', 'io.identifier')
            ->orderBy('i18n.title')
            ->limit(20)
            ->get();
    }

    /**
     * Get version links for a DAM asset.
     */
    public function getVersionLinks(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('dam_version_links')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Get format holdings for a DAM asset.
     */
    public function getFormatHoldings(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('dam_format_holdings')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Get external links for a DAM asset.
     */
    public function getExternalLinks(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('dam_external_links')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Save version links (delete + re-insert approach for simplicity with multi-row forms).
     */
    public function saveVersionLinks(int $objectId, array $data): void
    {
        $titles = $data['version_title'] ?? [];
        $types = $data['version_type'] ?? [];
        $languages = $data['version_language'] ?? [];
        $languageCodes = $data['version_language_code'] ?? [];
        $years = $data['version_year'] ?? [];
        $notes = $data['version_notes'] ?? [];
        $ids = $data['version_id'] ?? [];

        // Collect IDs that should be kept
        $keepIds = array_filter($ids, fn($v) => $v !== '' && $v !== null);

        // Delete rows not in the submitted set
        $query = DB::table('dam_version_links')->where('object_id', $objectId);
        if (!empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();

        // Upsert each row
        foreach ($titles as $i => $title) {
            $title = trim($title ?? '');
            if ($title === '') {
                continue;
            }
            $row = [
                'object_id' => $objectId,
                'title' => $title,
                'version_type' => $types[$i] ?? 'language',
                'language_name' => $languages[$i] ?? null,
                'language_code' => $languageCodes[$i] ?? null,
                'year' => $years[$i] ?? null,
                'notes' => $notes[$i] ?? null,
                'updated_at' => now(),
            ];

            $existingId = $ids[$i] ?? '';
            if ($existingId !== '' && $existingId !== null) {
                DB::table('dam_version_links')->where('id', $existingId)->update($row);
            } else {
                $row['created_at'] = now();
                DB::table('dam_version_links')->insert($row);
            }
        }
    }

    /**
     * Save format holdings (delete + re-insert approach for multi-row forms).
     */
    public function saveFormatHoldings(int $objectId, array $data): void
    {
        $formats = $data['holding_format'] ?? [];
        $formatDetails = $data['holding_format_details'] ?? [];
        $institutions = $data['holding_institution'] ?? [];
        $locations = $data['holding_location'] ?? [];
        $accessions = $data['holding_accession'] ?? [];
        $conditions = $data['holding_condition'] ?? [];
        $accesses = $data['holding_access'] ?? [];
        $urls = $data['holding_url'] ?? [];
        $verifieds = $data['holding_verified'] ?? [];
        $primaries = $data['holding_primary'] ?? [];
        $accessNotes = $data['holding_access_notes'] ?? [];
        $notes = $data['holding_notes'] ?? [];
        $ids = $data['holding_id'] ?? [];

        // Collect IDs that should be kept
        $keepIds = array_filter($ids, fn($v) => $v !== '' && $v !== null);

        // Delete rows not in the submitted set
        $query = DB::table('dam_format_holdings')->where('object_id', $objectId);
        if (!empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();

        // Primary IDs come as values in checkbox array
        $primaryIds = is_array($primaries) ? $primaries : [];

        // Upsert each row
        foreach ($formats as $i => $format) {
            $format = trim($format ?? '');
            $institution = trim($institutions[$i] ?? '');
            if ($format === '' && $institution === '') {
                continue;
            }
            $existingId = $ids[$i] ?? '';
            $isPrimary = in_array($existingId, $primaryIds) || in_array('new', $primaryIds);

            $row = [
                'object_id' => $objectId,
                'format_type' => $format ?: 'Other',
                'format_details' => $formatDetails[$i] ?? null,
                'holding_institution' => $institution ?: 'Unknown',
                'holding_location' => $locations[$i] ?? null,
                'accession_number' => $accessions[$i] ?? null,
                'condition_status' => $conditions[$i] ?? 'unknown',
                'access_status' => $accesses[$i] ?? 'unknown',
                'access_url' => $urls[$i] ?? null,
                'verified_date' => !empty($verifieds[$i]) ? $verifieds[$i] : null,
                'is_primary' => $isPrimary ? 1 : 0,
                'access_notes' => $accessNotes[$i] ?? null,
                'notes' => $notes[$i] ?? null,
                'updated_at' => now(),
            ];

            if ($existingId !== '' && $existingId !== null) {
                // Fix primary: use the actual ID for existing rows
                $row['is_primary'] = in_array($existingId, $primaryIds) ? 1 : 0;
                DB::table('dam_format_holdings')->where('id', $existingId)->update($row);
            } else {
                $row['created_at'] = now();
                DB::table('dam_format_holdings')->insert($row);
            }
        }
    }

    /**
     * Save external links (delete + re-insert approach for multi-row forms).
     */
    public function saveExternalLinks(int $objectId, array $data): void
    {
        $types = $data['link_type'] ?? [];
        $urls = $data['link_url'] ?? [];
        $titles = $data['link_title'] ?? [];
        $verifieds = $data['link_verified'] ?? [];
        $primaries = $data['link_primary'] ?? [];
        $persons = $data['link_person'] ?? [];
        $roles = $data['link_role'] ?? [];
        $descriptions = $data['link_description'] ?? [];
        $ids = $data['link_id'] ?? [];

        // Collect IDs that should be kept
        $keepIds = array_filter($ids, fn($v) => $v !== '' && $v !== null);

        // Delete rows not in the submitted set
        $query = DB::table('dam_external_links')->where('object_id', $objectId);
        if (!empty($keepIds)) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();

        // Primary IDs come as values in checkbox array
        $primaryIds = is_array($primaries) ? $primaries : [];

        // Upsert each row
        foreach ($urls as $i => $url) {
            $url = trim($url ?? '');
            if ($url === '') {
                continue;
            }
            $existingId = $ids[$i] ?? '';
            $isPrimary = in_array($existingId, $primaryIds);

            $row = [
                'object_id' => $objectId,
                'link_type' => $types[$i] ?? 'Other',
                'url' => $url,
                'title' => $titles[$i] ?? null,
                'verified_date' => !empty($verifieds[$i]) ? $verifieds[$i] : null,
                'is_primary' => $isPrimary ? 1 : 0,
                'person_name' => $persons[$i] ?? null,
                'person_role' => $roles[$i] ?? null,
                'description' => $descriptions[$i] ?? null,
                'updated_at' => now(),
            ];

            if ($existingId !== '' && $existingId !== null) {
                DB::table('dam_external_links')->where('id', $existingId)->update($row);
            } else {
                $row['created_at'] = now();
                DB::table('dam_external_links')->insert($row);
            }
        }
    }
}
