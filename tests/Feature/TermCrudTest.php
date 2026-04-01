<?php

namespace Tests\Feature;

use AhgCore\Models\Term;
use AhgCore\Models\TermI18n;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CRUD tests for Taxonomy Terms
 *
 * Tests the real AtoM i18n schema: term + term_i18n tables.
 */
class TermCrudTest extends TestCase
{
    use DatabaseTransactions;

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_subject_term(): void
    {
        $term = TermFactory::new()->subject()
            ->withI18n(['name' => 'World War II'])
            ->create();

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'taxonomy_id' => 35,
        ]);
        $this->assertDatabaseHas('term_i18n', [
            'id' => $term->id,
            'culture' => 'en',
            'name' => 'World War II',
        ]);
    }

    public function test_can_create_place_term(): void
    {
        $term = TermFactory::new()->place()
            ->withI18n(['name' => 'Johannesburg'])
            ->create();

        $this->assertDatabaseHas('term', ['id' => $term->id, 'taxonomy_id' => 42]);
        $this->assertDatabaseHas('term_i18n', ['id' => $term->id, 'name' => 'Johannesburg']);
    }

    public function test_can_create_genre_term(): void
    {
        $term = TermFactory::new()->genre()
            ->withI18n(['name' => 'Photographs'])
            ->create();

        $this->assertDatabaseHas('term', ['id' => $term->id, 'taxonomy_id' => 43]);
        $this->assertDatabaseHas('term_i18n', ['id' => $term->id, 'name' => 'Photographs']);
    }

    public function test_can_create_term_with_code(): void
    {
        $term = TermFactory::new()->create(['code' => 'MIL-001']);

        $this->assertDatabaseHas('term', [
            'id' => $term->id,
            'code' => 'MIL-001',
        ]);
    }

    public function test_can_create_hierarchical_terms(): void
    {
        $parent = TermFactory::new()->subject()
            ->withI18n(['name' => 'Military History'])
            ->create();

        $child = TermFactory::new()->subject()
            ->withI18n(['name' => 'World War I'])
            ->create(['parent_id' => $parent->id]);

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

    public function test_can_get_name_via_i18n(): void
    {
        $term = TermFactory::new()
            ->withI18n(['name' => 'I18n Lookup Test'])
            ->create();

        $this->assertEquals('I18n Lookup Test', $term->getName('en'));
    }

    public function test_can_get_terms_by_taxonomy(): void
    {
        $before = Term::where('taxonomy_id', 35)->count();
        TermFactory::new()->count(5)->subject()->create();

        $this->assertEquals($before + 5, Term::where('taxonomy_id', 35)->count());
    }

    public function test_can_get_children_of_term(): void
    {
        $parent = TermFactory::new()->subject()->create();
        TermFactory::new()->count(3)->subject()->create(['parent_id' => $parent->id]);

        $children = Term::where('parent_id', $parent->id)->get();

        $this->assertCount(3, $children);
    }

    public function test_can_search_terms_by_name(): void
    {
        TermFactory::new()->withI18n(['name' => 'UniqueSearchAfrica'])->create(['taxonomy_id' => 42]);
        TermFactory::new()->withI18n(['name' => 'UniqueSearchAfricaHistory'])->create();
        TermFactory::new()->withI18n(['name' => 'UniqueSearchAmerica'])->create();

        $results = Term::whereHas('i18n', function ($q) {
            $q->where('name', 'LIKE', '%UniqueSearchAfrica%');
        })->get();

        $this->assertCount(2, $results);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_term_name(): void
    {
        $term = TermFactory::new()
            ->withI18n(['name' => 'Original Name'])
            ->create();

        TermI18n::where('id', $term->id)->where('culture', 'en')
            ->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('term_i18n', [
            'id' => $term->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_move_term_to_new_parent(): void
    {
        $parent1 = TermFactory::new()->subject()->create();
        $parent2 = TermFactory::new()->subject()->create();
        $child = TermFactory::new()->subject()->create(['parent_id' => $parent1->id]);

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

    // ========================================================================
    // TAXONOMY FILTERING TESTS
    // ========================================================================

    public function test_can_filter_by_subject_taxonomy(): void
    {
        $before = Term::where('taxonomy_id', 35)->count();
        TermFactory::new()->count(4)->subject()->create();

        $this->assertEquals($before + 4, Term::where('taxonomy_id', 35)->count());
    }

    public function test_can_filter_by_place_taxonomy(): void
    {
        $before = Term::where('taxonomy_id', 42)->count();
        TermFactory::new()->count(5)->place()->create();

        $this->assertEquals($before + 5, Term::where('taxonomy_id', 42)->count());
    }

    // ========================================================================
    // PAGINATION TESTS
    // ========================================================================

    public function test_terms_can_be_paginated(): void
    {
        TermFactory::new()->count(25)->create();

        $paginated = Term::paginate(10);

        $this->assertEquals(10, $paginated->perPage());
        $this->assertGreaterThanOrEqual(25, $paginated->total());
    }

    // ========================================================================
    // HIERARCHY TESTS
    // ========================================================================

    public function test_can_build_subject_hierarchy(): void
    {
        $history = TermFactory::new()->subject()->withI18n(['name' => 'History'])->create();
        $africa = TermFactory::new()->subject()->withI18n(['name' => 'African History'])->create(['parent_id' => $history->id]);
        $sa = TermFactory::new()->subject()->withI18n(['name' => 'South African History'])->create(['parent_id' => $africa->id]);

        $this->assertEquals($history->id, $africa->parent_id);
        $this->assertEquals($africa->id, $sa->parent_id);
    }
}
