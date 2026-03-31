<?php

namespace Tests\Feature;

use AhgCore\Models\QubitAccession;
use Database\Factories\AccessionFactory;
use Database\Factories\ActorFactory;
use Database\Factories\InformationObjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive CRUD tests for Accessions
 * 
 * Coverage:
 * - Create accessions (gift, purchase, donation, deposit, transfer)
 * - Read accessions (browse, search, filter)
 * - Update accessions
 * - Delete accessions
 * - Appraisal workflow
 * - Processing workflow
 * - Rights and copyright
 * - Related collections
 */
class AccessionCrudTest extends TestCase
{
    use RefreshDatabase;

    protected AccessionFactory $accessionFactory;
    protected ActorFactory $actorFactory;
    protected InformationObjectFactory $ioFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessionFactory = new AccessionFactory();
        $this->actorFactory = new ActorFactory();
        $this->ioFactory = new InformationObjectFactory();
    }

    // ========================================================================
    // CREATE TESTS - By Acquisition Type
    // ========================================================================

    public function test_can_create_gift_accession(): void
    {
        $accession = AccessionFactory::new()->gift()->create([
            'title' => 'Gift of personal papers',
            '来源' => 'John Smith',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            'title' => 'Gift of personal papers',
            '取得方式' => 'gift',
        ]);
    }

    public function test_can_create_purchase_accession(): void
    {
        $accession = AccessionFactory::new()->purchase()->create([
            'title' => 'Purchased collection',
            '版税' => 5000,
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            '取得方式' => 'purchase',
        ]);
    }

    public function test_can_create_donation_accession(): void
    {
        $accession = AccessionFactory::new()->donation()->create([
            'title' => 'Donated photographs',
            '来源' => 'Jane Doe',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            '取得方式' => 'donation',
        ]);
    }

    public function test_can_create_accession_with_identifier(): void
    {
        $accession = AccessionFactory::new()->create([
            'identifier' => 'ACC-2024-0001',
            'title' => 'Test Accession',
        ]);

        $this->assertDatabaseHas('accession', [
            'identifier' => 'ACC-2024-0001',
        ]);
    }

    public function test_can_create_accession_with_date(): void
    {
        $accession = AccessionFactory::new()->create([
            'date' => '2024-01-15',
            '接收日期' => '2024-01-20',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            'date' => '2024-01-15',
        ]);
    }

    // ========================================================================
    // READ TESTS
    // ========================================================================

    public function test_can_find_accession_by_id(): void
    {
        $accession = AccessionFactory::new()->create();

        $found = QubitAccession::find($accession->id);

        $this->assertNotNull($found);
        $this->assertEquals($accession->id, $found->id);
    }

    public function test_can_get_all_accessions(): void
    {
        AccessionFactory::new()->count(10)->create();

        $accessions = QubitAccession::all();

        $this->assertCount(10, $accessions);
    }

    public function test_can_filter_by_acquisition_type(): void
    {
        AccessionFactory::new()->count(5)->gift()->create();
        AccessionFactory::new()->count(3)->purchase()->create();
        AccessionFactory::new()->count(2)->donation()->create();

        $gifts = QubitAccession::where('取得方式', 'gift')->get();

        $this->assertCount(5, $gifts);
    }

    public function test_can_search_accessions_by_title(): void
    {
        AccessionFactory::new()->create(['title' => 'Personal Papers of John Smith']);
        AccessionFactory::new()->create(['title' => 'Financial Records 1950']);
        AccessionFactory::new()->create(['title' => 'Smith Family Photographs']);

        $results = QubitAccession::where('title', 'LIKE', '%Smith%')->get();

        $this->assertCount(2, $results);
    }

    public function test_can_filter_by_copyright_status(): void
    {
        AccessionFactory::new()->count(3)->create(['版权状态' => 'unknown']);
        AccessionFactory::new()->count(2)->create(['版权状态' => 'copyright']);
        AccessionFactory::new()->count(1)->create(['版权状态' => 'public_domain']);

        $copyright = QubitAccession::where('版权状态', 'copyright')->get();

        $this->assertCount(2, $copyright);
    }

    public function test_can_filter_by_condition(): void
    {
        AccessionFactory::new()->count(4)->create(['condition' => 'good']);
        AccessionFactory::new()->count(2)->create(['condition' => 'poor']);

        $poor = QubitAccession::where('condition', 'poor')->get();

        $this->assertCount(2, $poor);
    }

    public function test_can_filter_by_repository(): void
    {
        $repo1 = $this->faker->numberBetween(1, 100);
        $repo2 = $this->faker->numberBetween(1, 100);

        AccessionFactory::new()->count(3)->create(['repository_id' => $repo1]);
        AccessionFactory::new()->count(2)->create(['repository_id' => $repo2]);

        $repo1Accessions = QubitAccession::where('repository_id', $repo1)->get();

        $this->assertCount(3, $repo1Accessions);
    }

    // ========================================================================
    // UPDATE TESTS
    // ========================================================================

    public function test_can_update_accession_title(): void
    {
        $accession = AccessionFactory::new()->create([
            'title' => 'Original Title',
        ]);

        $accession->update(['title' => 'Updated Title']);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_update_acquisition_type(): void
    {
        $accession = AccessionFactory::new()->gift()->create();

        $accession->update(['取得方式' => 'purchase']);

        $this->assertEquals('purchase', $accession->fresh()->取得方式);
    }

    public function test_can_update_copyright_status(): void
    {
        $accession = AccessionFactory::new()->create(['版权状态' => 'unknown']);

        $accession->update(['版权状态' => 'copyright']);

        $this->assertEquals('copyright', $accession->fresh()->版权状态);
    }

    public function test_can_update_condition(): void
    {
        $accession = AccessionFactory::new()->create(['condition' => 'good']);

        $accession->update(['condition' => 'poor']);

        $this->assertEquals('poor', $accession->fresh()->condition);
    }

    public function test_can_update_location(): void
    {
        $accession = AccessionFactory::new()->create();

        $accession->update(['storage_location' => 'Box A-123']);

        $this->assertEquals('Box A-123', $accession->fresh()->storage_location);
    }

    // ========================================================================
    // APPRAISAL WORKFLOW TESTS
    // ========================================================================

    public function test_can_record_appraisal(): void
    {
        $accession = AccessionFactory::new()->create();

        $accession->update([
            'appraisal_note' => 'Significant for South African history',
            'appraisal_date' => now()->toDateString(),
            'appraisal_by' => 'Admin User',
        ]);

        $this->assertNotNull($accession->fresh()->appraisal_note);
        $this->assertNotNull($accession->fresh()->appraisal_date);
    }

    public function test_appraisal_can_be_approved(): void
    {
        $accession = AccessionFactory::new()->create([
            '评价备注' => 'Recommended for permanent retention',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            '评价备注' => 'Recommended for permanent retention',
        ]);
    }

    public function test_appraisal_can_be_rejected(): void
    {
        $accession = AccessionFactory::new()->create([
            '评价备注' => 'Not suitable for collection',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            '评价备注' => 'Not suitable for collection',
        ]);
    }

    // ========================================================================
    // PROCESSING WORKFLOW TESTS
    // ========================================================================

    public function test_can_start_processing(): void
    {
        $accession = AccessionFactory::new()->create([
            'processing_notes' => 'Processing started',
            'processing_date' => now()->toDateString(),
            'processing_by' => 'Archivist A',
        ]);

        $this->assertNotNull($accession->processing_notes);
        $this->assertEquals('Archivist A', $accession->processing_by);
    }

    public function test_can_set_processing_priority(): void
    {
        $accession = AccessionFactory::new()->create([
            'processing_priority' => 'high',
        ]);

        $this->assertEquals('high', $accession->fresh()->processing_priority);
    }

    public function test_can_complete_processing(): void
    {
        $accession = AccessionFactory::new()->create([
            'processing_notes' => 'Processing completed - all materials sorted and described',
        ]);

        $this->assertStringContains('completed', $accession->processing_notes);
    }

    // ========================================================================
    // RIGHTS AND COPYRIGHT TESTS
    // ========================================================================

    public function test_can_set_copyright_status(): void
    {
        $accession = AccessionFactory::new()->create([
            '版权状态' => 'copyright',
            '版权持有人' => 'Estate of Author',
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            '版权状态' => 'copyright',
            '版权持有人' => 'Estate of Author',
        ]);
    }

    public function test_can_set_public_domain(): void
    {
        $accession = AccessionFactory::new()->create([
            '版权状态' => 'public_domain',
        ]);

        $this->assertEquals('public_domain', $accession->fresh()->版权状态);
    }

    public function test_can_set_orphaned_copyright(): void
    {
        $accession = AccessionFactory::new()->create([
            '版权状态' => 'orphaned',
        ]);

        $this->assertEquals('orphaned', $accession->fresh()->版权状态);
    }

    // ========================================================================
    // RELATED MATERIAL TESTS
    // ========================================================================

    public function test_can_link_to_related_material(): void
    {
        $accession = AccessionFactory::new()->create([
            '相关收藏' => 'Related to ACC-2023-0015',
            'related_material' => 'See also: Photographs collection',
        ]);

        $this->assertNotNull($accession->related_material);
    }

    public function test_can_link_to_external_documents(): void
    {
        $accession = AccessionFactory::new()->create([
            '外部机构' => 'National Archives of South Africa',
            'external_documents' => 'Deed of gift on file',
        ]);

        $this->assertNotNull($accession->外部机构);
        $this->assertNotNull($accession->external_documents);
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

    public function test_deleting_accession_does_not_delete_related_io(): void
    {
        $accession = AccessionFactory::new()->create();
        $io = InformationObjectFactory::new()->create([
            'accession_id' => $accession->id,
        ]);

        $accession->delete();

        // IO should still exist
        $this->assertDatabaseHas('information_object', ['id' => $io->id]);
    }

    // ========================================================================
    // STATISTICS TESTS
    // ========================================================================

    public function test_can_count_by_acquisition_type(): void
    {
        AccessionFactory::new()->count(5)->gift()->create();
        AccessionFactory::new()->count(3)->purchase()->create();
        AccessionFactory::new()->count(2)->donation()->create();

        $counts = QubitAccession::selectRaw('取得方式, COUNT(*) as cnt')
            ->groupBy('取得方式')
            ->pluck('cnt', '取得方式')
            ->toArray();

        $this->assertEquals(5, $counts['gift']);
        $this->assertEquals(3, $counts['purchase']);
        $this->assertEquals(2, $counts['donation']);
    }

    public function test_can_count_by_copyright_status(): void
    {
        AccessionFactory::new()->count(4)->create(['版权状态' => 'unknown']);
        AccessionFactory::new()->count(3)->create(['版权状态' => 'copyright']);
        AccessionFactory::new()->count(1)->create(['版权状态' => 'public_domain']);

        $counts = QubitAccession::selectRaw('版权状态, COUNT(*) as cnt')
            ->whereNotNull('版权状态')
            ->groupBy('版权状态')
            ->pluck('cnt', '版权状态')
            ->toArray();

        $this->assertEquals(4, $counts['unknown']);
        $this->assertEquals(3, $counts['copyright']);
    }

    public function test_can_count_by_condition(): void
    {
        AccessionFactory::new()->count(6)->create(['condition' => 'good']);
        AccessionFactory::new()->count(2)->create(['condition' => 'poor']);
        AccessionFactory::new()->count(1)->create(['condition' => 'excellent']);

        $counts = QubitAccession::selectRaw('condition, COUNT(*) as cnt')
            ->whereNotNull('condition')
            ->groupBy('condition')
            ->pluck('cnt', 'condition')
            ->toArray();

        $this->assertEquals(6, $counts['good']);
        $this->assertEquals(2, $counts['poor']);
    }

    // ========================================================================
    // PAGINATION TESTS
    // ========================================================================

    public function test_accessions_can_be_paginated(): void
    {
        AccessionFactory::new()->count(50)->create();

        $paginated = QubitAccession::paginate(20);

        $this->assertEquals(20, $paginated->perPage());
        $this->assertEquals(50, $paginated->total());
        $this->assertEquals(3, $paginated->lastPage());
    }

    // ========================================================================
    // DONOR RELATIONS TESTS
    // ========================================================================

    public function test_can_link_donor(): void
    {
        $accession = AccessionFactory::new()->create([
            'donor_id' => 123,
        ]);

        $this->assertDatabaseHas('accession', [
            'id' => $accession->id,
            'donor_id' => 123,
        ]);
    }

    public function test_can_record_source(): void
    {
        $accession = AccessionFactory::new()->create([
            '来源' => 'National Museum',
        ]);

        $this->assertEquals('National Museum', $accession->来源);
    }

    // ========================================================================
    // PHYSICAL DESCRIPTION TESTS
    // ========================================================================

    public function test_can_add_physical_description(): void
    {
        $accession = AccessionFactory::new()->create([
            '物理描述' => '10 boxes, 5 meters of shelving',
            '物理描述媒介' => 'paper, photographs',
        ]);

        $this->assertNotNull($accession->物理描述);
        $this->assertEquals('paper, photographs', $accession->物理描述媒介);
    }

    public function test_can_add_storage_location(): void
    {
        $accession = AccessionFactory::new()->create([
            'storage_location' => 'Archive Building, Room 101, Shelf A',
            '位置' => 'Compact shelving unit 3',
        ]);

        $this->assertEquals('Archive Building, Room 101, Shelf A', $accession->storage_location);
    }
}
