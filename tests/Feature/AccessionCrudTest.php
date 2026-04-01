<?php

namespace Tests\Feature;

use AhgCore\Models\Accession;
use AhgCore\Models\AccessionI18n;
use Database\Factories\AccessionFactory;
use Database\Factories\InformationObjectFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * CRUD tests for Accessions
 *
 * Tests the real AtoM i18n schema: accession + accession_i18n tables.
 */
class AccessionCrudTest extends TestCase
{
    use DatabaseTransactions;

    // ========================================================================
    // CREATE TESTS
    // ========================================================================

    public function test_can_create_accession(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['title' => 'Gift of personal papers'])
            ->create();

        $this->assertDatabaseHas('accession', ['id' => $accession->id]);
        $this->assertDatabaseHas('accession_i18n', [
            'id' => $accession->id,
            'culture' => 'en',
            'title' => 'Gift of personal papers',
        ]);
    }

    public function test_can_create_accession_with_identifier(): void
    {
        $accession = AccessionFactory::new()->create([
            'identifier' => 'ACC-2024-0001',
        ]);

        $this->assertDatabaseHas('accession', [
            'identifier' => 'ACC-2024-0001',
        ]);
    }

    public function test_can_create_accession_with_date(): void
    {
        $accession = AccessionFactory::new()->create([
            'date' => '2024-01-15',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            'date' => '2024-01-15',
        ]);
    }

    public function test_can_create_accession_with_processing_notes(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['processing_notes' => 'Processing started', 'title' => 'Test'])
            ->create();

        $this->assertDatabaseHas('accession_i18n', [
            'id' => $accession->id,
            'processing_notes' => 'Processing started',
        ]);
    }

    // ========================================================================
    // READ TESTS
    // ========================================================================

    public function test_can_find_accession_by_id(): void
    {
        $accession = AccessionFactory::new()->create();

        $found = Accession::find($accession->id);

        $this->assertNotNull($found);
        $this->assertEquals($accession->id, $found->id);
    }

    public function test_can_get_title_via_i18n(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['title' => 'I18n Accession Title'])
            ->create();

        $this->assertEquals('I18n Accession Title', $accession->getTranslated('title'));
    }

    public function test_can_search_accessions_by_title(): void
    {
        AccessionFactory::new()->withI18n(['title' => 'Personal Papers of UniqueSmith'])->create();
        AccessionFactory::new()->withI18n(['title' => 'Financial Records 1950'])->create();
        AccessionFactory::new()->withI18n(['title' => 'UniqueSmith Family Photographs'])->create();

        $results = Accession::whereHas('i18n', function ($q) {
            $q->where('title', 'LIKE', '%UniqueSmith%');
        })->get();

        $this->assertCount(2, $results);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_accession_title(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['title' => 'Original Title'])
            ->create();

        AccessionI18n::where('id', $accession->id)->where('culture', 'en')
            ->update(['title' => 'Updated Title']);

        $this->assertDatabaseHas('accession_i18n', [
            'id' => $accession->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_update_scope_and_content(): void
    {
        $accession = AccessionFactory::new()->create();

        AccessionI18n::where('id', $accession->id)->where('culture', 'en')
            ->update(['scope_and_content' => 'Updated scope.']);

        $this->assertEquals('Updated scope.', $accession->getTranslated('scope_and_content'));
    }

    public function test_can_update_source_of_acquisition(): void
    {
        $accession = AccessionFactory::new()->create();

        AccessionI18n::where('id', $accession->id)->where('culture', 'en')
            ->update(['source_of_acquisition' => 'National Museum']);

        $this->assertEquals('National Museum', $accession->getTranslated('source_of_acquisition'));
    }

    // ========================================================================
    // DELETE TESTS
    // ========================================================================

    public function test_can_delete_accession(): void
    {
        $accession = AccessionFactory::new()->create();
        $id = $accession->id;

        $accession->delete();

        $this->assertDatabaseMissing('accession', ['id' => $id]);
    }

    // ========================================================================
    // PAGINATION TESTS
    // ========================================================================

    public function test_accessions_can_be_paginated(): void
    {
        AccessionFactory::new()->count(25)->create();

        $paginated = Accession::paginate(10);

        $this->assertEquals(10, $paginated->perPage());
        $this->assertGreaterThanOrEqual(25, $paginated->total());
    }

    // ========================================================================
    // I18N FIELDS TESTS
    // ========================================================================

    public function test_can_set_appraisal(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['appraisal' => 'Significant for South African history'])
            ->create();

        $this->assertDatabaseHas('accession_i18n', [
            'id' => $accession->id,
            'appraisal' => 'Significant for South African history',
        ]);
    }

    public function test_can_set_physical_characteristics(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['physical_characteristics' => '10 boxes, 5 meters of shelving'])
            ->create();

        $this->assertEquals(
            '10 boxes, 5 meters of shelving',
            $accession->getTranslated('physical_characteristics')
        );
    }

    public function test_can_set_location_information(): void
    {
        $accession = AccessionFactory::new()
            ->withI18n(['location_information' => 'Archive Building, Room 101'])
            ->create();

        $this->assertEquals(
            'Archive Building, Room 101',
            $accession->getTranslated('location_information')
        );
    }
}
