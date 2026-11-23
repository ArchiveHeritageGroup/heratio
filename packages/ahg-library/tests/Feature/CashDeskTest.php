<?php

/**
 * CashDeskTest - fine payment, waiver, receipts and cash sessions (#1474).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\LibraryCashDeskService;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryFineException;
use AhgLibrary\Services\LibraryFineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CashDeskTest extends LibraryFeatureTestCase
{
    private LibraryFineService $fines;
    private LibraryCashDeskService $desk;
    private int $patron;

    protected function setUp(): void
    {
        $this->migrationFiles[] = '2026_11_22_100000_create_library_cash_desk_tables.php';
        parent::setUp();

        Schema::create('ahg_dropdown', function (Blueprint $t) {
            $t->id();
            $t->string('taxonomy');
            $t->string('code');
            $t->string('label');
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->text('metadata')->nullable();
        });
        DB::table('ahg_dropdown')->insert([
            ['taxonomy' => 'library_payment_method', 'code' => 'cash', 'label' => 'Cash', 'sort_order' => 10, 'is_active' => 1, 'metadata' => '{"is_cash":true}'],
            ['taxonomy' => 'library_payment_method', 'code' => 'card', 'label' => 'Card', 'sort_order' => 20, 'is_active' => 1, 'metadata' => '{"is_cash":false}'],
        ]);

        $this->fines = new LibraryFineService();
        $this->desk = new LibraryCashDeskService();
        $this->patron = (int) DB::table('library_patron')->insertGetId([
            'card_number' => 'LIB-1', 'patron_type' => 'student', 'first_name' => 'Jane', 'last_name' => 'Doe',
            'membership_start' => now()->toDateString(), 'membership_expiry' => now()->addYear()->toDateString(),
            'max_checkouts' => 5, 'max_renewals' => 2, 'max_holds' => 3, 'borrowing_status' => 'active',
            'total_fines_owed' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fine(float $amount, array $over = []): int
    {
        $id = (int) DB::table('library_fine')->insertGetId(array_merge([
            'patron_id' => $this->patron, 'fine_type' => 'overdue', 'amount' => $amount, 'paid_amount' => 0,
            'currency' => 'ZAR', 'status' => 'outstanding', 'fine_date' => now()->toDateString(), 'created_at' => now(),
        ], $over));
        LibraryFineService::refreshPatronTotals($this->patron);

        return $id;
    }

    private function row(int $id): object
    {
        return DB::table('library_fine')->where('id', $id)->first();
    }

    private function patronRow(): object
    {
        return DB::table('library_patron')->where('id', $this->patron)->first();
    }

    public function test_full_payment_writes_fine_columns_receipt_and_patron_totals(): void
    {
        $f = $this->fine(30);
        $this->desk->open(7, 100, null);
        $r = $this->fines->pay($this->patron, [$f], 30, 'cash', null, 50, 7, 'k1');

        $row = $this->row($f);
        $this->assertSame('paid', $row->status);
        $this->assertEquals(30, $row->paid_amount);
        $this->assertSame('cash', $row->payment_method);
        $this->assertNotNull($row->payment_date);
        $this->assertEquals(0, $this->patronRow()->total_fines_owed);
        $this->assertEquals(30, $this->patronRow()->total_fines_paid);

        $receipt = $this->fines->receipt($r);
        $this->assertMatchesRegularExpression('/^FR-\d{4}-\d{6}$/', $receipt->receipt_number);
        $this->assertEquals(20, $receipt->change_given);
        $this->assertCount(1, $receipt->lines);
    }

    public function test_part_payment_stays_outstanding_and_allocates_oldest_first(): void
    {
        $old = $this->fine(10, ['fine_date' => '2026-01-01']);
        $new = $this->fine(10, ['fine_date' => '2026-02-01']);
        $this->desk->open(7, 0, null);
        $this->fines->pay($this->patron, [$new, $old], 15, 'card', 'TXN-1', null, 7, 'k2');

        $this->assertSame('paid', $this->row($old)->status);
        $this->assertSame('outstanding', $this->row($new)->status);
        $this->assertEquals(5, $this->row($new)->paid_amount);
        $this->assertEquals(5, $this->patronRow()->total_fines_owed);
    }

    public function test_resubmitted_form_does_not_charge_twice(): void
    {
        $f = $this->fine(20);
        $this->desk->open(7, 0, null);
        $a = $this->fines->pay($this->patron, [$f], 5, 'cash', null, null, 7, 'same');
        $b = $this->fines->pay($this->patron, [$f], 5, 'cash', null, null, 7, 'same');

        $this->assertSame($a, $b);
        $this->assertEquals(5, $this->row($f)->paid_amount);
        $this->assertSame(1, DB::table('library_fine_receipt')->count());
    }

    /** @dataProvider refusedPayments */
    public function test_refused_payments_change_nothing(float $amount, string $method, ?string $ref, ?float $tendered): void
    {
        $f = $this->fine(10);
        $this->desk->open(7, 0, null);
        try {
            $this->fines->pay($this->patron, [$f], $amount, $method, $ref, $tendered, 7, 'bad');
            $this->fail('expected refusal');
        } catch (LibraryFineException) {
            $this->assertEquals(0, $this->row($f)->paid_amount);
            $this->assertSame(0, DB::table('library_fine_receipt')->count());
        }
    }

    public static function refusedPayments(): array
    {
        return [
            'overpayment' => [10.01, 'cash', null, null],
            'zero' => [0, 'cash', null, null],
            'unknown tender' => [5, 'cheque', 'X', null],
            'card without reference' => [5, 'card', null, null],
            'card number as reference' => [5, 'card', '4111 1111 1111 1111', null],
            'short tender' => [5, 'cash', null, 4],
        ];
    }

    public function test_payment_needs_an_open_session_and_the_patrons_own_fine(): void
    {
        $f = $this->fine(10);
        try {
            $this->fines->pay($this->patron, [$f], 5, 'cash', null, null, 7, 'a');
            $this->fail('no session');
        } catch (LibraryFineException) {
            $this->assertEquals(0, $this->row($f)->paid_amount);
        }

        $this->desk->open(7, 0, null);
        $other = $this->fine(10, ['patron_id' => 999]);
        $this->expectException(LibraryFineException::class);
        $this->fines->pay($this->patron, [$other], 5, 'cash', null, null, 7, 'b');
    }

    public function test_waiver_writes_columns_keeps_paid_part_and_clears_owed(): void
    {
        $f = $this->fine(20);
        $this->desk->open(7, 0, null);
        $this->fines->pay($this->patron, [$f], 5, 'cash', null, null, 7, 'w');
        $this->fines->waive($f, 'Hospitalised', 9);

        $row = $this->row($f);
        $this->assertSame('waived', $row->status);
        $this->assertEquals(9, $row->waived_by);
        $this->assertSame('Hospitalised', $row->waive_reason);
        $this->assertNotNull($row->waived_date);
        $this->assertEquals(5, $row->paid_amount);
        $this->assertEquals(0, $this->patronRow()->total_fines_owed);
        $this->assertEquals(15, DB::table('library_fine_waiver')->where('fine_id', $f)->value('amount'));

        $this->expectException(LibraryFineException::class);
        $this->fines->waive($f, 'again', 9);
    }

    public function test_accrual_after_settlement_charges_only_the_new_days(): void
    {
        $this->assertSame(12.0, LibraryFineService::chargeableOverdue(5, 12));
        $f = $this->fine(10, ['checkout_id' => 5]);
        $this->desk->open(7, 0, null);
        $this->fines->pay($this->patron, [$f], 10, 'cash', null, null, 7, 'c');

        $this->assertSame(2.0, LibraryFineService::chargeableOverdue(5, 12));
        $this->assertSame(0.0, LibraryFineService::chargeableOverdue(5, 10));
    }

    public function test_return_after_paying_does_not_raise_the_whole_fine_again(): void
    {
        DB::table('library_loan_rule')->insert([
            'material_type' => 'monograph', 'patron_type' => '*', 'loan_period_days' => 7, 'max_renewals' => 1,
            'fine_per_day' => 1.00, 'grace_period_days' => 0, 'is_loanable' => 1, 'created_at' => now(),
        ]);
        $item = DB::table('library_item')->insertGetId(['information_object_id' => 1, 'material_type' => 'monograph', 'created_at' => now()]);
        $copy = DB::table('library_copy')->insertGetId(['library_item_id' => $item, 'barcode' => 'C1', 'status' => 'checked_out', 'created_at' => now(), 'updated_at' => now()]);
        $checkout = DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy, 'patron_id' => $this->patron, 'checkout_date' => now()->subDays(20),
            'due_date' => now()->subDays(10)->toDateString(), 'status' => 'active', 'created_at' => now(),
        ]);
        $f = $this->fine(8, ['checkout_id' => $checkout]);
        $this->desk->open(7, 0, null);
        $this->fines->pay($this->patron, [$f], 8, 'cash', null, null, 7, 'r');

        $this->assertTrue((new LibraryCirculationService())->return((int) $checkout, 7));

        $open = DB::table('library_fine')->where('checkout_id', $checkout)->where('status', 'outstanding')->get();
        $this->assertCount(1, $open);
        $this->assertGreaterThanOrEqual(2, (float) $open[0]->amount);
        $this->assertLessThanOrEqual(3, (float) $open[0]->amount);
        $this->assertEquals((float) $open[0]->amount, (float) $this->patronRow()->total_fines_owed);
    }

    public function test_nightly_command_does_not_recharge_a_settled_fine(): void
    {
        DB::table('library_loan_rule')->insert([
            'material_type' => 'monograph', 'patron_type' => '*', 'loan_period_days' => 7, 'max_renewals' => 1,
            'fine_per_day' => 1.00, 'grace_period_days' => 0, 'is_loanable' => 1, 'created_at' => now(),
        ]);
        $item = DB::table('library_item')->insertGetId(['information_object_id' => 1, 'material_type' => 'monograph', 'created_at' => now()]);
        $copy = DB::table('library_copy')->insertGetId(['library_item_id' => $item, 'barcode' => 'C2', 'status' => 'checked_out', 'created_at' => now(), 'updated_at' => now()]);
        $checkout = DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy, 'patron_id' => $this->patron, 'checkout_date' => now()->subDays(20),
            'due_date' => now()->subDays(10)->toDateString(), 'status' => 'overdue', 'created_at' => now(),
        ]);
        $f = $this->fine(8, ['checkout_id' => $checkout]);
        $this->desk->open(7, 0, null);
        $this->fines->pay($this->patron, [$f], 8, 'cash', null, null, 7, 'n');

        // Ten days at 1.00 with 8.00 settled: only the unpaid 2.00 is raised.
        $this->artisan('ahg:library-process-fines')->assertExitCode(0);

        $open = DB::table('library_fine')->where('checkout_id', $checkout)->where('status', 'outstanding')->get();
        $this->assertCount(1, $open);
        $this->assertEquals(2, (float) $open[0]->amount);
        $this->assertEquals(2, $this->patronRow()->total_fines_owed);
    }

    public function test_session_lifecycle_variance_and_second_person_reconciliation(): void
    {
        $f = $this->fine(30);
        $s = $this->desk->open(7, 100, 4);
        $this->fines->pay($this->patron, [$f], 20, 'cash', null, null, 7, 'x');
        $this->fines->pay($this->patron, [$f], 10, 'card', 'T1', null, 7, 'y');

        try {
            $this->desk->close($s, 115, null, 7, false);
            $this->fail('variance without a note');
        } catch (LibraryFineException) {
            $this->assertSame('open', $this->desk->find($s)->status);
        }

        $closed = $this->desk->close($s, 115, 'Short 5', 7, false);
        $this->assertEquals(120, $closed->expected_cash);
        $this->assertEquals(-5, $closed->variance);
        $this->assertEquals(20, $closed->cash_takings);
        $this->assertEquals(10, $closed->other_takings);
        $this->assertNull($closed->open_key);

        try {
            $this->desk->reconcile($s, 'mine', 7);
            $this->fail('self reconciliation');
        } catch (LibraryFineException) {
            $this->assertSame('closed', $this->desk->find($s)->status);
        }
        $this->desk->reconcile($s, 'Checked', 9);
        $this->assertSame('reconciled', $this->desk->find($s)->status);

        // A closed session takes no more money.
        $g = $this->fine(5);
        $this->expectException(LibraryFineException::class);
        $this->fines->pay($this->patron, [$g], 5, 'cash', null, null, 7, 'z');
    }

    public function test_one_open_session_per_operator_and_only_opener_or_supervisor_closes(): void
    {
        $s = $this->desk->open(7, 10, null);
        try {
            $this->desk->open(7, 10, null);
            $this->fail('second session');
        } catch (LibraryFineException) {
            $this->assertSame(1, DB::table('library_cash_session')->count());
        }
        try {
            $this->desk->close($s, 10, null, 8, false);
            $this->fail('stranger closed');
        } catch (LibraryFineException) {
            $this->assertSame('open', $this->desk->find($s)->status);
        }
        $this->assertSame('closed', $this->desk->close($s, 10, null, 8, true)->status);
    }

    public function test_end_of_day_totals_and_branch_scope(): void
    {
        $a = $this->fine(10);
        $b = $this->fine(6);
        $w = $this->fine(4);
        $s1 = $this->desk->open(7, 50, 1);
        $this->fines->pay($this->patron, [$a], 10, 'cash', null, null, 7, 'e1');
        $s2 = $this->desk->open(8, 0, 2);
        $this->fines->pay($this->patron, [$b], 6, 'card', 'T', null, 8, 'e2');
        $this->fines->waive($w, 'Goodwill', 9);
        $this->desk->close($s1, 60, null, 7, false);

        $all = $this->desk->endOfDay(now()->toDateString(), null);
        $this->assertSame(1000, $all['totals']['ZAR']['cash']);
        $this->assertSame(600, $all['totals']['ZAR']['other']);
        $this->assertSame(400, $all['totals']['ZAR']['waived']);
        $this->assertSame(2, $all['unfinished']);

        $one = $this->desk->endOfDay(now()->toDateString(), 2);
        $this->assertSame(0, $one['totals']['ZAR']['cash']);
        $this->assertSame(600, $one['totals']['ZAR']['other']);
        $this->assertTrue(LibraryCashDeskService::canAccess($this->desk->find($s2), 2));
        $this->assertFalse(LibraryCashDeskService::canAccess($this->desk->find($s1), 2));
        $this->assertTrue(LibraryCashDeskService::canAccess($this->desk->find($s1), null));
    }

    public function test_same_form_key_with_different_details_is_refused(): void
    {
        $f = $this->fine(20);
        $this->desk->open(7, 0, null);
        $first = $this->fines->pay($this->patron, [$f], 5, 'cash', null, null, 7, 'back');
        $this->assertFalse($this->fines->replayed);

        try {
            $this->fines->pay($this->patron, [$f], 6, 'cash', null, null, 7, 'back');
            $this->fail('a different amount on a spent form key must be refused');
        } catch (LibraryFineException) {
            $this->assertEquals(5, $this->row($f)->paid_amount);
            $this->assertSame(1, DB::table('library_fine_receipt')->count());
        }

        $this->assertSame($first, $this->fines->pay($this->patron, [$f], 5, 'cash', null, null, 7, 'back'));
        $this->assertTrue($this->fines->replayed);

        $second = $this->fines->pay($this->patron, [$f], 6, 'cash', null, null, 7, 'fresh');
        $this->assertNotSame($first, $second);
        $this->assertFalse($this->fines->replayed);
        $this->assertEquals(11, $this->row($f)->paid_amount);
    }

    public function test_tender_without_a_cash_flag_cannot_be_used(): void
    {
        DB::table('ahg_dropdown')->insert(['taxonomy' => 'library_payment_method', 'code' => 'voucher', 'label' => 'Voucher', 'sort_order' => 30, 'is_active' => 1, 'metadata' => null]);
        $this->assertNull($this->fines->tenders()['voucher']['is_cash']);

        $f = $this->fine(10);
        $this->desk->open(7, 0, null);
        $this->expectException(LibraryFineException::class);
        $this->fines->pay($this->patron, [$f], 5, 'voucher', 'V1', null, 7, 'v');
    }

    /** @dataProvider references */
    public function test_card_number_guard(string $reference, bool $refused): void
    {
        $this->assertSame($refused, LibraryFineService::looksLikeCardNumber($reference));
    }

    public static function references(): array
    {
        return [
            'plain' => ['4111111111111111', true],
            'spaces' => ['4111 1111 1111 1111', true],
            'dots' => ['4111.1111.1111.1111', true],
            'slashes' => ['4111/1111/1111/1111', true],
            'dashes' => ['4111-1111-1111-1111', true],
            'after a prefix group' => ['REF 12 4111 1111 1111 1111', true],
            'amex' => ['3782 822463 10005', true],
            'full-width digits' => ["\u{FF14}\u{FF11}\u{FF11}\u{FF11}", true],
            'bank reference' => ['EFT 20261122000123', false],
            'terminal reference' => ['TXN-000123', false],
            'short' => ['12345678', false],
        ];
    }

    public function test_end_of_day_follows_the_receipt_date_across_midnight(): void
    {
        $this->travelTo('2026-03-10 20:00:00');
        $a = $this->fine(10);
        $b = $this->fine(4);
        $s = $this->desk->open(7, 0, 1);
        $this->fines->pay($this->patron, [$a], 10, 'cash', null, null, 7, 'd1');

        $this->travelTo('2026-03-11 09:00:00');
        $this->fines->pay($this->patron, [$b], 4, 'cash', null, null, 7, 'd2');

        $one = $this->desk->endOfDay('2026-03-10', 1);
        $two = $this->desk->endOfDay('2026-03-11', 1);
        $this->assertSame(1000, $one['totals']['ZAR']['cash']);
        $this->assertSame(400, $two['totals']['ZAR']['cash']);
        $this->assertSame(1, $two['unfinished'], 'the session still open must hold day two as not final');
        $this->assertSame(400, $two['sessions']->firstWhere('id', $s)->takings['cash']);

        $this->travelTo('2026-03-12 09:00:00');
        $this->assertSame(1, $this->desk->endOfDay('2026-03-12', 1)['unfinished']);
        $this->desk->close($s, 14, null, 7, false);
        $this->assertSame(0, $this->desk->endOfDay('2026-03-12', 1)['totals']['ZAR']['variance']);
        $this->assertSame(1, $this->desk->endOfDay('2026-03-12', 1)['unfinished'], 'closed but not yet reconciled');
        $this->travelBack();
    }

    public function test_end_of_day_keeps_currencies_apart(): void
    {
        $z = $this->fine(10);
        $u = $this->fine(3, ['currency' => 'USD']);
        $this->desk->open(7, 0, 1);
        $this->fines->pay($this->patron, [$z], 10, 'cash', null, null, 7, 'c1');
        $this->desk->open(8, 0, 1, 'USD');
        $this->fines->pay($this->patron, [$u], 3, 'cash', null, null, 8, 'c2');

        $r = $this->desk->endOfDay(now()->toDateString(), 1);
        $this->assertSame(1000, $r['totals']['ZAR']['cash']);
        $this->assertSame(300, $r['totals']['USD']['cash']);
    }

    public function test_session_currency_must_be_configured_or_held_by_a_fine(): void
    {
        $this->fine(3, ['currency' => 'USD']);
        $this->assertSame(['ZAR', 'USD'], $this->desk->openableCurrencies());
        $this->expectException(LibraryFineException::class);
        $this->desk->open(7, 0, null, 'EUR');
    }

    public function test_staff_routes_are_acl_guarded(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['library.fines.index', 'library.fines.pay', 'library.fines.receipt', 'library.cash-desk.index', 'library.cash-desk.open', 'library.cash-desk.end-of-day', 'library.cash-desk.session', 'library.cash-desk.close'] as $name) {
            $this->assertContains('acl:update', $routes->getByName($name)->gatherMiddleware(), $name);
        }
        foreach (['library.fines.waive', 'library.cash-desk.reconcile'] as $name) {
            $this->assertContains('acl:delete', $routes->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_views_compile_and_render(): void
    {
        foreach (['fines', 'receipt', 'index', 'session', 'end-of-day', '_alerts'] as $v) {
            $path = view('ahg-library::cash-desk.' . $v)->getPath();
            $this->assertNotEmpty(app('blade.compiler')->compileString((string) file_get_contents($path)));
        }
    }
}
