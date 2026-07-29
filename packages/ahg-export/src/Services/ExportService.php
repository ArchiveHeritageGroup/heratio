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

    /**
     * Information-object CSV, filtered by the form fields. Standard-aware: the
     * column set follows the records' description standard (taxonomy 70). A
     * Dublin Core institution gets DC elements, not the generic ISAD columns.
     * The standard is taken from an explicit `standard` filter when supplied,
     * otherwise auto-detected as the dominant standard among the filtered set;
     * anything other than a known code falls back to the ISAD shape (#1434).
     */
    public function streamInformationObjectCsv(array $filters)
    {
        if ($this->resolveExportStandard($filters) === 'dc') {
            return $this->streamInformationObjectCsvDc($filters);
        }

        $limit = (int) ($filters['limit'] ?? 0);

        return $this->csvDownload(
            'information-objects-' . date('Ymd-His') . '.csv',
            ['legacyId', 'identifier', 'title', 'levelOfDescription', 'repository', 'scopeAndContent', 'culture'],
            function ($out) use ($filters, $limit) {
                $q = DB::table('information_object as io')
                    ->leftJoin('information_object_i18n as i18n', fn ($j) => $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en'))
                    ->leftJoin('term_i18n as lvl', fn ($j) => $j->on('io.level_of_description_id', '=', 'lvl.id')->where('lvl.culture', '=', 'en'))
                    ->leftJoin('actor_i18n as repo', fn ($j) => $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', 'en'))
                    ->where('io.id', '>', 1)
                    ->select('io.id', 'io.identifier', 'i18n.title', 'lvl.name as level', 'repo.authorized_form_of_name as repository', 'i18n.scope_and_content');
                $this->applyIoCsvFilters($q, $filters);
                if ($limit > 0) {
                    $q->limit($limit);
                }
                foreach ($q->orderBy('io.lft')->cursor() as $r) {
                    fputcsv($out, [$r->id, $r->identifier, $r->title, $r->level, $r->repository, $r->scope_and_content, 'en']);
                }
            }
        );
    }

    /**
     * Apply the shared IO-CSV filters (repository, level, parent + descendants)
     * to a query aliased `io`. Used by every per-standard CSV variant and by
     * standard auto-detection, so the filter semantics stay identical.
     */
    private function applyIoCsvFilters($q, array $filters): void
    {
        $repositoryId = ! empty($filters['repository_id']) ? (int) $filters['repository_id'] : null;
        $levelIds     = array_filter(array_map('intval', (array) ($filters['level_ids'] ?? [])));
        $parentSlug   = trim((string) ($filters['parent_slug'] ?? ''));
        $includeDesc  = ! empty($filters['include_descendants']);

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
    }

    /**
     * Resolve which description standard the CSV should follow: an explicit
     * `standard` code when given, else the dominant standard (taxonomy 70 code)
     * among the filtered records, else 'isad'.
     */
    private function resolveExportStandard(array $filters): string
    {
        $known = ['isad', 'dc', 'dacs', 'rad', 'mods', 'ric'];
        $explicit = strtolower(trim((string) ($filters['standard'] ?? '')));
        if (in_array($explicit, $known, true)) {
            return $explicit;
        }

        $q = DB::table('information_object as io')
            ->join('term as std', 'io.display_standard_id', '=', 'std.id')
            ->where('io.id', '>', 1)
            ->whereNotNull('io.display_standard_id');
        $this->applyIoCsvFilters($q, $filters);
        $code = (string) $q->groupBy('std.code')
            ->orderByRaw('count(*) desc')
            ->value('std.code');

        return in_array($code, $known, true) ? $code : 'isad';
    }

    /**
     * Dublin Core CSV. Emits the DC elements (sourced from the same fields the
     * DC editor uses: creators via event type 111, subjects tax 35, coverage
     * via place tax 42, type tax 62, plus core identifier/title/description and
     * the parent as dc:relation). Processed in id-chunks with batch lookups so
     * there is no per-row N+1. #1434.
     */
    public function streamInformationObjectCsvDc(array $filters)
    {
        $limit = (int) ($filters['limit'] ?? 0);

        return $this->csvDownload(
            'information-objects-dc-' . date('Ymd-His') . '.csv',
            ['legacyId', 'identifier', 'title', 'creator', 'subject', 'description', 'publisher', 'contributor', 'date', 'type', 'coverage', 'relation', 'culture'],
            function ($out) use ($filters, $limit) {
                $idq = DB::table('information_object as io')->where('io.id', '>', 1)->select('io.id');
                $this->applyIoCsvFilters($idq, $filters);
                $idq->orderBy('io.lft');
                if ($limit > 0) {
                    $idq->limit($limit);
                }
                $ids = $idq->pluck('io.id')->all();

                foreach (array_chunk($ids, 500) as $chunk) {
                    $core = DB::table('information_object as io')
                        ->leftJoin('information_object_i18n as i18n', fn ($j) => $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en'))
                        ->leftJoin('actor_i18n as repo', fn ($j) => $j->on('io.repository_id', '=', 'repo.id')->where('repo.culture', '=', 'en'))
                        ->whereIn('io.id', $chunk)
                        ->select('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'i18n.scope_and_content', 'repo.authorized_form_of_name as repository')
                        ->get()->keyBy('id');

                    // Events -> creators (type 111), contributors (other actor events), date.
                    $events = DB::table('event')
                        ->leftJoin('event_i18n as ei', fn ($j) => $j->on('event.id', '=', 'ei.id')->where('ei.culture', '=', 'en'))
                        ->leftJoin('actor_i18n as ai', fn ($j) => $j->on('event.actor_id', '=', 'ai.id')->where('ai.culture', '=', 'en'))
                        ->whereIn('event.object_id', $chunk)
                        ->select('event.object_id', 'event.type_id', 'event.start_date', 'ei.date as date_display', 'ai.authorized_form_of_name as actor_name')
                        ->get()->groupBy('object_id');

                    // Access-point terms -> subject (35), coverage/place (42), type (62).
                    $terms = DB::table('object_term_relation as otr')
                        ->join('term', 'otr.term_id', '=', 'term.id')
                        ->leftJoin('term_i18n as ti', fn ($j) => $j->on('otr.term_id', '=', 'ti.id')->where('ti.culture', '=', 'en'))
                        ->whereIn('otr.object_id', $chunk)
                        ->whereIn('term.taxonomy_id', [35, 42, 62])
                        ->select('otr.object_id', 'term.taxonomy_id', 'ti.name')
                        ->get()->groupBy('object_id');

                    // Parent titles for dc:relation (isPartOf).
                    $parentIds = array_filter(array_unique(array_map(fn ($r) => (int) $r->parent_id, $core->all())), fn ($p) => $p > 1);
                    $parents = $parentIds
                        ? DB::table('information_object_i18n')->whereIn('id', $parentIds)->where('culture', 'en')->pluck('title', 'id')->all()
                        : [];

                    foreach ($chunk as $id) {
                        $r = $core[$id] ?? null;
                        if (! $r) {
                            continue;
                        }
                        $evs = $events[$id] ?? collect();
                        $trm = $terms[$id] ?? collect();
                        $pick = function ($col, $where) use ($trm) {
                            return $trm->filter($where)->pluck($col)->filter()->unique()->implode(' | ');
                        };
                        $creators = $evs->where('type_id', 111)->pluck('actor_name')->filter()->unique()->implode(' | ');
                        $contribs = $evs->where('type_id', '!=', 111)->pluck('actor_name')->filter()->unique()->implode(' | ');
                        $date = $evs->pluck('date_display')->filter()->first()
                            ?: $evs->pluck('start_date')->filter()->first() ?: '';

                        fputcsv($out, [
                            $r->id,
                            $r->identifier,
                            $r->title,
                            $creators,
                            $pick('name', fn ($t) => (int) $t->taxonomy_id === 35),  // subject
                            $r->scope_and_content,
                            $r->repository,                                            // publisher
                            $contribs,
                            $date,
                            $pick('name', fn ($t) => (int) $t->taxonomy_id === 62),  // type
                            $pick('name', fn ($t) => (int) $t->taxonomy_id === 42),  // coverage (place)
                            $parents[(int) $r->parent_id] ?? '',                      // relation (isPartOf)
                            'en',
                        ]);
                    }
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
