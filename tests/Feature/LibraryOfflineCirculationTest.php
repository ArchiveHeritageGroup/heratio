<?php

/**
 * LibraryOfflineCirculationTest - #1474 item 4: replay in order, loan rules as
 * at transaction time, and conflicts held for staff rather than applied.
 */

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryOfflineCirculationService as Offline;
use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Support\LibraryOfflineRuleHistory;
use App\Http\Middleware\CheckAcl;
use Carbon\CarbonImmutable;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LibraryOfflineCirculationTest extends TestCase
{
    use DatabaseTransactions;

    private const MATERIAL = 'offlinetest';

    private const USER = 990001;

    private Offline $offline;

    private string $device;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['library_offline_txn', 'library_offline_rule_snapshot', 'library_copy', 'library_patron', 'library_checkout', 'library_fine', 'library_hold'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->markTestSkipped("{$table} not present in this install.");
            }
        }

        LibraryCirculationService::forgetSchemaCache();
        // Fixtures reach back 30 days; the window itself has its own test.
        AhgSettingsService::set('library_offline_max_days', '60', 'library');
        DB::table('library_offline_rule_snapshot')->delete();
        $this->offline = new Offline();
        $this->device = (string) Str::uuid();
        $this->rule(14, 2.00);
        LibraryOfflineRuleHistory::capture('test');
        DB::table('library_offline_rule_snapshot')->update(['effective_from' => '2020-01-01 00:00:00']);
    }

    protected function tearDown(): void
    {
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function txnId(array $t): int
    {
        return (int) DB::table('library_offline_txn')->where('client_uuid', $t['uuid'])->value('id');
    }

    private function rule(int $days, float $finePerDay = 1.00, ?float $cap = null): void
    {
        DB::table('library_loan_rule')->updateOrInsert(
            ['branch_id' => LibraryBranch::ALL, 'material_type' => self::MATERIAL, 'patron_type' => '*'],
            ['loan_period_days' => $days, 'renewal_period_days' => $days, 'max_renewals' => 2, 'fine_per_day' => $finePerDay, 'fine_cap' => $cap, 'grace_period_days' => 0, 'is_loanable' => 1]
        );
    }

    private function copy(string $status = 'available'): object
    {
        $itemId = DB::table('library_item')->insertGetId(['information_object_id' => 0, 'material_type' => self::MATERIAL, 'created_at' => now()]);
        $id = DB::table('library_copy')->insertGetId(['library_item_id' => $itemId, 'copy_number' => 1, 'barcode' => 'OFF-' . Str::random(16), 'status' => $status, 'created_at' => now()]);

        return DB::table('library_copy')->where('id', $id)->first();
    }

    private function patron(array $over = []): object
    {
        $id = DB::table('library_patron')->insertGetId($over + [
            'card_number' => 'OFFP-' . Str::random(16),
            'patron_type' => 'public',
            'first_name' => 'Thandi',
            'last_name' => 'Offline',
            'membership_start' => '2020-01-01',
            'max_checkouts' => 5,
            'borrowing_status' => 'active',
            'total_fines_owed' => 0,
            'created_at' => now(),
        ]);

        return DB::table('library_patron')->where('id', $id)->first();
    }

    private function txn(string $action, object $copy, ?object $patron, string $at, array $over = []): array
    {
        return $over + [
            'uuid' => (string) Str::uuid(),
            'device_id' => $this->device,
            'seq' => ++$this->seq,
            'action' => $action,
            'copy_barcode' => $copy->barcode,
            'patron_card' => $action === Offline::ACTION_CHECKOUT ? $patron->card_number : null,
            'expected_patron_card' => $action === Offline::ACTION_RETURN && $patron ? $patron->card_number : null,
            'occurred_at' => $at,
        ];
    }

    private function run1(array $t): array
    {
        return $this->offline->replay([$t], self::USER, null)[0];
    }

    private function account(?int $group): \AhgCore\Models\User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'offline-' . $id, 'email' => uniqid('offline-', true) . '@example.test', 'password_hash' => 'x', 'active' => 1]);
        if ($group !== null) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        \Illuminate\Support\Facades\Cache::forget("acl_groups_{$id}");

        return \AhgCore\Models\User::findOrFail($id);
    }

    private function ago(int $days): string
    {
        return CarbonImmutable::now('UTC')->subDays($days)->format('Y-m-d\TH:i:s\Z');
    }

    private function loan(int $copyId): ?object
    {
        return DB::table('library_checkout')->where('copy_id', $copyId)->orderByDesc('id')->first();
    }

    public function test_checkout_is_dated_and_due_from_the_transaction_time(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $at = $this->ago(3);

        $result = $this->run1($this->txn('checkout', $copy, $patron, $at));

        $this->assertSame('applied', $result['status']);
        $loan = $this->loan($copy->id);
        $this->assertSame(CarbonImmutable::parse($at)->format('Y-m-d H:i:s'), $loan->checkout_date);
        $this->assertSame(CarbonImmutable::parse($at)->addDays(14)->toDateString(), $loan->due_date);
        $this->assertSame('checked_out', DB::table('library_copy')->where('id', $copy->id)->value('status'));
    }

    public function test_loan_rules_are_those_in_force_at_the_transaction_not_at_sync(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $at = $this->ago(5);

        // The rule is cut from 14 to 3 days two days after the offline loan.
        $this->rule(3, 2.00);
        LibraryOfflineRuleHistory::capture('test');
        DB::table('library_offline_rule_snapshot')->orderByDesc('id')->limit(1)->update(['effective_from' => CarbonImmutable::now('UTC')->subDays(3)->format('Y-m-d H:i:s')]);

        $this->run1($this->txn('checkout', $copy, $patron, $at));
        $this->assertSame(CarbonImmutable::parse($at)->addDays(14)->toDateString(), $this->loan($copy->id)->due_date);

        // A loan after the change gets the new period.
        [$copy2, $patron2] = [$this->copy(), $this->patron()];
        $later = $this->ago(1);
        $this->run1($this->txn('checkout', $copy2, $patron2, $later));
        $this->assertSame(CarbonImmutable::parse($later)->addDays(3)->toDateString(), $this->loan($copy2->id)->due_date);
    }

    public function test_saving_a_rule_records_the_version_before_and_after(): void
    {
        DB::table('library_offline_rule_snapshot')->delete();

        (new LibraryCirculationService())->saveLoanRule(['material_type' => self::MATERIAL, 'patron_type' => '*', 'loan_period_days' => 9, 'is_loanable' => 1]);

        $versions = DB::table('library_offline_rule_snapshot')->orderBy('id')->pluck('rules_json')->all();
        $this->assertCount(2, $versions);
        $days = fn ($json) => collect(json_decode($json, true))->firstWhere('material_type', self::MATERIAL)['loan_period_days'];
        $this->assertSame(14, $days($versions[0]));
        $this->assertSame(9, $days($versions[1]));
    }

    public function test_replay_runs_in_sequence_even_if_the_batch_arrives_shuffled(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $out = $this->txn('checkout', $copy, $patron, $this->ago(4));
        $back = $this->txn('return', $copy, $patron, $this->ago(2));

        $results = $this->offline->replay([$back, $out], self::USER, null);

        $this->assertSame(['applied', 'applied'], array_column($results, 'status'));
        $loan = $this->loan($copy->id);
        $this->assertSame('returned', $loan->status);
        $this->assertSame(CarbonImmutable::parse($back['occurred_at'])->format('Y-m-d H:i:s'), $loan->return_date);
        $this->assertSame('available', DB::table('library_copy')->where('id', $copy->id)->value('status'));
    }

    public function test_a_retried_transaction_is_applied_only_once(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1));

        $this->run1($t);
        $second = $this->run1($t);

        $this->assertSame('applied', $second['status']);
        $this->assertSame(1, DB::table('library_checkout')->where('copy_id', $copy->id)->count());
        $this->assertSame(1, (int) DB::table('library_patron')->where('id', $patron->id)->value('total_checkouts'));
    }

    public function test_another_users_transaction_id_is_refused(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1));
        $this->run1($t);

        $this->assertSame('refused', $this->offline->replay([$t], self::USER + 1, null)[0]['status']);
    }

    public function test_a_copy_lent_elsewhere_is_a_conflict_and_changes_nothing(): void
    {
        [$copy, $online, $offline] = [$this->copy(), $this->patron(), $this->patron()];
        $this->assertNotNull((new LibraryCirculationService())->checkout((int) $copy->id, (int) $online->id));

        $result = $this->run1($this->txn('checkout', $copy, $offline, $this->ago(1)));

        $this->assertSame('conflict', $result['status']);
        $this->assertSame('already_on_loan', $result['conflict_code']);
        $this->assertSame((int) $online->id, (int) $this->loan($copy->id)->patron_id);
        $this->assertSame(0, DB::table('library_checkout')->where('patron_id', $offline->id)->count());
    }

    public function test_a_loan_that_overlapped_the_transaction_is_a_conflict_even_if_since_returned(): void
    {
        [$copy, $other, $patron] = [$this->copy(), $this->patron(), $this->patron()];
        DB::table('library_checkout')->insert(['copy_id' => $copy->id, 'patron_id' => $other->id, 'checkout_date' => CarbonImmutable::now('UTC')->subDays(5), 'due_date' => CarbonImmutable::now('UTC')->addDays(5)->toDateString(), 'return_date' => CarbonImmutable::now('UTC')->subDay(), 'status' => 'returned', 'created_at' => now()]);

        $this->assertSame('already_on_loan', $this->run1($this->txn('checkout', $copy, $patron, $this->ago(3)))['conflict_code']);
    }

    public function test_a_suspended_patron_conflicts_and_can_be_overridden_with_a_note(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron(['borrowing_status' => 'suspended'])];
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1));

        $this->assertSame('patron_suspended', $this->run1($t)['conflict_code']);
        $id = (int) DB::table('library_offline_txn')->where('client_uuid', $t['uuid'])->value('id');

        $row = $this->offline->resolve($id, Offline::RESOLVE_APPLY, 'Suspension was lifted at the desk', self::USER, null);

        $this->assertSame('applied', $row->status);
        $this->assertSame('apply', $row->resolution);
        $this->assertSame((int) $patron->id, (int) $this->loan($copy->id)->patron_id);
    }

    public function test_overrides_accumulate_across_policy_conflicts(): void
    {
        $copy = $this->copy();
        $patron = $this->patron(['borrowing_status' => 'suspended', 'max_checkouts' => 0]);
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1));
        $this->run1($t);
        $id = (int) DB::table('library_offline_txn')->where('client_uuid', $t['uuid'])->value('id');

        $first = $this->offline->resolve($id, Offline::RESOLVE_APPLY, 'ok', self::USER, null);
        $this->assertSame('over_limit', $first->conflict_code);

        $this->assertSame('applied', $this->offline->resolve($id, Offline::RESOLVE_APPLY, 'ok', self::USER, null)->status);
    }

    public function test_apply_cannot_force_a_copy_that_is_out(): void
    {
        [$copy, $holder, $patron] = [$this->copy(), $this->patron(), $this->patron()];
        (new LibraryCirculationService())->checkout((int) $copy->id, (int) $holder->id);
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1));
        $this->run1($t);
        $id = (int) DB::table('library_offline_txn')->where('client_uuid', $t['uuid'])->value('id');

        $row = $this->offline->resolve($id, Offline::RESOLVE_APPLY, 'force', self::USER, null);

        $this->assertSame('conflict', $row->status);
        $this->assertSame(0, DB::table('library_checkout')->where('patron_id', $patron->id)->count());
    }

    public function test_a_return_after_a_conflicted_checkout_waits_and_does_not_close_someone_elses_loan(): void
    {
        [$copy, $holder, $patron] = [$this->copy(), $this->patron(), $this->patron()];
        (new LibraryCirculationService())->checkout((int) $copy->id, (int) $holder->id);

        $results = $this->offline->replay([
            $this->txn('checkout', $copy, $patron, $this->ago(2)),
            $this->txn('return', $copy, $patron, $this->ago(1)),
        ], self::USER, null);

        $this->assertSame('already_on_loan', $results[0]['conflict_code']);
        $this->assertSame('depends_on_conflict', $results[1]['conflict_code']);
        $this->assertSame('active', $this->loan($copy->id)->status);
    }

    public function test_a_return_for_a_different_borrower_is_a_conflict(): void
    {
        [$copy, $holder, $other] = [$this->copy(), $this->patron(), $this->patron()];
        (new LibraryCirculationService())->checkout((int) $copy->id, (int) $holder->id);
        DB::table('library_checkout')->where('copy_id', $copy->id)->update(['checkout_date' => CarbonImmutable::now('UTC')->subDays(3)]);

        $this->assertSame('loan_patron_mismatch', $this->run1($this->txn('return', $copy, $other, $this->ago(1)))['conflict_code']);
        $this->assertSame('active', $this->loan($copy->id)->status);
    }

    public function test_return_fine_is_counted_to_the_return_not_to_the_sync(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $this->run1($this->txn('checkout', $copy, $patron, $this->ago(30)));   // due 16 days ago
        $this->run1($this->txn('return', $copy, $patron, $this->ago(13)));     // 3 days late

        $fine = DB::table('library_fine')->where('patron_id', $patron->id)->first();
        $this->assertEquals(6.00, (float) $fine->amount);
        $this->assertEquals(6.00, (float) DB::table('library_patron')->where('id', $patron->id)->value('total_fines_owed'));
    }

    public function test_a_return_that_would_cut_a_fine_waits_for_a_named_decision(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $this->run1($this->txn('checkout', $copy, $patron, $this->ago(30)));
        (new LibraryCirculationService())->calculateAllOverdueFines();
        $accrued = (float) DB::table('library_fine')->where('patron_id', $patron->id)->value('amount');
        $this->assertGreaterThan(6.00, $accrued);

        $t = $this->txn('return', $copy, $patron, $this->ago(13));
        $this->assertSame('fine_reduction', $this->run1($t)['conflict_code']);
        $this->assertEquals($accrued, (float) DB::table('library_fine')->where('patron_id', $patron->id)->value('amount'));
        $this->assertSame('active', $this->loan($copy->id)->status);

        // A plain re-check cannot clear it.
        $this->assertSame('conflict', $this->offline->resolve($this->txnId($t), Offline::RESOLVE_RETRY, null, self::USER, null)->status);

        $row = $this->offline->resolve($this->txnId($t), Offline::RESOLVE_APPLY, 'Return slip seen', self::USER + 5, null);
        $this->assertSame('applied', $row->status);
        $this->assertSame(self::USER + 5, (int) $row->resolved_by);
        $this->assertEquals(6.00, (float) DB::table('library_fine')->where('patron_id', $patron->id)->value('amount'));
    }

    public function test_a_waiver_needs_a_decision_and_names_who_made_it(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $this->run1($this->txn('checkout', $copy, $patron, $this->ago(30)));
        (new LibraryCirculationService())->calculateAllOverdueFines();

        $t = $this->txn('return', $copy, $patron, $this->ago(25));
        $this->assertSame('fine_reduction', $this->run1($t)['conflict_code']);
        $this->assertSame('outstanding', DB::table('library_fine')->where('patron_id', $patron->id)->value('status'));

        $this->offline->resolve($this->txnId($t), Offline::RESOLVE_APPLY, 'Returned on time, slip seen', self::USER + 5, null);

        $fine = DB::table('library_fine')->where('patron_id', $patron->id)->first();
        $this->assertSame('waived', $fine->status);
        $this->assertSame(self::USER + 5, (int) $fine->waived_by);
        $this->assertStringContainsString('slip seen', $fine->waive_reason);
        $this->assertEquals(0.00, (float) DB::table('library_patron')->where('id', $patron->id)->value('total_fines_owed'));
    }

    public function test_retry_cannot_apply_a_time_still_in_the_future(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $t = $this->txn('checkout', $copy, $patron, CarbonImmutable::now('UTC')->addDays(2)->format('Y-m-d\TH:i:s\Z'));
        $this->run1($t);

        foreach ([Offline::RESOLVE_RETRY, Offline::RESOLVE_APPLY] as $resolution) {
            $row = $this->offline->resolve($this->txnId($t), $resolution, 'try', self::USER, null);
            $this->assertSame('clock_invalid', $row->conflict_code);
            $this->assertSame('conflict', $row->status);
        }
        $this->assertNull($this->loan($copy->id));
    }

    public function test_a_past_clock_conflict_clears_only_by_a_decision(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $t = $this->txn('checkout', $copy, $patron, CarbonImmutable::now('UTC')->addHour()->format('Y-m-d\TH:i:s\Z'));
        $this->run1($t);
        DB::table('library_offline_txn')->where('id', $this->txnId($t))->update(['occurred_at' => CarbonImmutable::now('UTC')->subHour()->format('Y-m-d H:i:s')]);

        $this->assertSame('conflict', $this->offline->resolve($this->txnId($t), Offline::RESOLVE_RETRY, null, self::USER, null)->status);
        $this->assertSame('applied', $this->offline->resolve($this->txnId($t), Offline::RESOLVE_APPLY, 'Clock checked', self::USER, null)->status);
    }

    public function test_out_of_order_needs_a_decision_and_can_then_clear(): void
    {
        $patron = $this->patron();
        $this->run1($this->txn('checkout', $this->copy(), $patron, $this->ago(1), ['seq' => 5]));
        $copy = $this->copy();
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1), ['seq' => 4]);
        $this->assertSame('out_of_order', $this->run1($t)['conflict_code']);

        $this->assertSame('out_of_order', $this->offline->resolve($this->txnId($t), Offline::RESOLVE_RETRY, null, self::USER, null)->conflict_code);
        $this->assertNull($this->loan($copy->id));

        $row = $this->offline->resolve($this->txnId($t), Offline::RESOLVE_APPLY, 'Queue restored from backup', self::USER, null);
        $this->assertSame('applied', $row->status);
        $this->assertSame('Queue restored from backup', $row->resolution_note);
    }

    public function test_a_desk_clock_behind_the_server_is_held(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $slow = CarbonImmutable::now('UTC')->subHours(2)->format('Y-m-d\TH:i:s\Z');

        $result = $this->offline->replay([$this->txn('checkout', $copy, $patron, $this->ago(1))], self::USER, null, false, $slow)[0];

        $this->assertSame('clock_behind', $result['conflict_code']);
        $this->assertNull($this->loan($copy->id));
    }

    public function test_a_desk_clock_in_step_with_the_server_passes(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $clock = CarbonImmutable::now('UTC')->subSeconds(30)->format('Y-m-d\TH:i:s\Z');

        $this->assertSame('applied', $this->offline->replay([$this->txn('checkout', $copy, $patron, $this->ago(1))], self::USER, null, false, $clock)[0]['status']);
    }

    public function test_a_transaction_older_than_the_offline_window_is_held(): void
    {
        AhgSettingsService::set('library_offline_max_days', '7', 'library');
        [$copy, $patron] = [$this->copy(), $this->patron()];

        $this->assertSame('outside_window', $this->run1($this->txn('checkout', $copy, $patron, $this->ago(8)))['conflict_code']);
        $this->assertNull($this->loan($copy->id));
        $this->assertSame('applied', $this->run1($this->txn('checkout', $this->copy(), $patron, $this->ago(6)))['status']);
    }

    public function test_a_queue_entry_cannot_be_sent_under_another_operator(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];

        $result = $this->run1($this->txn('checkout', $copy, $patron, $this->ago(1), ['operator_id' => self::USER + 1]));

        $this->assertSame('refused', $result['status']);
        $this->assertSame(0, DB::table('library_offline_txn')->where('client_uuid', $result['uuid'])->count());
    }

    public function test_saving_the_default_loan_period_records_a_version_before_and_after(): void
    {
        AhgSettingsService::set('library_default_loan_days', '14', 'library');
        DB::table('library_offline_rule_snapshot')->delete();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->actingAs($this->account(\AhgCore\Models\AclGroup::ADMINISTRATOR_ID));

        $this->post('/admin/ahgSettings/library', ['settings' => ['library_default_loan_days' => '21']])->assertRedirect();

        $versions = DB::table('library_offline_rule_snapshot')->orderBy('id')->pluck('default_loan_days_json')->all();
        $this->assertCount(2, $versions);
        $this->assertSame(14, json_decode($versions[0], true)['0']);
        $this->assertSame(21, json_decode($versions[1], true)['0']);
    }

    public function test_a_return_promotes_the_next_hold(): void
    {
        [$copy, $patron, $waiting] = [$this->copy(), $this->patron(), $this->patron()];
        $this->run1($this->txn('checkout', $copy, $patron, $this->ago(3)));
        $hold = (new LibraryCirculationService())->placeHold((int) $copy->library_item_id, (int) $waiting->id);

        $this->run1($this->txn('return', $copy, $patron, $this->ago(1)));

        $this->assertSame('on_hold', DB::table('library_copy')->where('id', $copy->id)->value('status'));
        $this->assertSame('ready', DB::table('library_hold')->where('id', $hold)->value('status'));
    }

    public function test_a_future_clock_and_a_backwards_sequence_are_conflicts(): void
    {
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $future = CarbonImmutable::now('UTC')->addHour()->format('Y-m-d\TH:i:s\Z');
        $this->assertSame('clock_invalid', $this->run1($this->txn('checkout', $copy, $patron, $future))['conflict_code']);

        $copy2 = $this->copy();
        $this->device = (string) Str::uuid();
        $this->run1($this->txn('checkout', $copy2, $patron, $this->ago(1), ['seq' => 5]));
        $this->assertSame('out_of_order', $this->run1($this->txn('checkout', $this->copy(), $patron, $this->ago(1), ['seq' => 4]))['conflict_code']);
    }

    public function test_a_corrected_clock_does_not_put_later_transactions_out_of_order(): void
    {
        $patron = $this->patron();
        $future = CarbonImmutable::now('UTC')->addDays(2)->format('Y-m-d\TH:i:s\Z');
        $this->run1($this->txn('checkout', $this->copy(), $patron, $future));

        $this->assertSame('applied', $this->run1($this->txn('checkout', $this->copy(), $patron, $this->ago(1)))['status']);
    }

    public function test_the_loan_limit_is_counted_as_at_the_transaction(): void
    {
        $patron = $this->patron(['max_checkouts' => 1]);
        $first = $this->copy();
        DB::table('library_checkout')->insert(['copy_id' => $first->id, 'patron_id' => $patron->id, 'checkout_date' => CarbonImmutable::now('UTC')->subDays(10), 'due_date' => CarbonImmutable::now('UTC')->addDays(4)->toDateString(), 'return_date' => CarbonImmutable::now('UTC')->subDays(6), 'status' => 'returned', 'created_at' => now()]);

        // At limit while that loan was open; free once it closed.
        $this->assertSame('over_limit', $this->run1($this->txn('checkout', $this->copy(), $patron, $this->ago(8)))['conflict_code']);
        $this->assertSame('applied', $this->run1($this->txn('checkout', $this->copy(), $patron, $this->ago(2)))['status']);
    }

    public function test_every_route_requires_sign_in_and_update_permission(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'library-manage/circulation/offline'));

        $this->assertCount(8, $routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, $route->uri());
            $this->assertContains('acl:update', $middleware, $route->uri());
        }
        $this->postJson('/library-manage/circulation/offline/sync', [])->assertUnauthorized();
    }

    public function test_sync_endpoint_validates_and_replays(): void
    {
        $this->withoutMiddleware([CheckAcl::class, \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        $this->actingAs(new GenericUser(['id' => self::USER]));
        [$copy, $patron] = [$this->copy(), $this->patron()];

        $this->postJson('/library-manage/circulation/offline/sync', ['desk_clock' => now()->toIso8601String(), 'transactions' => [['uuid' => 'nope']]])->assertStatus(422);

        $this->postJson('/library-manage/circulation/offline/sync', ['desk_clock' => now()->toIso8601String(), 'transactions' => [$this->txn('checkout', $copy, $patron, $this->ago(1), ['operator_id' => self::USER])]])
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('results.0.status', 'applied');
    }

    public function test_patron_package_carries_no_contact_details(): void
    {
        $patron = $this->patron(['email' => 'private@example.org', 'phone' => '0123456789', 'total_fines_owed' => 12.5]);

        $rows = $this->offline->patronsPage(null, (int) $patron->id - 1, 1);

        $this->assertSame('T. Offline', $rows[0]->display_name);
        $json = json_encode($rows[0]);
        $this->assertStringNotContainsString('private@example.org', $json);
        $this->assertStringNotContainsString('0123456789', $json);
        $this->assertStringNotContainsString('12.5', $json);
    }

    public function test_desk_and_conflicts_pages_render_and_a_conflict_can_be_rejected(): void
    {
        $user = $this->account(\AhgCore\Models\AclGroup::EDITOR_ID);
        foreach ([['library_offline_status', 'conflict', 'Conflict'], ['library_offline_conflict', 'already_on_loan', 'Copy was on loan elsewhere'], ['library_offline_action', 'checkout', 'Checkout'], ['library_offline_resolution', 'reject', 'Reject'], ['library_offline_resolution', 'retry', 'Check again']] as [$taxonomy, $code, $label]) {
            DB::table('ahg_dropdown')->insertOrIgnore(['taxonomy' => $taxonomy, 'taxonomy_label' => $taxonomy, 'taxonomy_section' => 'library', 'code' => $code, 'label' => $label, 'is_active' => 1]);
        }
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->actingAs($this->account(null));
        $this->get('/library-manage/circulation/offline/package?kind=patrons')->assertForbidden();
        $this->actingAs($user);

        [$copy, $holder, $patron] = [$this->copy(), $this->patron(), $this->patron()];
        (new LibraryCirculationService())->checkout((int) $copy->id, (int) $holder->id);
        $t = $this->txn('checkout', $copy, $patron, $this->ago(1));
        $this->offline->replay([$t], (int) $user->id, null);
        $id = (int) DB::table('library_offline_txn')->where('client_uuid', $t['uuid'])->value('id');

        $this->get('/library-manage/circulation/offline')->assertOk()->assertSee('id="od-root"', false);
        $this->get('/library-manage/circulation/offline/sw.js')->assertOk()->assertHeader('Service-Worker-Allowed', '/library-manage/circulation/offline');
        $this->get('/library-manage/circulation/offline/conflicts')->assertOk()
            ->assertSee($copy->barcode)->assertSee('Copy was on loan elsewhere');

        $this->post("/library-manage/circulation/offline/conflicts/{$id}/resolve", ['resolution' => 'reject'])
            ->assertSessionHasErrors('note');
        $this->post("/library-manage/circulation/offline/conflicts/{$id}/resolve", ['resolution' => 'reject', 'note' => 'Copy was on the shelf'])
            ->assertRedirect();
        $this->assertSame('rejected', DB::table('library_offline_txn')->where('id', $id)->value('status'));
    }

    public function test_the_conflicts_page_shows_what_a_fine_decision_changes(): void
    {
        $user = $this->account(\AhgCore\Models\AclGroup::EDITOR_ID);
        DB::table('ahg_dropdown')->insertOrIgnore(['taxonomy' => 'library_offline_status', 'taxonomy_label' => 'x', 'taxonomy_section' => 'library', 'code' => 'conflict', 'label' => 'Conflict', 'is_active' => 1]);
        $this->actingAs($user);
        [$copy, $patron] = [$this->copy(), $this->patron()];
        $this->run1($this->txn('checkout', $copy, $patron, $this->ago(30)));
        (new LibraryCirculationService())->calculateAllOverdueFines();
        $current = number_format((float) DB::table('library_fine')->where('patron_id', $patron->id)->value('amount'), 2);
        $this->run1($this->txn('return', $copy, $patron, $this->ago(13)));

        $this->get('/library-manage/circulation/offline/conflicts')->assertOk()
            ->assertSee("Fine now {$current}, after this return 6.00.");
    }

    public function test_desk_partial_renders_with_the_ids_the_script_needs(): void
    {
        $html = view('ahg-library::circulation.offline._desk', ['userId' => 7, 'branchId' => null])->render();

        foreach (['od-root', 'od-net', 'od-cache', 'od-refresh', 'od-sync', 'od-clear', 'od-form', 'od-patron', 'od-copy', 'od-message', 'od-count', 'od-queue', 'od-locked', 'od-unlock', 'od-pin', 'od-pin-setup', 'od-pin-new', 'od-pin-save', 'od-lock'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html);
        }
    }
}
