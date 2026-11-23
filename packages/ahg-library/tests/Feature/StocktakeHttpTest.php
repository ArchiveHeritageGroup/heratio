<?php

/**
 * StocktakeHttpTest - the staff screens end to end on the real MySQL schema,
 * inside a rolled-back transaction (#1474 item 5).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgLibrary\Exceptions\StocktakeException;
use AhgLibrary\Services\LibraryStocktakeService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StocktakeHttpTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        if (! Schema::hasTable('library_stocktake')) {
            $this->markTestSkipped('Stocktake tables are not installed.');
        }
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert([
            'id' => $id, 'username' => 'stocktake-' . $id, 'email' => uniqid('stocktake-', true) . '@example.test',
            'password_hash' => Hash::make('x'), 'active' => 1,
        ]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }

    public function test_count_reconcile_sign_off_and_post_through_the_screens(): void
    {
        $admin = $this->admin();
        $ioId = (int) DB::table('information_object')->value('id');
        $itemId = (int) DB::table('library_item')->insertGetId([
            'information_object_id' => $ioId, 'material_type' => 'monograph', 'created_at' => now(),
        ]);
        foreach (['ST-HERE', 'ST-GONE'] as $barcode) {
            DB::table('library_copy')->insert([
                'library_item_id' => $itemId, 'barcode' => $barcode, 'status' => 'available',
                'shelf_location' => 'B2', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $assetId = (int) DB::table('heritage_asset')->insertGetId([
            'information_object_id' => $ioId, 'recognition_status' => 'recognised',
            'current_carrying_amount' => 500, 'revaluation_surplus' => 0,
        ]);

        $this->actingAs($admin)->get('/library-manage/stocktake')->assertOk()->assertSee('Start a stocktake');

        $this->post('/library-manage/stocktake', ['name' => 'HTTP count', 'shelf_from' => 'B1', 'shelf_to' => 'B3'])->assertRedirect();
        $id = (int) DB::table('library_stocktake')->where('name', 'HTTP count')->value('id');

        $this->post("/library-manage/stocktake/{$id}/scan", ['scanned_shelf' => 'B2', 'barcodes' => "ST-HERE\nST-UNKNOWN"])->assertRedirect();
        $this->post("/library-manage/stocktake/{$id}/reconcile")->assertRedirect();
        $this->get("/library-manage/stocktake/{$id}")->assertOk()->assertSee('ST-GONE')->assertSee('ST-UNKNOWN');

        // The starter is refused; another person signs.
        $this->post("/library-manage/stocktake/{$id}/sign-off", ['confirm' => '1'])->assertRedirect()->assertSessionHas('error');
        DB::table('library_stocktake')->where('id', $id)->update(['created_by' => null]);
        $this->post("/library-manage/stocktake/{$id}/sign-off", ['confirm' => '1'])->assertRedirect()->assertSessionHas('success');

        $line = DB::table('library_stocktake_line')->where('stocktake_id', $id)->where('barcode', 'ST-GONE')->first();
        $this->assertSame('missing', $line->classification);
        $this->assertSame($assetId, (int) $line->heritage_asset_id);
        $this->assertEquals(250.00, (float) $line->proposed_amount);

        DB::table('heritage_institution_config')->whereNull('repository_id')->delete();
        DB::table('heritage_institution_config')->insert(['repository_id' => null, 'region_code' => 'test', 'currency' => 'EUR']);

        $this->get("/library-manage/stocktake/{$id}")->assertOk()->assertSee('Post impairment');
        $this->post("/library-manage/stocktake/{$id}/lines/{$line->id}/impairment", [
            // A currency sent by the browser is ignored.
            'amount' => '250', 'valuation_date' => now()->toDateString(), 'currency' => 'JPY',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(['EUR'], DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $assetId)->distinct()->pluck('currency')->all());
        $this->assertSame('missing', DB::table('library_copy')->where('barcode', 'ST-GONE')->value('status'));

        $this->assertEquals(-250.00, (float) DB::table('ahg_heritage_oci_movement')
            ->where('heritage_asset_id', $assetId)->where('posted_to', 'P&L')->sum('amount'));
        $this->assertEquals(250.00, (float) DB::table('heritage_asset')->where('id', $assetId)->value('current_carrying_amount'));

        $csv = $this->get("/library-manage/stocktake/{$id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString('ST-GONE', $csv);
    }

    public function test_a_line_from_another_stocktake_cannot_be_posted(): void
    {
        $admin = $this->admin();
        $a = (int) DB::table('library_stocktake')->insertGetId(['name' => 'A', 'status' => 'signed_off', 'started_at' => now()]);
        $b = (int) DB::table('library_stocktake')->insertGetId(['name' => 'B', 'status' => 'signed_off', 'started_at' => now()]);
        $line = (int) DB::table('library_stocktake_line')->insertGetId(['stocktake_id' => $b, 'classification' => 'missing']);

        $this->actingAs($admin)->post("/library-manage/stocktake/{$a}/lines/{$line}/impairment", [
            'amount' => '1', 'valuation_date' => now()->toDateString(), 'currency' => 'EUR',
        ])->assertNotFound();
    }

    public function test_a_copy_that_changed_after_sign_off_shows_as_needing_review(): void
    {
        $admin = $this->admin();
        $ioId = (int) DB::table('information_object')->value('id');
        $itemId = (int) DB::table('library_item')->insertGetId([
            'information_object_id' => $ioId, 'material_type' => 'monograph', 'created_at' => now(),
        ]);
        $copyId = (int) DB::table('library_copy')->insertGetId([
            'library_item_id' => $itemId, 'barcode' => 'ST-STALE', 'status' => 'withdrawn',
            'shelf_location' => 'B2', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assetId = (int) DB::table('heritage_asset')->insertGetId([
            'information_object_id' => $ioId, 'recognition_status' => 'recognised',
            'current_carrying_amount' => 500, 'revaluation_surplus' => 0,
        ]);
        $id = (int) DB::table('library_stocktake')->insertGetId([
            'name' => 'Stale', 'status' => 'signed_off', 'started_at' => now(), 'reconciled_at' => now(),
        ]);
        DB::table('library_stocktake_line')->insert([
            'stocktake_id' => $id, 'copy_id' => $copyId, 'barcode' => 'ST-STALE', 'library_item_id' => $itemId,
            'information_object_id' => $ioId, 'copy_status' => 'available', 'classification' => 'missing',
            'reason_code' => 'not_scanned', 'heritage_asset_id' => $assetId,
            'carrying_snapshot' => 500, 'proposed_amount' => 500,
        ]);

        $this->actingAs($admin)->get("/library-manage/stocktake/{$id}")->assertOk()
            ->assertSee('Needs review')->assertDontSee('Post impairment');
    }

    public function test_a_database_failure_is_logged_and_not_shown_to_staff(): void
    {
        $admin = $this->admin();
        $id = (int) DB::table('library_stocktake')->insertGetId(['name' => 'Fail', 'status' => 'open', 'started_at' => now()]);
        $secret = 'select * from library_stocktake_line where secret_column = 1';
        $this->mock(LibraryStocktakeService::class, function ($mock) use ($secret) {
            $mock->shouldReceive('reconcile')->andThrow(new QueryException('mysql', $secret, [], new \RuntimeException('Deadlock found')));
        });
        Log::spy();

        $response = $this->actingAs($admin)->post("/library-manage/stocktake/{$id}/reconcile")->assertRedirect();

        $message = (string) $response->baseResponse->getSession()->get('error');
        $this->assertStringContainsString('could not be completed', $message);
        $this->assertStringNotContainsString('secret_column', $message);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_a_business_rule_refusal_reaches_the_screen(): void
    {
        $admin = $this->admin();
        $id = (int) DB::table('library_stocktake')->insertGetId(['name' => 'Rule', 'status' => 'open', 'started_at' => now()]);
        $this->mock(LibraryStocktakeService::class, function ($mock) {
            $mock->shouldReceive('reconcile')->andThrow(new StocktakeException('A clear refusal'));
        });

        $this->actingAs($admin)->post("/library-manage/stocktake/{$id}/reconcile")
            ->assertRedirect()->assertSessionHas('error', 'A clear refusal');
    }

    public function test_guests_are_kept_out(): void
    {
        $this->get('/library-manage/stocktake')->assertRedirect();
        $this->post('/library-manage/stocktake', ['name' => 'x'])->assertRedirect();
    }
}
