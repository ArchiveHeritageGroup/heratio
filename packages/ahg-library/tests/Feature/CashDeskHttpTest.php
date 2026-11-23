<?php

/**
 * CashDeskHttpTest - the fine and cash-desk screens through the real routes,
 * theme and ACL middleware, on the MySQL test database in a transaction (#1474).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashDeskHttpTest extends TestCase
{
    use DatabaseTransactions;

    private int $patron;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('library_cash_session')) {
            $this->markTestSkipped('Needs the MySQL test schema with the cash desk tables.');
        }
        DB::unprepared((string) file_get_contents(dirname(__DIR__, 2) . '/database/seed_dropdowns.sql'));

        $this->patron = (int) DB::table('library_patron')->insertGetId([
            'card_number' => 'CD-' . Str::random(8), 'patron_type' => 'adult', 'first_name' => 'Thandi', 'last_name' => 'Example',
            'membership_start' => now()->toDateString(), 'membership_expiry' => now()->addYear()->toDateString(),
            'borrowing_status' => 'active', 'total_fines_owed' => 25, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(?int $group): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'desk-' . $id, 'email' => uniqid('desk-', true) . '@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group !== null) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    private function fine(float $amount): int
    {
        return (int) DB::table('library_fine')->insertGetId([
            'patron_id' => $this->patron, 'fine_type' => 'overdue', 'amount' => $amount, 'paid_amount' => 0,
            'currency' => \AhgLibrary\Support\LibrarySettings::currency(), 'status' => 'outstanding',
            'description' => 'Overdue test', 'fine_date' => now()->toDateString(), 'created_at' => now(),
        ]);
    }

    public function test_full_desk_flow_through_the_screens(): void
    {
        $operator = $this->user(100);
        $reviewer = $this->user(100);
        $fine = $this->fine(25);

        $this->actingAs($operator)->get(route('library.cash-desk.index'))->assertOk()->assertSee('Opening float');
        $this->actingAs($operator)->post(route('library.cash-desk.open'), ['opening_float' => '100.00'])->assertRedirect();
        $session = DB::table('library_cash_session')->where('opened_by', $operator->id)->first();

        $this->actingAs($operator)->get(route('library.fines.index', $this->patron))
            ->assertOk()->assertSee('Record payment')->assertSee('Overdue test');

        $paid = $this->actingAs($operator)->post(route('library.fines.pay', $this->patron), [
            'fine_ids' => [$fine], 'amount' => '25.00', 'payment_method' => 'cash', 'tendered' => '30.00', 'form_key' => (string) Str::uuid(),
        ]);
        $receipt = DB::table('library_fine_receipt')->where('session_id', $session->id)->first();
        $paid->assertRedirect(route('library.fines.receipt', $receipt->id));
        $this->actingAs($operator)->get(route('library.fines.receipt', $receipt->id))
            ->assertOk()->assertSee($receipt->receipt_number)->assertSee('5.00');

        // The patron page used to fail as soon as a patron had any fine row.
        $this->actingAs($operator)->get(route('library.circulation.patron', $this->patron))
            ->assertOk()->assertSee('Take payment or waive');

        $this->actingAs($operator)->post(route('library.cash-desk.close', $session->id), ['counted_cash' => '125.00'])->assertRedirect();
        $this->actingAs($reviewer)->get(route('library.cash-desk.session', $session->id))->assertOk()->assertSee('Mark reconciled');
        $this->actingAs($reviewer)->post(route('library.cash-desk.reconcile', $session->id), [])->assertRedirect();
        $this->assertSame('reconciled', DB::table('library_cash_session')->where('id', $session->id)->value('status'));

        $this->actingAs($reviewer)->get(route('library.cash-desk.end-of-day'))->assertOk()->assertSee('25.00');
    }

    public function test_waive_through_the_screen(): void
    {
        $admin = $this->user(100);
        $fine = $this->fine(10);

        $this->actingAs($admin)->post(route('library.fines.waive', $fine), ['waive_reason' => 'Goodwill'])
            ->assertRedirect(route('library.fines.index', $this->patron));
        $this->assertSame('waived', DB::table('library_fine')->where('id', $fine)->value('status'));
    }

    public function test_user_without_rights_is_refused_and_nothing_changes(): void
    {
        $nobody = $this->user(null);
        $fine = $this->fine(10);

        $this->actingAs($nobody)->get(route('library.cash-desk.index'))->assertForbidden();
        $this->actingAs($nobody)->post(route('library.fines.waive', $fine), ['waive_reason' => 'Mine'])->assertForbidden();
        $this->actingAs($nobody)->post(route('library.fines.pay', $this->patron), [
            'fine_ids' => [$fine], 'amount' => '10.00', 'payment_method' => 'cash', 'form_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertSame('outstanding', DB::table('library_fine')->where('id', $fine)->value('status'));
    }

    private function desk(User $u, float $float = 0): object
    {
        $this->actingAs($u)->post(route('library.cash-desk.open'), ['opening_float' => $float])->assertRedirect();

        return DB::table('library_cash_session')->where('opened_by', $u->id)->first();
    }

    private function payment(int $fine, string $amount = '5.00'): array
    {
        return ['fine_ids' => [$fine], 'amount' => $amount, 'payment_method' => 'cash', 'form_key' => (string) Str::uuid()];
    }

    public function test_fines_form_is_not_cached_and_a_stale_key_is_replaced(): void
    {
        $op = $this->user(100);
        $fine = $this->fine(20);
        $this->desk($op);

        $page = $this->actingAs($op)->get(route('library.fines.index', $this->patron))->assertOk();
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));

        $form = $this->payment($fine);
        $this->actingAs($op)->post(route('library.fines.pay', $this->patron), $form)->assertSessionHas('success');
        $again = $this->actingAs($op)->post(route('library.fines.pay', $this->patron), $form);
        $again->assertSessionHas('warning');

        $stale = array_merge($form, ['amount' => '6.00']);
        $refused = $this->actingAs($op)->from(route('library.fines.index', $this->patron))
            ->post(route('library.fines.pay', $this->patron), $stale);
        $refused->assertRedirect(route('library.fines.index', $this->patron))->assertSessionHas('error');
        $this->assertArrayNotHasKey('form_key', (array) session()->getOldInput());
        $this->assertEquals(5, DB::table('library_fine')->where('id', $fine)->value('paid_amount'));
        $this->assertSame(1, DB::table('library_fine_receipt')->where('patron_id', $this->patron)->count());
    }

    public function test_operator_moved_off_the_session_branch_cannot_pay_but_can_close(): void
    {
        $op = $this->user(100);
        \AhgLibrary\Support\LibraryBranch::assignStaff($op->id, 910001, false, false);
        $fine = $this->fine(20);
        $session = $this->desk($op);
        $this->assertSame(910001, (int) $session->branch_id);

        // The desk refuses the switch while the drawer is open.
        $this->actingAs($op)->post(route('library.circulation.branch'), ['branch_id' => 910002])->assertSessionHas('error');
        $this->assertSame(910001, (int) DB::table('library_staff_branch')->where('user_id', $op->id)->value('branch_id'));

        // An administrator can still move and lock them.
        \AhgLibrary\Support\LibraryBranch::assignStaff($op->id, 910002, false, true);
        $this->actingAs($op)->post(route('library.fines.pay', $this->patron), $this->payment($fine))->assertSessionHas('error');
        $this->assertEquals(0, DB::table('library_fine')->where('id', $fine)->value('paid_amount'));

        $this->actingAs($op)->get(route('library.cash-desk.session', $session->id))->assertOk();
        $this->actingAs($op)->post(route('library.cash-desk.close', $session->id), ['counted_cash' => '0.00'])->assertSessionHas('success');
        $this->assertSame('closed', DB::table('library_cash_session')->where('id', $session->id)->value('status'));
    }

    public function test_waiver_respects_and_records_the_fines_branch(): void
    {
        DB::table('library_patron')->where('id', $this->patron)->update(['branch_id' => 910001]);
        $fine = $this->fine(10);

        $other = $this->user(100);
        \AhgLibrary\Support\LibraryBranch::assignStaff($other->id, 910002, false, true);
        $this->actingAs($other)->post(route('library.fines.waive', $fine), ['waive_reason' => 'Not mine'])->assertForbidden();
        $this->assertSame('outstanding', DB::table('library_fine')->where('id', $fine)->value('status'));

        $all = $this->user(100);
        \AhgLibrary\Support\LibraryBranch::assignStaff($all->id, null, true, false);
        $this->actingAs($all)->post(route('library.fines.waive', $fine), ['waive_reason' => 'Goodwill'])->assertRedirect();
        $this->assertSame(910001, (int) DB::table('library_fine_waiver')->where('fine_id', $fine)->value('branch_id'));
    }

    public function test_fine_in_another_currency_is_flagged_and_payable_in_its_own_session(): void
    {
        $op = $this->user(100);
        $other = \AhgLibrary\Support\LibrarySettings::currency() === 'XTS' ? 'XXX' : 'XTS';
        $fine = $this->fine(10);
        DB::table('library_fine')->where('id', $fine)->update(['currency' => $other]);

        $this->actingAs($op)->get(route('library.cash-desk.index'))->assertOk()->assertSee($other);
        $this->actingAs($op)->post(route('library.cash-desk.open'), ['opening_float' => 0])->assertRedirect();
        $this->actingAs($op)->get(route('library.fines.index', $this->patron))->assertOk()->assertSee('Other currency');
        $this->actingAs($op)->post(route('library.fines.pay', $this->patron), $this->payment($fine))->assertSessionHas('error');

        $session = DB::table('library_cash_session')->where('opened_by', $op->id)->first();
        $this->actingAs($op)->post(route('library.cash-desk.close', $session->id), ['counted_cash' => '0.00']);
        $this->actingAs($op)->post(route('library.cash-desk.open'), ['opening_float' => 0, 'currency' => $other])->assertRedirect();
        $this->actingAs($op)->post(route('library.fines.pay', $this->patron), $this->payment($fine))->assertSessionHas('success');
        $this->assertSame($other, DB::table('library_fine')->where('id', $fine)->value('currency'));
    }

    public function test_dropdown_manager_sets_the_cash_flag_and_settings_editor_cannot_erase_it(): void
    {
        $admin = $this->user(100);

        $this->actingAs($admin)->postJson(route('dropdown.add-term'), [
            'taxonomy' => 'library_payment_method', 'label' => 'Till two', 'code' => 'till_two',
        ])->assertStatus(422);
        $this->actingAs($admin)->postJson(route('dropdown.add-term'), [
            'taxonomy' => 'library_payment_method', 'label' => 'Till two', 'code' => 'till_two', 'flag' => '1',
        ])->assertOk();
        $id = (int) DB::table('ahg_dropdown')->where('taxonomy', 'library_payment_method')->where('code', 'till_two')->value('id');
        $this->assertTrue(json_decode(DB::table('ahg_dropdown')->where('id', $id)->value('metadata'), true)['is_cash']);

        $this->actingAs($admin)->postJson(route('dropdown.update-term'), ['id' => $id, 'field' => 'flag', 'value' => '0'])->assertOk();
        $this->assertFalse(json_decode(DB::table('ahg_dropdown')->where('id', $id)->value('metadata'), true)['is_cash']);

        $before = DB::table('ahg_dropdown')->where('taxonomy', 'library_payment_method')->count();
        $this->actingAs($admin)->post(route('settings.dropdown.store', 'library_payment_method'), [
            'name' => 'Library Payment Method', 'values' => ['Cash'],
        ])->assertRedirect(route('dropdown.index'));
        $this->assertSame($before, DB::table('ahg_dropdown')->where('taxonomy', 'library_payment_method')->count());
        $this->assertTrue(json_decode(DB::table('ahg_dropdown')->where('taxonomy', 'library_payment_method')->where('code', 'cash')->value('metadata'), true)['is_cash']);
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get(route('library.cash-desk.index'))->assertRedirect();
        $this->assertGuest();
    }
}
