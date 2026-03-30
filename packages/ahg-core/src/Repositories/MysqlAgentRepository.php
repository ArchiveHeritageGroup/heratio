<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\AgentRepository;
use Illuminate\Support\Facades\DB;

class MysqlAgentRepository implements AgentRepository
{
    public function findById(int $id, string $culture = 'en'): ?object
    {
        return DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->leftJoin('slug', 'actor.id', '=', 'slug.object_id')
            ->where('actor.id', $id)
            ->where('actor_i18n.culture', $culture)
            ->select('actor.*', 'actor_i18n.*', 'slug.slug')
            ->first();
    }

    public function findBySlug(string $slug, string $culture = 'en'): ?object
    {
        return DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('slug', 'actor.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('actor_i18n.culture', $culture)
            ->select('actor.*', 'actor_i18n.*', 'slug.slug')
            ->first();
    }

    public function getCreatedDescriptions(int $id, string $culture = 'en', int $limit = 50): array
    {
        return DB::table('event')
            ->join('information_object_i18n as ioi', 'event.object_id', '=', 'ioi.id')
            ->leftJoin('slug', 'event.object_id', '=', 'slug.object_id')
            ->where('event.actor_id', $id)
            ->where('ioi.culture', $culture)
            ->whereNotNull('ioi.title')
            ->select('event.object_id as id', 'ioi.title', 'slug.slug', 'event.type_id')
            ->distinct()
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getRelatedAgents(int $id): array
    {
        return DB::table('relation')
            ->leftJoin('actor_i18n as ai', function ($j) use ($id) {
                $j->on(DB::raw("CASE WHEN relation.subject_id = {$id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ai.id')
                  ->where('ai.culture', 'en');
            })
            ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
            })
            ->where(function ($q) use ($id) {
                $q->where('relation.subject_id', $id)->orWhere('relation.object_id', $id);
            })
            ->whereNotNull('ai.authorized_form_of_name')
            ->select('ai.authorized_form_of_name as name', 'slug.slug', 'ti.name as relation_type')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function getMaintainedRepositories(int $id): array
    {
        return DB::table('repository')
            ->join('actor_i18n', 'repository.id', '=', 'actor_i18n.id')
            ->leftJoin('slug', 'repository.id', '=', 'slug.object_id')
            ->join('relation', function ($j) use ($id) {
                $j->on('relation.object_id', '=', 'repository.id')
                  ->where('relation.subject_id', $id);
            })
            ->where('actor_i18n.culture', 'en')
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug')
            ->get()
            ->toArray();
    }
}
