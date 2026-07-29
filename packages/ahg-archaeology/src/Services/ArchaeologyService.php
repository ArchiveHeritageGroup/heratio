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

    // ─── Stratigraphic contexts (layers) - #1428 Phase 1 ────────────────────────

    /**
     * Every context recorded for a site, ordered by elevation then number, with
     * its type label and find count. Empty until the table exists.
     *
     * @return Collection<int, object>
     */
    public function contextsForSite(int $siteId): Collection
    {
        if (! Schema::hasTable('archaeology_context')) {
            return collect();
        }
        $culture = app()->getLocale();

        return DB::table('archaeology_context as c')
            ->leftJoin('term_i18n as ty', function ($j) use ($culture) {
                $j->on('ty.id', '=', 'c.context_type_id')->where('ty.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ph', function ($j) use ($culture) {
                $j->on('ph.id', '=', 'c.phase_id')->where('ph.culture', '=', $culture);
            })
            ->where('c.site_id', $siteId)
            ->where('c.status', 'active')
            ->orderByRaw('c.top_elevation_m IS NULL, c.top_elevation_m DESC')
            ->orderBy('c.context_number')
            ->get([
                'c.id', 'c.context_number', 'c.top_elevation_m', 'c.bottom_elevation_m',
                'c.description', 'c.information_object_id',
                'ty.name as type_name', 'ph.name as phase_name',
                DB::raw('(select count(*) from archaeology_object o where o.context_id = c.id and o.status = "active") as find_count'),
            ]);
    }

    /**
     * One context with its labels, its site, its linked description slug (for
     * drawings) and its finds.
     */
    public function context(int $id): ?object
    {
        if (! Schema::hasTable('archaeology_context')) {
            return null;
        }
        $culture = app()->getLocale();

        $c = DB::table('archaeology_context as c')
            ->leftJoin('term_i18n as ty', function ($j) use ($culture) {
                $j->on('ty.id', '=', 'c.context_type_id')->where('ty.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ph', function ($j) use ($culture) {
                $j->on('ph.id', '=', 'c.phase_id')->where('ph.culture', '=', $culture);
            })
            ->where('c.id', $id)
            ->select('c.*', 'ty.name as type_name', 'ph.name as phase_name')
            ->first();
        if (! $c) {
            return null;
        }

        $c->site = DB::table('archaeology_site as s')
            ->leftJoin('information_object_i18n as i', function ($j) use ($culture) {
                $j->on('i.id', '=', 's.information_object_id')->where('i.culture', '=', $culture);
            })
            ->where('s.id', $c->site_id)
            ->select('s.id', 's.site_number', 'i.title')
            ->first();

        $c->description_slug = $c->information_object_id
            ? DB::table('slug')->where('object_id', $c->information_object_id)->value('slug')
            : null;

        $c->finds = ! Schema::hasTable('archaeology_object') ? collect() : DB::table('archaeology_object as o')
            ->leftJoin('information_object_i18n as i', function ($j) use ($culture) {
                $j->on('i.id', '=', 'o.information_object_id')->where('i.culture', '=', $culture);
            })
            ->where('o.context_id', $id)
            ->where('o.status', 'active')
            ->orderBy('o.accession_number')
            ->get(['o.id', 'o.accession_number', 'i.title']);

        return $c;
    }

    /**
     * Create or update a context. On create (and when a context has no linked
     * description yet) a child description is made under the site so plan and
     * section drawings can be uploaded to it. Returns the context id.
     *
     * @param array<string,mixed> $data
     */
    public function saveContext(array $data, ?int $id = null): int
    {
        $siteId = (int) ($data['site_id'] ?? 0);
        $number = trim((string) ($data['context_number'] ?? ''));
        $now = now();

        $clean = fn ($v) => ($v === '' || $v === null) ? null : $v;
        $fields = [
            'site_id'              => $siteId,
            'context_number'       => $number,
            'context_type_id'      => $clean($data['context_type_id'] ?? null),
            'phase_id'             => $clean($data['phase_id'] ?? null),
            'description'          => $clean($data['description'] ?? null),
            'interpretation'       => $clean($data['interpretation'] ?? null),
            'top_elevation_m'      => $clean($data['top_elevation_m'] ?? null),
            'bottom_elevation_m'   => $clean($data['bottom_elevation_m'] ?? null),
            'excavation_reference' => $clean($data['excavation_reference'] ?? null),
            'excavator'            => $clean($data['excavator'] ?? null),
            'excavation_date'      => $clean($data['excavation_date'] ?? null),
            'date_earliest'        => $clean($data['date_earliest'] ?? null),
            'date_latest'          => $clean($data['date_latest'] ?? null),
            'dating_note'          => $clean($data['dating_note'] ?? null),
            'status'               => 'active',
            'updated_at'           => $now,
        ];

        if ($id) {
            DB::table('archaeology_context')->where('id', $id)->update($fields);
        } else {
            $fields['created_at'] = $now;
            $id = (int) DB::table('archaeology_context')->insertGetId($fields);
        }

        $this->ensureContextDescription($id, $siteId, $number, $data['context_type_id'] ?? null);

        return $id;
    }

    /**
     * Give a context a descriptive record (child of the site's description) so
     * its plan/section drawings have a home. No-op if it already has one, or if
     * the site itself has no description. New node's tree position is filled by
     * the next nested-set/closure rebuild; the context sheet and the digital
     * object uploader do not depend on it.
     */
    private function ensureContextDescription(int $contextId, int $siteId, string $number, $typeTermId): void
    {
        $ctx = DB::table('archaeology_context')->where('id', $contextId)->first();
        if (! $ctx || $ctx->information_object_id) {
            return;
        }
        $siteIoId = (int) (DB::table('archaeology_site')->where('id', $siteId)->value('information_object_id') ?? 0);
        if ($siteIoId <= 0) {
            return;
        }

        $culture = 'en';
        $typeName = $typeTermId
            ? (string) DB::table('term_i18n')->where('id', $typeTermId)->where('culture', $culture)->value('name')
            : '';
        $title = trim('Context '.$number.($typeName !== '' ? ' - '.$typeName : ''));
        $now = now();

        $siteIo = DB::table('information_object')->where('id', $siteIoId)->first();
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject', 'created_at' => $now, 'updated_at' => $now, 'serial_number' => 0,
        ]);
        DB::table('information_object')->insert([
            'id' => $objectId, 'parent_id' => $siteIoId,
            'repository_id' => $siteIo->repository_id ?? null,
            'lft' => 0, 'rgt' => 0, 'source_culture' => $culture,
        ]);
        DB::table('information_object_i18n')->insert([
            'id' => $objectId, 'culture' => $culture, 'title' => $title,
        ]);
        $slug = \Illuminate\Support\Str::slug($title) ?: ('context-'.$objectId);
        if (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug .= '-'.$objectId;
        }
        DB::table('slug')->insert(['object_id' => $objectId, 'slug' => $slug]);
        DB::table('status')->updateOrInsert(['object_id' => $objectId, 'type_id' => 158], ['status_id' => 160]);

        DB::table('archaeology_context')->where('id', $contextId)->update(['information_object_id' => $objectId]);
    }

    /**
     * Backfill archaeology_object.context_id from the legacy free-text
     * context_reference, matching context_number within the same site. Returns
     * the number of finds linked. Idempotent.
     */
    public function backfillContextIds(): int
    {
        if (! Schema::hasTable('archaeology_context') || ! Schema::hasColumn('archaeology_object', 'context_id')) {
            return 0;
        }
        $linked = 0;
        $finds = DB::table('archaeology_object')
            ->whereNull('context_id')
            ->whereNotNull('context_reference')
            ->where('context_reference', '!=', '')
            ->whereNotNull('site_id')
            ->get(['id', 'site_id', 'context_reference']);
        foreach ($finds as $f) {
            $ctxId = DB::table('archaeology_context')
                ->where('site_id', $f->site_id)
                ->where('context_number', trim((string) $f->context_reference))
                ->value('id');
            if ($ctxId) {
                DB::table('archaeology_object')->where('id', $f->id)->update(['context_id' => $ctxId]);
                $linked++;
            }
        }

        return $linked;
    }

    // ─── Stratigraphic relationships (Harris Matrix) - #1428 Phase 2 ─────────────

    /**
     * relationship_type => [reciprocal, human label, temporal direction]. The
     * three "later" types (above/cuts/fills) form the directed later-than graph
     * used for cycle detection; their reciprocals are "earlier"; the last three
     * are symmetric (no ordering).
     */
    public const REL_TYPES = [
        'above'      => ['reciprocal' => 'below',      'label' => 'is above',       'dir' => 'later'],
        'below'      => ['reciprocal' => 'above',      'label' => 'is below',       'dir' => 'earlier'],
        'cuts'       => ['reciprocal' => 'cut_by',     'label' => 'cuts',           'dir' => 'later'],
        'cut_by'     => ['reciprocal' => 'cuts',       'label' => 'is cut by',      'dir' => 'earlier'],
        'fills'      => ['reciprocal' => 'filled_by',  'label' => 'fills',          'dir' => 'later'],
        'filled_by'  => ['reciprocal' => 'fills',      'label' => 'is filled by',   'dir' => 'earlier'],
        'same_as'    => ['reciprocal' => 'same_as',    'label' => 'is the same as', 'dir' => 'none'],
        'bonds_with' => ['reciprocal' => 'bonds_with', 'label' => 'bonds with',     'dir' => 'none'],
        'abuts'      => ['reciprocal' => 'abuts',      'label' => 'abuts',          'dir' => 'none'],
    ];

    /**
     * A context's own relationships, each resolved to the related context's
     * number, ordered by type then number.
     *
     * @return Collection<int, object>
     */
    public function relationshipsForContext(int $contextId): Collection
    {
        if (! Schema::hasTable('archaeology_context_relationship')) {
            return collect();
        }

        return DB::table('archaeology_context_relationship as r')
            ->join('archaeology_context as c', 'c.id', '=', 'r.related_context_id')
            ->where('r.context_id', $contextId)
            ->orderBy('r.relationship_type')
            ->orderBy('c.context_number')
            ->get(['r.id', 'r.relationship_type', 'r.note', 'c.id as related_id', 'c.context_number as related_number']);
    }

    /**
     * Other contexts in the same site, for the relationship-target dropdown.
     *
     * @return Collection<int, object>
     */
    public function contextPickList(int $siteId, ?int $excludeId = null): Collection
    {
        if (! Schema::hasTable('archaeology_context')) {
            return collect();
        }

        return DB::table('archaeology_context')
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('context_number')
            ->get(['id', 'context_number']);
    }

    /**
     * Add a stratigraphic relationship and its mirror. Rejects self-relations,
     * unknown types, and any directional edge that would create a loop in the
     * later-than graph. Returns ['ok'=>bool, 'error'=>?string].
     *
     * @return array{ok:bool, error?:string}
     */
    public function addRelationship(int $contextId, int $relatedId, string $type, ?string $note = null): array
    {
        if (! isset(self::REL_TYPES[$type])) {
            return ['ok' => false, 'error' => 'Unknown relationship type.'];
        }
        if ($contextId === $relatedId) {
            return ['ok' => false, 'error' => 'A context cannot relate to itself.'];
        }

        $meta = self::REL_TYPES[$type];
        if ($meta['dir'] !== 'none') {
            [$later, $earlier] = $meta['dir'] === 'later' ? [$contextId, $relatedId] : [$relatedId, $contextId];
            // Cycle if the "earlier" context is already later than the "later" one.
            if ($this->laterThanReaches($earlier, $later)) {
                return ['ok' => false, 'error' => 'That would create a stratigraphic loop (the other context is already earlier in the sequence).'];
            }
        }

        $now = now();
        DB::table('archaeology_context_relationship')->insertOrIgnore([
            'context_id' => $contextId, 'related_context_id' => $relatedId,
            'relationship_type' => $type, 'note' => $note, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('archaeology_context_relationship')->insertOrIgnore([
            'context_id' => $relatedId, 'related_context_id' => $contextId,
            'relationship_type' => $meta['reciprocal'], 'note' => $note, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['ok' => true];
    }

    /** Remove a relationship and its mirror. */
    public function removeRelationship(int $id): void
    {
        if (! Schema::hasTable('archaeology_context_relationship')) {
            return;
        }
        $row = DB::table('archaeology_context_relationship')->where('id', $id)->first();
        if (! $row) {
            return;
        }
        DB::table('archaeology_context_relationship')->where('id', $id)->delete();
        $recip = self::REL_TYPES[$row->relationship_type]['reciprocal'] ?? null;
        if ($recip) {
            DB::table('archaeology_context_relationship')
                ->where('context_id', $row->related_context_id)
                ->where('related_context_id', $row->context_id)
                ->where('relationship_type', $recip)
                ->delete();
        }
    }

    /**
     * Can $from reach $target following later-than edges (above/cuts/fills:
     * source is later than target)? Used to reject cycles before insertion.
     */
    private function laterThanReaches(int $from, int $target): bool
    {
        $edges = DB::table('archaeology_context_relationship')
            ->whereIn('relationship_type', ['above', 'cuts', 'fills'])
            ->get(['context_id', 'related_context_id'])
            ->groupBy('context_id');

        $seen = [];
        $stack = [$from];
        while ($stack) {
            $n = (int) array_pop($stack);
            if ($n === $target) {
                return true;
            }
            if (isset($seen[$n])) {
                continue;
            }
            $seen[$n] = true;
            foreach ($edges[$n] ?? [] as $e) {
                $stack[] = (int) $e->related_context_id;
            }
        }

        return false;
    }
}
