<?php

/**
 * ExportService - Service for Heratio
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

    // ── Actual export generation (#1357) ────────────────────────────────────
    // All read-only SELECTs over the AtoM schema, streamed row-by-row via a DB
    // cursor so a 450k-record export never buffers in memory. No base-AtoM
    // tables are written.

    /**
     * Stream a CSV download. $rowGenerator receives the open output handle and
     * fputcsv's the data rows; the header is written first (with a UTF-8 BOM so
     * Excel renders accented characters correctly).
     */
    private function csvDownload(string $filename, array $header, callable $rowGenerator)
    {
        return response()->streamDownload(function () use ($header, $rowGenerator) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            $rowGenerator($out);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Information-object CSV (AtoM-import-shaped), filtered by the form fields. */
    public function streamInformationObjectCsv(array $filters)
    {
        $repositoryId = ! empty($filters['repository_id']) ? (int) $filters['repository_id'] : null;
        $levelIds     = array_filter(array_map('intval', (array) ($filters['level_ids'] ?? [])));
        $parentSlug   = trim((string) ($filters['parent_slug'] ?? ''));
        $includeDesc  = ! empty($filters['include_descendants']);
        $limit        = (int) ($filters['limit'] ?? 0);

        return $this->csvDownload(
            'information-objects-' . date('Ymd-His') . '.csv',
            ['legacyId', 'identifier', 'title', 'levelOfDescription', 'repository', 'scopeAndContent', 'culture'],
            function ($out) use ($repositoryId, $levelIds, $parentSlug, $includeDesc, $limit) {
                $q = DB::table('information_object as io')
                    ->leftJoin('information_object_i18n as i18n', fn ($j) => $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en'))
                    ->leftJoin('term_i18n as lvl', fn ($j) => $j->on('io.level_of_description_id', '=', 'lvl.id')->where('lvl.culture', '=', 'en'))
                    ->leftJoin('actor_i18n as repo', fn ($j) => $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', 'en'))
                    ->where('io.id', '>', 1)
                    ->select('io.id', 'io.identifier', 'i18n.title', 'lvl.name as level', 'repo.authorized_form_of_name as repository', 'i18n.scope_and_content');
                if ($repositoryId) {
                    $q->where('io.repository_id', $repositoryId);
                }
                if ($levelIds) {
                    $q->whereIn('io.level_of_description_id', $levelIds);
                }
                if ($parentSlug !== '') {
                    $pid = (int) DB::table('slug')->where('slug', $parentSlug)->value('object_id');
                    if ($pid) {
                        $p = DB::table('information_object')->where('id', $pid)->first();
                        if ($p && $includeDesc) {
                            $q->whereBetween('io.lft', [$p->lft, $p->rgt]);
                        } else {
                            $q->where('io.parent_id', $pid);
                        }
                    }
                }
                if ($limit > 0) {
                    $q->limit($limit);
                }
                foreach ($q->orderBy('io.lft')->cursor() as $r) {
                    fputcsv($out, [$r->id, $r->identifier, $r->title, $r->level, $r->repository, $r->scope_and_content, 'en']);
                }
            }
        );
    }

    /** Accession CSV, optionally date-bounded (accession has no repository_id). */
    public function streamAccessionCsv(array $filters)
    {
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo   = trim((string) ($filters['date_to'] ?? ''));

        return $this->csvDownload(
            'accessions-' . date('Ymd-His') . '.csv',
            ['id', 'identifier', 'date', 'title', 'sourceOfAcquisition', 'scopeAndContent', 'culture'],
            function ($out) use ($dateFrom, $dateTo) {
                $q = DB::table('accession as a')
                    ->leftJoin('accession_i18n as i', fn ($j) => $j->on('a.id', '=', 'i.id')->where('i.culture', '=', 'en'))
                    ->select('a.id', 'a.identifier', 'a.date', 'i.title', 'i.source_of_acquisition', 'i.scope_and_content');
                if ($dateFrom !== '') {
                    $q->where('a.date', '>=', $dateFrom);
                }
                if ($dateTo !== '') {
                    $q->where('a.date', '<=', $dateTo);
                }
                foreach ($q->orderBy('a.id')->cursor() as $r) {
                    fputcsv($out, [$r->id, $r->identifier, $r->date, $r->title, $r->source_of_acquisition, $r->scope_and_content, 'en']);
                }
            }
        );
    }

    /** Authority (actor) CSV, excluding repositories; optional row limit. */
    public function streamActorCsv(int $limit = 0)
    {
        return $this->csvDownload(
            'authority-records-' . date('Ymd-His') . '.csv',
            ['id', 'authorizedFormOfName', 'datesOfExistence', 'history', 'culture'],
            function ($out) use ($limit) {
                $q = DB::table('actor as a')
                    ->leftJoin('actor_i18n as i', fn ($j) => $j->on('a.id', '=', 'i.id')->where('i.culture', '=', 'en'))
                    ->where('a.id', '>', 1)
                    ->whereNotIn('a.id', fn ($sub) => $sub->from('repository')->select('id'))
                    ->select('a.id', 'i.authorized_form_of_name', 'i.dates_of_existence', 'i.history')
                    ->orderBy('a.id');
                if ($limit > 0) {
                    $q->limit($limit);
                }
                foreach ($q->cursor() as $r) {
                    fputcsv($out, [$r->id, $r->authorized_form_of_name, $r->dates_of_existence, $r->history, 'en']);
                }
            }
        );
    }

    /** Repository CSV; optional row limit. */
    public function streamRepositoryCsv(int $limit = 0)
    {
        return $this->csvDownload(
            'repositories-' . date('Ymd-His') . '.csv',
            ['id', 'identifier', 'name', 'culture'],
            function ($out) use ($limit) {
                $q = DB::table('repository as r')
                    ->leftJoin('actor_i18n as i', fn ($j) => $j->on('r.id', '=', 'i.id')->where('i.culture', '=', 'en'))
                    ->select('r.id', 'r.identifier', 'i.authorized_form_of_name as name')
                    ->orderBy('i.authorized_form_of_name');
                if ($limit > 0) {
                    $q->limit($limit);
                }
                foreach ($q->cursor() as $r) {
                    fputcsv($out, [$r->id, $r->identifier, $r->name, 'en']);
                }
            }
        );
    }
}
