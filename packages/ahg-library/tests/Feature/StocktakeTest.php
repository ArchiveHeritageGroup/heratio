<?php

/**
 * StocktakeTest - reconcile classification, sign-off rules and the posting of
 * missing copies through the real OCI and register services (#1474 item 5).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgHeritageManage\Services\OciMovementService;
use AhgLibrary\Controllers\StocktakeController;
use AhgLibrary\Exceptions\StocktakeException;
use AhgLibrary\Services\LibraryStocktakeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class StocktakeTest extends LibraryFeatureTestCase
{
    protected LibraryStocktakeService $svc;
    protected int $itemId;

    protected function setUp(): void
    {
        $this->migrationFiles[] = '2026_11_22_100000_create_library_stocktake_tables.php';
        parent::setUp();
        \AhgLibrary\Support\LibraryBranch::forgetSchemaCache();
        // The branch migration uses MySQL-only index checks, so add the column here.
        if (! Schema::hasColumn('library_copy', 'branch_id')) {
            Schema::table('library_copy', fn (Blueprint $t) => $t->integer('branch_id')->nullable());
        }
        if (! Schema::hasColumn('library_item', 'shelf_location')) {
            Schema::table('library_item', fn (Blueprint $t) => $t->string('shelf_location', 100)->nullable());
        }
        $this->svc = new LibraryStocktakeService();
        $this->heritageSchema();
        $this->itemId = $this->item(500);
    }

    protected function tearDown(): void
    {
        \AhgLibrary\Support\LibraryBranch::forgetSchemaCache();
        parent::tearDown();
    }

    private function heritageSchema(): void
    {
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

    private function item(int $ioId): int
    {
        return (int) DB::table('library_item')->insertGetId([
            'information_object_id' => $ioId, 'material_type' => 'monograph', 'created_at' => now(),
        ]);
    }

    private function copy(string $barcode, array $over = []): int
    {
        return (int) DB::table('library_copy')->insertGetId(array_merge([
            'library_item_id' => $this->itemId, 'barcode' => $barcode, 'status' => 'available',
            'shelf_location' => 'A2', 'branch_id' => 7, 'created_at' => now(), 'updated_at' => now(),
        ], $over));
    }

    private function loan(int $copyId, string $date): void
    {
        DB::table('library_checkout')->insert([
            'copy_id' => $copyId, 'patron_id' => 1, 'checkout_date' => $date,
            'due_date' => now()->addDays(7)->toDateString(), 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function stocktake(array $over = []): int
    {
        return $this->svc->create(array_merge(['name' => 'Count', 'branch_id' => 7, 'shelf_from' => 'A1', 'shelf_to' => 'A9'], $over), 1);
    }

    private function classOf(int $id, string $barcode): array
    {
        $l = DB::table('library_stocktake_line')->where('stocktake_id', $id)->where('barcode', $barcode)->first();

        return [$l->classification ?? null, $l->reason_code ?? null];
    }

    private function asset(float $carrying, float $surplus = 0, int $ioId = 500): int
    {
        return (int) DB::table('heritage_asset')->insertGetId([
            'information_object_id' => $ioId, 'recognition_status' => 'recognised',
            'current_carrying_amount' => $carrying, 'revaluation_surplus' => $surplus,
        ]);
    }

    private function signedMissingLine(): object
    {
        $this->copy('GONE');
        $id = $this->stocktake();
        $this->svc->reconcile($id);
        $this->svc->signOff($id, 2, 'ok');

        return DB::table('library_stocktake_line')->where('stocktake_id', $id)->where('barcode', 'GONE')->first();
    }

    public function test_reconcile_classifies_every_case(): void
    {
        $this->copy('FOUND');
        $this->copy('MOVED');
        $this->copy('GONE');
        $loaned = $this->copy('LENT', ['status' => 'checked_out']);
        $this->loan($loaned, now()->subDays(3)->toDateTimeString());
        $stale = $this->copy('LENT-SHELF', ['status' => 'checked_out']);
        $this->loan($stale, now()->subDays(3)->toDateTimeString());
        $this->copy('NOLOAN', ['status' => 'checked_out']);
        $this->copy('HOLD', ['status' => 'on_hold']);
        $this->copy('GONE-BEFORE', ['status' => 'withdrawn']);
        $this->copy('OTHER', ['branch_id' => 8]);
        $this->copy('A10', ['shelf_location' => 'A10']);

        $id = $this->stocktake();
        $this->svc->recordScans($id, 'A2', ['FOUND', 'LENT-SHELF', 'NOSUCH', 'GONE-BEFORE', 'OTHER', 'A10'], 1);
        $this->svc->recordScans($id, 'A3', ['MOVED'], 1);
        $this->svc->reconcile($id);

        $this->assertSame(['found', 'on_home_shelf'], $this->classOf($id, 'FOUND'));
        $this->assertSame(['misfiled', 'wrong_shelf'], $this->classOf($id, 'MOVED'));
        $this->assertSame(['missing', 'not_scanned'], $this->classOf($id, 'GONE'));
        $this->assertSame(['on_loan', 'open_loan'], $this->classOf($id, 'LENT'));
        $this->assertSame(['unexpected', 'open_loan_on_shelf'], $this->classOf($id, 'LENT-SHELF'));
        $this->assertSame(['missing', 'status_out_no_loan'], $this->classOf($id, 'NOLOAN'));
        $this->assertSame(['elsewhere', 'held_status'], $this->classOf($id, 'HOLD'));
        $this->assertSame(['unexpected', 'unknown_barcode'], $this->classOf($id, 'NOSUCH'));
        $this->assertSame(['unexpected', 'status_excluded'], $this->classOf($id, 'GONE-BEFORE'));
        $this->assertSame(['unexpected', 'other_branch'], $this->classOf($id, 'OTHER'));
        // Natural order: A10 is past A9, so it is outside this range.
        $this->assertSame(['misfiled', 'outside_range'], $this->classOf($id, 'A10'));
    }

    public function test_a_loan_issued_after_the_scan_is_not_a_discrepancy(): void
    {
        $copy = $this->copy('BUSY');
        $id = $this->stocktake();
        $this->svc->recordScans($id, 'A2', ['BUSY'], 1);
        $this->loan($copy, now()->addMinute()->toDateTimeString());
        $this->svc->reconcile($id);

        $this->assertSame('found', $this->classOf($id, 'BUSY')[0]);
    }

    public function test_a_copy_returned_during_the_count_is_not_missing(): void
    {
        $copy = $this->copy('BACK');
        DB::table('library_checkout')->insert([
            'copy_id' => $copy, 'patron_id' => 1, 'checkout_date' => now()->subDays(5),
            'due_date' => now()->toDateString(), 'return_date' => now()->addMinute(), 'status' => 'returned',
        ]);
        $id = $this->stocktake();
        $this->svc->reconcile($id);

        $this->assertSame(['elsewhere', 'returned_during_count'], $this->classOf($id, 'BACK'));
    }

    public function test_a_new_scan_forces_another_reconcile_before_sign_off(): void
    {
        $this->copy('X');
        $id = $this->stocktake();
        $this->svc->reconcile($id);
        $this->svc->recordScans($id, 'A2', ['X'], 1);

        $this->expectException(RuntimeException::class);
        $this->svc->signOff($id, 2, null);
    }

    public function test_the_person_who_started_it_cannot_sign_it_off(): void
    {
        $id = $this->stocktake();
        $this->svc->reconcile($id);

        $this->expectException(RuntimeException::class);
        $this->svc->signOff($id, 1, null);
    }

    public function test_a_signed_off_stocktake_is_frozen(): void
    {
        $id = $this->stocktake();
        $this->svc->reconcile($id);
        $this->svc->signOff($id, 2, null);

        $this->expectException(RuntimeException::class);
        $this->svc->recordScans($id, 'A2', ['LATE'], 1);
    }

    public function test_posting_takes_surplus_first_and_moves_the_register(): void
    {
        $assetId = $this->asset(1000, 100);
        app(OciMovementService::class)->recordRevaluation($assetId, 900, 1000, '2026-01-01', null, null, 'up', 500, 1, 'EUR');

        $line = $this->signedMissingLine();
        $this->assertSame($assetId, (int) $line->heritage_asset_id);
        $this->assertEquals(1000.00, (float) $line->proposed_amount);

        $ids = $this->svc->postImpairment($line->id, 250, '2026-11-20', null, 2);
        $this->assertCount(2, $ids);

        $rows = DB::table('ahg_heritage_oci_movement')->whereIn('id', $ids)->pluck('amount', 'posted_to');
        $this->assertEquals(-100.00, (float) $rows['OCI']);
        $this->assertEquals(-150.00, (float) $rows['P&L']);
        $this->assertSame('EUR', DB::table('ahg_heritage_oci_movement')->where('id', $ids[0])->value('currency'));

        $asset = DB::table('heritage_asset')->find($assetId);
        $this->assertEquals(750.00, (float) $asset->current_carrying_amount);
        $this->assertEquals(250.00, (float) $asset->impairment_loss);
        $this->assertEquals(0.00, (float) $asset->revaluation_surplus);
        $this->assertSame(1, DB::table('heritage_impairment_assessment')->where('heritage_asset_id', $assetId)->count());
    }

    public function test_the_same_line_cannot_be_posted_twice(): void
    {
        $this->asset(1000);
        $line = $this->signedMissingLine();
        $this->svc->postImpairment($line->id, 100, '2026-11-20', null, 2);

        try {
            $this->svc->postImpairment($line->id, 100, '2026-11-20', null, 2);
            $this->fail('Second post should be refused');
        } catch (RuntimeException) {
            $this->assertSame(1, DB::table('ahg_heritage_oci_movement')->count());
        }
    }

    public function test_an_amount_above_carrying_is_refused_and_writes_nothing(): void
    {
        $assetId = $this->asset(100);
        $line = $this->signedMissingLine();

        try {
            $this->svc->postImpairment($line->id, 100.01, '2026-11-20', null, 2);
            $this->fail('Should be refused');
        } catch (RuntimeException) {
            $this->assertSame(0, DB::table('ahg_heritage_oci_movement')->count());
            $this->assertEquals(100.00, (float) DB::table('heritage_asset')->where('id', $assetId)->value('current_carrying_amount'));
        }
    }

    public function test_a_failure_part_way_rolls_back_the_ledger(): void
    {
        $this->asset(1000);
        $line = $this->signedMissingLine();
        Schema::drop('heritage_impairment_assessment');
        // heritageAvailable() would refuse first; recreate it with a column missing.
        Schema::create('heritage_impairment_assessment', function (Blueprint $t) {
            $t->increments('id');
        });

        try {
            $this->svc->postImpairment($line->id, 100, '2026-11-20', null, 2);
            $this->fail('Should have failed');
        } catch (\Throwable) {
            $this->assertSame(0, DB::table('ahg_heritage_oci_movement')->count());
            $this->assertNull(DB::table('library_stocktake_line')->where('id', $line->id)->value('posted_at'));
        }
    }

    public function test_a_record_with_two_register_entries_is_not_guessed(): void
    {
        $this->asset(100);
        $this->asset(200);
        $line = $this->signedMissingLine();

        $this->assertNull($line->heritage_asset_id);
        $this->expectException(RuntimeException::class);
        $this->svc->postImpairment($line->id, 10, '2026-11-20', null, 2);
    }

    public function test_the_proposal_shares_carrying_across_copies_held(): void
    {
        $this->asset(900);
        $this->copy('HELD-1', ['shelf_location' => 'Z1']);
        $this->copy('HELD-2', ['shelf_location' => 'Z1']);
        $this->copy('OLD', ['shelf_location' => 'Z1', 'status' => 'withdrawn']);
        $line = $this->signedMissingLine();

        $this->assertEquals(300.00, (float) $line->proposed_amount);
    }

    public function test_a_copy_is_impaired_once_across_consecutive_stocktakes(): void
    {
        $assetId = $this->asset(1000);
        $line = $this->signedMissingLine();
        $this->svc->postImpairment($line->id, 100, '2026-11-20', null, 2);
        $this->assertSame('missing', DB::table('library_copy')->where('id', $line->copy_id)->value('status'));

        // The posted copy is no longer expected on the shelf.
        $next = $this->stocktake();
        $this->svc->reconcile($next);
        $this->assertNull(DB::table('library_stocktake_line')->where('stocktake_id', $next)->where('copy_id', $line->copy_id)->first());
        $this->assertSame(1, DB::table('ahg_heritage_oci_movement')->where('heritage_asset_id', $assetId)->count());
    }

    public function test_overlapping_stocktakes_cannot_post_the_same_copy(): void
    {
        $this->asset(1000);
        $this->copy('GONE');
        $a = $this->stocktake();
        $b = $this->stocktake();
        foreach ([$a, $b] as $id) {
            $this->svc->reconcile($id);
            $this->svc->signOff($id, 2, null);
        }
        $lineA = DB::table('library_stocktake_line')->where('stocktake_id', $a)->where('barcode', 'GONE')->first();
        $lineB = DB::table('library_stocktake_line')->where('stocktake_id', $b)->where('barcode', 'GONE')->first();
        $this->svc->postImpairment($lineA->id, 100, '2026-11-20', null, 2);

        try {
            $this->svc->postImpairment($lineB->id, 100, '2026-11-20', null, 2);
            $this->fail('The second stocktake must be refused');
        } catch (StocktakeException $e) {
            $this->assertStringContainsString('already been posted', $e->getMessage());
            $this->assertSame(1, DB::table('ahg_heritage_oci_movement')->count());
        }
    }

    public function test_a_copy_that_moved_after_sign_off_needs_review(): void
    {
        $assetId = $this->asset(1000);
        $line = $this->signedMissingLine();

        foreach ([
            'status change' => fn () => DB::table('library_copy')->where('id', $line->copy_id)->update(['status' => 'withdrawn']),
            'open loan' => function () use ($line) {
                DB::table('library_copy')->where('id', $line->copy_id)->update(['status' => 'available']);
                $this->loan($line->copy_id, now()->addMinute()->toDateTimeString());
            },
            'closed loan' => function () use ($line) {
                DB::table('library_checkout')->where('copy_id', $line->copy_id)->update([
                    'return_date' => now()->addMinutes(2)->toDateTimeString(), 'status' => 'returned',
                ]);
            },
        ] as $case => $change) {
            $change();
            try {
                $this->svc->postImpairment($line->id, 100, '2026-11-20', null, 2);
                $this->fail('Should be refused: ' . $case);
            } catch (StocktakeException $e) {
                $this->assertStringContainsString('Needs review', $e->getMessage(), $case);
            }
        }
        $this->assertSame(0, DB::table('ahg_heritage_oci_movement')->count());
        $this->assertEquals(1000.00, (float) DB::table('heritage_asset')->where('id', $assetId)->value('current_carrying_amount'));
    }

    public function test_register_surplus_without_ledger_rows_is_refused(): void
    {
        $assetId = $this->asset(1000, 100);
        $line = $this->signedMissingLine();

        try {
            $this->svc->postImpairment($line->id, 50, '2026-11-20', null, 2);
            $this->fail('Should be refused');
        } catch (StocktakeException $e) {
            $this->assertStringContainsString('differs from the OCI ledger', $e->getMessage());
            $this->assertSame(0, DB::table('ahg_heritage_oci_movement')->count());
            $this->assertEquals(100.00, (float) DB::table('heritage_asset')->where('id', $assetId)->value('revaluation_surplus'));
        }
    }

    public function test_ledger_surplus_above_the_register_is_refused(): void
    {
        $assetId = $this->asset(1000, 40);
        app(OciMovementService::class)->recordRevaluation($assetId, 900, 1000, '2026-01-01', null, null, 'up', 500, 1, 'EUR');
        $line = $this->signedMissingLine();

        $this->expectException(StocktakeException::class);
        $this->svc->postImpairment($line->id, 50, '2026-11-20', null, 2);
    }

    public function test_a_copy_without_a_barcode_is_never_postable(): void
    {
        $this->asset(1000);
        foreach ([null, '  '] as $i => $barcode) {
            $this->copy('TEMP' . $i);
            DB::table('library_copy')->where('barcode', 'TEMP' . $i)->update(['barcode' => $barcode]);
        }
        $id = $this->stocktake();
        $this->svc->reconcile($id);
        $this->svc->signOff($id, 2, null);

        $lines = DB::table('library_stocktake_line')->where('stocktake_id', $id)->get();
        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertSame(['elsewhere', 'no_barcode'], [$line->classification, $line->reason_code]);
            $this->assertNull($line->heritage_asset_id);
            try {
                $this->svc->postImpairment($line->id, 10, '2026-11-20', null, 2);
                $this->fail('Should be refused');
            } catch (StocktakeException) {
                $this->assertSame(0, DB::table('ahg_heritage_oci_movement')->count());
            }
        }
    }

    public function test_posting_needs_a_configured_currency(): void
    {
        DB::table('heritage_institution_config')->update(['currency' => null]);
        $this->asset(1000);
        $line = $this->signedMissingLine();

        $this->expectException(StocktakeException::class);
        $this->expectExceptionMessage('institution currency');
        $this->svc->postImpairment($line->id, 10, '2026-11-20', null, 2);
    }

    public function test_an_asset_with_another_ledger_currency_is_refused(): void
    {
        $assetId = $this->asset(1000, 100);
        app(OciMovementService::class)->recordRevaluation($assetId, 900, 1000, '2026-01-01', null, null, 'up', 500, 1, 'USD');
        $line = $this->signedMissingLine();

        $this->expectException(StocktakeException::class);
        $this->expectExceptionMessage('currency other than EUR');
        $this->svc->postImpairment($line->id, 10, '2026-11-20', null, 2);
    }

    public function test_csv_cells_cannot_run_as_formulas(): void
    {
        $this->assertSame("'=1+1", StocktakeController::csvSafe('=1+1'));
        $this->assertSame('Plain', StocktakeController::csvSafe('Plain'));
    }

    public function test_financial_routes_are_administrator_only(): void
    {
        foreach (['library.stocktake.sign-off', 'library.stocktake.post-impairment'] as $name) {
            $this->assertContains('admin', Route::getRoutes()->getByName($name)->gatherMiddleware());
        }
        foreach (['library.stocktake.index', 'library.stocktake.scan', 'library.stocktake.reconcile'] as $name) {
            $this->assertContains('acl:update', Route::getRoutes()->getByName($name)->gatherMiddleware());
        }
    }
}
