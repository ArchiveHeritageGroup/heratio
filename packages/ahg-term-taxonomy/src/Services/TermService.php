<?php

/**
 * TermService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgTermTaxonomy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TermService
{
    /**
     * Scope note type_id in AtoM (term_id 122 = "Scope note" in note.type_id).
     */
    private const SCOPE_NOTE_TYPE_ID = 122;

    /**
     * Get a term by its slug, joining term + term_i18n + object + slug.
     */
    public function getBySlug(string $slug, string $culture): ?object
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->join('object', 'term.id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('term_i18n.culture', $culture)
            ->select([
                'term.id',
                'term.taxonomy_id',
                'term.code',
                'term.parent_id',
                'term.lft',
                'term.rgt',
                'term.source_culture',
                'term_i18n.name',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Get a term by its id, joining term + term_i18n + object + slug.
     */
    public function getById(int $id, string $culture): ?object
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->join('object', 'term.id', '=', 'object.id')
            ->where('term.id', $id)
            ->where('term_i18n.culture', $culture)
            ->select([
                'term.id',
                'term.taxonomy_id',
                'term.code',
                'term.parent_id',
                'term.lft',
                'term.rgt',
                'term.source_culture',
                'term_i18n.name',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    /**
     * Get taxonomy name from taxonomy_i18n.
     */
    public function getTaxonomyName(int $taxonomyId, string $culture): ?string
    {
        return DB::table('taxonomy_i18n')
            ->where('id', $taxonomyId)
            ->where('culture', $culture)
            ->value('name');
    }

    /**
     * Get scope note for a term from note + note_i18n.
     */
    public function getScopeNote(int $termId, string $culture): ?object
    {
        return DB::table('note')
            ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
            ->where('note.object_id', $termId)
            ->where('note.type_id', self::SCOPE_NOTE_TYPE_ID)
            ->where('note_i18n.culture', $culture)
            ->select('note.id', 'note_i18n.content')
            ->first();
    }

    /**
     * Count related descriptions (information objects linked via object_term_relation).
     */
    public function getRelatedDescriptionCount(int $termId): int
    {
        return DB::table('object_term_relation')
            ->where('term_id', $termId)
            ->count();
    }

    /**
     * List all taxonomies from taxonomy_i18n.
     */
    public function getTaxonomies(string $culture): \Illuminate\Support\Collection
    {
        return DB::table('taxonomy')
            ->join('taxonomy_i18n', 'taxonomy.id', '=', 'taxonomy_i18n.id')
            ->where('taxonomy_i18n.culture', $culture)
            ->select([
                'taxonomy.id',
                'taxonomy_i18n.name as name',
                'taxonomy_i18n.note as note',
            ])
            ->orderBy('taxonomy_i18n.name', 'asc')
            ->get();
    }

    /**
     * Get all terms for a given taxonomy.
     */
    public function getTermsForTaxonomy(int $taxonomyId, string $culture): \Illuminate\Support\Collection
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.culture', $culture)
            ->select([
                'term.id',
                'term_i18n.name',
                'slug.slug',
            ])
            ->orderBy('term_i18n.name', 'asc')
            ->get();
    }

    /**
     * Create a new term (transaction: object -> term -> term_i18n -> slug).
     * Uses nested set: places as last child of the taxonomy root.
     */
    public function create(array $data, string $culture): string
    {
        return DB::transaction(function () use ($data, $culture) {
            $taxonomyId = $data['taxonomy_id'];
            $name = $data['name'];
            $code = $data['code'] ?? null;

            // Determine parent_id. Terms in AtoM use the taxonomy root
            // term as parent. Find the root term for this taxonomy (parent_id IS NULL or
            // the term with the smallest lft in the taxonomy).
            $parentTerm = DB::table('term')
                ->where('taxonomy_id', $taxonomyId)
                ->orderBy('lft', 'asc')
                ->select('id', 'lft', 'rgt')
                ->first();

            if ($parentTerm) {
                // Place as last child: new lft = parent rgt, new rgt = parent rgt + 1
                // But for terms, we want to place at the end of the taxonomy's tree.
                // Find the max rgt for this taxonomy.
                $maxRgt = DB::table('term')
                    ->where('taxonomy_id', $taxonomyId)
                    ->max('rgt');

                $newLft = $maxRgt + 1;
                $newRgt = $maxRgt + 2;

                // Shift existing nested set values to make room
                // Only shift terms in the same taxonomy whose rgt >= maxRgt + 1
                // Actually for terms, the nested set is per-taxonomy (they share the term table),
                // so we shift all terms with lft or rgt >= newLft
                DB::table('term')
                    ->where('taxonomy_id', $taxonomyId)
                    ->where('rgt', '>=', $newLft)
                    ->increment('rgt', 2);

                DB::table('term')
                    ->where('taxonomy_id', $taxonomyId)
                    ->where('lft', '>=', $newLft)
                    ->increment('lft', 2);

                $parentId = $parentTerm->id;
            } else {
                // No existing terms in this taxonomy — first term
                $newLft = 1;
                $newRgt = 2;
                $parentId = null;
            }

            // Insert into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitTerm',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // Insert into term table
            DB::table('term')->insert([
                'id' => $objectId,
                'taxonomy_id' => $taxonomyId,
                'code' => $code,
                'parent_id' => $parentId,
                'lft' => $newLft,
                'rgt' => $newRgt,
                'source_culture' => $culture,
            ]);

            // Insert into term_i18n table
            DB::table('term_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
                'name' => $name,
            ]);

            // Generate slug
            $baseSlug = Str::slug($name ?: 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            DB::table('slug')->insert([
                'object_id' => $objectId,
                'slug' => $slug,
                'serial_number' => 0,
            ]);

            return $slug;
        });
    }

    /**
     * Update a term (term + term_i18n + notes + use-for + relationships + touch object).
     */
    public function update(int $termId, array $data, string $culture): void
    {
        DB::transaction(function () use ($termId, $data, $culture) {
            // Update term table
            $termUpdate = [];
            if (array_key_exists('code', $data)) {
                $termUpdate['code'] = $data['code'];
            }
            if (!empty($termUpdate)) {
                DB::table('term')
                    ->where('id', $termId)
                    ->update($termUpdate);
            }

            // Update term_i18n table
            if (array_key_exists('name', $data)) {
                DB::table('term_i18n')
                    ->where('id', $termId)
                    ->where('culture', $culture)
                    ->update([
                        'name' => $data['name'],
                    ]);
            }

            // Update use-for (other_name / other_name_i18n)
            if (array_key_exists('use_for', $data)) {
                // Delete existing use-for entries
                $existingOtherNameIds = DB::table('other_name')
                    ->where('object_id', $termId)
                    ->pluck('id')->toArray();
                if (!empty($existingOtherNameIds)) {
                    DB::table('other_name_i18n')->whereIn('id', $existingOtherNameIds)->delete();
                    DB::table('other_name')->whereIn('id', $existingOtherNameIds)->delete();
                }

                // Insert new use-for entries (comma-separated)
                $useForValue = trim($data['use_for'] ?? '');
                if ($useForValue !== '') {
                    $labels = array_map('trim', explode(',', $useForValue));
                    foreach ($labels as $label) {
                        if ($label === '') {
                            continue;
                        }
                        $otherNameId = DB::table('other_name')->insertGetId([
                            'object_id' => $termId,
                            'type_id' => null,
                            'source_culture' => $culture,
                        ]);
                        DB::table('other_name_i18n')->insert([
                            'id' => $otherNameId,
                            'culture' => $culture,
                            'name' => $label,
                        ]);
                    }
                }
            }

            // Update notes (scope=122, source=121, display=123)
            $noteTypeMap = [
                'scopeNotes' => 122,
                'sourceNotes' => 121,
                'displayNotes' => 123,
            ];
            foreach ($noteTypeMap as $key => $typeId) {
                if (array_key_exists($key, $data)) {
                    // Delete existing notes of this type
                    $existingNoteIds = DB::table('note')
                        ->where('object_id', $termId)
                        ->where('type_id', $typeId)
                        ->pluck('id')->toArray();
                    if (!empty($existingNoteIds)) {
                        DB::table('note_i18n')->whereIn('id', $existingNoteIds)->delete();
                        DB::table('note')->whereIn('id', $existingNoteIds)->delete();
                    }

                    // Insert new notes
                    $notes = $data[$key] ?? [];
                    foreach ($notes as $note) {
                        $content = trim($note['content'] ?? '');
                        if ($content === '') {
                            continue;
                        }
                        $noteObjId = DB::table('object')->insertGetId([
                            'class_name' => 'QubitNote',
                            'created_at' => now(),
                            'updated_at' => now(),
                            'serial_number' => 0,
                        ]);
                        DB::table('note')->insert([
                            'id' => $noteObjId,
                            'object_id' => $termId,
                            'type_id' => $typeId,
                            'scope' => null,
                            'user_id' => null,
                            'source_culture' => $culture,
                        ]);
                        DB::table('note_i18n')->insert([
                            'id' => $noteObjId,
                            'culture' => $culture,
                            'content' => $content,
                        ]);
                    }
                }
            }

            // Update narrow terms (create new child terms from newline/comma-separated text)
            if (!empty($data['narrow_terms'])) {
                $narrowText = trim($data['narrow_terms']);
                if ($narrowText !== '') {
                    // Split on newlines or commas
                    $narrowNames = preg_split('/[\r\n,]+/', $narrowText);
                    $taxonomyId = DB::table('term')->where('id', $termId)->value('taxonomy_id');

                    foreach ($narrowNames as $narrowName) {
                        $narrowName = trim($narrowName);
                        if ($narrowName === '') {
                            continue;
                        }

                        // Get parent's rgt to place new child
                        $parentRgt = DB::table('term')->where('id', $termId)->value('rgt');

                        // Shift nested set to make room
                        DB::table('term')
                            ->where('taxonomy_id', $taxonomyId)
                            ->where('rgt', '>=', $parentRgt)
                            ->increment('rgt', 2);
                        DB::table('term')
                            ->where('taxonomy_id', $taxonomyId)
                            ->where('lft', '>', $parentRgt)
                            ->increment('lft', 2);

                        $newLft = $parentRgt;
                        $newRgt = $parentRgt + 1;

                        $childObjId = DB::table('object')->insertGetId([
                            'class_name' => 'QubitTerm',
                            'created_at' => now(),
                            'updated_at' => now(),
                            'serial_number' => 0,
                        ]);

                        DB::table('term')->insert([
                            'id' => $childObjId,
                            'taxonomy_id' => $taxonomyId,
                            'code' => null,
                            'parent_id' => $termId,
                            'lft' => $newLft,
                            'rgt' => $newRgt,
                            'source_culture' => $culture,
                        ]);

                        DB::table('term_i18n')->insert([
                            'id' => $childObjId,
                            'culture' => $culture,
                            'name' => $narrowName,
                        ]);

                        $baseSlug = Str::slug($narrowName ?: 'untitled');
                        $slug = $baseSlug;
                        $counter = 1;
                        while (DB::table('slug')->where('slug', $slug)->exists()) {
                            $slug = $baseSlug . '-' . $counter;
                            $counter++;
                        }
                        DB::table('slug')->insert([
                            'object_id' => $childObjId,
                            'slug' => $slug,
                            'serial_number' => 0,
                        ]);
                    }
                }
            }

            // Touch object.updated_at
            DB::table('object')
                ->where('id', $termId)
                ->update([
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Delete a term and all related data.
     * Removes: notes -> object_term_relation -> term_i18n -> term -> slug -> object.
     * Fixes nested set gap.
     */
    public function delete(int $termId): void
    {
        DB::transaction(function () use ($termId) {
            // Get the term's nested set values
            $term = DB::table('term')
                ->where('id', $termId)
                ->select('id', 'taxonomy_id', 'lft', 'rgt')
                ->first();

            if (!$term) {
                return;
            }

            $width = $term->rgt - $term->lft + 1;

            // Collect all descendant IDs (nested set: lft between this node's lft and rgt)
            $descendantIds = DB::table('term')
                ->where('taxonomy_id', $term->taxonomy_id)
                ->whereBetween('lft', [$term->lft, $term->rgt])
                ->pluck('id')
                ->toArray();

            // Delete notes (note_i18n first, then note)
            $noteIds = DB::table('note')
                ->whereIn('object_id', $descendantIds)
                ->pluck('id')
                ->toArray();

            if (!empty($noteIds)) {
                DB::table('note_i18n')
                    ->whereIn('id', $noteIds)
                    ->delete();

                DB::table('note')
                    ->whereIn('id', $noteIds)
                    ->delete();
            }

            // Delete object_term_relation entries
            DB::table('object_term_relation')
                ->whereIn('term_id', $descendantIds)
                ->delete();

            // Delete term_i18n rows
            DB::table('term_i18n')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Delete term rows
            DB::table('term')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Delete slug rows
            DB::table('slug')
                ->whereIn('object_id', $descendantIds)
                ->delete();

            // Delete object rows
            DB::table('object')
                ->whereIn('id', $descendantIds)
                ->delete();

            // Close the gap in the nested set (only within the same taxonomy)
            DB::table('term')
                ->where('taxonomy_id', $term->taxonomy_id)
                ->where('lft', '>', $term->rgt)
                ->decrement('lft', $width);

            DB::table('term')
                ->where('taxonomy_id', $term->taxonomy_id)
                ->where('rgt', '>', $term->rgt)
                ->decrement('rgt', $width);
        });
    }

    /**
     * Get the slug for a term by its ID.
     */
    public function getSlug(int $termId): ?string
    {
        return DB::table('slug')
            ->where('object_id', $termId)
            ->value('slug');
    }
}
