<?php

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
        return $query
            ->join('actor_i18n', 'user.id', '=', 'actor_i18n.id')
            ->join('object', 'user.id', '=', 'object.id')
            ->join('slug', 'user.id', '=', 'slug.object_id')
            ->leftJoin('acl_user_group', 'user.id', '=', 'acl_user_group.user_id')
            ->leftJoin('acl_group_i18n', function ($join) {
                $join->on('acl_user_group.group_id', '=', 'acl_group_i18n.id')
                    ->where('acl_group_i18n.culture', '=', $this->culture);
            })
            ->where('actor_i18n.culture', $this->culture);
    }

    protected function getBaseSelect(): array
    {
        // Not used — browse() uses raw select for GROUP_CONCAT
        return [];
    }

    public function browse(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? 30)));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'alphabetic';
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
                'actor_i18n.authorized_form_of_name as name',
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
                ->join('actor_i18n', 'user.id', '=', 'actor_i18n.id')
                ->join('object', 'user.id', '=', 'object.id')
                ->join('slug', 'user.id', '=', 'slug.object_id')
                ->where('actor_i18n.culture', $this->culture);

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
            \Log::error(static::class . ' browse error: ' . $e->getMessage());

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
                $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir);
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
