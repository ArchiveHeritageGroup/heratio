<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\FunctionRepository;
use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

class MysqlFunctionRepository implements FunctionRepository
{
    public function findById(int $id, string $culture = 'en'): ?object
    {
        return DB::table('function_object as fo')
            ->tap(fn ($q) => I18nFallback::join($q, 'function_object_i18n as foi', 'fo.id', $culture, required: true))
            ->leftJoin('slug', 'fo.id', '=', 'slug.object_id')
            ->where('fo.id', $id)
            ->select('fo.*', I18nFallback::col('foi', 'authorized_form_of_name', 'authorized_form_of_name'), I18nFallback::col('foi', 'classification', 'classification'), I18nFallback::col('foi', 'dates', 'dates'), I18nFallback::col('foi', 'description', 'description'), I18nFallback::col('foi', 'history', 'history'), I18nFallback::col('foi', 'legislation', 'legislation'), I18nFallback::col('foi', 'institution_identifier', 'institution_identifier'), I18nFallback::col('foi', 'revision_history', 'revision_history'), I18nFallback::col('foi', 'rules', 'rules'), I18nFallback::col('foi', 'sources', 'sources'), 'slug.slug')
            ->first();
    }

    public function findBySlug(string $slug, string $culture = 'en'): ?object
    {
        return DB::table('function_object as fo')
            ->tap(fn ($q) => I18nFallback::join($q, 'function_object_i18n as foi', 'fo.id', $culture, required: true))
            ->join('slug', 'fo.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('fo.*', I18nFallback::col('foi', 'authorized_form_of_name', 'authorized_form_of_name'), I18nFallback::col('foi', 'classification', 'classification'), I18nFallback::col('foi', 'dates', 'dates'), I18nFallback::col('foi', 'description', 'description'), I18nFallback::col('foi', 'history', 'history'), I18nFallback::col('foi', 'legislation', 'legislation'), I18nFallback::col('foi', 'institution_identifier', 'institution_identifier'), I18nFallback::col('foi', 'revision_history', 'revision_history'), I18nFallback::col('foi', 'rules', 'rules'), I18nFallback::col('foi', 'sources', 'sources'), 'slug.slug')
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
            ->tap(fn ($q) => I18nFallback::join($q, 'function_object_i18n as foi', 'fo.id', $culture, required: true))
            ->leftJoin('slug', 'fo.id', '=', 'slug.object_id')
            ->where('fo.parent_id', $id)
            ->select('fo.id', I18nFallback::col('foi', 'authorized_form_of_name', 'name'), 'slug.slug')
            ->get()
            ->toArray();
    }
}
