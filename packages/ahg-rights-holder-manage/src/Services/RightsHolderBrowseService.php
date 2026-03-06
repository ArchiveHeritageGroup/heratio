<?php

namespace AhgRightsHolderManage\Services;

use AhgCore\Services\BrowseService;

class RightsHolderBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'rights_holder';
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
            'rights_holder.id',
            'actor_i18n.authorized_form_of_name as name',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'rights_holder.id', '=', 'actor_i18n.id')
            ->join('object', 'rights_holder.id', '=', 'object.id')
            ->join('slug', 'rights_holder.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture);
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
