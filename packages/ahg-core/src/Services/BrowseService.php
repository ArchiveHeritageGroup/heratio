<?php

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

abstract class BrowseService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    abstract protected function getTable(): string;

    abstract protected function getI18nTable(): string;

    abstract protected function getI18nNameColumn(): string;

    protected function getBaseJoins($query)
    {
        $table = $this->getTable();

        return $query
            ->join($this->getI18nTable(), "{$table}.id", '=', $this->getI18nTable() . '.id')
            ->join('object', "{$table}.id", '=', 'object.id')
            ->join('slug', "{$table}.id", '=', 'slug.object_id')
            ->where($this->getI18nTable() . '.culture', $this->culture);
    }

    protected function getBaseSelect(): array
    {
        $table = $this->getTable();
        $i18nTable = $this->getI18nTable();
        $nameCol = $this->getI18nNameColumn();

        return [
            "{$table}.id",
            "{$i18nTable}.{$nameCol} as name",
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getSortOptions(): array
    {
        return [
            'alphabetic' => __('Name'),
            'lastUpdated' => __('Date modified'),
            'identifier' => __('Identifier'),
        ];
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        $i18nTable = $this->getI18nTable();
        $nameCol = $this->getI18nNameColumn();

        switch ($sort) {
            case 'alphabetic':
                $query->orderBy("{$i18nTable}.{$nameCol}", $sortDir);
                break;
            case 'identifier':
                $query->orderBy('actor.description_identifier', $sortDir);
                $query->orderBy("{$i18nTable}.{$nameCol}", $sortDir);
                break;
            case 'lastUpdated':
            default:
                $query->orderBy('object.updated_at', $sortDir);
                break;
        }

        return $query;
    }

    protected function applySearch($query, string $subquery)
    {
        if ($subquery !== '') {
            $i18nTable = $this->getI18nTable();
            $nameCol = $this->getI18nNameColumn();
            $query->where("{$i18nTable}.{$nameCol}", 'LIKE', "%{$subquery}%");
        }

        return $query;
    }

    public function browse(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = max(1, min(100, (int) ($params['limit'] ?? SettingHelper::hitsPerPage())));
        $skip = ($page - 1) * $limit;
        $sort = $params['sort'] ?? 'lastUpdated';
        $sortDir = !empty($params['sortDir']) ? $params['sortDir'] : (($sort === 'lastUpdated') ? 'desc' : 'asc');
        $subquery = trim($params['subquery'] ?? '');

        try {
            $query = DB::table($this->getTable());
            $query = $this->getBaseJoins($query);
            $query->select($this->getBaseSelect());

            $query = $this->applySearch($query, $subquery);

            $total = $query->count();

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

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
