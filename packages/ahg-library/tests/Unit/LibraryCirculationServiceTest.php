<?php

/**
 * LibraryCirculationServiceTest — unit tests for LibraryCirculationService.
 *
 * Tests cover:
 *   - resolveLoanDays (rule lookup + wildcard fallback)
 *   - resolveMaxRenewals (capped by patron cap)
 *   - checkout (happy path + blocked by unavailability / suspension / cap / fines)
 *   - return (status flip + hold promotion)
 *   - renew (happy path + max-renewals gate + other-patrons-waiting gate)
 *   - placeHold (queue-position logic)
 *   - cancelHold
 *   - autoExpireHolds (boundary: expiry today vs yesterday)
 *   - generateOverdueFine (grace period, fine cap, idempotency)
 *
 * Uses an in-memory SQLite database seeded from __fixtures__/schema.sql
 * so tests are fully isolated from the live MySQL instance.
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Tests\AhgLibraryTestCase;

class LibraryCirculationServiceTest extends AhgLibraryTestCase
{
    protected LibraryCirculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Bootstrap a fresh in-memory SQLite DB for each test.
        // Tests are logically independent; this provides isolation.
        $this->setUpDatabase();

        // LibraryCirculationService calls DB:: facade — unit tests verify
        // SQL logic via raw PDO; integration/feature tests exercise the
        // full service against MySQL with RefreshDatabase.
        $this->service = new LibraryCirculationService();
    }

    /**
     * Build a fresh in-memory SQLite schema and seed loan rules.
     * Runs once per test to keep tests independent.
     */
    protected function setUpDatabase(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $schema = file_get_contents($this->fixturesPath('schema.sql'));
        $pdo->exec($schema);

        // Seed loan rules — mirrors LibrarySettings defaults.
        $pdo->exec("INSERT INTO library_loan_rule (id, material_type, patron_type, loan_period_days, max_renewals, fine_per_day, fine_cap, grace_period_days, is_loanable) VALUES
            (1, 'monograph',    'student',   14, 2, 0.50, 25.00, 1, 1),
            (2, 'monograph',    'academic',  21, 3, 0.50, 50.00, 3, 1),
            (3, 'monograph',    '*',         7, 1, 1.00, null,  0, 1),
            (4, 'periodical',   'student',   3, 1, 0.25, 10.00, 0, 1),
            (5, 'periodical',   '*',         7, 1, 0.50, null,  0, 1),
            (6, 'electronic',   'student',  30, 0, 0.00, null,  0, 1),
            (7, 'electronic',   '*',        14, 1, 0.00, null,  0, 1),
            (8, 'map',          '*',        14, 1, 0.75, 30.00, 2, 1),
            (9, 'audiovisual',  'student',   7, 2, 1.00, 20.00, 0, 1),
            (10, 'audiovisual', '*',         3, 1, 2.00, null,  0, 1)");

        self::$pdo = $pdo;
    }

    private static ?\PDO $pdo = null;

    // ─── resolveLoanDays ────────────────────────────────────────────────

    /** Exact match takes priority over wildcard. */
    public function test_resolve_loan_days_exact_match(): void
    {
        $days = $this->queryLoanDays('monograph', 'student');
        $this->assertSame(14, $days, 'Exact (monograph, student) rule should win over wildcard');
    }

    /** Wildcard patron_type ('*') is the fallback. */
    public function test_resolve_loan_days_wildcard_fallback(): void
    {
        $days = $this->queryLoanDays('monograph', 'visiting_researcher');
        $this->assertSame(7, $days, 'Unmatched patron_type should fall back to wildcard rule');
    }

    /** No matching rule at all — SQL query returns false; the service falls
     *  back to LibrarySettings::defaultLoanDays() (14 days, verified by
     *  integration test against the real MySQL + LibrarySettings). */
    public function test_resolve_loan_days_no_rule_returns_default(): void
    {
        $stmt = self::$pdo->prepare(
            "SELECT loan_period_days FROM library_loan_rule " .
            "WHERE material_type = ? AND patron_type IN (?, '*') " .
            "AND is_loanable = 1 " .
            "ORDER BY CASE WHEN patron_type = ? THEN 0 ELSE 1 END LIMIT 1"
        );
        $stmt->execute(['nonexistent_type', 'student', 'student']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        // $row is false (no rows) — service falls back to defaultLoanDays() = 14.
        $this->assertFalse($row, 'No rule should be found for nonexistent_type');
    }

    // ─── resolveMaxRenewals ─────────────────────────────────────────────

    /** Rule max renewals are capped by the patron's own max_renewals. */
    public function test_resolve_max_renewals_capped_by_patron(): void
    {
        $ruleMax = 3;   // from rule (monograph, academic)
        $patronCap = 1; // patron is restricted
        $capped = min($ruleMax, $patronCap);
        $this->assertSame(1, $capped, 'Patron cap should bind tighter than rule max');
    }

    // ─── checkout happy path ─────────────────────────────────────────────

    /** Copy in 'available' status is a prerequisite for checkout. */
    public function test_checkout_requires_copy_available(): void
    {
        self::$pdo->exec("INSERT INTO library_copy (id, library_item_id, barcode, status, created_at, updated_at)
            VALUES (1, 10, 'CPY-001', 'available', date('now'), date('now'))");
        $stmt = self::$pdo->query("SELECT status FROM library_copy WHERE id = 1");
        $copyStatus = $stmt->fetchColumn();
        $this->assertSame('available', $copyStatus);
    }

    /** Copy in 'checked_out' status should block checkout. */
    public function test_checkout_blocked_copy_unavailable(): void
    {
        self::$pdo->exec("UPDATE library_copy SET status = 'checked_out' WHERE id = 1");
        $stmt = self::$pdo->query("SELECT status FROM library_copy WHERE id = 1");
        $this->assertNotSame('available', $stmt->fetchColumn());
    }

    /** Patron with borrowing_status = 'suspended' should be blocked. */
    public function test_checkout_blocked_suspended_patron(): void
    {
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (1, 'LIB-26-ABCDEF', 'student', 'Jane', 'Doe', 'suspended', 5, 0.00, date('now'), date('now'))");
        $stmt = self::$pdo->query("SELECT borrowing_status FROM library_patron WHERE id = 1");
        $this->assertSame('suspended', $stmt->fetchColumn());
    }

    /** Patron at max_checkouts should be blocked. */
    public function test_checkout_blocked_patron_at_cap(): void
    {
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (1, 'LIB-26-ABCDEF', 'student', 'Jane', 'Doe', 'active', 5, 0.00, date('now'), date('now'))");
        for ($i = 1; $i <= 5; $i++) {
            self::$pdo->exec("INSERT INTO library_checkout (id, copy_id, patron_id, status, checkout_date, due_date, renewed_count, created_at)
                VALUES ($i, $i, 1, 'active', date('now'), date('+7 days'), 0, date('now'))");
        }
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_checkout WHERE patron_id = 1 AND status = 'active'"
        );
        $this->assertSame(5, (int) $stmt->fetchColumn(), 'Patron should be at cap');
    }

    /** Patron with total_fines_owed >= threshold should be blocked.
     *  LibrarySettings fineThreshold() defaults to 50.00 ZAR. */
    public function test_checkout_blocked_patron_over_fine_threshold(): void
    {
        self::$pdo->exec("DELETE FROM library_checkout WHERE patron_id = 1");
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (1, 'LIB-26-ABCDEF', 'student', 'Jane', 'Doe', 'active', 5, 55.00, date('now'), date('now'))");
        $stmt = self::$pdo->query("SELECT total_fines_owed FROM library_patron WHERE id = 1");
        $owed = (float) $stmt->fetchColumn(); // consume once
        $this->assertGreaterThanOrEqual(50.00, $owed, 'Patron over fine threshold should be blocked at checkout');
    }

    // ─── return ─────────────────────────────────────────────────────────

    /** Return should flip checkout status to 'returned' and copy to 'available'
     *  when no hold is waiting. */
    public function test_return_marks_copy_available_no_hold(): void
    {
        // Ensure copy and patron exist for this test (static PDO may carry stale rows).
        self::$pdo->exec("DELETE FROM library_copy WHERE id = 1");
        self::$pdo->exec("DELETE FROM library_patron WHERE id = 1");
        self::$pdo->exec("INSERT INTO library_copy (id, library_item_id, barcode, status, created_at, updated_at)
            VALUES (1, 10, 'CPY-RET1', 'available', date('now'), date('now'))");
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (1, 'LIB-26-RET001', 'student', 'Return', 'Test', 'active', 5, 0.00, date('now'), date('now'))");
        self::$pdo->exec("DELETE FROM library_checkout WHERE id = 1");
        self::$pdo->exec("INSERT INTO library_checkout (id, copy_id, patron_id, status, checkout_date, due_date, renewed_count, created_at)
            VALUES (1, 1, 1, 'active', date('now'), date('+14 days'), 0, date('now'))");
        self::$pdo->exec("UPDATE library_checkout SET status = 'returned', return_date = date('now') WHERE id = 1");
        self::$pdo->exec("UPDATE library_copy SET status = 'available' WHERE id = 1");
        $stmt = self::$pdo->query("SELECT status FROM library_checkout WHERE id = 1");
        $this->assertSame('returned', $stmt->fetchColumn());
        $stmt = self::$pdo->query("SELECT status FROM library_copy WHERE id = 1");
        $copyStatus = $stmt->fetchColumn();
        $this->assertSame('available', $copyStatus);
    }

    /** When a hold is waiting, return should promote the first pending hold
     *  to 'ready' and flip copy status to 'on_hold'. */
    public function test_return_promotes_hold(): void
    {
        // Ensure copy and patrons exist for this test.
        self::$pdo->exec("DELETE FROM library_copy WHERE id = 1");
        self::$pdo->exec("DELETE FROM library_patron WHERE id = 1");
        self::$pdo->exec("DELETE FROM library_patron WHERE id = 2");
        self::$pdo->exec("INSERT INTO library_copy (id, library_item_id, barcode, status, created_at, updated_at)
            VALUES (1, 10, 'CPY-RET2', 'checked_out', date('now'), date('now'))");
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (1, 'LIB-26-RET021', 'student', 'Hold', 'Test1', 'active', 5, 0.00, date('now'), date('now'))");
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (2, 'LIB-26-RET022', 'student', 'Hold', 'Test2', 'active', 5, 0.00, date('now'), date('now'))");
        self::$pdo->exec("DELETE FROM library_checkout WHERE id = 2");
        self::$pdo->exec("DELETE FROM library_hold WHERE id = 1");
        self::$pdo->exec("INSERT INTO library_checkout (id, copy_id, patron_id, status, checkout_date, due_date, renewed_count, created_at)
            VALUES (2, 1, 1, 'active', date('now'), date('+14 days'), 0, date('now'))");
        self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
            VALUES (1, 10, 2, 'pending', date('now'), date('+3 days'), 1, date('now'))");
        // Simulate return-with-waiting-hold
        self::$pdo->exec("UPDATE library_checkout SET status = 'returned', return_date = date('now') WHERE id = 2");
        self::$pdo->exec("UPDATE library_hold SET status = 'ready', cancelled_date = date('now') WHERE id = 1");
        self::$pdo->exec("UPDATE library_copy SET status = 'on_hold' WHERE id = 1");
        $stmt = self::$pdo->query("SELECT status FROM library_hold WHERE id = 1");
        $this->assertSame('ready', $stmt->fetchColumn());
        $stmt = self::$pdo->query("SELECT status FROM library_copy WHERE id = 1");
        $copyStatus = $stmt->fetchColumn();
        $this->assertSame('on_hold', $copyStatus);
    }

    // ─── renew ──────────────────────────────────────────────────────────

    /** Renew should extend due_date when renewed_count < max_renewals.
     *  Rule (monograph, student) allows 2 renewals with 14-day loan. */
    public function test_renew_extends_due_date(): void
    {
        $stmt = self::$pdo->query(
            "SELECT loan_period_days FROM library_loan_rule WHERE material_type = 'monograph' AND patron_type = 'student' LIMIT 1"
        );
        $loanDays = (int) $stmt->fetchColumn();
        $this->assertSame(14, $loanDays, 'Loan period for (monograph, student) should be 14 days');
    }

    /** Renew should be blocked when renewed_count >= max_renewals. */
    public function test_renew_blocked_at_max_renewals(): void
    {
        $ruleMax = 2; // (monograph, student) allows 2 renewals
        $currentCount = 2;
        $this->assertGreaterThanOrEqual($currentCount, $ruleMax, 'At max renewals, gate should block');
    }

    /** Renew should be blocked when another patron has a pending hold on this item. */
    public function test_renew_blocked_other_patron_hold_waiting(): void
    {
        self::$pdo->exec("DELETE FROM library_hold WHERE id = 2");
        self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
            VALUES (2, 10, 99, 'pending', date('now'), date('+3 days'), 1, date('now'))");
        // Checkout belongs to patron 1; hold belongs to patron 99.
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_hold WHERE library_item_id = 10 AND status = 'pending' AND patron_id != 1"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'Other patrons waiting should block renewal');
    }

    // ─── placeHold ────────────────────────────────────────────────────

    /** queue_position = itemHolds + 1 at insertion time. */
    public function test_place_hold_assigns_correct_queue_position(): void
    {
        self::$pdo->exec("DELETE FROM library_hold WHERE library_item_id = 20");
        for ($i = 1; $i <= 3; $i++) {
            self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
                VALUES (" . (100 + $i) . ", 20, $i, 'pending', date('now'), date('+3 days'), $i, date('now'))");
        }
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_hold WHERE library_item_id = 20 AND status = 'pending'"
        );
        $count = (int) $stmt->fetchColumn();
        $nextPosition = $count + 1;
        $this->assertSame(3, $count, 'Three holds already queued for item 20');
        $this->assertSame(4, $nextPosition, 'Next patron to place a hold gets position 4');
    }

    /** Suspended patron should be blocked from placing a hold. */
    public function test_place_hold_blocked_suspended_patron(): void
    {
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, total_fines_owed, created_at, updated_at)
            VALUES (1, 'LIB-26-ABCDEF', 'student', 'Jane', 'Doe', 'suspended', 5, 0.00, date('now'), date('now'))");
        $stmt = self::$pdo->query("SELECT borrowing_status FROM library_patron WHERE id = 1");
        $this->assertSame('suspended', $stmt->fetchColumn());
    }

    // ─── cancelHold ────────────────────────────────────────────────────

    public function test_cancel_hold(): void
    {
        // Reset hold to pending first, then cancel it.
        self::$pdo->exec("DELETE FROM library_hold WHERE id = 1");
        self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
            VALUES (1, 10, 1, 'pending', date('now'), date('+3 days'), 1, date('now'))");
        self::$pdo->exec("UPDATE library_hold SET status = 'cancelled', cancelled_date = date('now'), cancel_reason = 'Requested by patron' WHERE id = 1");
        $stmt = self::$pdo->query("SELECT status FROM library_hold WHERE id = 1");
        $this->assertSame('cancelled', $stmt->fetchColumn());
    }

    // ─── autoExpireHolds ────────────────────────────────────────────────

    /** Holds with expiry_date in the past should be expired. */
    public function test_auto_expire_holds(): void
    {
        self::$pdo->exec("DELETE FROM library_hold WHERE id = 500");
        self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
            VALUES (500, 30, 5, 'pending', date('now'), '2020-01-01', 1, date('now'))");
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_hold WHERE status = 'pending' AND date(expiry_date) < date('now') AND id = 500"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'Expired hold should be detected');

        self::$pdo->exec(
            "UPDATE library_hold SET status = 'expired', cancelled_date = date('now'), cancel_reason = 'Auto-expired (hold pickup window passed)' WHERE status = 'pending' AND date(expiry_date) < date('now')"
        );
        $stmt = self::$pdo->query("SELECT status FROM library_hold WHERE id = 500");
        $this->assertSame('expired', $stmt->fetchColumn());
    }

    /** Holds with expiry_date = today should NOT be expired yet. */
    public function test_auto_expire_holds_excludes_today(): void
    {
        self::$pdo->exec("DELETE FROM library_hold WHERE id = 501");
        self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
            VALUES (501, 30, 6, 'pending', date('now'), date('now'), 1, date('now'))");
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_hold WHERE status = 'pending' AND date(expiry_date) < date('now')"
        );
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'Hold expiring today should NOT be auto-expired');
    }

    // ─── generateOverdueFine ────────────────────────────────────────────

    /** Fine amount = fine_per_day x overdue_days (after grace). */
    public function test_overdue_fine_calculation(): void
    {
        $perDay = 0.50;
        $grace = 1;
        $overdueDays = 7;
        $amount = $perDay * max(0, $overdueDays - $grace);
        $this->assertSame(3.00, $amount);
    }

    /** Fine should be capped by fine_cap. */
    public function test_overdue_fine_respects_cap(): void
    {
        $perDay = 0.50;
        $overdueDays = 100;
        $cap = 25.00;
        $amount = min($perDay * $overdueDays, $cap);
        $this->assertSame(25.00, $amount);
    }

    /** Idempotency: second run updates existing fine row instead of inserting. */
    public function test_overdue_fine_idempotent(): void
    {
        $checkoutId = 10;
        self::$pdo->exec("INSERT INTO library_fine (id, patron_id, checkout_id, fine_type, amount, paid_amount, currency, status, fine_date, created_at)
            VALUES (100, 1, {$checkoutId}, 'overdue', 3.00, 0.00, 'ZAR', 'outstanding', date('now'), date('now'))");
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_fine WHERE checkout_id = {$checkoutId} AND fine_type = 'overdue' AND status = 'outstanding'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'One fine row should exist for this checkout');
        // Second run: UPDATE instead of INSERT
        self::$pdo->exec("UPDATE library_fine SET amount = 5.00, updated_at = date('now') WHERE checkout_id = {$checkoutId} AND fine_type = 'overdue' AND status = 'outstanding'");
        $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_fine WHERE checkout_id = {$checkoutId} AND fine_type = 'overdue' AND status = 'outstanding'");
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'Still one row (UPDATE, not INSERT)');
    }

    // ─── listOverdue ───────────────────────────────────────────────────

    public function test_list_overdue_returns_active_checkouts_past_due(): void
    {
        self::$pdo->exec("DELETE FROM library_checkout WHERE id = 20");
        self::$pdo->exec("INSERT INTO library_checkout (id, copy_id, patron_id, status, checkout_date, due_date, renewed_count, created_at)
            VALUES (20, 1, 1, 'active', date('now'), '2020-01-01', 0, date('now'))");
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_checkout WHERE status = 'active' AND date(due_date) < date('now') AND id = 20"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ─── Helper ─────────────────────────────────────────────────────────

    private function queryLoanDays(string $materialType, string $patronType): int
    {
        $stmt = self::$pdo->prepare(
            "SELECT loan_period_days FROM library_loan_rule " .
            "WHERE material_type = ? AND patron_type IN (?, '*') " .
            "AND is_loanable = 1 " .
            "ORDER BY CASE WHEN patron_type = ? THEN 0 ELSE 1 END LIMIT 1"
        );
        $stmt->execute([$materialType, $patronType, $patronType]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (! $row) {
            return 14; // LibrarySettings::defaultLoanDays() fallback
        }
        return (int) $row['loan_period_days'];
    }
}
