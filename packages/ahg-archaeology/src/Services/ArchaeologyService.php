<?php

/**
 * ArchaeologyService — reads and writes the archaeology collections tables.
 *
 * Sites and finds are extensions of `information_object`, so titles, scope,
 * hierarchy and ACL come from the descriptive record and only domain fields
 * live in `archaeology_site` / `archaeology_object`. Every typological value is
 * a taxonomy term id, resolved to a label at read time.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArchaeology\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArchaeologyService
{
    /**
     * Terms for one configured vocabulary, for populating a select.
     *
     * @return Collection<int, object{id:int, name:string}>
     */
    public function vocabulary(string $key): Collection
    {
        $name = config("ahg-archaeology.vocabularies.{$key}");
        if (! $name || ! Schema::hasTable('taxonomy')) {
            return collect();
        }

        $culture = app()->getLocale();

        return DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term_i18n.id', '=', 'term.id')->where('term_i18n.culture', '=', $culture);
            })
            ->join('taxonomy', 'taxonomy.id', '=', 'term.taxonomy_id')
            ->join('taxonomy_i18n', function ($j) use ($culture) {
                $j->on('taxonomy_i18n.id', '=', 'taxonomy.id')->where('taxonomy_i18n.culture', '=', $culture);
            })
            ->whereRaw('LOWER(taxonomy_i18n.name) = ?', [mb_strtolower($name)])
            ->orderBy('term_i18n.name')
            ->get(['term.id', 'term_i18n.name']);
    }

    /**
     * All vocabularies this module uses, keyed as in config.
     *
     * @return array<string, Collection>
     */
    public function vocabularies(): array
    {
        $out = [];
        foreach (array_keys((array) config('ahg-archaeology.vocabularies', [])) as $key) {
            $out[$key] = $this->vocabulary($key);
        }

        return $out;
    }

    // ─── Sites ─────────────────────────────────────────────────────────────────

    /**
     * @param array{period_id?:int|null, site_type_id?:int|null, region?:string|null, excavated?:string|null, q?:string|null} $filters
     */
    public function sites(array $filters = [], int $perPage = 50)
    {
        if (! Schema::hasTable('archaeology_site')) {
            return collect();
        }

        $culture = app()->getLocale();

        $query = DB::table('archaeology_site as s')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 's.information_object_id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as period', function ($j) use ($culture) {
                $j->on('period.id', '=', 's.period_id')->where('period.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as stype', function ($j) use ($culture) {
                $j->on('stype.id', '=', 's.site_type_id')->where('stype.culture', '=', $culture);
            })
            ->where('s.status', 'active');

        if (! empty($filters['period_id'])) {
            $query->where('s.period_id', (int) $filters['period_id']);
        }
        if (! empty($filters['site_type_id'])) {
            $query->where('s.site_type_id', (int) $filters['site_type_id']);
        }
        if (! empty($filters['region'])) {
            $query->where('s.region', $filters['region']);
        }
        if (isset($filters['excavated']) && $filters['excavated'] !== '' && $filters['excavated'] !== null) {
            $query->where('s.excavated', (int) (bool) $filters['excavated']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($w) use ($term) {
                $w->where('s.site_number', 'like', $term)
                  ->orWhere('s.national_site_number', 'like', $term)
                  ->orWhere('s.locality', 'like', $term)
                  ->orWhere('ioi.title', 'like', $term);
            });
        }

        return $query
            ->orderBy('s.site_number')
            ->select([
                's.*',
                'ioi.title',
                'period.name as period_name',
                'stype.name as site_type_name',
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function site(int $id): ?object
    {
        if (! Schema::hasTable('archaeology_site')) {
            return null;
        }

        $culture = app()->getLocale();

        return DB::table('archaeology_site as s')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 's.information_object_id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as period', function ($j) use ($culture) {
                $j->on('period.id', '=', 's.period_id')->where('period.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as stype', function ($j) use ($culture) {
                $j->on('stype.id', '=', 's.site_type_id')->where('stype.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as prot', function ($j) use ($culture) {
                $j->on('prot.id', '=', 's.protection_status_id')->where('prot.culture', '=', $culture);
            })
            ->where('s.id', $id)
            ->select([
                's.*',
                'ioi.title',
                'period.name as period_name',
                'stype.name as site_type_name',
                'prot.name as protection_status_name',
            ])
            ->first();
    }

    /**
     * Assemblage summary for a site: how much of what was recovered.
     *
     * Sums item_count rather than counting rows, because a bulk record stands
     * for many physical objects.
     */
    public function siteAssemblage(int $siteId): Collection
    {
        if (! Schema::hasTable('archaeology_object')) {
            return collect();
        }

        $culture = app()->getLocale();

        return DB::table('archaeology_object as o')
            ->leftJoin('term_i18n as mat', function ($j) use ($culture) {
                $j->on('mat.id', '=', 'o.material_id')->where('mat.culture', '=', $culture);
            })
            ->where('o.site_id', $siteId)
            ->where('o.status', 'active')
            ->groupBy('mat.name')
            ->orderByDesc(DB::raw('SUM(o.item_count)'))
            ->get([
                DB::raw("COALESCE(mat.name, 'Undetermined') as material"),
                DB::raw('COUNT(*) as records'),
                DB::raw('SUM(o.item_count) as items'),
            ]);
    }

    // ─── Objects ───────────────────────────────────────────────────────────────

    /**
     * @param array{site_id?:int|null, material_id?:int|null, object_type_id?:int|null, period_id?:int|null, q?:string|null} $filters
     */
    public function objects(array $filters = [], int $perPage = 50)
    {
        if (! Schema::hasTable('archaeology_object')) {
            return collect();
        }

        $culture = app()->getLocale();

        $query = DB::table('archaeology_object as o')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'o.information_object_id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('archaeology_site as s', 's.id', '=', 'o.site_id')
            ->leftJoin('term_i18n as otype', function ($j) use ($culture) {
                $j->on('otype.id', '=', 'o.object_type_id')->where('otype.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as mat', function ($j) use ($culture) {
                $j->on('mat.id', '=', 'o.material_id')->where('mat.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as period', function ($j) use ($culture) {
                $j->on('period.id', '=', 'o.period_id')->where('period.culture', '=', $culture);
            })
            ->where('o.status', 'active');

        foreach (['site_id', 'material_id', 'object_type_id', 'period_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where("o.{$key}", (int) $filters[$key]);
            }
        }

        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($w) use ($term) {
                $w->where('o.accession_number', 'like', $term)
                  ->orWhere('o.context_reference', 'like', $term)
                  ->orWhere('o.find_location', 'like', $term)
                  ->orWhere('ioi.title', 'like', $term);
            });
        }

        return $query
            ->orderBy('o.accession_number')
            ->select([
                'o.*',
                'ioi.title',
                's.site_number',
                'otype.name as object_type_name',
                'mat.name as material_name',
                'period.name as period_name',
            ])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function object(int $id): ?object
    {
        if (! Schema::hasTable('archaeology_object')) {
            return null;
        }

        $culture = app()->getLocale();

        $join = fn ($j, $alias, $col) => $j->on("{$alias}.id", '=', $col)->where("{$alias}.culture", '=', $culture);

        return DB::table('archaeology_object as o')
            ->leftJoin('information_object_i18n as ioi', fn ($j) => $join($j, 'ioi', 'o.information_object_id'))
            ->leftJoin('archaeology_site as s', 's.id', '=', 'o.site_id')
            ->leftJoin('term_i18n as otype', fn ($j) => $join($j, 'otype', 'o.object_type_id'))
            ->leftJoin('term_i18n as mat', fn ($j) => $join($j, 'mat', 'o.material_id'))
            ->leftJoin('term_i18n as tech', fn ($j) => $join($j, 'tech', 'o.technique_id'))
            ->leftJoin('term_i18n as period', fn ($j) => $join($j, 'period', 'o.period_id'))
            ->leftJoin('term_i18n as recov', fn ($j) => $join($j, 'recov', 'o.recovery_method_id'))
            ->leftJoin('term_i18n as dating', fn ($j) => $join($j, 'dating', 'o.dating_method_id'))
            ->leftJoin('term_i18n as cond', fn ($j) => $join($j, 'cond', 'o.condition_id'))
            ->where('o.id', $id)
            ->select([
                'o.*',
                'ioi.title',
                's.site_number',
                's.id as site_row_id',
                'otype.name as object_type_name',
                'mat.name as material_name',
                'tech.name as technique_name',
                'period.name as period_name',
                'recov.name as recovery_method_name',
                'dating.name as dating_method_name',
                'cond.name as condition_name',
            ])
            ->first();
    }

    // ─── Dashboard ─────────────────────────────────────────────────────────────

    /**
     * @return array{sites:int, excavated:int, objects:int, items:int, unsited:int}
     */
    public function statistics(): array
    {
        $hasSites = Schema::hasTable('archaeology_site');
        $hasObjects = Schema::hasTable('archaeology_object');

        return [
            'sites'     => $hasSites ? (int) DB::table('archaeology_site')->where('status', 'active')->count() : 0,
            'excavated' => $hasSites ? (int) DB::table('archaeology_site')->where('status', 'active')->where('excavated', 1)->count() : 0,
            'objects'   => $hasObjects ? (int) DB::table('archaeology_object')->where('status', 'active')->count() : 0,
            // Physical objects, not records - a bulk record stands for many.
            'items'     => $hasObjects ? (int) DB::table('archaeology_object')->where('status', 'active')->sum('item_count') : 0,
            // Finds with no site are a data-quality problem worth surfacing.
            'unsited'   => $hasObjects ? (int) DB::table('archaeology_object')->where('status', 'active')->whereNull('site_id')->count() : 0,
        ];
    }

    /**
     * Breakdown by a term-backed column, for the dashboard.
     */
    public function breakdown(string $table, string $column, int $limit = 10): Collection
    {
        if (! in_array($table, ['archaeology_site', 'archaeology_object'], true) || ! Schema::hasTable($table)) {
            return collect();
        }

        $culture = app()->getLocale();

        return DB::table("{$table} as x")
            ->leftJoin('term_i18n as t', function ($j) use ($culture, $column) {
                $j->on('t.id', '=', "x.{$column}")->where('t.culture', '=', $culture);
            })
            ->where('x.status', 'active')
            ->groupBy('t.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit($limit)
            ->get([
                DB::raw("COALESCE(t.name, 'Not recorded') as label"),
                DB::raw('COUNT(*) as total'),
            ]);
    }
}
