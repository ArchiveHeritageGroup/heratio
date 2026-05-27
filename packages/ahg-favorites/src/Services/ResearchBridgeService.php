<?php

/**
 * ResearchBridgeService - bridges favorites into the ahg-research package.
 *
 * Mirrors atom-ahg-plugins/ahgFavoritesPlugin/lib/Services/ResearchBridgeService.php
 * (PSIS). Lets a researcher send a batch of favourites into one of their
 * research collections, projects, or bibliographies in one click.
 *
 * The research package is optional. Every method guards with
 * isResearchEnabled() so the bridge degrades to a flash message on
 * installs that ship without ahg-research.
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

namespace AhgFavorites\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResearchBridgeService
{
    /**
     * Detect whether the ahg-research package's tables are installed.
     *
     * We probe one canonical table per resource type rather than reading a
     * plugin-enabled flag (Heratio composes packages, it has no plugin
     * registry). Returns false silently if the migration has not been run.
     */
    public function isResearchEnabled(): bool
    {
        return Schema::hasTable('research_collection')
            && Schema::hasTable('research_project')
            && Schema::hasTable('research_bibliography');
    }

    private function getResearcherId(int $userId): ?int
    {
        if (! Schema::hasTable('research_researcher')) {
            return null;
        }

        $row = DB::table('research_researcher')->where('user_id', $userId)->first();

        return $row ? (int) $row->id : null;
    }

    /**
     * Resolve the IO ids referenced by a set of favourite ids that belong to
     * the caller. Filters to information_object rows; custom-type favourites
     * (research_project / journal / custom links) can't be cited and are
     * dropped here.
     */
    private function resolveObjectIds(int $userId, array $favoriteIds): array
    {
        $favoriteIds = array_filter(array_map('intval', $favoriteIds));
        if (empty($favoriteIds)) {
            return [];
        }

        return DB::table('favorites')
            ->where('user_id', $userId)
            ->whereIn('id', $favoriteIds)
            ->where('object_type', 'information_object')
            ->pluck('archival_description_id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->all();
    }

    /**
     * Resolve favourites with their notes (used when include_notes=true on
     * send-to-collection).
     */
    private function resolveFavorites(int $userId, array $favoriteIds): \Illuminate\Support\Collection
    {
        $favoriteIds = array_filter(array_map('intval', $favoriteIds));
        if (empty($favoriteIds)) {
            return collect();
        }

        return DB::table('favorites')
            ->where('user_id', $userId)
            ->whereIn('id', $favoriteIds)
            ->where('object_type', 'information_object')
            ->get();
    }

    // ------------------------------------------------------------------
    // Picker lists
    // ------------------------------------------------------------------

    public function getResearcherCollections(int $userId): array
    {
        if (! $this->isResearchEnabled()) {
            return [];
        }
        $researcherId = $this->getResearcherId($userId);
        if (! $researcherId) {
            return [];
        }

        return DB::table('research_collection')
            ->where('researcher_id', $researcherId)
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->toArray();
    }

    public function getResearcherProjects(int $userId): array
    {
        if (! $this->isResearchEnabled()) {
            return [];
        }
        $researcherId = $this->getResearcherId($userId);
        if (! $researcherId) {
            return [];
        }

        return DB::table('research_project')
            ->where('researcher_id', $researcherId)
            ->orderBy('title')
            ->get(['id', 'title', 'description'])
            ->toArray();
    }

    public function getResearcherBibliographies(int $userId): array
    {
        if (! $this->isResearchEnabled()) {
            return [];
        }
        $researcherId = $this->getResearcherId($userId);
        if (! $researcherId) {
            return [];
        }

        return DB::table('research_bibliography')
            ->where('researcher_id', $researcherId)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'citation_style'])
            ->toArray();
    }

    // ------------------------------------------------------------------
    // Send-to actions
    // ------------------------------------------------------------------

    public function sendToCollection(int $userId, array $favoriteIds, int $collectionId, bool $includeNotes = true): array
    {
        if (! $this->isResearchEnabled()) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('Research package not installed.')];
        }

        $researcherId = $this->getResearcherId($userId);
        if (! $researcherId) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('You are not registered as a researcher.')];
        }

        $owns = DB::table('research_collection')
            ->where('id', $collectionId)
            ->where('researcher_id', $researcherId)
            ->exists();
        if (! $owns) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('Collection not found.')];
        }

        $favorites = $this->resolveFavorites($userId, $favoriteIds);
        $added = 0;
        $skipped = 0;

        foreach ($favorites as $fav) {
            $objectId = (int) $fav->archival_description_id;
            if (! $objectId) {
                $skipped++;

                continue;
            }

            $exists = DB::table('research_collection_item')
                ->where('collection_id', $collectionId)
                ->where('object_id', $objectId)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            DB::table('research_collection_item')->insert([
                'collection_id' => $collectionId,
                'object_id' => $objectId,
                'object_type' => 'information_object',
                'reference_code' => $fav->reference_code,
                'notes' => $includeNotes ? $fav->notes : null,
                'sort_order' => 0,
                'created_at' => now(),
            ]);
            $added++;
        }

        return [
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'message' => __('Added :added items to collection.', ['added' => $added])
                .($skipped ? ' '.__(':skipped already present or invalid.', ['skipped' => $skipped]) : ''),
        ];
    }

    public function sendToProject(int $userId, array $favoriteIds, int $projectId): array
    {
        if (! $this->isResearchEnabled()) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('Research package not installed.')];
        }

        $researcherId = $this->getResearcherId($userId);
        if (! $researcherId) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('You are not registered as a researcher.')];
        }

        $owns = DB::table('research_project')
            ->where('id', $projectId)
            ->where('researcher_id', $researcherId)
            ->exists();
        if (! $owns) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('Project not found.')];
        }

        $objectIds = $this->resolveObjectIds($userId, $favoriteIds);
        $added = 0;
        $skipped = 0;

        foreach ($objectIds as $objectId) {
            $exists = DB::table('research_project_resource')
                ->where('project_id', $projectId)
                ->where('resource_type', 'object')
                ->where('object_id', $objectId)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            DB::table('research_project_resource')->insert([
                'project_id' => $projectId,
                'resource_type' => 'object',
                'object_id' => $objectId,
                'added_by' => $userId,
                'sort_order' => 0,
                'added_at' => now(),
            ]);
            $added++;
        }

        return [
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'message' => __('Added :added items to project.', ['added' => $added])
                .($skipped ? ' '.__(':skipped skipped.', ['skipped' => $skipped]) : ''),
        ];
    }

    public function sendToBibliography(int $userId, array $favoriteIds, int $bibliographyId, string $style = 'chicago'): array
    {
        if (! $this->isResearchEnabled()) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('Research package not installed.')];
        }

        $researcherId = $this->getResearcherId($userId);
        if (! $researcherId) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('You are not registered as a researcher.')];
        }

        $owns = DB::table('research_bibliography')
            ->where('id', $bibliographyId)
            ->where('researcher_id', $researcherId)
            ->exists();
        if (! $owns) {
            return ['success' => false, 'added' => 0, 'skipped' => 0, 'message' => __('Bibliography not found.')];
        }

        $favorites = $this->resolveFavorites($userId, $favoriteIds);
        $culture = app()->getLocale();
        $added = 0;
        $skipped = 0;

        foreach ($favorites as $fav) {
            $objectId = (int) $fav->archival_description_id;
            if (! $objectId) {
                $skipped++;

                continue;
            }

            $exists = DB::table('research_bibliography_entry')
                ->where('bibliography_id', $bibliographyId)
                ->where('object_id', $objectId)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            DB::table('research_bibliography_entry')->insert([
                'bibliography_id' => $bibliographyId,
                'object_id' => $objectId,
                'entry_type' => 'archival',
                'title' => $fav->archival_description,
                'archive_name' => null,
                'notes' => $fav->notes,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $added++;
        }

        return [
            'success' => true,
            'added' => $added,
            'skipped' => $skipped,
            'message' => __('Added :added citations.', ['added' => $added])
                .($skipped ? ' '.__(':skipped skipped.', ['skipped' => $skipped]) : ''),
        ];
    }
}
