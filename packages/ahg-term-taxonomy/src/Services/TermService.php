<?php

/**
 * TermService - Service for Heratio
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
     *
     * LEFT JOIN i18n in the requested culture with an en fallback so the term
     * still resolves (and `name` stays populated) when no row exists in the
     * target culture. INNER JOIN here would 404 every term opened with
     * ?sf_culture=<X> before that culture is translated.
     */
    public function getBySlug(string $slug, string $culture): ?object
    {
        return $this->baseTermQuery($culture)
            ->where('slug.slug', $slug)
            ->first();
    }

    /**
     * Get a term by its id, joining term + term_i18n + object + slug.
     *
     * Same fallback semantics as getBySlug - see that method for rationale.
     */
    public function getById(int $id, string $culture): ?object
    {
        return $this->baseTermQuery($culture)
            ->where('term.id', $id)
            ->first();
    }

    /**
     * Shared query builder for getBySlug / getById. LEFT JOINs term_i18n in
     * the current culture and again as an en fallback, then COALESCEs name.
     */
    private function baseTermQuery(string $culture): \Illuminate\Database\Query\Builder
    {
        return DB::table('term')
            ->leftJoin('term_i18n as ti_cur', function ($j) use ($culture) {
                $j->on('term.id', '=', 'ti_cur.id')
                    ->where('ti_cur.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as ti_fb', function ($j) {
                $j->on('term.id', '=', 'ti_fb.id')
                    ->where('ti_fb.culture', '=', 'en');
            })
            ->join('slug', 'term.id', '=', 'slug.object_id')
            ->join('object', 'term.id', '=', 'object.id')
            ->select([
                'term.id',
                'term.taxonomy_id',
                'term.code',
                'term.parent_id',
                'term.lft',
                'term.rgt',
                'term.source_culture',
                DB::raw('COALESCE(NULLIF(ti_cur.name, ""), ti_fb.name) AS name'),
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ]);
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

            // The term tree is one global tree under ROOT_TERM_ID; a taxonomy's
            // top-level terms are children of the root (#1546). A broad term
            // from the form (id or exact name) is honoured when it belongs to
            // the same taxonomy.
            $parentId = $this->resolveBroadTerm($data['parent_id'] ?? null, (int) $taxonomyId, $culture)
                ?? self::ROOT_TERM_ID;

            // Append as the parent's last child in the global nested set.
            $parentRgt = (int) DB::table('term')->where('id', $parentId)->value('rgt');
            if ($parentRgt > 0) {
                DB::table('term')->where('rgt', '>=', $parentRgt)->increment('rgt', 2);
                DB::table('term')->where('lft', '>', $parentRgt)->increment('lft', 2);
                $newLft = $parentRgt;
            } else {
                $newLft = (int) DB::table('term')->max('rgt') + 1;
            }
            $newRgt = $newLft + 1;

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

            // #1333 dual-write: maintain the term closure tree alongside lft/rgt.
            app(\AhgCore\Services\ClosureMaintenanceService::class)
                ->addNode('term', (int) $objectId, (int) $parentId);

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
                $slug = $baseSlug.'-'.$counter;
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
     * A broad term chosen on the create form: a term id, or an exact name in
     * the given taxonomy. Null when blank or not a term of that taxonomy.
     */
    private function resolveBroadTerm(mixed $value, int $taxonomyId, string $culture): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $q = DB::table('term')->where('term.taxonomy_id', $taxonomyId);
        $id = ctype_digit($value)
            ? $q->where('term.id', (int) $value)->value('term.id')
            : $q->join('term_i18n', 'term_i18n.id', '=', 'term.id')
                ->where('term_i18n.culture', $culture)->where('term_i18n.name', $value)
                ->value('term.id');

        return $id ? (int) $id : null;
    }

    /**
     * Update a term (term + term_i18n + notes + use-for + relationships + touch object).
     */
    /**
     * Flat snapshot of the term fields an edit can change, for the
     * security_audit_log before/after diff (#676).
     */
    private function auditSnapshot(int $termId, string $culture): array
    {
        $term = (array) (DB::table('term')->where('id', $termId)
            ->select('code', 'parent_id', 'taxonomy_id')
            ->first() ?? []);
        $i18n = (array) (DB::table('term_i18n')->where('id', $termId)
            ->where('culture', $culture)
            ->select('name')
            ->first() ?? []);

        return array_merge($term, $i18n);
    }

    /**
     * Taxonomies whose terms the platform itself depends on: AtoM's
     * QubitTaxonomy::$lockedTaxonomies (root, actor name types, setting labels,
     * collection types, media types, digital object usage, relation types and
     * notes, term relation types, status types, publication status,
     * information object templates, job status). Nothing moves into or out.
     */
    public const LOCKED_TAXONOMY_IDS = [30, 36, 41, 45, 46, 47, 49, 56, 57, 59, 60, 70, 79];

    /** The single root of the term tree; every taxonomy's top-level terms hang off it. */
    public const ROOT_TERM_ID = 110;

    /**
     * Move a term, with everything beneath it, into another taxonomy
     * (heratio#1534). AtoM only re-labels the one term; here the whole subtree
     * moves, and a term that sat under a parent in the old taxonomy is placed
     * at the top level of the new one, with closure and nested set kept right.
     * Links to descriptions (object_term_relation) key on the term id and are
     * untouched, so every record keeps its access point.
     *
     * @return array{moved:int,reparented:bool}
     */
    public function moveToTaxonomy(int $termId, int $targetTaxonomyId): array
    {
        $term = DB::table('term')->where('id', $termId)->first(['id', 'parent_id', 'taxonomy_id', 'lft', 'rgt']);
        if (! $term || $termId === self::ROOT_TERM_ID) {
            throw new \DomainException('Term not found.');
        }
        if ((int) $term->taxonomy_id === $targetTaxonomyId) {
            throw new \DomainException('The term is already in that taxonomy.');
        }
        if (in_array((int) $term->taxonomy_id, self::LOCKED_TAXONOMY_IDS, true) || in_array($targetTaxonomyId, self::LOCKED_TAXONOMY_IDS, true)) {
            throw new \DomainException('Terms cannot be moved into or out of a system taxonomy.');
        }
        if (! DB::table('taxonomy')->where('id', $targetTaxonomyId)->exists()) {
            throw new \DomainException('Target taxonomy not found.');
        }

        $subtree = \Illuminate\Support\Facades\Schema::hasTable('term_closure')
            ? DB::table('term_closure')->where('ancestor', $termId)->pluck('descendant')->map('intval')->all()
            : [];
        if ($subtree === []) {
            $subtree = DB::table('term')->whereBetween('lft', [$term->lft, $term->rgt])->pluck('id')->map('intval')->all();
        }
        $subtree = array_values(array_unique(array_merge([$termId], $subtree)));
        $reparent = (int) $term->parent_id !== self::ROOT_TERM_ID;

        DB::transaction(function () use ($termId, $targetTaxonomyId, $subtree, $reparent) {
            DB::table('term')->whereIn('id', $subtree)->update(['taxonomy_id' => $targetTaxonomyId]);
            if ($reparent) {
                DB::table('term')->where('id', $termId)->update(['parent_id' => self::ROOT_TERM_ID]);
                app(\AhgCore\Services\ClosureMaintenanceService::class)->moveNode('term', $termId, self::ROOT_TERM_ID);
            }
            DB::table('object')->whereIn('id', $subtree)->update(['updated_at' => now()]);
        });

        if ($reparent) {
            // The term tree is one global nested set; rebuild it from parent_id
            // rather than shift ranges by hand (about 2,000 rows, a rare admin act).
            // --connection is explicit: the command still defaults to 'atom',
            // which would rebuild the wrong database.
            \Illuminate\Support\Facades\Artisan::call('ahg:nested-set-rebuild', [
                '--model' => 'term',
                '--connection' => DB::connection()->getName(),
            ]);
        }

        \AhgCore\Support\AuditLog::captureEdit($termId, 'term',
            ['taxonomy_id' => (int) $term->taxonomy_id, 'parent_id' => (int) $term->parent_id],
            ['taxonomy_id' => $targetTaxonomyId, 'parent_id' => $reparent ? self::ROOT_TERM_ID : (int) $term->parent_id]);

        return ['moved' => count($subtree), 'reparented' => $reparent];
    }

    /**
     * Merge one term into another of the same taxonomy (heratio#1533). Every
     * reference to the loser moves to the winner: description access points
     * (a description that already has the winner keeps one link), term
     * relations, narrower terms, and every column with a foreign key to
     * term.id (levels of description, event types, note types and so on, so
     * nothing cascades away). The loser's names become "use for" labels on the
     * winner, and the loser is deleted.
     *
     * @return array{links: int, narrower: int, labels: int}
     */
    public function mergeInto(int $loserId, int $winnerId): array
    {
        $loser = DB::table('term')->where('id', $loserId)->first(['id', 'taxonomy_id', 'parent_id']);
        $winner = DB::table('term')->where('id', $winnerId)->first(['id', 'taxonomy_id']);
        if (! $loser || ! $winner || in_array(self::ROOT_TERM_ID, [$loserId, $winnerId], true)) {
            throw new \DomainException(__('Term not found.'));
        }
        if ($loserId === $winnerId) {
            throw new \DomainException(__('A term cannot be merged into itself.'));
        }
        if ((int) $loser->taxonomy_id !== (int) $winner->taxonomy_id) {
            throw new \DomainException(__('Terms can only be merged within one taxonomy.'));
        }
        if (in_array((int) $loser->taxonomy_id, self::LOCKED_TAXONOMY_IDS, true)) {
            throw new \DomainException(__('Terms of a system taxonomy cannot be merged.'));
        }
        $loserSubtree = app(\AhgCore\Services\HierarchyQueryService::class)->descendantIds('term', $loserId, false);
        if (in_array($winnerId, array_map('intval', $loserSubtree), true)) {
            throw new \DomainException(__('The term kept is narrower than the term merged; move it out first.'));
        }

        $auditBefore = ['merged_id' => $loserId, 'into' => $winnerId];
        $result = DB::transaction(function () use ($loserId, $winnerId) {
            // Access points: one link per description.
            $already = DB::table('object_term_relation')->where('term_id', $winnerId)->pluck('object_id')->all();
            $doubled = DB::table('object_term_relation')->where('term_id', $loserId)->whereIn('object_id', $already)->pluck('id')->all();
            if ($doubled) {
                DB::table('object_term_relation')->whereIn('id', $doubled)->delete();
                DB::table('object')->whereIn('id', $doubled)->delete();
            }
            $links = DB::table('object_term_relation')->where('term_id', $loserId)->update(['term_id' => $winnerId]);

            // Every other column that points at a term.
            $columns = DB::select("SELECT k.table_name AS t, k.column_name AS c FROM information_schema.key_column_usage k
                WHERE k.constraint_schema = DATABASE() AND k.referenced_table_name = 'term' AND k.referenced_column_name = 'id'");
            foreach ($columns as $col) {
                if (in_array($col->t, ['term', 'term_i18n', 'term_closure', 'object_term_relation'], true)) {
                    continue;
                }
                $links += DB::table($col->t)->where($col->c, $loserId)->update([$col->c => $winnerId]);
            }

            // Term-to-term relations (related, converse): drop the pair's own, re-point the rest.
            $between = DB::table('relation')
                ->where(fn ($q) => $q->where('subject_id', $loserId)->where('object_id', $winnerId))
                ->orWhere(fn ($q) => $q->where('subject_id', $winnerId)->where('object_id', $loserId))
                ->pluck('id')->all();
            if ($between) {
                DB::table('relation_i18n')->whereIn('id', $between)->delete();
                DB::table('relation')->whereIn('id', $between)->delete();
                DB::table('object')->whereIn('id', $between)->delete();
            }
            DB::table('relation')->where('subject_id', $loserId)->update(['subject_id' => $winnerId]);
            DB::table('relation')->where('object_id', $loserId)->update(['object_id' => $winnerId]);

            // Narrower terms move under the winner.
            $narrower = DB::table('term')->where('parent_id', $loserId)->pluck('id');
            $closure = app(\AhgCore\Services\ClosureMaintenanceService::class);
            foreach ($narrower as $childId) {
                DB::table('term')->where('id', $childId)->update(['parent_id' => $winnerId]);
                $closure->moveNode('term', (int) $childId, $winnerId);
            }

            // The loser's names and use-for labels become use-for labels of the winner.
            $winnerNames = DB::table('term_i18n')->where('id', $winnerId)->pluck('name', 'culture')->all();
            $labels = DB::table('other_name')->where('object_id', $loserId)->update(['object_id' => $winnerId]);
            foreach (DB::table('term_i18n')->where('id', $loserId)->get(['culture', 'name']) as $row) {
                if ($row->name === null || $row->name === '' || ($winnerNames[$row->culture] ?? null) === $row->name) {
                    continue;
                }
                $otherNameId = DB::table('other_name')->insertGetId(['object_id' => $winnerId, 'type_id' => null, 'source_culture' => $row->culture]);
                DB::table('other_name_i18n')->insert(['id' => $otherNameId, 'culture' => $row->culture, 'name' => $row->name]);
                $labels++;
            }

            $this->delete($loserId);
            DB::table('object')->where('id', $winnerId)->update(['updated_at' => now()]);

            return ['links' => $links, 'narrower' => $narrower->count(), 'labels' => $labels];
        });

        // Narrower terms changed parent and delete() closed a gap: rebuild the
        // one global term nested set (see moveToTaxonomy for --connection).
        \Illuminate\Support\Facades\Artisan::call('ahg:nested-set-rebuild', [
            '--model' => 'term',
            '--connection' => DB::connection()->getName(),
        ]);

        \AhgCore\Support\AuditLog::captureMutation($winnerId, 'term', 'merge', ['data' => $auditBefore + $result]);

        return $result;
    }

    public function update(int $termId, array $data, string $culture): void
    {
        // Before the transaction, so a rolled-back edit records nothing.
        $auditBefore = $this->auditSnapshot($termId, $culture);

        DB::transaction(function () use ($termId, $data, $culture) {
            // Update term table
            $termUpdate = [];
            if (array_key_exists('code', $data)) {
                $termUpdate['code'] = $data['code'];
            }
            if (! empty($termUpdate)) {
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
                if (! empty($existingOtherNameIds)) {
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
                    if (! empty($existingNoteIds)) {
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
            if (! empty($data['narrow_terms'])) {
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
                            $slug = $baseSlug.'-'.$counter;
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

        // #676: field-level diff, so the audit log shows what changed about the
        // term rather than only that something did.
        \AhgCore\Support\AuditLog::captureEdit(
            $termId, 'term', $auditBefore, $this->auditSnapshot($termId, $culture)
        );
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

            if (! $term) {
                return;
            }

            $width = $term->rgt - $term->lft + 1;

            // Collect all descendant term IDs (incl. self). Closure when built
            // (subtree stays within the taxonomy as parent_id chains do; also
            // catches null-lft orphans), else lft/rgt. heratio#1333 read-swap.
            $descendantIds = app(\AhgCore\Services\HierarchyQueryService::class)
                ->descendantIds('term', (int) $termId, true);

            // Delete notes (note_i18n first, then note)
            $noteIds = DB::table('note')
                ->whereIn('object_id', $descendantIds)
                ->pluck('id')
                ->toArray();

            if (! empty($noteIds)) {
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

            // #1333 dual-write: term_closure rows cascade via the FK; clear the
            // FK-less sibling sidecar for the deleted subtree.
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_node_sibling_order')) {
                DB::table('ahg_node_sibling_order')
                    ->where('entity', 'term')
                    ->whereIn('node_id', $descendantIds)
                    ->delete();
            }

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
