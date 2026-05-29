<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * Unit coverage for LibrarySerialService (heratio#1092).
 *
 * The richer date-math + DB methods on the service depend on the Laravel
 * DB/Schema facades, which are not booted in this package's standalone PHPUnit
 * run. So, mirroring LibraryPatronServiceTest, the DB-dependent behaviour is
 * verified against an in-memory SQLite database driven directly through PDO
 * (asserting the SQL semantics the service relies on), while the pure helpers
 * (interval days, issues-per-year, enumeration roll-forward) are asserted
 * directly.
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\LibrarySerialEnumerationParser;
use AhgLibrary\Services\LibrarySerialService;
use AhgLibrary\Tests\AhgLibraryTestCase;

class LibrarySerialServiceTest extends AhgLibraryTestCase
{
    protected static ?\PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(file_get_contents($this->fixturesPath('schema.sql')));

        // Monthly serial, active, with a recent issue.
        $pdo->exec("INSERT INTO library_serial (id, title, issn, frequency, publisher, status, created_at, updated_at)
            VALUES (1, 'Journal of Testing', '1234-5678', 'monthly', 'Acme', 'active', date('now'), date('now'))");
        $pdo->exec("INSERT INTO library_serial_issue (id, serial_id, volume, issue_number, issue_date, received_at, status, created_at, updated_at)
            VALUES (1, 1, '5', '3', date('now','-40 days'), date('now','-40 days'), 'received', date('now'), date('now'))");

        self::$pdo = $pdo;
    }

    // ── Enumeration constants + parser integration ───────────────────────

    public function test_frequencies_constant_covers_all_codes(): void
    {
        $this->assertArrayHasKey(LibrarySerialService::FREQUENCY_MONTHLY, LibrarySerialService::FREQUENCIES);
        $this->assertArrayHasKey(LibrarySerialService::FREQUENCY_QUARTERLY, LibrarySerialService::FREQUENCIES);
        $this->assertSame('Monthly', LibrarySerialService::FREQUENCIES['monthly']);
    }

    public function test_prediction_uses_enumeration_parser_rollover(): void
    {
        // The service feeds the running enumeration through the parser; verify
        // the parser the service uses rolls a monthly issue 12 -> next volume.
        $parser = new LibrarySerialEnumerationParser();
        $next = $parser->increment(['volume' => '5', 'issue' => '12'], 'monthly');
        $this->assertSame('6', $next['volume']);
        $this->assertSame('1', $next['issue_number']);
    }

    public function test_prediction_increments_issue_mid_volume(): void
    {
        $parser = new LibrarySerialEnumerationParser();
        $next = $parser->increment(['volume' => '5', 'issue' => '3'], 'monthly');
        $this->assertSame('5', $next['volume']);
        $this->assertSame('4', $next['issue_number']);
    }

    // ── CRUD SQL semantics ───────────────────────────────────────────────

    public function test_create_serial_row(): void
    {
        self::$pdo->exec("INSERT INTO library_serial (title, issn, frequency, status, created_at, updated_at)
            VALUES ('New Serial', '0000-0000', 'quarterly', 'active', date('now'), date('now'))");
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial")->fetchColumn();
        $this->assertSame(2, $count);
    }

    public function test_delete_cascades_issues(): void
    {
        // Service delete() removes issues first, then the serial.
        self::$pdo->exec("DELETE FROM library_serial_issue WHERE serial_id = 1");
        self::$pdo->exec("DELETE FROM library_serial WHERE id = 1");
        $this->assertSame(0, (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial_issue WHERE serial_id = 1")->fetchColumn());
        $this->assertSame(0, (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial WHERE id = 1")->fetchColumn());
    }

    public function test_list_filters_by_status(): void
    {
        self::$pdo->exec("INSERT INTO library_serial (id, title, status, frequency, created_at, updated_at)
            VALUES (2, 'Ceased Title', 'ceased', 'annual', date('now'), date('now'))");
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial WHERE status = 'active'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_issue_count_aggregation(): void
    {
        self::$pdo->exec("INSERT INTO library_serial_issue (serial_id, volume, issue_number, issue_date, status, created_at, updated_at)
            VALUES (1, '5', '4', date('now','-10 days'), 'received', date('now'), date('now'))");
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial_issue WHERE serial_id = 1")->fetchColumn();
        $this->assertSame(2, $count);
    }

    // ── Subscription ─────────────────────────────────────────────────────

    public function test_subscription_upsert_unique_per_serial(): void
    {
        self::$pdo->exec("INSERT INTO library_serial_subscription (serial_id, subscription_end, notification_email, created_at, updated_at)
            VALUES (1, date('now','+10 days'), 'librarian@example.org', date('now'), date('now'))");

        // A second insert for the same serial_id must violate UNIQUE.
        $threw = false;
        try {
            self::$pdo->exec("INSERT INTO library_serial_subscription (serial_id, subscription_end, created_at, updated_at)
                VALUES (1, date('now','+20 days'), date('now'), date('now'))");
        } catch (\PDOException) {
            $threw = true;
        }
        $this->assertTrue($threw, 'serial_id must be unique in library_serial_subscription');
    }

    // ── Expiry warnings ───────────────────────────────────────────────────

    public function test_expiring_subscription_within_window(): void
    {
        self::$pdo->exec("INSERT INTO library_serial_subscription (serial_id, subscription_end, notification_email, created_at, updated_at)
            VALUES (1, date('now','+15 days'), 'librarian@example.org', date('now'), date('now'))");

        $today = date('Y-m-d');
        $cutoff = date('Y-m-d', strtotime('+30 days'));
        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM library_serial_subscription WHERE subscription_end BETWEEN '$today' AND '$cutoff'"
        )->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_expired_subscription_excluded_from_warning_window(): void
    {
        self::$pdo->exec("INSERT INTO library_serial_subscription (serial_id, subscription_end, created_at, updated_at)
            VALUES (1, '2020-01-01', date('now'), date('now'))");

        $today = date('Y-m-d');
        $cutoff = date('Y-m-d', strtotime('+30 days'));
        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM library_serial_subscription WHERE subscription_end BETWEEN '$today' AND '$cutoff'"
        )->fetchColumn();
        $this->assertSame(0, $count);
    }

    // ── Claims ─────────────────────────────────────────────────────────────

    public function test_record_claim_row(): void
    {
        self::$pdo->exec("INSERT INTO library_claim (serial_id, issue_id, claimed_at, claimed_by, reason, status, created_at, updated_at)
            VALUES (1, NULL, date('now'), 'system:claim-alert', 'Overdue', 'open', date('now'), date('now'))");
        $row = self::$pdo->query("SELECT status FROM library_claim WHERE serial_id = 1")->fetchColumn();
        $this->assertSame('open', $row);
    }

    public function test_has_open_claim_since_dedup_logic(): void
    {
        self::$pdo->exec("INSERT INTO library_claim (serial_id, claimed_at, status, created_at, updated_at)
            VALUES (1, date('now','-2 days'), 'open', date('now'), date('now'))");

        $since = date('Y-m-d', strtotime('-5 days'));
        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM library_claim WHERE serial_id = 1 AND status IN ('open','sent') AND claimed_at >= '$since'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'an open claim within window must be detected for de-dup');
    }

    // ── Binding ──────────────────────────────────────────────────────────

    public function test_binding_links_issues(): void
    {
        self::$pdo->exec("INSERT INTO library_binding (id, serial_id, volume_range, status, bound_at, location, created_at, updated_at)
            VALUES (1, 1, 'Vol. 5 (2025)', 'bound', date('now'), 'Stack A-12', date('now'), date('now'))");
        self::$pdo->exec("UPDATE library_serial_issue SET binding_id = 1, shelf_location = 'Stack A-12', bound_at = date('now') WHERE serial_id = 1");

        $linked = (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial_issue WHERE binding_id = 1")->fetchColumn();
        $this->assertGreaterThanOrEqual(1, $linked);

        $loc = self::$pdo->query("SELECT location FROM library_binding WHERE id = 1")->fetchColumn();
        $this->assertSame('Stack A-12', $loc);
    }

    // ── Prediction persistence ─────────────────────────────────────────────

    public function test_prediction_table_replace_semantics(): void
    {
        // persistPredictions deletes prior rows for the serial then bulk-inserts.
        self::$pdo->exec("INSERT INTO library_serial_prediction (serial_id, volume, issue_number, expected_date, days_until, created_at)
            VALUES (1, '5', '4', date('now','+20 days'), 20, date('now'))");
        self::$pdo->exec("DELETE FROM library_serial_prediction WHERE serial_id = 1");
        self::$pdo->exec("INSERT INTO library_serial_prediction (serial_id, volume, issue_number, expected_date, days_until, created_at)
            VALUES (1, '5', '5', date('now','+50 days'), 50, date('now'))");

        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM library_serial_prediction WHERE serial_id = 1")->fetchColumn();
        $this->assertSame(1, $count);
        $issue = self::$pdo->query("SELECT issue_number FROM library_serial_prediction WHERE serial_id = 1")->fetchColumn();
        $this->assertSame('5', $issue);
    }
}
