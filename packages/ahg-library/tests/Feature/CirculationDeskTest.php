<?php

/**
 * CirculationDeskTest - integration tests for the circulation service against
 * the real migration-backed schema (#1093).
 *
 * Exercises LibraryCirculationService + LibraryPatronService end-to-end with
 * RefreshDatabase so the new circulation migrations are proven to satisfy the
 * already-written service logic (the schema was previously fixture-only).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryPatronService;
use Illuminate\Support\Facades\DB;

class CirculationDeskTest extends LibraryFeatureTestCase
{
    protected LibraryCirculationService $circ;
    protected LibraryPatronService $patrons;

    protected function setUp(): void
    {
        parent::setUp();
        $this->circ = new LibraryCirculationService();
        $this->patrons = new LibraryPatronService();
        $this->seedFixtures();
    }

    /**
     * Seed a loan rule, a catalogue item and an available copy. Returns nothing;
     * helpers create patrons per-test.
     */
    protected function seedFixtures(): void
    {
        DB::table('library_loan_rule')->insert([
            'material_type' => 'monograph', 'patron_type' => 'student',
            'loan_period_days' => 14, 'max_renewals' => 2,
            'fine_per_day' => 0.50, 'fine_cap' => 25.00, 'grace_period_days' => 1,
            'is_loanable' => 1, 'created_at' => now(),
        ]);
        DB::table('library_loan_rule')->insert([
            'material_type' => 'monograph', 'patron_type' => '*',
            'loan_period_days' => 7, 'max_renewals' => 1,
            'fine_per_day' => 1.00, 'fine_cap' => null, 'grace_period_days' => 0,
            'is_loanable' => 1, 'created_at' => now(),
        ]);

        $itemId = DB::table('library_item')->insertGetId([
            'information_object_id' => 1,
            'material_type' => 'monograph',
            'call_number' => '025.4 SMI',
            'created_at' => now(),
        ]);
        DB::table('library_copy')->insert([
            'id' => 1, 'library_item_id' => $itemId, 'barcode' => 'CPY-0001',
            'status' => 'available', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function makePatron(array $overrides = []): int
    {
        return (int) DB::table('library_patron')->insertGetId(array_merge([
            'card_number' => 'LIB-26-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'patron_type' => 'student',
            'first_name' => 'Jane', 'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'membership_start' => now()->toDateString(),
            'membership_expiry' => now()->addYear()->toDateString(),
            'max_checkouts' => 5, 'max_renewals' => 2, 'max_holds' => 3,
            'borrowing_status' => 'active', 'total_fines_owed' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides));
    }

    public function test_resolve_loan_days_uses_rule_then_wildcard(): void
    {
        $this->assertSame(14, $this->circ->resolveLoanDays('monograph', 'student'));
        $this->assertSame(7, $this->circ->resolveLoanDays('monograph', 'visitor'));
    }

    public function test_checkout_happy_path(): void
    {
        $patronId = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $patronId);

        $this->assertNotNull($checkoutId);
        $this->assertDatabaseHas('library_checkout', [
            'id' => $checkoutId, 'copy_id' => 1, 'patron_id' => $patronId, 'status' => 'active',
        ]);
        $this->assertDatabaseHas('library_copy', ['id' => 1, 'status' => 'checked_out']);
        $this->assertDatabaseHas('library_patron', ['id' => $patronId, 'total_checkouts' => 1]);
    }

    public function test_checkout_blocked_when_copy_unavailable(): void
    {
        $patronId = $this->makePatron();
        DB::table('library_copy')->where('id', 1)->update(['status' => 'checked_out']);
        $this->assertNull($this->circ->checkout(1, $patronId));
    }

    public function test_checkout_blocked_when_patron_suspended(): void
    {
        $patronId = $this->makePatron(['borrowing_status' => 'suspended']);
        $this->assertNull($this->circ->checkout(1, $patronId));
    }

    public function test_checkout_blocked_over_fine_threshold(): void
    {
        $patronId = $this->makePatron(['total_fines_owed' => 75.00]);
        $this->assertNull($this->circ->checkout(1, $patronId));
    }

    public function test_renew_extends_due_date(): void
    {
        $patronId = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $patronId);
        $before = DB::table('library_checkout')->where('id', $checkoutId)->value('due_date');

        $this->assertTrue($this->circ->renew($checkoutId));
        $row = DB::table('library_checkout')->where('id', $checkoutId)->first();
        $this->assertSame(1, (int) $row->renewed_count);
        $this->assertTrue($row->due_date > $before);
    }

    public function test_renew_blocked_when_other_patron_waiting(): void
    {
        $borrower = $this->makePatron();
        $waiter   = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $borrower);
        $itemId = (int) DB::table('library_copy')->where('id', 1)->value('library_item_id');

        $this->circ->placeHold($itemId, $waiter);
        $this->assertFalse($this->circ->renew($checkoutId));
    }

    public function test_place_and_cancel_hold(): void
    {
        $patronId = $this->makePatron();
        $itemId = (int) DB::table('library_copy')->where('id', 1)->value('library_item_id');

        $holdId = $this->circ->placeHold($itemId, $patronId);
        $this->assertNotNull($holdId);
        $this->assertDatabaseHas('library_hold', ['id' => $holdId, 'status' => 'pending', 'queue_position' => 1]);

        $this->assertTrue($this->circ->cancelHold($holdId, 'Test'));
        $this->assertDatabaseHas('library_hold', ['id' => $holdId, 'status' => 'cancelled']);
    }

    public function test_return_promotes_waiting_hold(): void
    {
        $borrower = $this->makePatron();
        $waiter   = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $borrower);
        $itemId = (int) DB::table('library_copy')->where('id', 1)->value('library_item_id');
        $holdId = $this->circ->placeHold($itemId, $waiter);

        $this->assertTrue($this->circ->return($checkoutId));
        $this->assertDatabaseHas('library_hold', ['id' => $holdId, 'status' => 'ready']);
        $this->assertDatabaseHas('library_copy', ['id' => 1, 'status' => 'on_hold']);
    }

    public function test_return_marks_copy_available_when_no_hold(): void
    {
        $patronId = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $patronId);

        $this->assertTrue($this->circ->return($checkoutId, null, 'good'));
        $this->assertDatabaseHas('library_checkout', ['id' => $checkoutId, 'status' => 'returned']);
        $this->assertDatabaseHas('library_copy', ['id' => 1, 'status' => 'available']);
    }

    public function test_overdue_fine_generated_on_late_return(): void
    {
        $patronId = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $patronId);
        // Force the loan 10 days overdue.
        DB::table('library_checkout')->where('id', $checkoutId)
            ->update(['due_date' => now()->subDays(10)->toDateString()]);

        $this->assertTrue($this->circ->return($checkoutId));

        // grace 1 -> 9 fineable days * 0.50 = 4.50 (under 25 cap).
        $fine = DB::table('library_fine')->where('checkout_id', $checkoutId)->first();
        $this->assertNotNull($fine);
        $this->assertSame('4.50', number_format((float) $fine->amount, 2));
        $this->assertDatabaseHas('library_patron', ['id' => $patronId]);
        $owed = (float) DB::table('library_patron')->where('id', $patronId)->value('total_fines_owed');
        $this->assertEqualsWithDelta(4.50, $owed, 0.001);
    }

    public function test_calculate_all_overdue_fines_is_idempotent(): void
    {
        $patronId = $this->makePatron();
        $checkoutId = $this->circ->checkout(1, $patronId);
        DB::table('library_checkout')->where('id', $checkoutId)
            ->update(['due_date' => now()->subDays(10)->toDateString()]);

        $this->circ->calculateAllOverdueFines();
        $this->circ->calculateAllOverdueFines();

        $count = DB::table('library_fine')
            ->where('checkout_id', $checkoutId)
            ->where('fine_type', 'overdue')
            ->where('status', 'outstanding')
            ->count();
        $this->assertSame(1, $count, 'Re-running fine calc must update, not duplicate.');
    }

    public function test_auto_expire_holds(): void
    {
        $patronId = $this->makePatron();
        $itemId = (int) DB::table('library_copy')->where('id', 1)->value('library_item_id');
        $holdId = $this->circ->placeHold($itemId, $patronId);
        DB::table('library_hold')->where('id', $holdId)->update(['expiry_date' => '2020-01-01']);

        $affected = $this->circ->autoExpireHolds();
        $this->assertGreaterThanOrEqual(1, $affected);
        $this->assertDatabaseHas('library_hold', ['id' => $holdId, 'status' => 'expired']);
    }

    public function test_patron_service_create_and_expire_lapsed(): void
    {
        $id = $this->patrons->create([
            'first_name' => 'Lapsed', 'last_name' => 'Patron',
            'membership_expiry' => '2020-01-01',
        ]);
        $this->assertDatabaseHas('library_patron', ['id' => $id, 'borrowing_status' => 'active']);

        $expired = $this->patrons->expireLapsed();
        $this->assertGreaterThanOrEqual(1, $expired);
        $this->assertDatabaseHas('library_patron', ['id' => $id, 'borrowing_status' => 'expired']);
    }

    public function test_patron_category_seeded(): void
    {
        $this->assertDatabaseHas('library_patron_category', ['code' => 'student']);
        $this->assertDatabaseHas('library_patron_category', ['code' => 'academic']);
    }
}
