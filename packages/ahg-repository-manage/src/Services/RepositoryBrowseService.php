<?php

namespace AhgRepositoryManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class RepositoryBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'repository';
    }

    protected function getI18nTable(): string
    {
        return 'actor_i18n';
    }

    protected function getI18nNameColumn(): string
    {
        return 'authorized_form_of_name';
    }

    protected function getBaseSelect(): array
    {
        return [
            'repository.id',
            'actor_i18n.authorized_form_of_name as name',
            'actor.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->leftJoin('actor', 'repository.id', '=', 'actor.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('repository.id', '!=', 6); // Exclude root repository
    }

    protected function applySort($query, string $sort, string $sortDir)
    {
        switch ($sort) {
            case 'alphabetic':
                $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir);
                break;
            case 'identifier':
                $query->orderBy('actor.description_identifier', $sortDir);
                $query->orderBy('actor_i18n.authorized_form_of_name', $sortDir);
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
            $query->where(function ($q) use ($subquery) {
                $q->where('actor_i18n.authorized_form_of_name', 'LIKE', "%{$subquery}%")
                  ->orWhere('actor.description_identifier', 'LIKE', "%{$subquery}%");
            });
        }

        return $query;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
