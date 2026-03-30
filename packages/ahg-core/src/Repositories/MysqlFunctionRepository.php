<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\FunctionRepository;
use Illuminate\Support\Facades\DB;

class MysqlFunctionRepository implements FunctionRepository
{
    public function findById(int $id, string $culture = 'en'): ?object
    {
        return DB::table('function_object as fo')
            ->join('function_object_i18n as foi', 'fo.id', '=', 'foi.id')
            ->leftJoin('slug', 'fo.id', '=', 'slug.object_id')
            ->where('fo.id', $id)
            ->where('foi.culture', $culture)
            ->select('fo.*', 'foi.*', 'slug.slug')
            ->first();
    }

    public function findBySlug(string $slug, string $culture = 'en'): ?object
    {
        return DB::table('function_object as fo')
            ->join('function_object_i18n as foi', 'fo.id', '=', 'foi.id')
            ->join('slug', 'fo.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->where('foi.culture', $culture)
            ->select('fo.*', 'foi.*', 'slug.slug')
            ->first();
    }

    public function getRelatedDescriptions(int $id): array
    {
        return DB::table('relation')
            ->join('information_object_i18n as ioi', function ($j) use ($id) {
                $j->on(DB::raw("CASE WHEN relation.subject_id = {$id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'ioi.id')
                  ->where('ioi.culture', 'en');
            })
            ->leftJoin('slug', DB::raw("CASE WHEN relation.subject_id = {$id} THEN relation.object_id ELSE relation.subject_id END"), '=', 'slug.object_id')
            ->where(function ($q) use ($id) {
                $q->where('relation.subject_id', $id)->orWhere('relation.object_id', $id);
            })
            ->whereNotNull('ioi.title')
            ->select('ioi.title', 'slug.slug')
            ->limit(50)
            ->get()
            ->toArray();
    }

    public function getChildren(int $id, string $culture = 'en'): array
    {
        return DB::table('function_object as fo')
            ->join('function_object_i18n as foi', 'fo.id', '=', 'foi.id')
            ->leftJoin('slug', 'fo.id', '=', 'slug.object_id')
            ->where('fo.parent_id', $id)
            ->where('foi.culture', $culture)
            ->select('fo.id', 'foi.authorized_form_of_name as name', 'slug.slug')
            ->get()
            ->toArray();
    }
}
