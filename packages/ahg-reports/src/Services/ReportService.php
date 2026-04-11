<?php

/**
 * ReportService - Service for Heratio
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



namespace AhgReports\Services;

use AhgCore\Constants\TermId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportService
{
    public function getReportStats(): array
    {
        $stats = [];
        $stats['descriptions'] = DB::table('information_object')->count();
        $stats['authorities'] = DB::table('actor')->count();
        $stats['repositories'] = DB::table('repository')->count();
        $stats['accessions'] = DB::table('accession')->count();
        $stats['digital_objects'] = DB::table('digital_object')->count();
        $stats['users'] = DB::table('user')->count();
        $stats['donors'] = DB::table('donor')->count();
        $stats['physical_storage'] = DB::table('physical_object')->count();

        // Publication status counts
        $stats['published'] = DB::table('status')
            ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->where('status_id', TermId::PUBLICATION_STATUS_PUBLISHED)->count();
        $stats['draft'] = DB::table('status')
            ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
            ->where('status_id', TermId::PUBLICATION_STATUS_DRAFT)->count();

        // Recent updates (7 days)
        $stats['recent_updates'] = DB::table('object')
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        return $stats;
    }

    public function reportAccessions(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? now()->subYear()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $dateOf = $params['dateOf'] ?? 'created_at';
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('accession')
            ->join('object', 'accession.id', '=', 'object.id')
            ->leftJoin('accession_i18n', function ($join) use ($culture) {
                $join->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $culture);
            })
            ->select(
                'accession.id', 'accession.identifier',
                'accession_i18n.title', 'accession_i18n.scope_and_content',
                'accession_i18n.appraisal', 'accession_i18n.processing_notes',
                'object.created_at', 'object.updated_at'
            );

        $dateCol = $dateOf === 'updated_at' ? 'object.updated_at' : 'object.created_at';
        if ($dateStart) $query->where($dateCol, '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where($dateCol, '<=', $dateEnd . ' 23:59:59');

        $total = $query->count();
        $results = $query->orderByDesc($dateCol)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    public function reportDescriptions(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? now()->subMonth()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $dateOf = $params['dateOf'] ?? 'created_at';
        $levelId = $params['level'] ?? null;
        $pubStatus = $params['publicationStatus'] ?? null;
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('information_object as io')
            ->join('object', 'io.id', '=', 'object.id')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('status', function ($join) {
                $join->on('io.id', '=', 'status.object_id')
                    ->where('status.type_id', '=', TermId::STATUS_TYPE_PUBLICATION);
            })
            ->leftJoin('term_i18n as level_term', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'level_term.id')
                    ->where('level_term.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->leftJoin('repository as repo', 'io.repository_id', '=', 'repo.id')
            ->leftJoin('repository_i18n as repo_i18n', function ($join) use ($culture) {
                $join->on('repo.id', '=', 'repo_i18n.id')
                    ->where('repo_i18n.culture', '=', $culture);
            })
            ->select(
                'io.id', 'io.identifier', 'io.level_of_description_id',
                'io_i18n.title', 'io_i18n.alternate_title',
                'io_i18n.extent_and_medium', 'io_i18n.archival_history',
                'io_i18n.acquisition', 'io_i18n.scope_and_content',
                'io_i18n.appraisal', 'io_i18n.accruals', 'io_i18n.arrangement',
                'io_i18n.access_conditions', 'io_i18n.reproduction_conditions',
                'io_i18n.physical_characteristics', 'io_i18n.finding_aids',
                'io_i18n.location_of_originals', 'io_i18n.location_of_copies',
                'io_i18n.related_units_of_description',
                'io_i18n.institution_responsible_identifier',
                'io_i18n.rules', 'io_i18n.sources', 'io_i18n.revision_history',
                'io_i18n.culture',
                'level_term.name as level_name',
                'status.status_id as publication_status_id',
                'repo_i18n.authorized_form_of_name as repository_name',
                'object.created_at', 'object.updated_at'
            );

        $dateCol = $dateOf === 'updated_at' ? 'object.updated_at' : 'object.created_at';
        if ($dateStart) $query->where($dateCol, '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where($dateCol, '<=', $dateEnd . ' 23:59:59');
        if ($levelId) $query->where('io.level_of_description_id', $levelId);
        if ($pubStatus) $query->where('status.status_id', $pubStatus);

        $total = $query->count();
        $results = $query->orderByDesc($dateCol)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    public function reportAuthorities(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? now()->subYear()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $dateOf = $params['dateOf'] ?? 'created_at';
        $entityType = $params['entityType'] ?? null;
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('actor')
            ->join('object', 'actor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as type_term', function ($join) use ($culture) {
                $join->on('actor.entity_type_id', '=', 'type_term.id')
                    ->where('type_term.culture', '=', $culture);
            })
            ->leftJoin('slug', function ($join) {
                $join->on('actor.id', '=', 'slug.object_id');
            })
            ->where('object.class_name', 'QubitActor')
            ->select(
                'actor.id', 'actor.entity_type_id', 'actor.description_identifier',
                'actor_i18n.authorized_form_of_name', 'actor_i18n.dates_of_existence',
                'actor_i18n.history',
                'type_term.name as entity_type_name',
                'slug.slug',
                'object.created_at', 'object.updated_at'
            );

        $dateCol = $dateOf === 'updated_at' ? 'object.updated_at' : 'object.created_at';
        if ($dateStart) $query->where($dateCol, '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where($dateCol, '<=', $dateEnd . ' 23:59:59');
        if ($entityType) $query->where('actor.entity_type_id', $entityType);

        $total = $query->count();
        $results = $query->orderByDesc($dateCol)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    public function reportDonors(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? now()->subYear()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $dateOf = $params['dateOf'] ?? 'created_at';
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('donor')
            ->join('object', 'donor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('donor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('contact_information', 'donor.id', '=', 'contact_information.actor_id')
            ->leftJoin('contact_information_i18n', function ($join) use ($culture) {
                $join->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $culture);
            })
            ->select(
                'donor.id',
                'actor_i18n.authorized_form_of_name',
                'contact_information.email', 'contact_information.telephone',
                'contact_information_i18n.city', 'contact_information_i18n.region',
                'object.created_at', 'object.updated_at'
            );

        $dateCol = $dateOf === 'updated_at' ? 'object.updated_at' : 'object.created_at';
        if ($dateStart) $query->where($dateCol, '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where($dateCol, '<=', $dateEnd . ' 23:59:59');

        $total = $query->count();
        $results = $query->orderByDesc($dateCol)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['results' => $results, 'total' => $total, 'page' => $page, 'lastPage' => max(1, (int) ceil($total / $limit)), 'limit' => $limit];
    }

    public function reportRepositories(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? now()->subMonth()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $dateOf = $params['dateOf'] ?? 'created_at';
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('repository')
            ->join('object', 'repository.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('repository_i18n', function ($join) use ($culture) {
                $join->on('repository.id', '=', 'repository_i18n.id')
                    ->where('repository_i18n.culture', '=', $culture);
            })
            ->select(
                'repository.id', 'repository.identifier',
                'repository.desc_status_id', 'repository.desc_detail_id',
                'repository.desc_identifier',
                'actor_i18n.authorized_form_of_name',
                'repository_i18n.geocultural_context', 'repository_i18n.collecting_policies',
                'repository_i18n.buildings', 'repository_i18n.holdings',
                'repository_i18n.finding_aids', 'repository_i18n.opening_times',
                'repository_i18n.access_conditions', 'repository_i18n.disabled_access',
                'repository_i18n.research_services', 'repository_i18n.reproduction_services',
                'repository_i18n.public_facilities',
                'repository_i18n.desc_institution_identifier',
                'repository_i18n.desc_rules', 'repository_i18n.desc_sources',
                'repository_i18n.desc_revision_history',
                'object.created_at', 'object.updated_at'
            );

        $dateCol = $dateOf === 'updated_at' ? 'object.updated_at' : 'object.created_at';
        if ($dateStart) $query->where($dateCol, '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where($dateCol, '<=', $dateEnd . ' 23:59:59');

        $total = $query->count();
        $results = $query->orderByDesc($dateCol)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['results' => $results, 'total' => $total, 'page' => $page, 'lastPage' => max(1, (int) ceil($total / $limit)), 'limit' => $limit];
    }

    public function reportPhysicalStorage(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? now()->subYear()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $dateOf = $params['dateOf'] ?? 'created_at';
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('physical_object')
            ->join('object', 'physical_object.id', '=', 'object.id')
            ->leftJoin('physical_object_i18n', function ($join) use ($culture) {
                $join->on('physical_object.id', '=', 'physical_object_i18n.id')
                    ->where('physical_object_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as type_term', function ($join) use ($culture) {
                $join->on('physical_object.type_id', '=', 'type_term.id')
                    ->where('type_term.culture', '=', $culture);
            })
            ->select(
                'physical_object.id', 'physical_object.type_id',
                'physical_object_i18n.name', 'physical_object_i18n.location', 'physical_object_i18n.description',
                'type_term.name as type_name',
                'object.created_at', 'object.updated_at'
            );

        $dateCol = $dateOf === 'updated_at' ? 'object.updated_at' : 'object.created_at';
        if ($dateStart) $query->where($dateCol, '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where($dateCol, '<=', $dateEnd . ' 23:59:59');

        $total = $query->count();
        $results = $query->orderByDesc($dateCol)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['results' => $results, 'total' => $total, 'page' => $page, 'lastPage' => max(1, (int) ceil($total / $limit)), 'limit' => $limit];
    }

    public function reportUserActivity(array $params): array
    {
        $dateStart = $params['dateStart'] ?? now()->subMonth()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $actionUser = $params['actionUser'] ?? null;
        $userAction = $params['userAction'] ?? null;
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $auditTable = Schema::hasTable('ahg_audit_log') ? 'ahg_audit_log' : (Schema::hasTable('audit_log') ? 'audit_log' : null);
        if (!$auditTable) {
            return ['results' => collect(), 'total' => 0, 'page' => 1, 'lastPage' => 1, 'limit' => $limit, 'auditTable' => null];
        }

        $query = DB::table($auditTable);

        if ($dateStart) $query->where('created_at', '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where('created_at', '<=', $dateEnd . ' 23:59:59');
        if ($actionUser) $query->where('username', $actionUser);
        if ($userAction) $query->where('action', $userAction);

        $total = $query->count();
        $results = $query->orderByDesc('created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
            'auditTable' => $auditTable,
        ];
    }

    public function reportUpdates(array $params): array
    {
        $dateStart = $params['dateStart'] ?? now()->subMonth()->format('Y-m-d');
        $dateEnd = $params['dateEnd'] ?? now()->format('Y-m-d');
        $className = $params['className'] ?? null;
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);

        $query = DB::table('object')
            ->where('updated_at', '>=', $dateStart . ' 00:00:00')
            ->where('updated_at', '<=', $dateEnd . ' 23:59:59');

        $classMap = [
            'QubitInformationObject' => 'QubitInformationObject',
            'QubitActor' => 'QubitActor',
            'QubitRepository' => 'QubitRepository',
            'QubitAccession' => 'QubitAccession',
            'QubitPhysicalObject' => 'QubitPhysicalObject',
            'QubitDonor' => 'QubitDonor',
        ];

        if ($className && isset($classMap[$className])) {
            $query->where('class_name', $classMap[$className]);
        } elseif (!$className) {
            $query->whereIn('class_name', array_values($classMap));
        }

        $total = $query->count();
        $results = $query->select('id', 'class_name', 'created_at', 'updated_at')
            ->orderByDesc('updated_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'lastPage' => max(1, (int) ceil($total / $limit)),
            'limit' => $limit,
        ];
    }

    public function reportTaxonomies(array $params): array
    {
        $culture = $params['culture'] ?? 'en';
        $dateStart = $params['dateStart'] ?? null;
        $dateEnd = $params['dateEnd'] ?? null;
        $limit = min((int) ($params['limit'] ?? 20), 100);
        $page = max((int) ($params['page'] ?? 1), 1);
        $sort = $params['sort'] ?? 'nameUp';

        $query = DB::table('taxonomy')
            ->join('object', 'taxonomy.id', '=', 'object.id')
            ->leftJoin('taxonomy_i18n', function ($join) use ($culture) {
                $join->on('taxonomy.id', '=', 'taxonomy_i18n.id')
                    ->where('taxonomy_i18n.culture', '=', $culture);
            })
            ->where('taxonomy.id', '!=', 1)
            ->where('taxonomy.usage', '!=', 'taxonomyOfTaxonomies')
            ->select(
                'taxonomy.id', 'taxonomy.usage',
                'taxonomy_i18n.name', 'taxonomy_i18n.note',
                'object.created_at', 'object.updated_at',
                DB::raw('(SELECT COUNT(*) FROM term WHERE term.taxonomy_id = taxonomy.id) as term_count')
            );

        if ($dateStart) $query->where('object.created_at', '>=', $dateStart . ' 00:00:00');
        if ($dateEnd) $query->where('object.updated_at', '<=', $dateEnd . ' 23:59:59');

        $orderMap = [
            'nameUp' => ['taxonomy_i18n.name', 'asc'],
            'nameDown' => ['taxonomy_i18n.name', 'desc'],
            'updatedUp' => ['object.updated_at', 'asc'],
            'updatedDown' => ['object.updated_at', 'desc'],
        ];
        $order = $orderMap[$sort] ?? ['taxonomy_i18n.name', 'asc'];

        $total = $query->count();
        $results = $query->orderBy($order[0], $order[1])
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return ['results' => $results, 'total' => $total, 'page' => $page, 'lastPage' => max(1, (int) ceil($total / $limit)), 'limit' => $limit];
    }

    public function getLevelsOfDescription(string $culture = 'en'): \Illuminate\Support\Collection
    {
        return DB::table('term')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', 34)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();
    }

    public function getEntityTypes(string $culture = 'en'): \Illuminate\Support\Collection
    {
        return DB::table('term')
            ->join('term_i18n', function ($join) use ($culture) {
                $join->on('term.id', '=', 'term_i18n.id')
                    ->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', 32)
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();
    }

    public function getAvailableCultures(): array
    {
        return DB::table('setting_i18n')
            ->select('culture')
            ->distinct()
            ->pluck('culture')
            ->toArray();
    }

    public function getAuditUsers(): array
    {
        $auditTable = Schema::hasTable('ahg_audit_log') ? 'ahg_audit_log' : 'audit_log';
        return DB::table($auditTable)
            ->select('username')
            ->distinct()
            ->whereNotNull('username')
            ->orderBy('username')
            ->pluck('username')
            ->toArray();
    }

    public function exportCsv(array $data, array $headers, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($data, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($data as $row) {
                fputcsv($out, (array) $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
