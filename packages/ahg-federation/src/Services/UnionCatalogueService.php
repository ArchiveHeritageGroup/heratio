<?php

/**
 * UnionCatalogueService - opt-in union-catalogue read/write helpers.
 *
 * The first slice of the federated GLAM network (#1203). This service owns:
 *
 *   - the member registry (federation_member)
 *   - the single-row opt-in sharing config (federation_share_setting)
 *   - the portable union index (federation_union_record)
 *   - the publish pass that pushes this institution's opt-in, published
 *     discovery metadata into the union index (respecting the opt-in gate)
 *   - the cross-member search surface used by the public route + JSON API
 *
 * Every query is Schema::hasTable-guarded and try/catch wrapped so a fresh
 * install (tables not yet created) degrades to a dignified empty-state rather
 * than a 500. Reads over catalogue data are bounded (limit / paginate); the
 * publish pass streams in id batches and never loads the whole catalogue at
 * once.
 *
 * Carved out as fresh code alongside - never touching - the locked F3
 * SharePoint FederatedSearchService / FederationController / Connectors.
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

namespace AhgFederation\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnionCatalogueService
{
    /**
     * Publication status: status.type_id = 158 (publication status),
     * status.status_id = 160 (Published). Mirrors the OAI / Europeana
     * published-record filter exactly so the union index never exposes
     * a record we would not disseminate elsewhere.
     */
    public const STATUS_TYPE_PUBLICATION = 158;
    public const STATUS_PUBLISHED = 160;

    // -----------------------------------------------------------------
    // Member registry
    // -----------------------------------------------------------------

    /** All members, newest self-member first then by name. */
    public function members(): array
    {
        if (! $this->tableReady('federation_member')) {
            return [];
        }
        try {
            return DB::table('federation_member')
                ->orderByDesc('is_self')
                ->orderBy('name')
                ->limit(500)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Only members included in union searches (is_enabled = 1). */
    public function enabledMembers(): array
    {
        if (! $this->tableReady('federation_member')) {
            return [];
        }
        try {
            return DB::table('federation_member')
                ->where('is_enabled', 1)
                ->orderByDesc('is_self')
                ->orderBy('name')
                ->limit(500)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function findMember(int $id): ?object
    {
        if (! $this->tableReady('federation_member')) {
            return null;
        }
        try {
            return DB::table('federation_member')->where('id', $id)->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** The local self-member row, if one has been registered. */
    public function selfMember(): ?object
    {
        if (! $this->tableReady('federation_member')) {
            return null;
        }
        try {
            return DB::table('federation_member')
                ->where('is_self', 1)
                ->orderBy('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Insert or update a member. Returns the member id on success, null on
     * failure. Enforces a single self-member by clearing is_self on others
     * when this row claims it.
     */
    public function saveMember(array $data, ?int $id = null): ?int
    {
        if (! $this->tableReady('federation_member')) {
            return null;
        }
        try {
            $isSelf = ! empty($data['is_self']) ? 1 : 0;
            $row = [
                'name' => trim((string) ($data['name'] ?? '')),
                'base_url' => $this->nullable($data['base_url'] ?? null),
                'contact' => $this->nullable($data['contact'] ?? null),
                'share_scope' => $this->nullable($data['share_scope'] ?? null),
                'is_self' => $isSelf,
                'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
                'updated_at' => now(),
            ];

            if ($id) {
                DB::table('federation_member')->where('id', $id)->update($row);
                $savedId = $id;
            } else {
                $row['created_at'] = now();
                $savedId = (int) DB::table('federation_member')->insertGetId($row);
            }

            if ($isSelf) {
                DB::table('federation_member')
                    ->where('id', '!=', $savedId)
                    ->where('is_self', 1)
                    ->update(['is_self' => 0, 'updated_at' => now()]);
            }

            return $savedId;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function deleteMember(int $id): bool
    {
        if (! $this->tableReady('federation_member')) {
            return false;
        }
        try {
            // Drop the member's contributed union rows alongside the member.
            if ($this->tableReady('federation_union_record')) {
                DB::table('federation_union_record')->where('member_id', $id)->delete();
            }
            DB::table('federation_member')->where('id', $id)->delete();

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -----------------------------------------------------------------
    // Opt-in sharing config (single row, id=1, default OFF)
    // -----------------------------------------------------------------

    /**
     * The opt-in sharing config. Always returns a safe default object even
     * when the table is missing - default is OFF / published-only.
     */
    public function shareSetting(): object
    {
        $default = (object) [
            'id' => 1,
            'share_enabled' => 0,
            'published_only' => 1,
            'min_level_id' => null,
            'updated_at' => null,
        ];

        if (! $this->tableReady('federation_share_setting')) {
            return $default;
        }
        try {
            $row = DB::table('federation_share_setting')->orderBy('id')->first();

            return $row ?: $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function saveShareSetting(array $data): bool
    {
        if (! $this->tableReady('federation_share_setting')) {
            return false;
        }
        try {
            $row = [
                'share_enabled' => ! empty($data['share_enabled']) ? 1 : 0,
                'published_only' => array_key_exists('published_only', $data)
                    ? (! empty($data['published_only']) ? 1 : 0)
                    : 1,
                'min_level_id' => isset($data['min_level_id']) && $data['min_level_id'] !== ''
                    ? (int) $data['min_level_id']
                    : null,
                'updated_at' => now(),
            ];

            $existing = DB::table('federation_share_setting')->orderBy('id')->first();
            if ($existing) {
                DB::table('federation_share_setting')->where('id', $existing->id)->update($row);
            } else {
                DB::table('federation_share_setting')->insert($row);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Master opt-in switch - default OFF. */
    public function isSharingEnabled(): bool
    {
        return (bool) ((int) ($this->shareSetting()->share_enabled ?? 0));
    }

    // -----------------------------------------------------------------
    // Publish pass - push this institution's opt-in records into the index
    // -----------------------------------------------------------------

    /**
     * Stream this institution's published, opt-in discovery metadata into the
     * union index for the self-member. Idempotent: upserts by
     * (member_id, record_ref). Returns a loud, accounted summary array:
     *   enabled (bool), self_member_id, shared (int), skipped (int),
     *   reasons (array<string,int>), examined (int).
     *
     * Respects the opt-in gate: returns shared=0 with a reason when sharing
     * is disabled or no self-member is registered.
     */
    public function publish(int $batch = 500, ?callable $onProgress = null): array
    {
        $summary = [
            'enabled' => $this->isSharingEnabled(),
            'self_member_id' => null,
            'examined' => 0,
            'shared' => 0,
            'skipped' => 0,
            'reasons' => [],
        ];

        if (! $summary['enabled']) {
            $summary['reasons']['sharing_disabled'] = 1;

            return $summary;
        }

        $self = $this->selfMember();
        if (! $self) {
            $summary['reasons']['no_self_member'] = 1;

            return $summary;
        }
        $summary['self_member_id'] = (int) $self->id;

        if (! $this->tableReady('federation_union_record')
            || ! $this->tableReady('information_object')) {
            $summary['reasons']['index_not_ready'] = 1;

            return $summary;
        }

        $setting = $this->shareSetting();
        $minLevel = $setting->min_level_id ? (int) $setting->min_level_id : null;
        $culture = 'en';
        $base = rtrim((string) (function_exists('url') ? url('/') : ''), '/');

        try {
            $lastId = 0;
            while (true) {
                $rows = $this->fetchPublishBatch($lastId, $batch, $culture);
                if ($rows->isEmpty()) {
                    break;
                }

                foreach ($rows as $r) {
                    $lastId = (int) $r->id;
                    $summary['examined']++;

                    // Optional minimum level-of-description gate.
                    if ($minLevel !== null
                        && (int) ($r->level_of_description_id ?? 0) !== $minLevel) {
                        $summary['skipped']++;
                        $summary['reasons']['below_min_level'] =
                            ($summary['reasons']['below_min_level'] ?? 0) + 1;
                        continue;
                    }

                    $ref = ! empty($r->slug) ? (string) $r->slug : (string) $r->id;
                    $url = ! empty($r->slug)
                        ? $base.'/'.$r->slug
                        : $base.'/informationobject/'.(int) $r->id;

                    DB::table('federation_union_record')->updateOrInsert(
                        ['member_id' => (int) $self->id, 'record_ref' => $ref],
                        [
                            'title' => $this->clip($r->title, 1024),
                            'level' => $this->clip($r->level_label, 255),
                            'dates' => $this->clip($r->date_display, 255),
                            'repository' => $this->clip($r->repository_name, 512),
                            'url' => $this->clip($url, 1024),
                            'indexed_at' => now(),
                        ]
                    );
                    $summary['shared']++;
                }

                if ($onProgress) {
                    $onProgress($summary);
                }

                if ($rows->count() < $batch) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $summary['reasons']['error:'.substr($e->getMessage(), 0, 80)] = 1;
        }

        return $summary;
    }

    /**
     * One page of published IO discovery rows, keyed forward by id for a
     * bounded, memory-safe stream. Joins the publication-status gate, the
     * English i18n title, level label, repository name, and slug.
     */
    protected function fetchPublishBatch(int $afterId, int $batch, string $culture): \Illuminate\Support\Collection
    {
        return DB::table('information_object as io')
            ->join('object as o', 'io.id', '=', 'o.id')
            ->join('status as st', function ($j) {
                $j->on('io.id', '=', 'st.object_id')
                    ->where('st.type_id', '=', self::STATUS_TYPE_PUBLICATION);
            })
            ->leftJoin('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->leftJoin('term_i18n as lvl', function ($j) use ($culture) {
                $j->on('io.level_of_description_id', '=', 'lvl.id')
                    ->where('lvl.culture', '=', $culture);
            })
            ->leftJoin('repository as repo', 'io.repository_id', '=', 'repo.id')
            ->leftJoin('actor_i18n as repoi', function ($j) use ($culture) {
                $j->on('repo.id', '=', 'repoi.id')->where('repoi.culture', '=', $culture);
            })
            ->leftJoin('event as ev', function ($j) {
                $j->on('io.id', '=', 'ev.object_id');
            })
            ->leftJoin('event_i18n as evi', function ($j) use ($culture) {
                $j->on('ev.id', '=', 'evi.id')->where('evi.culture', '=', $culture);
            })
            ->where('st.status_id', '=', self::STATUS_PUBLISHED)
            ->where('io.id', '>', $afterId)
            ->where('io.id', '>', 1)
            ->groupBy('io.id', 'io.level_of_description_id', 'i18n.title',
                'lvl.name', 'repoi.authorized_form_of_name', 's.slug')
            ->orderBy('io.id')
            ->limit($batch)
            ->select([
                'io.id',
                'io.level_of_description_id',
                'i18n.title as title',
                'lvl.name as level_label',
                'repoi.authorized_form_of_name as repository_name',
                's.slug as slug',
                DB::raw('MIN(evi.date) as date_display'),
            ])
            ->get();
    }

    // -----------------------------------------------------------------
    // Union search surface (cross-member, paginated, grouped by member)
    // -----------------------------------------------------------------

    /**
     * Cross-member search of the union index. Returns a structure ready for
     * both the HTML view and the JSON API:
     *   [ total, page, perPage, lastPage, q, groups => [ member, rows[] ] ].
     *
     * Bounded by pagination. Always returns a safe empty structure when the
     * tables are missing - never throws.
     */
    public function search(string $q, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $q = trim($q);

        $empty = [
            'total' => 0,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => 1,
            'q' => $q,
            'memberCount' => 0,
            'groups' => [],
        ];

        if (! $this->tableReady('federation_union_record')
            || ! $this->tableReady('federation_member')) {
            return $empty;
        }

        try {
            $enabledIds = DB::table('federation_member')
                ->where('is_enabled', 1)
                ->pluck('id')
                ->all();

            $empty['memberCount'] = count($enabledIds);
            if (empty($enabledIds)) {
                return $empty;
            }

            $builder = DB::table('federation_union_record as ur')
                ->whereIn('ur.member_id', $enabledIds);

            if ($q !== '') {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
                $builder->where(function ($w) use ($like) {
                    $w->where('ur.title', 'like', $like)
                        ->orWhere('ur.repository', 'like', $like)
                        ->orWhere('ur.dates', 'like', $like);
                });
            }

            $total = (clone $builder)->count();
            $lastPage = max(1, (int) ceil($total / $perPage));

            $rows = $builder
                ->join('federation_member as m', 'm.id', '=', 'ur.member_id')
                ->orderBy('m.name')
                ->orderBy('ur.title')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->select([
                    'ur.id', 'ur.member_id', 'ur.record_ref', 'ur.title',
                    'ur.level', 'ur.dates', 'ur.repository', 'ur.url',
                    'm.name as member_name', 'm.base_url as member_base_url',
                ])
                ->get();

            // Group the current page by member institution.
            $groups = [];
            foreach ($rows as $row) {
                $key = (int) $row->member_id;
                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'member' => (object) [
                            'id' => $row->member_id,
                            'name' => $row->member_name,
                            'base_url' => $row->member_base_url,
                        ],
                        'rows' => [],
                    ];
                }
                $groups[$key]['rows'][] = $row;
            }

            return [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'lastPage' => $lastPage,
                'q' => $q,
                'memberCount' => count($enabledIds),
                'groups' => array_values($groups),
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /** Total rows currently in the union index (for the admin dashboard). */
    public function unionRecordCount(): int
    {
        if (! $this->tableReady('federation_union_record')) {
            return 0;
        }
        try {
            return (int) DB::table('federation_union_record')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    protected function tableReady(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function nullable($v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;

        return ($v === '' || $v === null) ? null : (string) $v;
    }

    protected function clip($v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = (string) $v;

        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }
}
