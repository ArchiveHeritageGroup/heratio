<?php

/**
 * ExportService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgExport\Services;

use Illuminate\Support\Facades\DB;

class ExportService
{
    /**
     * Get all repositories for export filter dropdowns.
     */
    public function getRepositories(): array
    {
        return DB::table('repository')
            ->join('actor_i18n', function ($join) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get()
            ->toArray();
    }

    /**
     * Get all levels of description (taxonomy_id = 34) for export filter.
     */
    public function getLevelsOfDescription(): array
    {
        return DB::table('term')
            ->join('term_i18n', function ($join) {
                $join->on('term.id', '=', 'term_i18n.id')
                     ->where('term_i18n.culture', '=', 'en');
            })
            ->where('term.taxonomy_id', 34)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get()
            ->toArray();
    }

    /**
     * Count accession records, optionally filtered by repository.
     */
    public function getAccessionCount(?int $repositoryId = null): int
    {
        $query = DB::table('accession');
        if ($repositoryId) {
            $query->where('accession.repository_id', $repositoryId);
        }
        return $query->count();
    }

    /**
     * Count information objects for CSV export.
     */
    public function getInformationObjectCount(?int $repositoryId = null): int
    {
        $query = DB::table('information_object');
        if ($repositoryId) {
            $query->where('information_object.repository_id', $repositoryId);
        }
        return $query->count();
    }

    /**
     * Count authority records for export.
     */
    public function getAuthorityCount(): int
    {
        return DB::table('actor')
            ->whereNotNull('id')
            ->count();
    }

    /**
     * Count repository records for export.
     */
    public function getRepositoryCount(): int
    {
        return DB::table('repository')->count();
    }

    /**
     * Get top-level archival descriptions (fonds/collections) for EAD export.
     */
    public function getTopLevelFonds(): array
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                     ->where('i18n.culture', '=', 'en');
            })
            ->where('io.parent_id', 1)
            ->whereNotNull('i18n.title')
            ->select('io.id', 'io.identifier', 'i18n.title')
            ->orderBy('i18n.title')
            ->get()
            ->toArray();
    }

    /**
     * Get export formats available.
     */
    public function getExportFormats(): array
    {
        return [
            'csv' => ['name' => 'CSV', 'icon' => 'fas fa-file-csv', 'description' => 'Comma-separated values'],
            'ead' => ['name' => 'EAD 2002', 'icon' => 'fas fa-file-code', 'description' => 'Encoded Archival Description'],
            'dc'  => ['name' => 'Dublin Core', 'icon' => 'fas fa-file-alt', 'description' => 'Dublin Core XML'],
        ];
    }
}
