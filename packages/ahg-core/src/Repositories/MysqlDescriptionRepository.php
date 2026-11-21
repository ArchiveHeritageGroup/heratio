<?php

namespace AhgCore\Repositories;

use AhgCore\Contracts\DescriptionRepository;
use Illuminate\Support\Facades\DB;
use AhgCore\Support\I18nFallback;

class MysqlDescriptionRepository implements DescriptionRepository
{
    public function findById(int $id, string $culture = 'en'): ?object
    {
        return DB::table('information_object as io')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as ioi', 'io.id', $culture))
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $id)
            ->select('io.*', I18nFallback::col('ioi', 'title', 'title'), I18nFallback::col('ioi', 'alternate_title', 'alternate_title'), I18nFallback::col('ioi', 'edition', 'edition'), I18nFallback::col('ioi', 'extent_and_medium', 'extent_and_medium'), I18nFallback::col('ioi', 'archival_history', 'archival_history'), I18nFallback::col('ioi', 'acquisition', 'acquisition'), I18nFallback::col('ioi', 'scope_and_content', 'scope_and_content'), I18nFallback::col('ioi', 'appraisal', 'appraisal'), I18nFallback::col('ioi', 'accruals', 'accruals'), I18nFallback::col('ioi', 'arrangement', 'arrangement'), I18nFallback::col('ioi', 'access_conditions', 'access_conditions'), I18nFallback::col('ioi', 'reproduction_conditions', 'reproduction_conditions'), I18nFallback::col('ioi', 'physical_characteristics', 'physical_characteristics'), I18nFallback::col('ioi', 'finding_aids', 'finding_aids'), I18nFallback::col('ioi', 'location_of_originals', 'location_of_originals'), I18nFallback::col('ioi', 'location_of_copies', 'location_of_copies'), I18nFallback::col('ioi', 'related_units_of_description', 'related_units_of_description'), I18nFallback::col('ioi', 'institution_responsible_identifier', 'institution_responsible_identifier'), I18nFallback::col('ioi', 'rules', 'rules'), I18nFallback::col('ioi', 'sources', 'sources'), I18nFallback::col('ioi', 'revision_history', 'revision_history'), 'slug.slug')
            ->first();
    }

    public function findBySlug(string $slug, string $culture = 'en'): ?object
    {
        return DB::table('information_object as io')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as ioi', 'io.id', $culture))
            ->join('slug', 'io.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('io.*', I18nFallback::col('ioi', 'title', 'title'), I18nFallback::col('ioi', 'alternate_title', 'alternate_title'), I18nFallback::col('ioi', 'edition', 'edition'), I18nFallback::col('ioi', 'extent_and_medium', 'extent_and_medium'), I18nFallback::col('ioi', 'archival_history', 'archival_history'), I18nFallback::col('ioi', 'acquisition', 'acquisition'), I18nFallback::col('ioi', 'scope_and_content', 'scope_and_content'), I18nFallback::col('ioi', 'appraisal', 'appraisal'), I18nFallback::col('ioi', 'accruals', 'accruals'), I18nFallback::col('ioi', 'arrangement', 'arrangement'), I18nFallback::col('ioi', 'access_conditions', 'access_conditions'), I18nFallback::col('ioi', 'reproduction_conditions', 'reproduction_conditions'), I18nFallback::col('ioi', 'physical_characteristics', 'physical_characteristics'), I18nFallback::col('ioi', 'finding_aids', 'finding_aids'), I18nFallback::col('ioi', 'location_of_originals', 'location_of_originals'), I18nFallback::col('ioi', 'location_of_copies', 'location_of_copies'), I18nFallback::col('ioi', 'related_units_of_description', 'related_units_of_description'), I18nFallback::col('ioi', 'institution_responsible_identifier', 'institution_responsible_identifier'), I18nFallback::col('ioi', 'rules', 'rules'), I18nFallback::col('ioi', 'sources', 'sources'), I18nFallback::col('ioi', 'revision_history', 'revision_history'), 'slug.slug')
            ->first();
    }

    public function getParent(int $id): ?object
    {
        $io = DB::table('information_object')->where('id', $id)->first();
        if (! $io || ! $io->parent_id || $io->parent_id <= 1) {
            return null;
        }

        return $this->findById($io->parent_id);
    }

    public function getChildren(int $id, string $culture = 'en', int $limit = 50): array
    {
        return DB::table('information_object as io')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as ioi', 'io.id', $culture))
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.parent_id', $id)
            ->select('io.id', I18nFallback::col('ioi', 'title', 'title'), 'slug.slug', 'io.lft')
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
                ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as ioi', 'io.id', $culture))
                ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
                ->where('io.id', $current->parent_id)
                ->select('io.id', 'io.parent_id', I18nFallback::col('ioi', 'title', 'title'), 'slug.slug')
                ->first();

            if (! $parent) {
                break;
            }
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
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'event.actor_id', $culture, required: true))
            ->leftJoin('slug', 'event.actor_id', '=', 'slug.object_id')
            ->where('event.object_id', $id)
            ->whereNotNull(I18nFallback::col('actor_i18n', 'authorized_form_of_name'))
            ->select('event.actor_id as id', I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'name'), 'slug.slug', 'event.type_id')
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
