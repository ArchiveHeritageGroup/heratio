<?php

namespace AhgActorManage\Services;

use AhgCore\Services\BrowseService;
use Illuminate\Support\Facades\DB;

class ActorBrowseService extends BrowseService
{
    protected function getTable(): string
    {
        return 'actor';
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
            'actor.id',
            'actor_i18n.authorized_form_of_name as name',
            'actor.entity_type_id',
            'actor.description_identifier as identifier',
            'object.updated_at',
            'slug.slug',
        ];
    }

    protected function getBaseJoins($query)
    {
        return $query
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor_i18n.culture', $this->culture)
            ->where('actor.id', '!=', 3)  // Exclude root actor
            ->where('actor.id', '!=', 4); // Exclude default actor
    }

    public function browse(array $params): array
    {
        $result = parent::browse($params);

        // Batch resolve entity type names
        if (!empty($result['hits'])) {
            $entityTypeIds = array_filter(array_unique(array_column($result['hits'], 'entity_type_id')));
            $entityTypeNames = [];
            if (!empty($entityTypeIds)) {
                $names = DB::table('term_i18n')
                    ->whereIn('id', $entityTypeIds)
                    ->where('culture', $this->culture)
                    ->pluck('name', 'id');
                $entityTypeNames = $names->toArray();
            }
            $result['entityTypeNames'] = $entityTypeNames;
        }

        return $result;
    }

    protected function transformRow($row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'entity_type_id' => $row->entity_type_id ?? null,
            'identifier' => $row->identifier ?? '',
            'updated_at' => $row->updated_at ?? '',
            'slug' => $row->slug ?? '',
        ];
    }
}
