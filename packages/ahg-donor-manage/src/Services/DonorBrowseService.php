<?php

namespace AhgDonorManage\Services;

use AhgCore\Services\BrowseService;

class DonorBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'donor';
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
            'donor.id',
            'actor_i18n.authorized_form_of_name as name',
            'actor.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'donor.id', '=', 'actor_i18n.id')
            ->join('object', 'donor.id', '=', 'object.id')
            ->join('slug', 'donor.id', '=', 'slug.object_id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->where('actor_i18n.culture', $this->culture);
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
