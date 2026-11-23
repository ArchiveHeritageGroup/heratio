<?php

/**
 * LibraryLoanCalendarTest - #1475 item 1: the loan-rule editor and the calendar
 * axis, so due dates avoid closed days and fines do not accrue across them.
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgLibrary\Services\LibraryCalendarService;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryOverdueNoticeService;
use AhgLibrary\Support\LibraryBranch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibraryLoanCalendarTest extends TestCase
{
    use DatabaseTransactions;

    private const BRANCH_A = 900101;

    private const BRANCH_B = 900102;

    private const MATERIAL = 'calendartest';

    private LibraryCalendarService $calendar;

    private LibraryCirculationService $circ;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['library_loan_rule', 'library_opening_hours', 'library_closure', 'library_fine'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} not present in this install.");
            }
        }

        LibraryCirculationService::forgetSchemaCache();
        AclService::forgetUser();
        $this->calendar = app(LibraryCalendarService::class);
        $this->calendar->forget();
        $this->circ = new LibraryCirculationService();
    }

    public function test_nothing_configured_means_every_day_is_open(): void
    {
        $this->assertFalse($this->calendar->isClosed('2026-12-25'));
        $this->assertSame('2026-12-25', $this->calendar->nextOpenDay('2026-12-25'));
        $this->assertSame(0, $this->calendar->closedDaysBetween('2026-12-01', '2026-12-31'));
    }

    public function test_a_closed_weekday_moves_the_due_date(): void
    {
        $this->hours(LibraryBranch::ALL, 7, false); // Sunday

        $this->assertTrue($this->calendar->isClosed('2026-11-22'));
        $this->assertSame('2026-11-23', $this->calendar->nextOpenDay('2026-11-22'));
    }

    public function test_a_branch_weekday_row_overrides_all_branches(): void
    {
        $this->hours(LibraryBranch::ALL, 7, false);
        $this->hours(self::BRANCH_A, 7, true);

        $this->assertFalse($this->calendar->isClosed('2026-11-22', self::BRANCH_A));
        $this->assertTrue($this->calendar->isClosed('2026-11-22', self::BRANCH_B));
    }

    public function test_a_branch_closure_does_not_close_other_branches(): void
    {
        $this->closure(self::BRANCH_A, '2026-12-01', '2026-12-03');

        $this->assertTrue($this->calendar->isClosed('2026-12-02', self::BRANCH_A));
        $this->assertFalse($this->calendar->isClosed('2026-12-02', self::BRANCH_B));
        $this->assertSame('2026-12-04', $this->calendar->nextOpenDay('2026-12-01', self::BRANCH_A));
    }

    public function test_an_all_branches_closure_applies_everywhere(): void
    {
        $this->closure(LibraryBranch::ALL, '2026-12-25', '2026-12-25');

        $this->assertTrue($this->calendar->isClosed('2026-12-25', self::BRANCH_A));
        $this->assertTrue($this->calendar->isClosed('2026-12-25'));
    }

    public function test_a_yearly_closure_wraps_the_year_end_and_starts_at_its_first_year(): void
    {
        $this->closure(LibraryBranch::ALL, '2026-12-30', '2027-01-02', true);

        $this->assertTrue($this->calendar->isClosed('2028-12-31'));
        $this->assertTrue($this->calendar->isClosed('2029-01-01'));
        $this->assertFalse($this->calendar->isClosed('2029-01-03'));
        $this->assertFalse($this->calendar->isClosed('2025-12-31'));
    }

    public function test_closed_days_are_counted_after_the_due_date_only(): void
    {
        $this->closure(LibraryBranch::ALL, '2026-12-10', '2026-12-12');

        $this->assertSame(2, $this->calendar->closedDaysBetween('2026-12-10', '2026-12-12'));
        $this->assertSame(3, $this->calendar->closedDaysBetween('2026-12-09', '2026-12-20'));
    }

    public function test_a_fully_closed_calendar_does_not_hang(): void
    {
        for ($d = 1; $d <= 7; $d++) {
            $this->hours(LibraryBranch::ALL, $d, false);
        }

        $this->assertSame('2026-12-01', $this->calendar->nextOpenDay('2026-12-01'));
    }

    public function test_checkout_due_date_skips_a_closure(): void
    {
        $this->rule(LibraryBranch::ALL, 7);
        $natural = date('Y-m-d', strtotime('+7 days'));
        $this->closure(self::BRANCH_A, $natural, $natural);
        [$copy, $patron] = $this->fixture(self::BRANCH_A);

        $id = $this->circ->checkout($copy, $patron);

        $this->assertSame(
            date('Y-m-d', strtotime('+8 days')),
            substr((string) DB::table('library_checkout')->where('id', $id)->value('due_date'), 0, 10)
        );
    }

    public function test_renewal_due_date_skips_a_closure(): void
    {
        $this->rule(LibraryBranch::ALL, 7);
        [$copy, $patron] = $this->fixture(self::BRANCH_A);
        $id = $this->circ->checkout($copy, $patron);
        $due = (string) DB::table('library_checkout')->where('id', $id)->value('due_date');
        $natural = date('Y-m-d', strtotime($due . ' +7 days'));
        $this->closure(self::BRANCH_A, $natural, $natural);

        $this->assertTrue($this->circ->renew($id));

        $this->assertSame(
            date('Y-m-d', strtotime($natural . ' +1 day')),
            substr((string) DB::table('library_checkout')->where('id', $id)->value('due_date'), 0, 10)
        );
    }

    public function test_fine_excludes_closed_days(): void
    {
        $this->assertSame(7.0, $this->fineFor(grace: 0, closedFrom: -6, closedTo: -4));
    }

    public function test_grace_is_counted_in_open_days(): void
    {
        $this->assertSame(5.0, $this->fineFor(grace: 2, closedFrom: -6, closedTo: -4));
    }

    public function test_fine_is_unchanged_with_no_closures(): void
    {
        $this->assertSame(10.0, $this->fineFor(grace: 0));
    }

    public function test_a_closure_over_every_charged_day_clears_the_fine_and_the_patron_total(): void
    {
        $this->rule(LibraryBranch::ALL, 14);
        [$copy, $patron] = $this->fixture(self::BRANCH_A);
        $id = DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy, 'patron_id' => $patron, 'branch_id' => self::BRANCH_A,
            'checkout_date' => now()->subDays(17), 'due_date' => date('Y-m-d', strtotime('-3 days')),
            'renewed_count' => 0, 'status' => 'active', 'created_at' => now(),
        ]);
        $method = new \ReflectionMethod(LibraryCirculationService::class, 'generateOverdueFine');
        $sweep = fn () => $method->invoke($this->circ, DB::table('library_checkout')->where('id', $id)->first());

        $sweep();
        $this->assertSame(3.0, (float) DB::table('library_fine')->where('checkout_id', $id)->value('amount'));
        $this->assertSame(3.0, (float) DB::table('library_patron')->where('id', $patron)->value('total_fines_owed'));

        $this->closure(self::BRANCH_A, date('Y-m-d', strtotime('-2 days')), date('Y-m-d'));
        $sweep();
        $this->assertSame(0.0, (float) DB::table('library_fine')->where('checkout_id', $id)->value('amount'));
        $this->assertSame(0.0, (float) DB::table('library_patron')->where('id', $patron)->value('total_fines_owed'));

        DB::table('library_closure')->where('branch_id', self::BRANCH_A)->delete();
        $this->calendar->forget();
        $sweep();
        $this->assertSame(1, DB::table('library_fine')->where('checkout_id', $id)->count());
        $this->assertSame(3.0, (float) DB::table('library_patron')->where('id', $patron)->value('total_fines_owed'));
    }

    public function test_overdue_notice_quotes_the_same_chargeable_days(): void
    {
        $this->rule(LibraryBranch::ALL, 14);
        $this->closure(self::BRANCH_A, date('Y-m-d', strtotime('-6 days')), date('Y-m-d', strtotime('-4 days')));

        $row = (object) [
            'material_type' => self::MATERIAL, 'patron_type' => 'public', 'branch_id' => self::BRANCH_A,
            'copy_branch_id' => null, 'due_date' => date('Y-m-d', strtotime('-10 days')),
            'first_name' => 'A', 'last_name' => 'B', 'title' => 'T', 'call_number' => '', 'barcode' => 'X',
        ];
        $method = new \ReflectionMethod(LibraryOverdueNoticeService::class, 'buildTokens');
        $tokens = $method->invoke(app(LibraryOverdueNoticeService::class), $row, 10);

        $this->assertSame('7.00', $tokens['fine_amount']);
        $this->assertSame('10', $tokens['days_overdue']);
    }

    public function test_editor_updates_a_rule_and_keeps_its_renewal_period(): void
    {
        $id = $this->rule(LibraryBranch::ALL, 14);
        DB::table('library_loan_rule')->where('id', $id)->update(['renewal_period_days' => 5]);

        $this->actingAs($this->user(100))
            ->get(route('library.loan-rules.edit', $id))->assertOk()->assertSee('Edit Loan Rule');

        $this->actingAs($this->user(100))
            ->put(route('library.loan-rules.update', $id), $this->ruleForm(['loan_period_days' => 21]))
            ->assertRedirect(route('library.loan-rules'));

        $row = DB::table('library_loan_rule')->where('id', $id)->first();
        $this->assertSame(21, (int) $row->loan_period_days);
        $this->assertSame(5, (int) $row->renewal_period_days);
    }

    public function test_editor_refuses_a_change_that_collides_with_another_rule(): void
    {
        $this->rule(LibraryBranch::ALL, 14, 'student');
        $id = $this->rule(LibraryBranch::ALL, 14, 'public');

        $this->actingAs($this->user(100))
            ->from(route('library.loan-rules.edit', $id))
            ->put(route('library.loan-rules.update', $id), $this->ruleForm(['patron_type' => 'student']))
            ->assertSessionHasErrors('material_type');
    }

    public function test_add_form_refuses_an_existing_combination_and_leaves_it_intact(): void
    {
        $id = $this->rule(LibraryBranch::ALL, 14, 'public');
        DB::table('library_loan_rule')->where('id', $id)->update([
            'notes' => 'keep me', 'renewal_period_days' => 5, 'fine_cap' => 20,
        ]);

        $this->actingAs($this->user(100))
            ->from(route('library.loan-rules'))
            ->post(route('library.loan-rules.save'), $this->ruleForm(['loan_period_days' => 30]))
            ->assertSessionHasErrors('material_type');

        $row = DB::table('library_loan_rule')->where('id', $id)->first();
        $this->assertSame(14, (int) $row->loan_period_days);
        $this->assertSame(5, (int) $row->renewal_period_days);
        $this->assertSame('keep me', $row->notes);
        $this->assertSame(20.0, (float) $row->fine_cap);
    }

    public function test_add_form_still_creates_a_new_combination(): void
    {
        $this->rule(LibraryBranch::ALL, 14, 'public');

        $this->actingAs($this->user(100))
            ->post(route('library.loan-rules.save'), $this->ruleForm(['patron_type' => '*']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('library.loan-rules'));

        $this->assertSame(2, DB::table('library_loan_rule')->where('material_type', self::MATERIAL)->count());
    }

    public function test_editing_a_rule_whose_branch_left_the_list_keeps_that_branch(): void
    {
        if (! LibraryBranch::available()) {
            $this->markTestSkipped('Branch axis not present in this install.');
        }
        $this->assertArrayNotHasKey(self::BRANCH_A, LibraryBranch::options());
        $id = $this->rule(self::BRANCH_A, 14);
        $admin = $this->user(100);

        $this->actingAs($admin)->get(route('library.loan-rules.edit', $id))->assertOk()
            ->assertSee('<option value="' . self::BRANCH_A . '" selected>', false);

        $this->actingAs($admin)
            ->put(route('library.loan-rules.update', $id), $this->ruleForm([
                'branch_id' => self::BRANCH_A, 'patron_type' => '*', 'loan_period_days' => 21,
            ]))
            ->assertSessionHasNoErrors();

        $row = DB::table('library_loan_rule')->where('id', $id)->first();
        $this->assertSame(self::BRANCH_A, (int) $row->branch_id);
        $this->assertSame(21, (int) $row->loan_period_days);
    }

    public function test_editor_refuses_an_unknown_material_type(): void
    {
        $this->actingAs($this->user(100))
            ->post(route('library.loan-rules.save'), $this->ruleForm(['material_type' => 'not-a-real-type']))
            ->assertSessionHasErrors('material_type');
    }

    public function test_user_without_rights_cannot_edit(): void
    {
        $id = $this->rule(LibraryBranch::ALL, 14);

        $this->actingAs($this->user())
            ->put(route('library.loan-rules.update', $id), $this->ruleForm())
            ->assertForbidden();
        $this->actingAs($this->user())
            ->post(route('library.calendar.closures.store'), $this->closureForm())
            ->assertForbidden();
    }

    public function test_locked_operator_cannot_change_all_branches_terms(): void
    {
        $user = $this->user(100);
        DB::table('library_staff_branch')->insert([
            'user_id' => $user->id, 'branch_id' => self::BRANCH_A, 'all_branches' => 0, 'locked' => 1,
        ]);
        $id = $this->rule(LibraryBranch::ALL, 14);

        $this->actingAs($user)
            ->put(route('library.loan-rules.update', $id), $this->ruleForm())
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('library.loan-rules.delete', $id))
            ->assertForbidden();
        $this->actingAs($user)
            ->post(route('library.calendar.closures.store'), $this->closureForm())
            ->assertForbidden();
        $this->assertTrue(DB::table('library_loan_rule')->where('id', $id)->exists());
    }

    public function test_closure_create_edit_delete(): void
    {
        $admin = $this->user(100);

        $this->actingAs($admin)->post(route('library.calendar.closures.store'), $this->closureForm())
            ->assertRedirect();
        $id = (int) DB::table('library_closure')->where('name', 'ZZ Holiday')->value('id');
        $this->assertGreaterThan(0, $id);

        $this->actingAs($admin)->get(route('library.calendar.closures.edit', $id))->assertOk()->assertSee('ZZ Holiday');

        $this->actingAs($admin)
            ->put(route('library.calendar.closures.update', $id), $this->closureForm(['name' => 'ZZ Renamed']))
            ->assertRedirect();
        $this->assertSame('ZZ Renamed', DB::table('library_closure')->where('id', $id)->value('name'));

        $this->actingAs($admin)->post(route('library.calendar.closures.delete', $id))->assertRedirect();
        $this->assertFalse(DB::table('library_closure')->where('id', $id)->exists());
    }

    public function test_closure_validation(): void
    {
        $admin = $this->user(100);

        $this->actingAs($admin)
            ->post(route('library.calendar.closures.store'), $this->closureForm(['closure_type' => 'nonsense']))
            ->assertSessionHasErrors('closure_type');
        $this->actingAs($admin)
            ->post(route('library.calendar.closures.store'), $this->closureForm(['end_date' => '2026-01-01']))
            ->assertSessionHasErrors('end_date');
        $this->actingAs($admin)
            ->post(route('library.calendar.closures.store'), $this->closureForm([
                'start_date' => '2028-02-29', 'end_date' => '2028-02-29', 'recurs_annually' => 1,
            ]))
            ->assertSessionHasErrors('start_date');
    }

    public function test_hours_save_and_reject_an_all_closed_week(): void
    {
        $admin = $this->user(100);
        $days = [];
        for ($d = 1; $d <= 7; $d++) {
            $days[$d] = ['is_open' => $d === 7 ? 0 : 1, 'opens_at' => $d === 7 ? '' : '08:00', 'closes_at' => $d === 7 ? '' : '17:00'];
        }

        $this->actingAs($admin)->put(route('library.calendar.hours'), ['branch_id' => 0, 'days' => $days])->assertRedirect();
        $this->assertSame(7, DB::table('library_opening_hours')->where('branch_id', 0)->count());
        $this->assertTrue(app(LibraryCalendarService::class)->isClosed('2026-11-22'));

        $closed = array_fill(1, 7, ['is_open' => 0]);
        $this->actingAs($admin)->put(route('library.calendar.hours'), ['branch_id' => 0, 'days' => $closed])
            ->assertSessionHasErrors('days');

        $days[1]['closes_at'] = '07:00';
        $this->actingAs($admin)->put(route('library.calendar.hours'), ['branch_id' => 0, 'days' => $days])
            ->assertSessionHasErrors('days.1.closes_at');
    }

    public function test_calendar_and_rules_pages_render(): void
    {
        $this->rule(LibraryBranch::ALL, 14);
        $this->closure(LibraryBranch::ALL, '2026-12-25', '2026-12-25');
        $admin = $this->user(100);

        $this->actingAs($admin)->get(route('library.calendar'))->assertOk()->assertSee('ZZ Closure');
        $this->actingAs($admin)->get(route('library.loan-rules'))->assertOk()
            ->assertSee(route('library.loan-rules.edit', DB::table('library_loan_rule')->where('material_type', self::MATERIAL)->value('id')));
    }

    private function fineFor(int $grace, ?int $closedFrom = null, ?int $closedTo = null): float
    {
        $ruleId = $this->rule(LibraryBranch::ALL, 14);
        DB::table('library_loan_rule')->where('id', $ruleId)->update(['fine_per_day' => 1, 'grace_period_days' => $grace]);
        if ($closedFrom !== null) {
            $this->closure(self::BRANCH_A, date('Y-m-d', strtotime("{$closedFrom} days")), date('Y-m-d', strtotime("{$closedTo} days")));
        }
        [$copy, $patron] = $this->fixture(self::BRANCH_A);
        $id = DB::table('library_checkout')->insertGetId([
            'copy_id' => $copy, 'patron_id' => $patron, 'branch_id' => self::BRANCH_A,
            'checkout_date' => now()->subDays(24), 'due_date' => date('Y-m-d', strtotime('-10 days')),
            'renewed_count' => 0, 'status' => 'active', 'created_at' => now(),
        ]);

        $method = new \ReflectionMethod(LibraryCirculationService::class, 'generateOverdueFine');
        $method->invoke($this->circ, DB::table('library_checkout')->where('id', $id)->first());

        return (float) DB::table('library_fine')->where('checkout_id', $id)->value('amount');
    }

    private function ruleForm(array $over = []): array
    {
        return $over + [
            'branch_id' => 0, 'material_type' => self::MATERIAL, 'patron_type' => 'public',
            'loan_period_days' => 14, 'max_renewals' => 2, 'grace_period_days' => 0,
            'fine_per_day' => 1, 'is_loanable' => 1,
        ];
    }

    private function closureForm(array $over = []): array
    {
        return $over + [
            'branch_id' => 0, 'closure_type' => 'public_holiday', 'name' => 'ZZ Holiday',
            'start_date' => '2026-12-25', 'end_date' => '2026-12-26',
        ];
    }

    private function rule(int $branch, int $days, string $patron = '*'): int
    {
        return (int) DB::table('library_loan_rule')->insertGetId([
            'branch_id' => $branch, 'material_type' => self::MATERIAL, 'patron_type' => $patron,
            'loan_period_days' => $days, 'renewal_period_days' => $days, 'max_renewals' => 2,
            'fine_per_day' => 1.00, 'grace_period_days' => 0, 'is_loanable' => 1, 'created_at' => now(),
        ]);
    }

    private function hours(int $branch, int $weekday, bool $open): void
    {
        DB::table('library_opening_hours')->insert(['branch_id' => $branch, 'weekday' => $weekday, 'is_open' => $open ? 1 : 0]);
        $this->calendar->forget();
    }

    private function closure(int $branch, string $from, string $to, bool $yearly = false): void
    {
        DB::table('library_closure')->insert([
            'branch_id' => $branch, 'closure_type' => 'public_holiday', 'name' => 'ZZ Closure',
            'start_date' => $from, 'end_date' => $to, 'recurs_annually' => $yearly ? 1 : 0,
        ]);
        $this->calendar->forget();
    }

    private function fixture(?int $branchId): array
    {
        $itemId = DB::table('library_item')->insertGetId([
            'information_object_id' => 0, 'material_type' => self::MATERIAL, 'created_at' => now(),
        ]);
        $copyId = DB::table('library_copy')->insertGetId([
            'library_item_id' => $itemId, 'copy_number' => 1, 'barcode' => 'CAL-' . uniqid('', true),
            'branch_id' => $branchId, 'status' => LibraryCirculationService::STATUS_AVAILABLE, 'created_at' => now(),
        ]);
        $patronId = DB::table('library_patron')->insertGetId([
            'card_number' => 'CAL-' . uniqid('', true), 'patron_type' => 'public', 'first_name' => 'Cal',
            'last_name' => 'Fixture', 'membership_start' => now()->toDateString(), 'max_checkouts' => 5,
            'max_renewals' => 2, 'borrowing_status' => 'active', 'total_fines_owed' => 0, 'created_at' => now(),
        ]);

        return [$copyId, $patronId];
    }

    private function user(?int $group = null): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'cal-' . $id, 'email' => uniqid('cal-', true) . '@example.test',
            'password_hash' => Hash::make('x'), 'active' => 1]);
        if ($group) {
            DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => $group]);
        }
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        return User::findOrFail($id);
    }
}
