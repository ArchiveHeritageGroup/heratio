<?php

/**
 * UserBrowseService - Service for Heratio
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

namespace AhgUserManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class UserBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'user';
    }

    protected function getI18nTable(): string
    {
        return 'actor_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'authorized_form_of_name';
    }

    protected function getBaseJoins($query)
    {
        // LEFT join: a user saved without a name (optional on the add form)
        // still lists, under the name fallback below.
        return $query
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('user.id', '=', 'actor_i18n.id')->where('actor_i18n.culture', '=', $this->culture);
            })
            ->join('object', 'user.id', '=', 'object.id')
            ->leftJoin('slug', 'user.id', '=', 'slug.object_id')
            ->leftJoin('acl_user_group', 'user.id', '=', 'acl_user_group.user_id')
            ->leftJoin('acl_group_i18n', function ($join) {
                $join->on('acl_user_group.group_id', '=', 'acl_group_i18n.id')
                    ->where('acl_group_i18n.culture', '=', $this->culture);
            });
    }

    /** The name shown and sorted on: this language's, else any language's, else the username. */
    protected function nameSql(): string
    {
        return "COALESCE(NULLIF(actor_i18n.authorized_form_of_name, ''), "
            ."(SELECT x.authorized_form_of_name FROM actor_i18n x WHERE x.id = user.id AND x.authorized_form_of_name <> '' LIMIT 1), "
            .'user.username)';
    }

    /** Search the name, the username and the email address. */
    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $like = '%'.addcslashes($subquery, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw($this->nameSql().' LIKE ?', [$like])
                    ->orWhere('user.username', 'LIKE', $like)
                    ->orWhere('user.email', 'LIKE', $like);
            });
        }

        return $query;
    }

    protected function getBaseSelect(): array
    {
        // Not used - browse() uses raw select for GROUP_CONCAT
        return [];
    }

    public function browse(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?: 'alphabetic';
        $sortDir = ($sort === 'lastUpdated') ? 'desc' : ($params['sortDir'] ?? 'asc');
        $subquery = trim($params['subquery'] ?? '');
        $status = $params['status'] ?? 'active';

        try {
            $query = DB::table('user');
            $query = $this->getBaseJoins($query);

            // Apply status filter
            if ($status === 'active') {
                $query->where('user.active', 1);
            } elseif ($status === 'inactive') {
                $query->where('user.active', 0);
            }
            // 'all' = no filter

            $query->select(DB::raw(implode(', ', [
                'user.id',
                $this->nameSql().' as name',
                'user.username',
                'user.email',
                'user.active',
                'object.updated_at',
                'slug.slug',
                'GROUP_CONCAT(DISTINCT acl_group_i18n.name ORDER BY acl_group_i18n.name SEPARATOR \', \') as `groups`',
            ])));

            $query->groupBy(
                'user.id',
                'actor_i18n.authorized_form_of_name',
                'user.username',
                'user.email',
                'user.active',
                'object.updated_at',
                'slug.slug'
            );

            $query = $this->applySearch($query, $subquery);

            // Count total (without GROUP BY)
            $countQuery = DB::table('user')
                ->leftJoin('actor_i18n', function ($j) {
                    $j->on('user.id', '=', 'actor_i18n.id')->where('actor_i18n.culture', '=', $this->culture);
                })
                ->join('object', 'user.id', '=', 'object.id')
                ->leftJoin('slug', 'user.id', '=', 'slug.object_id');

            // Apply same status filter to count
            if ($status === 'active') {
                $countQuery->where('user.active', 1);
            } elseif ($status === 'inactive') {
                $countQuery->where('user.active', 0);
            }

            $countQuery = $this->applySearch($countQuery, $subquery);
            $total = $countQuery->count();

            $query = $this->applySort($query, $sort, $sortDir);

            $rows = $query->skip($skip)->take($limit)->get();

            $hits = [];
            foreach ($rows as $row) {
                $hits[] = $this->transformRow($row);
            }

            return [
                'hits' => $hits,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (\Exception $e) {
            \Log::error(static::class.' browse error: '.$e->getMessage());

            return [
                'hits' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
            ];
        }
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderByRaw($this->nameSql().' '.($sortDir === 'desc' ? 'desc' : 'asc'));
                break;
            case 'email':
                $query->orderBy('user.email', $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'username' => $row->username ?? '',
            'email' => $row->email ?? '',
            'active' => $row->active ?? 0,
            'groups' => $row->groups ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
