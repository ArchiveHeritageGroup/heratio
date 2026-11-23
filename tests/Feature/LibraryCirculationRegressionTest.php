<?php

/**
 * Regression tests for the v1.167.0 library circulation defects: loanable
 * rules, overdue-status loans, shared fine calculation, offline replay against
 * the calendar, rule history and the OPAC PIN lockout message.
 */

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Services\LibraryCalendarService;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryOfflineCirculationService as Offline;
use AhgLibrary\Services\Sip2\Sip2Handler;
use AhgLibrary\Services\Sip2\Sip2Session;
use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Support\LibraryOfflineRuleHistory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryCirculationRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private const MATERIAL = 'verifytest';

    private const USER = 990001;

    private const TS = '20261122    120000';

    private Offline $offline;

    private LibraryCirculationService $circ;

    private string $device;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        LibraryCirculationService::forgetSchemaCache();
        AhgSettingsService::set('library_offline_max_days', '60', 'library');
        AhgSettingsService::set('library_auto_fine', '1', 'library');
        AhgSettingsService::clearCache();
        DB::table('library_offline_rule_snapshot')->delete();
        DB::table('library_closure')->delete();
        DB::table('library_opening_hours')->delete();
        app(LibraryCalendarService::class)->forget();
        $this->offline = new Offline();
        $this->circ = new LibraryCirculationService();
        $this->device = (string) Str::uuid();
        $this->rule(14, 2.00);
        LibraryOfflineRuleHistory::capture('test');
        DB::table('library_offline_rule_snapshot')->update(['effective_from' => '2020-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        app(LibraryCalendarService::class)->forget();
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function rule(int $days, float $perDay = 1.00, int $loanable = 1): void
    {
        DB::table('library_loan_rule')->updateOrInsert(
            ['branch_id' => LibraryBranch::ALL, 'material_type' => self::MATERIAL, 'patron_type' => '*'],
            ['loan_period_days' => $days, 'renewal_period_days' => $days, 'max_renewals' => 2, 'fine_per_day' => $perDay, 'fine_cap' => null, 'grace_period_days' => 0, 'is_loanable' => $loanable]
        );
    }

    private function copy(string $status = 'available'): object
    {
        $itemId = DB::table('library_item')->insertGetId(['information_object_id' => 0, 'material_type' => self::MATERIAL, 'created_at' => now()]);
        $id = DB::table('library_copy')->insertGetId(['library_item_id' => $itemId, 'copy_number' => 1, 'barcode' => 'VFY-' . Str::random(16), 'status' => $status, 'created_at' => now()]);

        return DB::table('library_copy')->where('id', $id)->first();
    }

    private function patron(): object
    {
        $id = DB::table('library_patron')->insertGetId([
            'card_number' => 'VFYP-' . Str::random(16), 'patron_type' => 'public', 'first_name' => 'Thandi', 'last_name' => 'Verify',
            'membership_start' => '2020-01-01', 'max_checkouts' => 5, 'borrowing_status' => 'active', 'total_fines_owed' => 0, 'created_at' => now(),
        ]);

        return DB::table('library_patron')->where('id', $id)->first();
    }

    private function loan(object $copy, object $patron, int $dueDaysAgo, string $status = 'active'): int
    {
        DB::table('library_copy')->where('id', $copy->id)->update(['status' => 'checked_out']);

        return (int) DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy->id, 'patron_id' => $patron->id,
            'checkout_date' => now()->subDays($dueDaysAgo + 14)->format('Y-m-d H:i:s'),
            'due_date' => now()->subDays($dueDaysAgo)->toDateString(),
            'renewed_count' => 0, 'status' => $status, 'created_at' => now(),
        ]);
    }

    private function txn(string $action, object $copy, object $patron, string $at): array
    {
        return [
            'uuid' => (string) Str::uuid(), 'device_id' => $this->device, 'seq' => ++$this->seq, 'action' => $action,
            'copy_barcode' => $copy->barcode,
            'patron_card' => $action === Offline::ACTION_CHECKOUT ? $patron->card_number : null,
            'expected_patron_card' => $action === Offline::ACTION_RETURN ? $patron->card_number : null,
            'occurred_at' => $at,
        ];
    }

    private function replay(array $t): array
    {
        return $this->offline->replay([$t], self::USER, null)[0];
    }

    private function closure(string $date): void
    {
        app(LibraryCalendarService::class)->saveClosure(['branch_id' => LibraryBranch::ALL, 'closure_type' => 'holiday', 'name' => 'Verify closure', 'start_date' => $date]);
    }

    private function fineAmount(int $checkoutId): ?float
    {
        $v = DB::table('library_fine')->where('checkout_id', $checkoutId)->where('fine_type', 'overdue')->value('amount');

        return $v === null ? null : (float) $v;
    }

    public function test_1_unloanable_rule_blocks_checkout(): void
    {
        $this->rule(14, 2.00, 0);
        $this->assertNull($this->circ->checkout($this->copy()->id, $this->patron()->id, self::USER), 'Checkout succeeded under a rule with is_loanable = 0');
    }

    public function test_1c_unloanable_rule_blocks_renewal(): void
    {
        [$copy, $patron] = [$this->copy('checked_out'), $this->patron()];
        $id = $this->loan($copy, $patron, -3);
        $due = DB::table('library_checkout')->where('id', $id)->value('due_date');

        $this->rule(14, 2.00, 0);

        $this->assertFalse($this->circ->renew($id));
        $this->assertSame($due, DB::table('library_checkout')->where('id', $id)->value('due_date'));
    }

    public function test_1d_flagged_overdue_loan_cannot_be_renewed(): void
    {
        [$copy, $patron] = [$this->copy('checked_out'), $this->patron()];
        $id = $this->loan($copy, $patron, 3, 'overdue');

        $this->assertFalse($this->circ->renew($id));
    }

    public function test_2_offline_return_applies_after_overdue_check(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $id = $this->loan($copy, $patron, 6);
        $this->artisan('ahg:library-overdue-check')->assertExitCode(0);
        $this->assertSame('overdue', DB::table('library_checkout')->where('id', $id)->value('status'));

        $at = CarbonImmutable::now('UTC')->subHour()->format('Y-m-d\TH:i:s\Z');
        $result = $this->replay($this->txn(Offline::ACTION_RETURN, $copy, $patron, $at));

        $this->assertSame('applied', $result['status'], 'conflict: ' . json_encode($result));
    }

    public function test_3a_calculate_fines_covers_a_flagged_overdue_loan(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $this->artisan('ahg:library-calculate-fines')->assertExitCode(0);
        $this->assertSame(10.0, $this->fineAmount($id), 'calculate-fines skipped a loan with status overdue');
    }

    public function test_3b_process_fines_charges_a_flagged_overdue_loan(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $this->artisan('ahg:library-process-fines')->assertExitCode(0);
        $this->assertSame(10.0, $this->fineAmount($id), 'process-fines accrued nothing for a loan 5 days overdue');
    }

    public function test_3c_process_fines_excludes_closed_days(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $this->closure(now()->subDays(2)->toDateString());
        $this->artisan('ahg:library-process-fines')->assertExitCode(0);
        $this->assertSame(8.0, $this->fineAmount($id), 'expected 4 open days at 2.00');
    }

    public function test_3d_process_fines_dry_run_reports_the_charge_and_writes_nothing(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $this->artisan('ahg:library-process-fines', ['--dry-run' => true])
            ->expectsOutputToContain('total=10.00 (dry-run)')
            ->assertExitCode(0);
        $this->assertNull($this->fineAmount($id));
    }

    public function test_3g_both_fine_commands_in_either_order_charge_a_loan_once(): void
    {
        foreach ([['calculate', 'process'], ['process', 'calculate']] as $order) {
            $patron = $this->patron();
            $id = $this->loan($this->copy(), $patron, 5, 'overdue');
            foreach ([...$order, ...$order] as $command) {
                $this->artisan("ahg:library-{$command}-fines")->assertExitCode(0);
            }
            $this->assertSame(1, DB::table('library_fine')->where('checkout_id', $id)->count());
            $this->assertSame(10.0, $this->fineAmount($id));
            $this->assertSame(10.0, (float) DB::table('library_patron')->where('id', $patron->id)->value('total_fines_owed'));
        }
    }

    public function test_3h_process_fines_respects_auto_fine_off(): void
    {
        AhgSettingsService::set('library_auto_fine', '0', 'library');
        AhgSettingsService::clearCache();
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $this->artisan('ahg:library-process-fines')->assertExitCode(0);
        $this->assertNull($this->fineAmount($id));
    }

    public function test_1b_offline_checkout_of_unloanable_item_needs_a_staff_decision(): void
    {
        $this->rule(14, 2.00, 0);
        LibraryOfflineRuleHistory::capture('test');
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $at = CarbonImmutable::now('UTC')->addSecond()->format('Y-m-d\TH:i:s\Z');

        $result = $this->replay($this->txn(Offline::ACTION_CHECKOUT, $copy, $patron, $at));
        $this->assertSame('not_loanable', $result['conflict_code']);
        $this->assertFalse(DB::table('library_checkout')->where('copy_id', $copy->id)->exists());

        $row = DB::table(Offline::TXN)->where('client_uuid', $result['uuid'])->first();
        $this->offline->resolve((int) $row->id, Offline::RESOLVE_APPLY, 'Already left the building', self::USER, null);
        $this->assertTrue(DB::table('library_checkout')->where('copy_id', $copy->id)->exists());
    }

    public function test_3e_nightly_sequence_charges_a_loan_that_goes_overdue(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5);
        $this->artisan('ahg:library-overdue-check')->assertExitCode(0);
        $this->artisan('ahg:library-calculate-fines')->assertExitCode(0);
        $this->artisan('ahg:library-process-fines')->assertExitCode(0);
        $this->assertSame(10.0, $this->fineAmount($id), 'No fine after the full nightly sequence');
    }

    public function test_3f_overdue_list_includes_flagged_loans(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $ids = array_map(fn ($r) => (int) $r->id, $this->circ->listOverdue());
        $this->assertContains($id, $ids, 'listOverdue omits a loan with status overdue');
    }

    public function test_4a_offline_due_date_skips_a_closed_day(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $at = CarbonImmutable::now('UTC')->subDays(3);
        $closed = $at->addDays(14)->toDateString();
        $this->closure($closed);

        $result = $this->replay($this->txn(Offline::ACTION_CHECKOUT, $copy, $patron, $at->format('Y-m-d\TH:i:s\Z')));

        $this->assertSame('applied', $result['status']);
        $due = DB::table('library_checkout')->where('copy_id', $copy->id)->value('due_date');
        $this->assertSame($at->addDays(15)->toDateString(), $due, 'Offline loan falls due on a closed day');
    }

    public function test_4b_offline_return_fine_excludes_closed_days(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $id = (int) DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy->id, 'patron_id' => $patron->id,
            'checkout_date' => $today->subDays(20)->format('Y-m-d H:i:s'), 'due_date' => $today->subDays(6)->toDateString(),
            'renewed_count' => 0, 'status' => 'active', 'created_at' => now(),
        ]);
        DB::table('library_copy')->where('id', $copy->id)->update(['status' => 'checked_out']);
        $this->closure($today->subDays(3)->toDateString());

        $at = $today->subDay()->setTime(12, 0)->format('Y-m-d\TH:i:s\Z');
        $this->assertSame('applied', $this->replay($this->txn(Offline::ACTION_RETURN, $copy, $patron, $at))['status']);

        $this->assertSame(8.0, $this->fineAmount($id), '5 days less 1 closed day at 2.00');
    }

    public function test_5_offline_settle_nets_off_part_payment(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $id = (int) DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy->id, 'patron_id' => $patron->id,
            'checkout_date' => $today->subDays(20)->format('Y-m-d H:i:s'), 'due_date' => $today->subDays(6)->toDateString(),
            'renewed_count' => 0, 'status' => 'active', 'created_at' => now(),
        ]);
        DB::table('library_copy')->where('id', $copy->id)->update(['status' => 'checked_out']);
        DB::table('library_fine')->insert([
            'patron_id' => $patron->id, 'checkout_id' => $id, 'fine_type' => 'overdue', 'amount' => 10.00, 'paid_amount' => 4.00,
            'currency' => 'ZAR', 'status' => 'outstanding', 'fine_date' => $today->toDateString(), 'created_at' => now(),
        ]);
        DB::table('library_patron')->where('id', $patron->id)->update(['total_fines_owed' => 6.00]);

        $at = $today->subDay()->setTime(12, 0)->format('Y-m-d\TH:i:s\Z');
        $this->assertSame('applied', $this->replay($this->txn(Offline::ACTION_RETURN, $copy, $patron, $at))['status']);

        $this->assertSame(6.0, (float) DB::table('library_patron')->where('id', $patron->id)->value('total_fines_owed'));
    }

    public function test_5b_offline_return_does_not_charge_a_fine_already_paid(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $today = CarbonImmutable::now('UTC')->startOfDay();
        $id = (int) DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy->id, 'patron_id' => $patron->id,
            'checkout_date' => $today->subDays(20)->format('Y-m-d H:i:s'), 'due_date' => $today->subDays(6)->toDateString(),
            'renewed_count' => 0, 'status' => 'active', 'created_at' => now(),
        ]);
        DB::table('library_copy')->where('id', $copy->id)->update(['status' => 'checked_out']);
        DB::table('library_fine')->insert([
            'patron_id' => $patron->id, 'checkout_id' => $id, 'fine_type' => 'overdue', 'amount' => 10.00, 'paid_amount' => 10.00,
            'currency' => 'ZAR', 'status' => 'paid', 'fine_date' => $today->toDateString(), 'created_at' => now(),
        ]);

        $at = $today->subDay()->setTime(12, 0)->format('Y-m-d\TH:i:s\Z');
        $this->assertSame('applied', $this->replay($this->txn(Offline::ACTION_RETURN, $copy, $patron, $at))['status']);

        $this->assertSame(0, DB::table('library_fine')->where('checkout_id', $id)->where('status', 'outstanding')->count());
    }

    public function test_6a_update_loan_rule_records_history(): void
    {
        $id = (int) DB::table('library_loan_rule')->where('material_type', self::MATERIAL)->value('id');
        $before = DB::table('library_offline_rule_snapshot')->count();

        $this->circ->updateLoanRule($id, ['material_type' => self::MATERIAL, 'patron_type' => '*', 'loan_period_days' => 3, 'fine_per_day' => 2, 'is_loanable' => 1]);

        $this->assertGreaterThan($before, DB::table('library_offline_rule_snapshot')->count());
    }

    public function test_6b_offline_loan_after_update_uses_the_new_rule(): void
    {
        $id = (int) DB::table('library_loan_rule')->where('material_type', self::MATERIAL)->value('id');
        $this->circ->updateLoanRule($id, ['material_type' => self::MATERIAL, 'patron_type' => '*', 'loan_period_days' => 3, 'fine_per_day' => 2, 'is_loanable' => 1]);

        [$copy, $patron] = [$this->copy(), $this->patron()];
        $at = CarbonImmutable::now('UTC');
        $this->assertSame('applied', $this->replay($this->txn(Offline::ACTION_CHECKOUT, $copy, $patron, $at->format('Y-m-d\TH:i:s\Z')))['status']);

        $this->assertSame($at->addDays(3)->toDateString(), DB::table('library_checkout')->where('copy_id', $copy->id)->value('due_date'));
    }

    private function sip2Fixture(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $patronId = DB::table('library_patron')->insertGetId([
            'card_number' => 'SIP' . $suffix, 'first_name' => 'Ada', 'last_name' => 'Reader',
            'membership_start' => '2026-01-01', 'max_checkouts' => 2, 'borrowing_status' => 'active',
        ]);
        DB::table('library_patron_pin')->insert(['patron_id' => $patronId, 'pin_hash' => password_hash('4321', PASSWORD_BCRYPT, ['cost' => 4])]);
        DB::table('library_sip2_account')->insert([
            'login' => 'kiosk' . $suffix, 'password_hash' => password_hash('terminal-secret', PASSWORD_BCRYPT, ['cost' => 4]),
            'institution_id' => 'MAIN', 'can_block' => 1, 'can_pay' => 1,
        ]);
        RateLimiter::clear('sip2:login:' . sha1('kiosk' . $suffix));
        RateLimiter::clear('sip2:pin:' . $patronId);

        $handler = app(Sip2Handler::class);
        $session = new Sip2Session();
        $this->assertSame('941', rtrim($handler->handle("9300CNkiosk{$suffix}|COterminal-secret|CPMAIN|", $session), "\r"));

        return [$handler, $session, $patronId, 'SIP' . $suffix];
    }

    public function test_7_sip2_fee_is_kept_apart_from_the_cash_desk_as_documented(): void
    {
        [$handler, $session, $patronId, $card] = $this->sip2Fixture();
        AhgSettingsService::set('library_default_currency', 'ZAR', 'library');
        AhgSettingsService::clearCache();
        $currency = \AhgLibrary\Support\LibrarySettings::currency();
        DB::table('library_fine')->insert(['patron_id' => $patronId, 'fine_type' => 'overdue', 'amount' => 10, 'currency' => $currency, 'status' => 'outstanding', 'fine_date' => '2026-10-01']);
        DB::table('library_patron')->where('id', $patronId)->update(['total_fines_owed' => 10]);
        $receipts = DB::table('library_fine_receipt')->count();

        $reply = rtrim($handler->handle('37' . self::TS . '0100' . $currency . "BV10.00|AOMAIN|AA{$card}|BKVFY-1|", $session), "\r");

        $this->assertStringStartsWith('38Y', $reply);
        $this->assertSame(0.0, (float) DB::table('library_patron')->where('id', $patronId)->value('total_fines_owed'));
        $this->assertSame(1, DB::table('library_sip2_payment')->where('patron_id', $patronId)->count());
        $this->assertSame($receipts, DB::table('library_fine_receipt')->count(), 'Guide says SIP2 payments create no receipt');
    }

    public function test_8a_opac_lockout_holds_for_the_correct_pin(): void
    {
        [, , $patronId, $card] = $this->sip2Fixture();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/opac/patron/authenticate', ['card_number' => $card, 'pin' => '000' . $i]);
        }
        $this->post('/opac/patron/authenticate', ['card_number' => $card, 'pin' => '4321'])->assertRedirect(route('opac.patron.login'));
        $this->assertNull(session('patron_id'));
        RateLimiter::clear('sip2:pin:' . $patronId);
    }

    public function test_8b_opac_lockout_does_not_call_the_correct_pin_incorrect(): void
    {
        [, , $patronId, $card] = $this->sip2Fixture();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/opac/patron/authenticate', ['card_number' => $card, 'pin' => '000' . $i]);
        }
        $response = $this->post('/opac/patron/authenticate', ['card_number' => $card, 'pin' => '4321']);
        $message = session('error');
        RateLimiter::clear('sip2:pin:' . $patronId);

        $response->assertRedirect();
        $this->assertNotSame(__('Incorrect PIN.'), $message, 'Correct PIN during lockout reported as incorrect');
    }

    public function test_control_calculate_fines_charges_an_active_loan_less_closed_days(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5);
        $this->closure(now()->subDays(2)->toDateString());
        $this->artisan('ahg:library-calculate-fines')->assertExitCode(0);
        $this->assertSame(8.0, $this->fineAmount($id));
    }

    public function test_control_offline_return_applies_on_an_active_loan(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $this->loan($copy, $patron, 6);
        $at = CarbonImmutable::now('UTC')->subHour()->format('Y-m-d\TH:i:s\Z');
        $this->assertSame('applied', $this->replay($this->txn(Offline::ACTION_RETURN, $copy, $patron, $at))['status']);
    }

    public function test_control_online_return_charges_a_flagged_loan(): void
    {
        $id = $this->loan($this->copy(), $this->patron(), 5, 'overdue');
        $this->assertTrue($this->circ->return($id, self::USER));
        $this->assertSame(10.0, $this->fineAmount($id));
    }

    public function test_control_correct_pin_signs_in_without_lockout(): void
    {
        [, , $patronId, $card] = $this->sip2Fixture();
        $this->post('/opac/patron/authenticate', ['card_number' => $card, 'pin' => '4321'])->assertRedirect(route('opac.patron.account'));
        $this->assertSame($patronId, session('patron_id'));
    }

    public function test_control_saved_rule_reaches_offline_loans(): void
    {
        $this->circ->saveLoanRule(['material_type' => self::MATERIAL, 'patron_type' => '*', 'loan_period_days' => 3, 'fine_per_day' => 2, 'is_loanable' => 1]);
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $at = CarbonImmutable::now('UTC');
        $this->assertSame('applied', $this->replay($this->txn(Offline::ACTION_CHECKOUT, $copy, $patron, $at->format('Y-m-d\TH:i:s\Z')))['status']);
        $this->assertSame($at->addDays(3)->toDateString(), DB::table('library_checkout')->where('copy_id', $copy->id)->value('due_date'));
    }
}
