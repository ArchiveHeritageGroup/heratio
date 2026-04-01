<?php

namespace Tests\Feature;

use AhgCore\Models\Term;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Comprehensive CRUD tests for Taxonomy Terms
 * 
 * Coverage:
 * - Create terms (subjects, places, genres, etc.)
 * - Hierarchical term structures
 * - Read, update, delete
 * - Taxonomy filtering
 * - Use For relationships
 * - Scope notes
 */
class TermCrudTest extends TestCase
{
    use DatabaseTransactions;

    protected TermFactory $termFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->termFactory = new TermFactory();
    }

    // ========================================================================
    // CREATE TESTS - By Taxonomy Type
    // ========================================================================

    public function test_can_create_subject_term(): void
    {
        $term = TermFactory::new()->subject()->create([
            'name' => 'World War II',
            'scope_note' => 'Use for documents related to the global war 1939-1945',
        ]);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'name' => 'World War II',
            'taxonomy_id' => 35, // Subject taxonomy
        ]);
    }

    public function test_can_create_place_term(): void
    {
        $term = TermFactory::new()->place()->create([
            'name' => 'Johannesburg',
        ]);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'name' => 'Johannesburg',
            'taxonomy_id' => 42, // Place taxonomy
        ]);
    }

    public function test_can_create_genre_term(): void
    {
        $term = TermFactory::new()->genre()->create([
            'name' => 'Photographs',
        ]);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'name' => 'Photographs',
            'taxonomy_id' => 43, // Genre taxonomy
        ]);
    }

    public function test_can_create_term_with_code(): void
    {
        $term = TermFactory::new()->create([
            'code' => 'MIL-001',
            'name' => 'Military Records',
        ]);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'code' => 'MIL-001',
        ]);
    }

    public function test_can_create_hierarchical_terms(): void
    {
        $parent = TermFactory::new()->subject()->create(['name' => 'Military History']);
        $child = TermFactory::new()->subject()->create([
            'name' => 'World War I',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
    }

    // ========================================================================
    // READ TESTS
    // ========================================================================

    public function test_can_find_term_by_id(): void
    {
        $term = TermFactory::new()->create();

        $found = Term::find($term->id);

        $this->assertNotNull($found);
        $this->assertEquals($term->id, $found->id);
    }

    public function test_can_get_terms_by_taxonomy(): void
    {
        TermFactory::new()->count(5)->subject()->create();
        TermFactory::new()->count(3)->place()->create();
        TermFactory::new()->count(2)->genre()->create();

        $subjects = Term::where('taxonomy_id', 35)->get();

        $this->assertCount(5, $subjects);
    }

    public function test_can_get_children_of_term(): void
    {
        $parent = TermFactory::new()->subject()->create(['name' => 'Africa']);
        TermFactory::new()->count(3)->create([
            'parent_id' => $parent->id,
            'taxonomy_id' => 35,
        ]);

        $children = Term::where('parent_id', $parent->id)->get();

        $this->assertCount(3, $children);
    }

    public function test_can_get_root_terms(): void
    {
        $root1 = TermFactory::new()->subject()->create(['name' => 'Topic A']);
        $root2 = TermFactory::new()->subject()->create(['name' => 'Topic B']);
        TermFactory::new()->create([
            'parent_id' => $root1->id,
            'taxonomy_id' => 35,
        ]);

        $roots = Term::whereNull('parent_id')->where('taxonomy_id', 35)->get();

        $this->assertCount(2, $roots);
    }

    public function test_can_search_terms_by_name(): void
    {
        TermFactory::new()->create(['name' => 'South Africa', 'taxonomy_id' => 42]);
        TermFactory::new()->create(['name' => 'Africa in the 20th Century']);
        TermFactory::new()->create(['name' => 'South America']);

        $results = Term::where('name', 'LIKE', '%Africa%')->get();

        $this->assertCount(2, $results);
    }

    public function test_can_get_terms_with_scope_notes(): void
    {
        TermFactory::new()->create(['name' => 'Test', 'scope_note' => 'Important note']);
        TermFactory::new()->create(['name' => 'Test2']);

        $withNotes = Term::whereNotNull('scope_note')->get();

        $this->assertGreaterThanOrEqual(1, $withNotes->count());
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_term_name(): void
    {
        $term = TermFactory::new()->create(['name' => 'Original Name']);

        $term->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_update_scope_note(): void
    {
        $term = TermFactory::new()->create();

        $term->update(['scope_note' => 'New scope note content']);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'scope_note' => 'New scope note content',
        ]);
    }

    public function test_can_update_use_for(): void
    {
        $term = TermFactory::new()->create(['use_for' => null]);

        $term->update(['use_for' => 'Alternate term 1; Alternate term 2']);

        $this->assertEquals('Alternate term 1; Alternate term 2', $term->fresh()->use_for);
    }

    public function test_can_move_term_to_new_parent(): void
    {
        $parent1 = TermFactory::new()->subject()->create(['name' => 'Parent 1']);
        $parent2 = TermFactory::new()->subject()->create(['name' => 'Parent 2']);
        $child = TermFactory::new()->create([
            'parent_id' => $parent1->id,
            'taxonomy_id' => 35,
        ]);

        $child->update(['parent_id' => $parent2->id]);

        $this->assertEquals($parent2->id, $child->fresh()->parent_id);
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_term(): void
    {
        $term = TermFactory::new()->create();

        $id = $term->id;
        $term->delete();

        $this->assertDatabaseMissing('term', ['id' => $id]);
    }

    public function test_deleting_parent_orphans_children(): void
    {
        $parent = TermFactory::new()->subject()->create(['name' => 'Parent']);
        $child = TermFactory::new()->create([
            'parent_id' => $parent->id,
            'taxonomy_id' => 35,
        ]);

        $parent->delete();

        $this->assertNull($child->fresh()->parent_id);
    }

    // ========================================================================
    // TAXONOMY FILTERING TESTS
    // ========================================================================

    public function test_can_filter_by_subject_taxonomy(): void
    {
        TermFactory::new()->count(4)->subject()->create();
        TermFactory::new()->count(2)->place()->create();

        $subjects = Term::where('taxonomy_id', 35)->get();

        $this->assertCount(4, $subjects);
    }

    public function test_can_filter_by_place_taxonomy(): void
    {
        TermFactory::new()->count(3)->subject()->create();
        TermFactory::new()->count(5)->place()->create();

        $places = Term::where('taxonomy_id', 42)->get();

        $this->assertCount(5, $places);
    }

    public function test_can_filter_by_genre_taxonomy(): void
    {
        TermFactory::new()->count(2)->subject()->create();
        TermFactory::new()->count(6)->genre()->create();

        $genres = Term::where('taxonomy_id', 43)->get();

        $this->assertCount(6, $genres);
    }

    // ========================================================================
    // USE FOR / UF RELATIONSHIP TESTS
    // ========================================================================

    public function test_can_set_use_for(): void
    {
        $term = TermFactory::new()->create([
            'name' => 'Photographs',
            'use_for' => 'Photos; Pictures; Images',
        ]);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'use_for' => 'Photos; Pictures; Images',
        ]);
    }

    public function test_can_search_by_use_for(): void
    {
        TermFactory::new()->create([
            'name' => 'Photographs',
            'use_for' => 'Photos; Pictures',
        ]);
        TermFactory::new()->create([
            'name' => 'Documents',
            'use_for' => 'Papers; Records',
        ]);

        $results = Term::where('use_for', 'LIKE', '%Photos%')->get();

        $this->assertCount(1, $results);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function test_term_requires_name(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Term::create([
            'taxonomy_id' => 35,
            // name is required
        ]);
    }

    public function test_term_requires_taxonomy(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        Term::create([
            'name' => 'Test Term',
            // taxonomy_id is required
        ]);
    }

    // ========================================================================
    // PAGINATION & SORTING TESTS
    // ========================================================================

    public function test_terms_can_be_paginated(): void
    {
        TermFactory::new()->count(50)->create();

        $paginated = Term::paginate(20);

        $this->assertEquals(20, $paginated->perPage());
        $this->assertEquals(50, $paginated->total());
    }

    public function test_terms_can_be_sorted_alphabetically(): void
    {
        TermFactory::new()->create(['name' => 'Zebra', 'taxonomy_id' => 35]);
        TermFactory::new()->create(['name' => 'Apple', 'taxonomy_id' => 35]);
        TermFactory::new()->create(['name' => 'Mango', 'taxonomy_id' => 35]);

        $terms = Term::where('taxonomy_id', 35)
            ->orderBy('name')
            ->get();

        $this->assertEquals('Apple', $terms->first()->name);
        $this->assertEquals('Zebra', $terms->last()->name);
    }

    // ========================================================================
    // STATISTICS TESTS
    // ========================================================================

    public function test_can_count_terms_by_taxonomy(): void
    {
        TermFactory::new()->count(10)->subject()->create();
        TermFactory::new()->count(7)->place()->create();
        TermFactory::new()->count(5)->genre()->create();

        $counts = Term::selectRaw('taxonomy_id, COUNT(*) as cnt')
            ->groupBy('taxonomy_id')
            ->pluck('cnt', 'taxonomy_id')
            ->toArray();

        $this->assertEquals(10, $counts[35] ?? 0);
        $this->assertEquals(7, $counts[42] ?? 0);
        $this->assertEquals(5, $counts[43] ?? 0);
    }

    public function test_can_count_root_terms_per_taxonomy(): void
    {
        TermFactory::new()->count(3)->subject()->create(); // roots
        TermFactory::new()->count(2)->subject()->create(); // with parents

        $rootCount = Term::where('taxonomy_id', 35)
            ->whereNull('parent_id')
            ->count();

        $this->assertEquals(3, $rootCount);
    }

    // ========================================================================
    // FULL HIERARCHY TESTS
    // ========================================================================

    public function test_can_build_subject_hierarchy(): void
    {
        // Create subject hierarchy
        $history = TermFactory::new()->subject()->create(['name' => 'History']);
        $africa = TermFactory::new()->subject()->create(['name' => 'African History', 'parent_id' => $history->id]);
        $sa = TermFactory::new()->subject()->create(['name' => 'South African History', 'parent_id' => $africa->id]);

        $this->assertEquals($history->id, $africa->parent_id);
        $this->assertEquals($africa->id, $sa->parent_id);
    }

    public function test_can_build_place_hierarchy(): void
    {
        // Create place hierarchy
        $continent = TermFactory::new()->place()->create(['name' => 'Africa']);
        $country = TermFactory::new()->place()->create(['name' => 'South Africa', 'parent_id' => $continent->id]);
        $province = TermFactory::new()->place()->create(['name' => 'Gauteng', 'parent_id' => $country->id]);

        $this->assertEquals(3, $continent->children()->count());
    }
}
