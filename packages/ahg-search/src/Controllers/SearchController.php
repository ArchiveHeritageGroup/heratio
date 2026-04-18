<?php

/**
 * SearchController - Controller for Heratio
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



namespace AhgSearch\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\AclService;
use AhgCore\Services\SettingHelper;
use AhgSearch\Services\ElasticsearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function __construct(
        protected ElasticsearchService $elasticsearch,
    ) {}

    /**
     * Full-text search results page with faceted filtering.
     */
    public function search(Request $request)
    {
        $query   = trim($request->input('q', ''));
        $page    = max(1, (int) $request->input('page', 1));
        $limit   = 30;
        $repo    = $request->input('repository') ? (int) $request->input('repository') : null;
        $level   = $request->input('level') ? (int) $request->input('level') : null;
        $dateFrom = $request->input('dateFrom') ?: null;
        $dateTo  = $request->input('dateTo') ?: null;
        $hasDo   = $request->has('hasDigitalObject') ? (bool) $request->input('hasDigitalObject') : null;
        $mediaType = $request->input('mediaType') ? (int) $request->input('mediaType') : null;
        $sort    = $request->input('sort', 'relevance');

        $hasFilters = $repo || $level || $dateFrom || $dateTo || $hasDo !== null || $mediaType;

        // If no query and no filters, show empty search page
        if ($query === '' && !$hasFilters) {
            return view('ahg-search::search', [
                'query'        => '',
                'pager'        => new SimplePager(['hits' => [], 'total' => 0, 'page' => 1, 'limit' => $limit]),
                'aggregations' => [],
                'activeFilters' => [],
                'sort'         => $sort,
            ]);
        }

        // Use advanced search with facets
        $results = $this->elasticsearch->advancedSearch([
            'query'           => $query,
            'repository'      => $repo,
            'level'           => $level,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'hasDigitalObject' => $hasDo,
            'mediaType'       => $mediaType,
            'sort'            => $sort,
            'page'            => $page,
            'limit'           => $limit,
        ]);

        $pager = new SimplePager([
            'hits'  => $results['hits'],
            'total' => $results['total'],
            'page'  => $page,
            'limit' => $limit,
        ]);

        // Build active filter labels for display
        $activeFilters = $this->buildActiveFilters($repo, $level, $dateFrom, $dateTo, $hasDo, $mediaType, $results['aggregations'] ?? []);

        return view('ahg-search::search', [
            'query'        => $query,
            'pager'        => $pager,
            'aggregations' => $results['aggregations'] ?? [],
            'activeFilters' => $activeFilters,
            'sort'         => $sort,
        ]);
    }

    /**
     * Dedicated advanced search page with full filter form.
     */
    public function advanced(Request $request)
    {
        $repositories = $this->elasticsearch->getRepositoryList();
        $levels       = $this->elasticsearch->getLevelsOfDescription();
        $mediaTypes   = $this->elasticsearch->getMediaTypes();

        // If the form was submitted, redirect to the main search with params
        if ($request->has('submitted')) {
            $params = array_filter([
                'q'               => $request->input('q'),
                'repository'      => $request->input('repository'),
                'level'           => $request->input('level'),
                'dateFrom'        => $request->input('dateFrom'),
                'dateTo'          => $request->input('dateTo'),
                'hasDigitalObject' => $request->input('hasDigitalObject'),
                'mediaType'       => $request->input('mediaType'),
                'sort'            => $request->input('sort'),
            ], fn($v) => $v !== null && $v !== '');

            return redirect()->route('search', $params);
        }

        return view('ahg-search::advanced', [
            'repositories' => $repositories,
            'levels'       => $levels,
            'mediaTypes'   => $mediaTypes,
            'query'        => $request->input('q', ''),
            'sort'         => $request->input('sort', 'relevance'),
        ]);
    }

    /**
     * Autocomplete JSON endpoint.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if ($query === '' || mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $raw = $this->elasticsearch->autocomplete($query);
        $results = [];

        foreach ($raw['hits']['hits'] ?? [] as $hit) {
            $type = $this->resolveType($hit['_index']);
            $source = $hit['_source'] ?? [];
            $i18n = $source['i18n']['en'] ?? [];

            $results[] = [
                'title'      => $i18n['title'] ?? $i18n['authorizedFormOfName'] ?? '[Untitled]',
                'slug'       => $source['slug'] ?? '',
                'type'       => $type,
                'identifier' => $source['identifier'] ?? null,
            ];
        }

        return response()->json($results);
    }

    /**
     * Description Updates — recently added/modified records across all entity types.
     */
    public function descriptionUpdates(Request $request)
    {
        // Require authenticated admin user
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (!AclService::canAdmin(Auth::id())) {
            abort(403, 'Insufficient permissions');
        }

        $className         = (string) $request->input('className', '');
        $dateStart         = (string) $request->input('dateStart', '');
        $dateEnd           = (string) $request->input('dateEnd', '');
        $dateOf            = (string) $request->input('dateOf', 'updated'); // created or updated
        $publicationStatus = (string) $request->input('publicationStatus', '');
        $userName          = (string) $request->input('user', '');
        $page            = max(1, (int) $request->input('page', 1));
        $limit           = SettingHelper::hitsPerPage();

        // Map className filter to class_name values in object table
        $classNameMap = [
            'QubitInformationObject' => 'QubitInformationObject',
            'QubitActor'             => 'QubitActor',
            'QubitRepository'        => 'QubitRepository',
            'QubitTerm'              => 'QubitTerm',
            'QubitFunction'          => 'QubitFunction',
        ];

        // Entity type dropdown options for the view
        $entityTypes = [
            ''                       => 'All',
            'QubitInformationObject'  => 'Information objects',
            'QubitActor'              => 'Authority records',
            'QubitRepository'         => 'Repositories',
            'QubitTerm'               => 'Terms',
            'QubitFunction'           => 'Functions',
        ];

        // Try ahg_audit_log first, fall back to object table
        $hasAuditLog = DB::getSchemaBuilder()->hasTable('ahg_audit_log');

        if ($hasAuditLog) {
            $results = $this->descriptionUpdatesFromAuditLog(
                $className, $dateStart, $dateEnd, $dateOf, $userName, $page, $limit
            );
        } else {
            $results = $this->descriptionUpdatesFromObjectTable(
                $className, $dateStart, $dateEnd, $dateOf, $publicationStatus, $page, $limit
            );
        }

        // Get users for the filter dropdown — fall back to username when actor_i18n
        // has no authorized_form_of_name, and skip blank entries entirely.
        $users = DB::table('user')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('actor_i18n.id', '=', 'user.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->select(
                'user.id',
                DB::raw('COALESCE(NULLIF(TRIM(actor_i18n.authorized_form_of_name), ""), user.username) AS display_name')
            )
            ->whereNotNull('user.username')
            ->where('user.username', '!=', '')
            ->orderBy('display_name')
            ->get()
            ->filter(fn ($u) => !empty(trim((string) $u->display_name)))
            ->pluck('display_name', 'id')
            ->toArray();

        return view('ahg-search::description-updates', [
            'results'           => $results['items'],
            'pager'             => $results['pager'],
            'entityTypes'       => $entityTypes,
            'users'             => $users,
            'className'         => $className,
            'dateStart'         => $dateStart,
            'dateEnd'           => $dateEnd,
            'dateOf'            => $dateOf,
            'publicationStatus' => $publicationStatus,
            'userName'          => $userName,
        ]);
    }

    /**
     * Query ahg_audit_log for description updates.
     */
    protected function descriptionUpdatesFromAuditLog(
        string $className, string $dateStart, string $dateEnd,
        string $dateOf, string $userName, int $page, int $limit
    ): array {
        // Map class names to audit log entity_type values
        $auditEntityMap = [
            'QubitInformationObject' => 'ioManage',
            'QubitActor'             => 'actor',
            'QubitRepository'        => 'Institution',
            'QubitTerm'              => 'term',
            'QubitFunction'          => 'functionManage',
        ];

        $query = DB::table('ahg_audit_log')
            ->select([
                'ahg_audit_log.id',
                'ahg_audit_log.entity_type',
                'ahg_audit_log.entity_id',
                'ahg_audit_log.entity_title',
                'ahg_audit_log.entity_slug',
                'ahg_audit_log.action',
                'ahg_audit_log.username',
                'ahg_audit_log.created_at',
            ])
            ->whereIn('ahg_audit_log.action', ['create', 'update'])
            ->orderBy('ahg_audit_log.created_at', 'desc');

        if ($className && isset($auditEntityMap[$className])) {
            $query->where('ahg_audit_log.entity_type', $auditEntityMap[$className]);
        }

        if ($dateStart) {
            $query->where('ahg_audit_log.created_at', '>=', $dateStart . ' 00:00:00');
        }

        if ($dateEnd) {
            $query->where('ahg_audit_log.created_at', '<=', $dateEnd . ' 23:59:59');
        }

        if ($userName) {
            $query->where('ahg_audit_log.user_id', $userName);
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;
        $rows = $query->offset($offset)->limit($limit)->get();

        // Reverse map for display
        $reverseEntityMap = array_flip($auditEntityMap);

        $items = $rows->map(function ($row) use ($reverseEntityMap) {
            $entityClass = $reverseEntityMap[$row->entity_type] ?? $row->entity_type;
            return (object) [
                'title'       => $row->entity_title ?: '[Untitled]',
                'slug'        => $row->entity_slug,
                'entity_type' => $this->humanEntityType($entityClass),
                'class_name'  => $entityClass,
                'repository'  => '',
                'date'        => $row->created_at,
                'username'    => $row->username ?: '',
                'action'      => $row->action,
            ];
        });

        $pager = new SimplePager([
            'hits'  => $items->toArray(),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        return ['items' => $items, 'pager' => $pager];
    }

    /**
     * Fallback: query the object table for description updates.
     */
    protected function descriptionUpdatesFromObjectTable(
        string $className, string $dateStart, string $dateEnd,
        string $dateOf, string $publicationStatus, int $page, int $limit
    ): array {
        $dateColumn = $dateOf === 'created' ? 'object.created_at' : 'object.updated_at';

        $query = DB::table('object')
            ->select([
                'object.id',
                'object.class_name',
                'object.created_at',
                'object.updated_at',
            ])
            ->whereIn('object.class_name', [
                'QubitInformationObject', 'QubitActor', 'QubitRepository', 'QubitTerm',
            ])
            ->orderBy($dateColumn, 'desc');

        if ($className) {
            $query->where('object.class_name', $className);
        }

        if ($dateStart) {
            $query->where($dateColumn, '>=', $dateStart . ' 00:00:00');
        }

        if ($dateEnd) {
            $query->where($dateColumn, '<=', $dateEnd . ' 23:59:59');
        }

        // Publication status filter (applies to information objects only, status type_id=158)
        if ($publicationStatus) {
            $statusId = $publicationStatus === 'published' ? 160 : 159; // 160=published, 159=draft
            $query->where(function ($q) use ($statusId) {
                $q->where('object.class_name', '!=', 'QubitInformationObject')
                  ->orWhereExists(function ($sub) use ($statusId) {
                      $sub->select(DB::raw(1))
                          ->from('status')
                          ->whereColumn('status.object_id', 'object.id')
                          ->where('status.type_id', 158)
                          ->where('status.status_id', $statusId);
                  });
            });
        }

        $total = $query->count();
        $offset = ($page - 1) * $limit;
        $rows = $query->offset($offset)->limit($limit)->get();

        // Now enrich rows with names via i18n tables
        $items = $rows->map(function ($row) {
            $title = $this->getEntityTitle($row->id, $row->class_name);
            $slug  = DB::table('slug')->where('object_id', $row->id)->value('slug') ?? '';

            return (object) [
                'title'       => $title ?: '[Untitled]',
                'slug'        => $slug,
                'entity_type' => $this->humanEntityType($row->class_name),
                'class_name'  => $row->class_name,
                'repository'  => '',
                'date'        => $row->updated_at,
                'username'    => '',
                'action'      => '',
            ];
        });

        $pager = new SimplePager([
            'hits'  => $items->toArray(),
            'total' => $total,
            'page'  => $page,
            'limit' => $limit,
        ]);

        return ['items' => $items, 'pager' => $pager];
    }

    /**
     * Get the display title for an entity by id and class_name.
     */
    protected function getEntityTitle(int $id, string $className): string
    {
        return match ($className) {
            'QubitInformationObject' => (string) DB::table('information_object_i18n')
                ->where('id', $id)->where('culture', 'en')->value('title'),
            'QubitActor', 'QubitRepository' => (string) DB::table('actor_i18n')
                ->where('id', $id)->where('culture', 'en')->value('authorized_form_of_name'),
            'QubitTerm' => (string) DB::table('term_i18n')
                ->where('id', $id)->where('culture', 'en')->value('name'),
            default => '',
        };
    }

    /**
     * Convert class_name to human-readable entity type.
     */
    protected function humanEntityType(string $className): string
    {
        return match ($className) {
            'QubitInformationObject' => 'Archival description',
            'QubitActor'             => 'Authority record',
            'QubitRepository'        => 'Repository',
            'QubitTerm'              => 'Term',
            'QubitFunction'          => 'Function',
            default                  => $className,
        };
    }

    /**
     * Global search/replace in information_object_i18n text fields.
     */
    public function globalReplace(Request $request)
    {
        // Require authenticated admin user
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        if (!AclService::canAdmin(Auth::id())) {
            abort(403, 'Insufficient permissions');
        }

        // Available i18n columns for replacement
        $columns = [
            'title'                              => 'Title',
            'alternate_title'                    => 'Alternate title',
            'edition'                            => 'Edition',
            'extent_and_medium'                  => 'Extent and medium',
            'archival_history'                   => 'Archival history',
            'acquisition'                        => 'Acquisition',
            'scope_and_content'                  => 'Scope and content',
            'appraisal'                          => 'Appraisal',
            'accruals'                           => 'Accruals',
            'arrangement'                        => 'Arrangement',
            'access_conditions'                  => 'Access conditions',
            'reproduction_conditions'            => 'Reproduction conditions',
            'physical_characteristics'           => 'Physical characteristics',
            'finding_aids'                       => 'Finding aids',
            'location_of_originals'              => 'Location of originals',
            'location_of_copies'                 => 'Location of copies',
            'related_units_of_description'       => 'Related units of description',
            'institution_responsible_identifier'  => 'Institution responsible identifier',
            'rules'                              => 'Rules',
            'sources'                            => 'Sources',
            'revision_history'                   => 'Revision history',
        ];

        if ($request->isMethod('get')) {
            return view('ahg-search::global-replace', [
                'columns'  => $columns,
                'results'  => null,
                'replaced' => false,
                'count'    => 0,
            ]);
        }

        // POST — either preview or execute
        $request->validate([
            'column'      => 'required|in:' . implode(',', array_keys($columns)),
            'pattern'     => 'required|string|min:1',
            'replacement' => 'present|string',
        ]);

        $column        = $request->input('column');
        $pattern       = $request->input('pattern');
        $replacement   = $request->input('replacement', '');
        $caseSensitive = $request->boolean('caseSensitive', true);
        $confirm       = $request->boolean('confirm', false);

        // Build the LIKE clause
        $likeOperator = $caseSensitive ? 'LIKE BINARY' : 'LIKE';

        // Preview: find affected records
        $affected = DB::table('information_object_i18n')
            ->join('slug', 'slug.object_id', '=', 'information_object_i18n.id')
            ->where('information_object_i18n.culture', 'en')
            ->whereRaw("information_object_i18n.`{$column}` {$likeOperator} ?", ['%' . $pattern . '%'])
            ->select([
                'information_object_i18n.id',
                'information_object_i18n.title',
                "information_object_i18n.{$column} as field_value",
                'slug.slug',
            ])
            ->limit(500)
            ->get();

        $totalAffected = DB::table('information_object_i18n')
            ->where('culture', 'en')
            ->whereRaw("`{$column}` {$likeOperator} ?", ['%' . $pattern . '%'])
            ->count();

        if ($confirm && $totalAffected > 0) {
            // Execute the replacement
            if ($caseSensitive) {
                $updatedCount = DB::table('information_object_i18n')
                    ->where('culture', 'en')
                    ->whereRaw("`{$column}` LIKE BINARY ?", ['%' . $pattern . '%'])
                    ->update([
                        $column => DB::raw("REPLACE(`{$column}`, " . DB::getPdo()->quote($pattern) . ", " . DB::getPdo()->quote($replacement) . ")"),
                    ]);
            } else {
                // Case-insensitive replace using REPLACE() — MySQL REPLACE is case-sensitive,
                // so we find rows case-insensitively then apply replace
                $updatedCount = DB::table('information_object_i18n')
                    ->where('culture', 'en')
                    ->whereRaw("`{$column}` LIKE ?", ['%' . $pattern . '%'])
                    ->update([
                        $column => DB::raw("REPLACE(`{$column}`, " . DB::getPdo()->quote($pattern) . ", " . DB::getPdo()->quote($replacement) . ")"),
                    ]);
            }

            return redirect()->route('search.globalReplace')
                ->with('success', "Successfully replaced {$updatedCount} record(s). Column: {$columns[$column]}.");
        }

        // Preview mode — show affected records with snippets
        $previewResults = $affected->map(function ($row) use ($column, $pattern, $replacement) {
            $currentValue = $row->field_value ?? '';
            $snippet = mb_strlen($currentValue) > 200
                ? '...' . mb_substr($currentValue, max(0, mb_strpos(mb_strtolower($currentValue), mb_strtolower($pattern)) - 50), 200) . '...'
                : $currentValue;
            $newSnippet = str_ireplace($pattern, $replacement, $snippet);

            return (object) [
                'id'            => $row->id,
                'title'         => $row->title ?: '[Untitled]',
                'slug'          => $row->slug ?? '',
                'current_value' => $snippet,
                'new_value'     => $newSnippet,
            ];
        });

        return view('ahg-search::global-replace', [
            'columns'        => $columns,
            'results'        => $previewResults,
            'replaced'       => false,
            'count'          => $totalAffected,
            'column'         => $column,
            'pattern'        => $pattern,
            'replacement'    => $replacement,
            'caseSensitive'  => $caseSensitive,
        ]);
    }

    /**
     * Build active filter labels for the result page.
     */
    protected function buildActiveFilters(
        ?int $repo, ?int $level, ?string $dateFrom, ?string $dateTo,
        ?bool $hasDo, ?int $mediaType, array $aggregations
    ): array {
        $filters = [];

        if ($repo) {
            $label = '[Unknown repository]';
            foreach ($aggregations['repositories'] ?? [] as $r) {
                if ((int) $r['id'] === $repo) {
                    $label = $r['label'];
                    break;
                }
            }
            // If not found in aggs, look up directly
            if ($label === '[Unknown repository]') {
                $repos = $this->elasticsearch->getRepositoryList();
                $label = $repos[$repo] ?? $label;
            }
            $filters[] = ['param' => 'repository', 'label' => 'Repository: ' . $label];
        }

        if ($level) {
            $label = '[Unknown level]';
            foreach ($aggregations['levels'] ?? [] as $l) {
                if ((int) $l['id'] === $level) {
                    $label = $l['label'];
                    break;
                }
            }
            if ($label === '[Unknown level]') {
                $levels = $this->elasticsearch->getLevelsOfDescription();
                $label = $levels[$level] ?? $label;
            }
            $filters[] = ['param' => 'level', 'label' => 'Level: ' . $label];
        }

        if ($dateFrom) {
            $filters[] = ['param' => 'dateFrom', 'label' => 'From: ' . $dateFrom];
        }

        if ($dateTo) {
            $filters[] = ['param' => 'dateTo', 'label' => 'To: ' . $dateTo];
        }

        if ($hasDo !== null) {
            $filters[] = ['param' => 'hasDigitalObject', 'label' => $hasDo ? 'Has digital object' : 'No digital object'];
        }

        if ($mediaType) {
            $label = '[Unknown media type]';
            foreach ($aggregations['mediaTypes'] ?? [] as $m) {
                if ((int) $m['id'] === $mediaType) {
                    $label = $m['label'];
                    break;
                }
            }
            $filters[] = ['param' => 'mediaType', 'label' => 'Media: ' . $label];
        }

        return $filters;
    }

    /**
     * Determine entity type from ES index name.
     */
    protected function resolveType(string $index): string
    {
        if (str_contains($index, 'qubitinformationobject')) {
            return 'informationobject';
        }
        if (str_contains($index, 'qubitactor')) {
            return 'actor';
        }
        if (str_contains($index, 'qubitrepository')) {
            return 'repository';
        }
        if (str_contains($index, 'qubitterm')) {
            return 'term';
        }

        return 'unknown';
    }
}
