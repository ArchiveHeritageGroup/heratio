<?php

/**
 * LibraryBranchCirculationTest - #1473, the circulation branch axis.
 *
 * Circulation used to resolve loan rules from (material_type, patron_type)
 * alone, so every outlet of a multi-branch service lent on identical terms and
 * no transaction recorded where it happened. These cases pin the resolution
 * order that replaces it - an exact-branch rule beats an all-branches rule -
 * and that a checkout records its branch and lends on that branch's period.
 *
 * Branch ids here are bare integers rather than real repository rows: rule
 * resolution never joins repository, and creating one means three CTI inserts
 * that would test the actor hierarchy rather than circulation.
 */

namespace Tests\Feature;

use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Support\LibraryBranch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibraryBranchCirculationTest extends TestCase
{
    use DatabaseTransactions;

    private const BRANCH_A = 900001;

    private const BRANCH_B = 900002;

    /** A material type of its own, so seeded rules cannot satisfy a case. */
    private const MATERIAL = 'branchtest';

    private LibraryCirculationService $circ;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['library_loan_rule', 'library_item', 'library_copy', 'library_patron', 'library_checkout'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} not present in this install.");
            }
        }

        if (! Schema::hasColumn('library_loan_rule', 'branch_id')) {
            $this->markTestSkipped('Branch axis not migrated in this install (#1473).');
        }

        LibraryCirculationService::forgetSchemaCache();
        $this->circ = new LibraryCirculationService();
    }

    private function rule(int $branchId, string $patronType, int $days, int $renewals = 2): void
    {
        DB::table('library_loan_rule')->insert([
            'branch_id' => $branchId,
            'material_type' => self::MATERIAL,
            'patron_type' => $patronType,
            'loan_period_days' => $days,
            'renewal_period_days' => $days,
            'max_renewals' => $renewals,
            'fine_per_day' => 1.00,
            'grace_period_days' => 0,
            'is_loanable' => 1,
            'created_at' => now(),
        ]);
    }

    public function test_all_branches_rule_applies_when_no_branch_is_given(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 21);

        $this->assertSame(21, $this->circ->resolveLoanDays(self::MATERIAL, 'public'));
    }

    public function test_exact_branch_rule_beats_the_all_branches_rule(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 21);
        $this->rule(self::BRANCH_A, '*', 7);

        $this->assertSame(7, $this->circ->resolveLoanDays(self::MATERIAL, 'public', self::BRANCH_A));
    }

    public function test_a_branch_without_its_own_rule_falls_back_to_all_branches(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 21);
        $this->rule(self::BRANCH_A, '*', 7);

        // Branch B never opted out of the system default and must keep it.
        $this->assertSame(21, $this->circ->resolveLoanDays(self::MATERIAL, 'public', self::BRANCH_B));
    }

    public function test_branch_outranks_patron_type_specificity(): void
    {
        // A documented precedence decision, not an accident: a branch's local
        // policy overrides the system default even when the system default
        // names the patron type exactly and the branch rule is a wildcard.
        $this->rule(LibraryBranch::ALL, 'student', 28);
        $this->rule(self::BRANCH_A, '*', 3);

        $this->assertSame(3, $this->circ->resolveLoanDays(self::MATERIAL, 'student', self::BRANCH_A));
    }

    public function test_patron_type_still_wins_within_one_branch(): void
    {
        $this->rule(self::BRANCH_A, '*', 14);
        $this->rule(self::BRANCH_A, 'student', 30);

        $this->assertSame(30, $this->circ->resolveLoanDays(self::MATERIAL, 'student', self::BRANCH_A));
        $this->assertSame(14, $this->circ->resolveLoanDays(self::MATERIAL, 'public', self::BRANCH_A));
    }

    public function test_max_renewals_resolves_per_branch_and_respects_the_patron_cap(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 21, 2);
        $this->rule(self::BRANCH_A, '*', 7, 5);

        $this->assertSame(5, $this->circ->resolveMaxRenewals(self::MATERIAL, 'public', 9, self::BRANCH_A));
        $this->assertSame(2, $this->circ->resolveMaxRenewals(self::MATERIAL, 'public', 9, self::BRANCH_B));

        // The patron's own cap is still the ceiling.
        $this->assertSame(1, $this->circ->resolveMaxRenewals(self::MATERIAL, 'public', 1, self::BRANCH_A));
    }

    /** Create an item, a copy at $branchId, a patron, and return [copyId, patronId]. */
    private function fixture(?int $branchId, string $patronType = 'public'): array
    {
        $itemId = DB::table('library_item')->insertGetId([
            'information_object_id' => 0,
            'material_type' => self::MATERIAL,
            'created_at' => now(),
        ]);

        $copyId = DB::table('library_copy')->insertGetId([
            'library_item_id' => $itemId,
            'copy_number' => 1,
            'barcode' => 'BR-' . uniqid('', true),
            'branch_id' => $branchId,
            'status' => LibraryCirculationService::STATUS_AVAILABLE,
            'created_at' => now(),
        ]);

        $patronId = DB::table('library_patron')->insertGetId([
            'card_number' => 'BR-' . uniqid('', true),
            'patron_type' => $patronType,
            'first_name' => 'Branch',
            'last_name' => 'Fixture',
            'membership_start' => now()->toDateString(),
            'max_checkouts' => 5,
            'borrowing_status' => 'active',
            'total_fines_owed' => 0,
            'created_at' => now(),
        ]);

        return [$copyId, $patronId];
    }

    public function test_checkout_list_is_scoped_to_one_branch(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 14);

        [$copyA, $patronA] = $this->fixture(self::BRANCH_A);
        [$copyB, $patronB] = $this->fixture(self::BRANCH_B);

        $this->circ->checkout($copyA, $patronA);
        $this->circ->checkout($copyB, $patronB);

        $atA = $this->circ->listCheckouts(['status' => 'active', 'branch_id' => self::BRANCH_A]);
        $atB = $this->circ->listCheckouts(['status' => 'active', 'branch_id' => self::BRANCH_B]);

        $this->assertCount(1, $atA);
        $this->assertCount(1, $atB);
        $this->assertNotSame($atA[0]->id, $atB[0]->id);
    }

    public function test_a_null_branch_filter_means_every_branch_not_none(): void
    {
        // The distinction that makes single-outlet installs keep working: null
        // is the consortium view, not an empty scope.
        $this->rule(LibraryBranch::ALL, '*', 14);

        [$copyA, $patronA] = $this->fixture(self::BRANCH_A);
        [$copyB, $patronB] = $this->fixture(self::BRANCH_B);
        $this->circ->checkout($copyA, $patronA);
        $this->circ->checkout($copyB, $patronB);

        $all = $this->circ->listCheckouts(['status' => 'active', 'branch_id' => null]);

        $this->assertGreaterThanOrEqual(2, count($all));
    }

    public function test_a_loan_predating_the_branch_column_is_still_listed_via_its_copy(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 14);
        [$copyA, $patronA] = $this->fixture(self::BRANCH_A);

        $checkoutId = $this->circ->checkout($copyA, $patronA);

        // Simulate history: the loan itself carries no branch, but its copy does.
        DB::table('library_checkout')->where('id', $checkoutId)->update(['branch_id' => null]);

        $atA = $this->circ->listCheckouts(['status' => 'active', 'branch_id' => self::BRANCH_A]);

        $this->assertCount(1, $atA, 'a pre-branch loan vanished from its own branch list');
    }

    public function test_overdue_list_derives_patron_name_and_days_overdue(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 14);
        [$copyA, $patronA] = $this->fixture(self::BRANCH_A);
        $checkoutId = $this->circ->checkout($copyA, $patronA);

        // Six days past due.
        DB::table('library_checkout')->where('id', $checkoutId)
            ->update(['due_date' => now()->subDays(6)->toDateString()]);

        $overdue = collect($this->circ->listOverdue())->firstWhere('id', $checkoutId);

        $this->assertNotNull($overdue, 'the overdue loan was not listed');
        // These three were bound in the view but never selected by the query,
        // so every row rendered blank / 0 / 0.00 before this fix.
        $this->assertSame('Branch Fixture', $overdue->patron_name);
        $this->assertSame(6, $overdue->days_overdue);
        $this->assertNull($overdue->fine_amount, 'no fine row exists yet, so it is not zero but unknown');
    }

    public function test_hold_queue_position_is_per_pickup_branch(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 14);
        [$copyA, $patronA] = $this->fixture(self::BRANCH_A);
        [, $patronA2] = $this->fixture(self::BRANCH_A);
        [, $patronB] = $this->fixture(self::BRANCH_B);

        $itemId = (int) DB::table('library_copy')->where('id', $copyA)->value('library_item_id');

        $h1 = $this->circ->placeHold($itemId, $patronA, self::BRANCH_A);
        $h2 = $this->circ->placeHold($itemId, $patronA2, self::BRANCH_A);
        $h3 = $this->circ->placeHold($itemId, $patronB, self::BRANCH_B);

        // Branch B's first patron is first in B's queue, not third overall. A
        // patron collecting at B is not behind the people waiting at A.
        $this->assertSame(1, (int) DB::table('library_hold')->where('id', $h1)->value('queue_position'));
        $this->assertSame(2, (int) DB::table('library_hold')->where('id', $h2)->value('queue_position'));
        $this->assertSame(1, (int) DB::table('library_hold')->where('id', $h3)->value('queue_position'));
    }

    public function test_a_returned_copy_promotes_the_hold_at_its_own_branch(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 14);
        [$copyB, $patronB] = $this->fixture(self::BRANCH_B);
        $itemId = (int) DB::table('library_copy')->where('id', $copyB)->value('library_item_id');

        // Somebody waiting at A, somebody waiting at B.
        [, $patronA] = $this->fixture(self::BRANCH_A);
        $this->circ->placeHold($itemId, $patronA, self::BRANCH_A);
        $this->circ->placeHold($itemId, $patronB, self::BRANCH_B);

        // A copy shelved at B is checked out and returned there.
        [, $borrower] = $this->fixture(self::BRANCH_B);
        $checkoutId = $this->circ->checkout($copyB, $borrower);
        $this->assertNotNull($checkoutId);
        $this->circ->return($checkoutId);

        $ready = DB::table('library_hold')
            ->where('library_item_id', $itemId)->where('status', 'ready')->first();

        $this->assertNotNull($ready, 'no hold was promoted');
        $this->assertSame(self::BRANCH_B, (int) $ready->branch_id,
            'a copy returned at branch B promoted a hold at another branch');
    }

    public function test_saving_a_rule_twice_edits_it_instead_of_duplicating(): void
    {
        $id = $this->circ->saveLoanRule([
            'branch_id' => self::BRANCH_A, 'material_type' => self::MATERIAL,
            'patron_type' => 'student', 'loan_period_days' => 3, 'is_loanable' => 1,
        ]);
        $again = $this->circ->saveLoanRule([
            'branch_id' => self::BRANCH_A, 'material_type' => self::MATERIAL,
            'patron_type' => 'student', 'loan_period_days' => 7, 'is_loanable' => 1,
        ]);

        // (branch, material, patron) is the table's unique key, so the second
        // save must edit the first rather than fail on a duplicate.
        $this->assertSame($id, $again);
        $this->assertSame(7, (int) DB::table('library_loan_rule')->where('id', $id)->value('loan_period_days'));
        $this->assertSame(7, $this->circ->resolveLoanDays(self::MATERIAL, 'student', self::BRANCH_A));

        $this->assertTrue($this->circ->deleteLoanRule($id));
        $this->assertFalse(DB::table('library_loan_rule')->where('id', $id)->exists());
    }

    public function test_checkout_records_the_branch_and_lends_on_its_loan_period(): void
    {
        $this->rule(LibraryBranch::ALL, '*', 21);
        $this->rule(self::BRANCH_A, '*', 7);

        // information_object_id is NOT NULL with no default and carries no FK
        // constraint; 0 keeps the fixture from dragging in the whole
        // information_object CTI chain to test a loan period.
        $itemId = DB::table('library_item')->insertGetId([
            'information_object_id' => 0,
            'material_type' => self::MATERIAL,
            'created_at' => now(),
        ]);

        $copyId = DB::table('library_copy')->insertGetId([
            'library_item_id' => $itemId,
            'copy_number' => 1,
            'barcode' => 'BR-' . uniqid(),
            'branch_id' => self::BRANCH_A,
            'status' => LibraryCirculationService::STATUS_AVAILABLE,
            'created_at' => now(),
        ]);

        $patronId = DB::table('library_patron')->insertGetId([
            'card_number' => 'BR-' . uniqid(),
            'patron_type' => 'public',
            'first_name' => 'Branch',
            'last_name' => 'Fixture',
            'membership_start' => now()->toDateString(),
            'max_checkouts' => 5,
            'borrowing_status' => 'active',
            'total_fines_owed' => 0,
            'created_at' => now(),
        ]);

        $checkoutId = $this->circ->checkout($copyId, $patronId);
        $this->assertNotNull($checkoutId, 'checkout was refused');

        $checkout = DB::table('library_checkout')->where('id', $checkoutId)->first();

        // The copy's branch, not the system default, decides both.
        $this->assertSame(self::BRANCH_A, (int) $checkout->branch_id);
        $this->assertSame(
            now()->addDays(7)->toDateString(),
            date('Y-m-d', strtotime($checkout->due_date)),
            'the loan took the all-branches 21-day period instead of the branch rule'
        );
    }
}
