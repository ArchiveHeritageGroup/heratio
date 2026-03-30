<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\DescriptionRepository;
use Illuminate\Support\Facades\DB;

class MysqlDescriptionRepository implements DescriptionRepository
{
    public function findById(int $id, string $culture = 'en'): ?object
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $id)
            ->where('ioi.culture', $culture)
            ->select('io.*', 'ioi.*', 'slug.slug')
            ->first();
    }

    public function findBySlug(string $slug, string $culture = 'en'): ?object
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('ioi.culture', $culture)
            ->select('io.*', 'ioi.*', 'slug.slug')
            ->first();
    }

    public function getParent(int $id): ?object
    {
        $io = DB::table('information_object')->where('id', $id)->first();
        if (!$io || !$io->parent_id || $io->parent_id <= 1) {
            return null;
        }

        return $this->findById($io->parent_id);
    }

    public function getChildren(int $id, string $culture = 'en', int $limit = 50): array
    {
        return DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', $id)
            ->where('ioi.culture', $culture)
            ->select('io.id', 'ioi.title', 'slug.slug', 'io.lft')
            ->orderBy('io.lft')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function getAncestors(int $id, string $culture = 'en'): array
    {
        $ancestors = [];
        $current = DB::table('information_object')->where('id', $id)->first();

        while ($current && $current->parent_id && $current->parent_id > 1) {
            $parent = DB::table('information_object as io')
                ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->where('io.id', $current->parent_id)
                ->where('ioi.culture', $culture)
                ->select('io.id', 'io.parent_id', 'ioi.title', 'slug.slug')
                ->first();

            if (!$parent) break;
            array_unshift($ancestors, $parent);
            $current = $parent;
        }

        return $ancestors;
    }

    public function getRelated(int $id): array
    {
        return DB::table('relation')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($id) {
                $j->on(DB::raw("CASE WHEN relation.subject_id = {$id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
                  ->where('ioi.culture', 'en');
            })
            ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
            ->leftJoin('term_i18n as ti', function ($j) {
                $j->on('relation.type_id', '=', 'ti.id')->where('ti.culture', 'en');
            })
            ->where(function ($q) use ($id) {
                $q->where('relation.subject_id', $id)->orWhere('relation.object_id', $id);
            })
            ->whereNotNull('ioi.title')
            ->select('ioi.title', 'slug.slug', 'ti.name as relation_type',
                DB::raw("CASE WHEN relation.subject_id = {$id} THEN 'outgoing' ELSE 'incoming' END as direction"))
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function getCreators(int $id, string $culture = 'en'): array
    {
        return DB::table('event')
            ->join('actor_i18n', 'event.actor_id', '=', 'actor_i18n.id')
            ->leftJoin('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $id)
            ->where('actor_i18n.culture', $culture)
            ->whereNotNull('actor_i18n.authorized_form_of_name')
            ->select('event.actor_id as id', 'actor_i18n.authorized_form_of_name as name', 'slug.slug', 'event.type_id')
            ->get()
            ->toArray();
    }

    public function getSubjects(int $id): array
    {
        return DB::table('object_term_relation')
            ->join('term_i18n', 'object_term_relation.term_id', '=', 'term_i18n.id')
            ->leftJoin('slug', 'object_term_relation.term_id', '=', 'slug.object_id')
            ->where('object_term_relation.object_id', $id)
            ->where('term_i18n.culture', 'en')
            ->select('term_i18n.name', 'slug.slug')
            ->get()
            ->toArray();
    }
}
