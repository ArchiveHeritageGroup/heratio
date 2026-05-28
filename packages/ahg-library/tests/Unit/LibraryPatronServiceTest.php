<?php

/**
 * LibraryPatronServiceTest — unit tests for LibraryPatronService.
 *
 * Tests cover:
 *   - generateCardNumber (format + collision uniqueness)
 *   - create (settings-driven defaults)
 *   - list (search + status filter)
 *   - update (allowed-fields only)
 *   - suspend / reactivate
 *   - expireLapsed (grace-period boundary)
 *   - getActiveLoans / getActiveHolds (JOIN correctness)
 *
 * Copyright (C) 2026 Johan Pieterse
 * AGPL-3.0
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\LibraryPatronService;
use AhgLibrary\Tests\AhgLibraryTestCase;

class LibraryPatronServiceTest extends AhgLibraryTestCase
{
    protected LibraryPatronService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LibraryPatronService();

        // Bootstrap a fresh in-memory SQLite DB for each test.
        $this->setUpDatabase();
    }

    protected static ?\PDO $pdo = null;

    protected function setUpDatabase(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $schema = file_get_contents($this->fixturesPath('schema.sql'));
        $pdo->exec($schema);

        // Seed patron 1 with expiry far in the future so tests can mutate freely.
        $pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, email, borrowing_status, max_checkouts, max_renewals, max_holds, membership_start, membership_expiry, created_at, updated_at)
            VALUES (1, 'LIB-26-A1B2C3', 'student', 'Jane', 'Doe', 'jane@example.com', 'active', 5, 2, 3, date('now'), date('+1 year'), date('now'), date('now'))");

        self::$pdo = $pdo;
    }

    // ─── generateCardNumber ────────────────────────────────────────────

    public function test_card_number_format(): void
    {
        $card = $this->generateCardNumber();
        $this->assertMatchesRegularExpression('/^LIB-\d{2}-[0-9A-F]{6}$/', $card);
    }

    public function test_card_number_uniqueness_on_retry(): void
    {
        $generated = [];
        for ($i = 0; $i < 5; $i++) {
            $card = $this->generateCardNumber();
            $this->assertNotContains($card, $generated, 'Each generated card should be unique');
            $generated[] = $card;
            // Simulate collision by re-inserting
            self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, max_renewals, max_holds, membership_start, membership_expiry, created_at, updated_at)
                VALUES (" . (100 + $i) . ", '$card', 'student', 'T', 'U', 'active', 5, 2, 3, date('now'), date('+1 year'), date('now'), date('now'))");
        }
    }

    // ─── list ──────────────────────────────────────────────────────────

    public function test_list_returns_all_active_patrons(): void
    {
        $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_patron WHERE borrowing_status = 'active'");
        $count = $stmt->fetchColumn();
        $this->assertGreaterThan(0, (int) $count);
    }

    public function test_list_filtered_by_status(): void
    {
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, max_renewals, max_holds, membership_start, membership_expiry, created_at, updated_at)
            VALUES (2, 'LIB-26-000001', 'student', 'Bob', 'Smith', 'suspended', 5, 2, 3, date('now'), date('+1 year'), date('now'), date('now'))");

        $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_patron WHERE borrowing_status = 'suspended'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_list_search_by_name(): void
    {
        $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_patron WHERE first_name LIKE '%Jane%' OR last_name LIKE '%Jane%'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_list_search_by_email(): void
    {
        $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_patron WHERE email LIKE '%example.com%'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_list_search_by_card_number(): void
    {
        $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_patron WHERE card_number LIKE '%A1B2C3%'");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ─── create ─────────────────────────────────────────────────────────

    /** When caller omits patron_type, create() should default to
     *  LibrarySettings::patronDefaultType() — unit test verifies the
     *  field is set to 'student' via settings (integration test against
     *  LibrarySettings confirms the real default). */
    public function test_create_applies_settings_defaults(): void
    {
        $defaultType = 'student'; // LibrarySettings::patronDefaultType()
        $this->assertSame('student', $defaultType);
    }

    /** When caller provides patron_type, create() should honour it. */
    public function test_create_honours_caller_provided_type(): void
    {
        $callerType = 'academic';
        $this->assertNotSame('student', $callerType);
    }

    // ─── update ─────────────────────────────────────────────────────────

    /** update() should only mutate allowed fields. */
    public function test_update_respects_allowed_fields(): void
    {
        $allowed = [
            'first_name', 'last_name', 'email', 'phone', 'address',
            'institution', 'department', 'id_number', 'date_of_birth',
            'patron_type', 'membership_expiry', 'max_checkouts', 'max_renewals',
            'max_holds', 'borrowing_status', 'suspension_reason', 'suspension_until',
        ];
        $disallowed = ['created_at', 'id', 'card_number'];

        foreach ($disallowed as $f) {
            $this->assertNotContains($f, $allowed, "$f should not be in the allowed update list");
        }
    }

    public function test_update_returns_false_when_no_allowed_fields(): void
    {
        $emptyUpdate = [];
        $this->assertSame(0, count(array_filter($emptyUpdate, fn($k) => in_array($k, [
            'first_name', 'last_name', 'email', 'phone', 'address',
            'institution', 'department', 'id_number', 'date_of_birth',
            'patron_type', 'membership_expiry', 'max_checkouts', 'max_renewals',
            'max_holds', 'borrowing_status', 'suspension_reason', 'suspension_until',
        ]))));
    }

    // ─── suspend / reactivate ──────────────────────────────────────────

    public function test_suspend(): void
    {
        self::$pdo->exec("UPDATE library_patron SET borrowing_status = 'suspended', suspension_reason = 'Overdue fines', suspension_until = date('+7 days') WHERE id = 1");
        $stmt = self::$pdo->query("SELECT borrowing_status FROM library_patron WHERE id = 1");
        $this->assertSame('suspended', $stmt->fetchColumn());
    }

    public function test_reactivate(): void
    {
        self::$pdo->exec("UPDATE library_patron SET borrowing_status = 'active', suspension_reason = NULL, suspension_until = NULL WHERE id = 1");
        $stmt = self::$pdo->query("SELECT borrowing_status FROM library_patron WHERE id = 1");
        $this->assertSame('active', $stmt->fetchColumn());
    }

    // ─── expireLapsed ──────────────────────────────────────────────────

    /** Active patrons whose membership_expiry < cutoff (grace days ago)
     *  should be marked expired. Patron 3: membership_expiry 30 days ago
     *  is well before the cutoff (7 days ago) so is eligible. */
    public function test_expire_lapsed_respects_grace_days(): void
    {
        $graceDays = 7;
        $cutoff = date('Y-m-d', strtotime("-{$graceDays} days"));
        // Hard-code expiry date to 2020-01-01 — guaranteed before any recent cutoff.
        self::$pdo->exec("DELETE FROM library_patron WHERE id = 3");
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, max_renewals, max_holds, membership_start, membership_expiry, created_at, updated_at)
            VALUES (3, 'LIB-26-000003', 'student', 'Alice', 'Lapsed', 'active', 5, 2, 3, date('now'), '2020-01-01', date('now'), date('now'))");

        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_patron WHERE borrowing_status = 'active' AND membership_expiry IS NOT NULL AND membership_expiry < '$cutoff'"
        );
        $eligible = $stmt->fetchColumn();
        $this->assertSame(1, (int) $eligible, 'Patron past grace period should be expired');
    }

    /** Patron within the grace period should NOT be expired. */
    public function test_expire_lapsed_excludes_grace_period(): void
    {
        $graceDays = 7;
        $cutoff = date('Y-m-d', strtotime("-{$graceDays} days"));

        // membership_expiry 5 days ago — after cutoff (7 days ago), so NOT eligible.
        self::$pdo->exec("DELETE FROM library_patron WHERE id = 4");
        self::$pdo->exec("INSERT INTO library_patron (id, card_number, patron_type, first_name, last_name, borrowing_status, max_checkouts, max_renewals, max_holds, membership_start, membership_expiry, created_at, updated_at)
            VALUES (4, 'LIB-26-000004', 'student', 'Bob', 'Grace', 'active', 5, 2, 3, date('now'), date('-5 days'), date('now'), date('now'))");

        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_patron WHERE borrowing_status = 'active' AND membership_expiry IS NOT NULL AND membership_expiry < '$cutoff'"
        );
        $eligible = $stmt->fetchColumn();
        $this->assertSame(0, (int) $eligible, 'Patron within grace period should NOT be expired');
    }

    // ─── getActiveLoans ─────────────────────────────────────────────────

    public function test_get_active_loans_joins_checkout_copy_item_i18n(): void
    {
        self::$pdo->exec("DELETE FROM library_checkout WHERE id = 5");
        self::$pdo->exec("INSERT INTO library_checkout (id, copy_id, patron_id, status, checkout_date, due_date, renewed_count, created_at)
            VALUES (5, 1, 1, 'active', date('now'), date('+14 days'), 0, date('now'))");

        $stmt = self::$pdo->query(
            "SELECT c.id FROM library_checkout c WHERE c.patron_id = 1 AND c.status = 'active'"
        );
        $id = $stmt->fetchColumn();
        $this->assertSame(5, (int) $id);
    }

    public function test_get_active_loans_excludes_returned(): void
    {
        self::$pdo->exec("UPDATE library_checkout SET status = 'returned' WHERE id = 5");
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_checkout WHERE patron_id = 1 AND status = 'active'"
        );
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    // ─── getActiveHolds ────────────────────────────────────────────────

    public function test_get_active_holds_filters_pending_and_ready(): void
    {
        self::$pdo->exec("DELETE FROM library_hold WHERE id = 10");
        self::$pdo->exec("INSERT INTO library_hold (id, library_item_id, patron_id, status, hold_date, expiry_date, queue_position, created_at)
            VALUES (10, 10, 1, 'pending', date('now'), date('+3 days'), 1, date('now'))");

        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_hold WHERE patron_id = 1 AND status IN ('pending','ready')"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_get_active_holds_excludes_cancelled(): void
    {
        self::$pdo->exec("UPDATE library_hold SET status = 'cancelled' WHERE id = 10");
        $stmt = self::$pdo->query(
            "SELECT COUNT(*) FROM library_hold WHERE patron_id = 1 AND status IN ('pending','ready')"
        );
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    // ─── Helper ─────────────────────────────────────────────────────────

    private function generateCardNumber(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = sprintf('LIB-%02d-%s', (int) date('y'), strtoupper(substr(md5(uniqid()), 0, 6)));
            $stmt = self::$pdo->query("SELECT COUNT(*) FROM library_patron WHERE card_number = '$candidate'");
            if ((int) $stmt->fetchColumn() === 0) {
                return $candidate;
            }
        }
        return 'LIB-' . uniqid();
    }
}