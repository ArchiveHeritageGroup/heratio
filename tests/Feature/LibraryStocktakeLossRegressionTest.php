<?php

/**
 * Regression check: what a stocktake loss writes to the impairment register.
 * Runs on the private in-memory SQLite harness of LibraryFeatureTestCase.
 */

namespace Tests\Feature;

use AhgLibrary\Services\LibraryStocktakeService;
use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Tests\Feature\LibraryFeatureTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibraryStocktakeLossRegressionTest extends LibraryFeatureTestCase
{
    protected function setUp(): void
    {
        $this->migrationFiles[] = '2026_11_22_100000_create_library_stocktake_tables.php';
        parent::setUp();
        LibraryBranch::forgetSchemaCache();
        if (! Schema::hasColumn('library_copy', 'branch_id')) {
            Schema::table('library_copy', fn (Blueprint $t) => $t->integer('branch_id')->nullable());
        }
        if (! Schema::hasColumn('library_item', 'shelf_location')) {
            Schema::table('library_item', fn (Blueprint $t) => $t->string('shelf_location', 100)->nullable());
        }
        Schema::create('heritage_asset', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('information_object_id')->nullable();
            $t->integer('object_id')->nullable();
            $t->string('recognition_status')->nullable();
            $t->decimal('current_carrying_amount', 18, 2)->default(0);
            $t->decimal('revaluation_surplus', 18, 2)->default(0);
            $t->decimal('impairment_loss', 18, 2)->default(0);
            $t->decimal('recoverable_amount', 18, 2)->nullable();
            $t->date('last_impairment_date')->nullable();
            $t->boolean('impairment_indicators')->default(false);
            $t->text('impairment_indicators_details')->nullable();
            $t->date('derecognition_date')->nullable();
            $t->dateTime('updated_at')->nullable();
        });
        Schema::create('ahg_heritage_oci_movement', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('information_object_id')->nullable();
            $t->integer('heritage_asset_id')->nullable();
            $t->string('movement_type', 16);
            $t->decimal('amount', 15, 2);
            $t->char('currency', 3);
            $t->date('valuation_date');
            $t->integer('valuer_id')->nullable();
            $t->string('valuation_method')->nullable();
            $t->text('reason')->nullable();
            $t->string('posted_to', 32);
            $t->integer('created_by_user_id')->nullable();
            $t->dateTime('created_at')->nullable();
        });
        Schema::create('heritage_institution_config', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('repository_id')->nullable();
            $t->string('currency', 3)->nullable();
        });
        DB::table('heritage_institution_config')->insert(['repository_id' => null, 'currency' => 'EUR']);
        Schema::create('heritage_impairment_assessment', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('heritage_asset_id');
            $t->date('assessment_date');
            $t->boolean('physical_damage')->default(false);
            $t->text('physical_damage_details')->nullable();
            $t->boolean('impairment_identified')->default(false);
            $t->decimal('carrying_amount_before', 18, 2)->nullable();
            $t->decimal('recoverable_amount', 18, 2)->nullable();
            $t->decimal('impairment_loss', 18, 2)->nullable();
            $t->decimal('carrying_amount_after', 18, 2)->nullable();
            $t->text('notes')->nullable();
            $t->integer('created_by')->nullable();
            $t->dateTime('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        LibraryBranch::forgetSchemaCache();
        parent::tearDown();
    }

    public function test_loss_sets_no_physical_damage_and_records_not_located(): void
    {
        $svc = new LibraryStocktakeService();
        $itemId = (int) DB::table('library_item')->insertGetId(['information_object_id' => 500, 'material_type' => 'monograph', 'created_at' => now()]);
        DB::table('library_copy')->insert(['library_item_id' => $itemId, 'barcode' => 'GONE', 'status' => 'available', 'shelf_location' => 'A2', 'branch_id' => 7, 'created_at' => now(), 'updated_at' => now()]);
        $assetId = (int) DB::table('heritage_asset')->insertGetId(['information_object_id' => 500, 'recognition_status' => 'recognised', 'current_carrying_amount' => 1000]);

        $id = $svc->create(['name' => 'Count', 'branch_id' => 7, 'shelf_from' => 'A1', 'shelf_to' => 'A9'], 1);
        $svc->reconcile($id);
        $svc->signOff($id, 2, 'ok');
        $line = DB::table('library_stocktake_line')->where('stocktake_id', $id)->where('barcode', 'GONE')->first();
        $svc->postImpairment($line->id, 250, '2026-11-20', null, 2);

        $row = DB::table('heritage_impairment_assessment')->where('heritage_asset_id', $assetId)->first();
        $this->assertEquals(0, (int) $row->physical_damage);
        $this->assertNull($row->physical_damage_details);
        $this->assertStringContainsString('not located', (string) $row->notes);
        $this->assertStringContainsString('not located', (string) DB::table('heritage_asset')->where('id', $assetId)->value('impairment_indicators_details'));
    }
}
