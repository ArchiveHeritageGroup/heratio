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
