<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\PlaceRepository;
use Illuminate\Support\Facades\DB;

class MysqlPlaceRepository implements PlaceRepository
{
    public function findById(int $id, string $culture = 'en'): ?object
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.id', $id)
            ->where('term.taxonomy_id', 42) // Places taxonomy
            ->where('term_i18n.culture', $culture)
            ->select('term.*', 'term_i18n.*', 'slug.slug')
            ->first();
    }

    public function getRelatedDescriptions(int $id): array
    {
        return DB::table('object_term_relation')
            ->join('information_object_i18n as ioi', 'object_term_relation.object_id', '=', 'ioi.id')
            ->leftJoin('slug', 'object_term_relation.object_id', '=', 'slug.object_id')
            ->where('object_term_relation.term_id', $id)
            ->where('ioi.culture', 'en')
            ->whereNotNull('ioi.title')
            ->select('ioi.title', 'slug.slug')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function getRelatedAgents(int $id): array
    {
        return DB::table('object_term_relation')
            ->join('actor_i18n', 'object_term_relation.object_id', '=', 'actor_i18n.id')
            ->leftJoin('slug', 'object_term_relation.object_id', '=', 'slug.object_id')
            ->where('object_term_relation.term_id', $id)
            ->where('actor_i18n.culture', 'en')
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->select('actor_i18n.authorized_form_of_name as name', 'slug.slug')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function getChildren(int $id, string $culture = 'en'): array
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.parent_id', $id)
            ->where('term.taxonomy_id', 42)
            ->where('term_i18n.culture', $culture)
            ->select('term.id', 'term_i18n.name', 'slug.slug')
            ->orderBy('term_i18n.name')
            ->get()
            ->toArray();
    }
}
