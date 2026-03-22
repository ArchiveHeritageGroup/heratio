<?php

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
