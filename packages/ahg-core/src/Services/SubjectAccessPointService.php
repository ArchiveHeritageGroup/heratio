<?php

/**
 * SubjectAccessPointService - Service for Heratio
 *
 * Bridge from sector-local subject lists (library_item_subject, museum item
 * keywords, DAM tags, etc) to the cross-cutting AHG subject taxonomy
 * (term + object_term_relation, taxonomy_id = 35). Anything written here
 * becomes searchable through the global GLAM browse, the subject facets in
 * museum / DAM / gallery / library browse, and the Elasticsearch term index.
 *
 * Idempotent: re-attaching the same heading does not duplicate the
 * object_term_relation row, and re-using an existing term name (case-
 * insensitive) does not create a duplicate term.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;

class SubjectAccessPointService
{
    /** taxonomy_id for QubitTerm subjects - same value used everywhere in the codebase */
    public const TAXONOMY_SUBJECT = 35;

    /**
     * Attach a list of subject headings (strings) to an information_object
     * as access points. Creates terms as needed; idempotent for relations.
     *
     * @param int      $informationObjectId  parent IO that owns the access points
     * @param string[] $headings             subject heading strings
     * @param string   $culture              source culture for the term_i18n row
     * @return int     count of NEW object_term_relation rows inserted
     */
    public static function attach(int $informationObjectId, array $headings, string $culture = 'en'): int
    {
        if ($informationObjectId <= 0 || empty($headings)) {
            return 0;
        }

        // De-dupe + clean input. Case-insensitive collapse so 'Apartheid'
        // and 'apartheid' resolve to the same term lookup.
        $clean = [];
        foreach ($headings as $h) {
            $h = trim((string) $h);
            if ($h === '') continue;
            $key = mb_strtolower($h);
            if (!isset($clean[$key])) $clean[$key] = $h;
        }
        if (empty($clean)) return 0;

        $inserted = 0;
        foreach ($clean as $heading) {
            $termId = self::findOrCreateTerm($heading, self::TAXONOMY_SUBJECT, $culture);
            if ($termId <= 0) continue;

            $exists = DB::table('object_term_relation')
                ->where('object_id', $informationObjectId)
                ->where('term_id', $termId)
                ->exists();
            if ($exists) continue;

            // The base table is `relation` (CTI: object_term_relation IS-A
            // QubitObject row whose class_name='QubitObjectTermRelation').
            // Mirror the same insert pattern used by the existing access-
            // point save paths in LibraryService / InformationObjectService.
            $relationObjectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitObjectTermRelation',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);
            DB::table('object_term_relation')->insert([
                'id'           => $relationObjectId,
                'object_id'    => $informationObjectId,
                'term_id'      => $termId,
                'source_culture' => $culture,
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Detach all subject access points (taxonomy 35) from the given IO. Used
     * by sector save paths that do a replace-all on subjects so the global
     * taxonomy stays in sync with the sector-local headings.
     *
     * Term rows themselves are NOT deleted - they may be referenced by other
     * objects. Only the per-object relations are removed.
     */
    public static function detachAll(int $informationObjectId): int
    {
        if ($informationObjectId <= 0) return 0;

        $relationIds = DB::table('object_term_relation as otr')
            ->join('term', 'term.id', '=', 'otr.term_id')
            ->where('otr.object_id', $informationObjectId)
            ->where('term.taxonomy_id', self::TAXONOMY_SUBJECT)
            ->pluck('otr.id')
            ->all();
        if (empty($relationIds)) return 0;

        DB::table('object_term_relation')->whereIn('id', $relationIds)->delete();
        DB::table('object')->whereIn('id', $relationIds)
            ->where('class_name', 'QubitObjectTermRelation')->delete();

        return count($relationIds);
    }

    /**
     * Replace-all helper - convenience wrapper for save flows that always
     * blow away then re-attach. Mirrors syncSubjects() semantics in
     * LibraryService.
     *
     * @return int  count of NEW relations after the resync
     */
    public static function sync(int $informationObjectId, array $headings, string $culture = 'en'): int
    {
        self::detachAll($informationObjectId);
        return self::attach($informationObjectId, $headings, $culture);
    }

    /**
     * Internal: lookup term by case-insensitive name, or create it. Mirrors
     * InformationObjectController::findOrCreateTerm so this service has no
     * cross-package dependency on the IO-manage package.
     */
    private static function findOrCreateTerm(string $name, int $taxonomyId, string $culture): int
    {
        $name = trim($name);
        if ($name === '') return 0;

        $existing = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->whereRaw('LOWER(term_i18n.name) = ?', [mb_strtolower($name)])
            ->value('term.id');
        if ($existing) return (int) $existing;

        $parentId = (int) (DB::table('term')
            ->where('taxonomy_id', $taxonomyId)
            ->select('parent_id')
            ->groupBy('parent_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(1)
            ->value('parent_id') ?? 110);

        $termId = DB::table('object')->insertGetId([
            'class_name' => 'QubitTerm',
            'created_at' => now(),
            'updated_at' => now(),
            'serial_number' => 0,
        ]);
        DB::table('term')->insert([
            'id'             => $termId,
            'taxonomy_id'    => $taxonomyId,
            'parent_id'      => $parentId,
            'lft'            => null,
            'rgt'            => null,
            'source_culture' => $culture,
        ]);
        DB::table('term_i18n')->insert([
            'id'      => $termId,
            'culture' => $culture,
            'name'    => $name,
        ]);

        $base = \Illuminate\Support\Str::slug($name) ?: ('term-' . $termId);
        $slug = $base; $n = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $n++;
        }
        DB::table('slug')->insert(['object_id' => $termId, 'slug' => $slug]);

        return $termId;
    }
}
