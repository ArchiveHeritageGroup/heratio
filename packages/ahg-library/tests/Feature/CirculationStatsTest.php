<?php

/**
 * CirculationStatsTest - circulation statistics service, exports, screen
 * guards and the scheduled email (#1476).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\CronSchedulerService;
use AhgLibrary\Services\LibraryCirculationStatsService;
use AhgLibrary\Support\LibraryBranch;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class CirculationStatsTest extends LibraryFeatureTestCase
{
    protected array $migrationFiles = [
        '2026_06_02_000101_create_library_circulation_tables.php',
        '2026_06_02_000102_create_library_patron_category_table.php',
        '2026_06_02_000103_create_library_notice_tables.php',
        '2026_06_02_000104_create_library_ill_request_tables.php',
    ];

    /**
     * The branch migration reads information_schema, which SQLite lacks, so
     * the columns it adds are added directly here.
     */
    protected function runLibraryMigrations(): void
    {
        parent::runLibraryMigrations();

        Schema::table('library_loan_rule', fn (Blueprint $t) => $t->integer('branch_id')->default(0));
        foreach (['library_copy', 'library_patron', 'library_hold', 'library_checkout'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->integer('branch_id')->nullable());
        }
    }

    private LibraryCirculationStatsService $stats;

    protected function setUp(): void
    {
        parent::setUp();
        LibraryBranch::forgetSchemaCache();
        Carbon::setTestNow('2026-06-15 10:00:00');
        CarbonImmutable::setTestNow('2026-06-15 10:00:00');
        $this->stats = new LibraryCirculationStatsService();
        $this->seedLoans();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        LibraryBranch::forgetSchemaCache();
        parent::tearDown();
    }

    /**
     * Branch 10: copy 1 issued three times by patron 1 (one late, one renewed).
     * Branch 20: copy 2 issued once by patron 2, still out and overdue.
     * Copy 3 at branch 10 never issued; copy 4 withdrawn.
     */
    private function seedLoans(): void
    {
        foreach ([1 => '=SUM(A1)', 2 => 'Quiet Book'] as $id => $call) {
            DB::table('library_item')->insert(['id' => $id, 'information_object_id' => $id, 'material_type' => 'monograph', 'call_number' => $call]);
        }
        $copy = fn ($id, $item, $branch, $extra = []) => DB::table('library_copy')->insert($extra + [
            'id' => $id, 'library_item_id' => $item, 'barcode' => 'C' . $id, 'branch_id' => $branch,
            'status' => 'available', 'acquisition_date' => '2026-01-01',
        ]);
        $copy(1, 1, 10);
        $copy(2, 1, 20);
        $copy(3, 2, 10);
        $copy(4, 2, 10, ['status' => 'withdrawn', 'withdrawal_date' => '2026-02-01']);

        foreach ([1 => 10, 2 => 20, 3 => 10] as $id => $branch) {
            DB::table('library_patron')->insert([
                'id' => $id, 'card_number' => 'P' . $id, 'patron_type' => 'adult', 'branch_id' => $branch,
                'first_name' => 'Secret' . $id, 'last_name' => 'Person', 'membership_start' => '2026-01-01',
                'max_checkouts' => 5, 'max_renewals' => 2, 'max_holds' => 3, 'borrowing_status' => 'active', 'total_fines_owed' => 0,
            ]);
        }

        $loan = fn ($id, $copyId, $patron, $branch, $out, $due, $back, $renewed = 0) => DB::table('library_checkout')->insert([
            'id' => $id, 'copy_id' => $copyId, 'patron_id' => $patron, 'branch_id' => $branch,
            'checkout_date' => $out, 'due_date' => $due, 'return_date' => $back,
            'renewed_count' => $renewed, 'status' => $back ? 'returned' : 'active',
        ]);
        $loan(1, 1, 1, 10, '2026-03-02 09:00:00', '2026-03-16', '2026-03-10 09:00:00', 1);
        $loan(2, 1, 1, 10, '2026-03-20 09:00:00', '2026-03-27', '2026-04-02 09:00:00');
        $loan(3, 1, 1, 10, '2026-04-30 23:30:00', '2026-05-14', '2026-05-01 09:00:00');
        $loan(4, 2, 2, 20, '2026-04-05 09:00:00', '2026-04-19', null);
        $loan(5, 3, 3, 10, '2025-12-01 09:00:00', '2025-12-15', '2025-12-10 09:00:00');

        DB::table('library_hold')->insert([
            ['library_item_id' => 1, 'patron_id' => 2, 'branch_id' => 10, 'hold_date' => '2026-03-05 09:00:00', 'status' => 'cancelled', 'queue_position' => 1],
            ['library_item_id' => 1, 'patron_id' => 3, 'branch_id' => 20, 'hold_date' => '2026-03-06 09:00:00', 'status' => 'pending', 'queue_position' => 2],
        ]);
        DB::table('library_fine')->insert([
            ['patron_id' => 1, 'checkout_id' => 2, 'fine_type' => 'overdue', 'amount' => 10, 'paid_amount' => 4, 'currency' => 'ZAR', 'status' => 'outstanding', 'fine_date' => '2026-04-02'],
            ['patron_id' => 2, 'checkout_id' => 4, 'fine_type' => 'overdue', 'amount' => 3, 'paid_amount' => 0, 'currency' => 'EUR', 'status' => 'waived', 'fine_date' => '2026-04-20'],
            ['patron_id' => 3, 'checkout_id' => null, 'fine_type' => 'damage', 'amount' => 7, 'paid_amount' => 7, 'currency' => 'ZAR', 'status' => 'paid', 'fine_date' => '2026-04-21'],
        ]);
    }

    public function test_all_branch_summary(): void
    {
        $s = $this->stats->build('2026-03-01', '2026-04-30')['summary'];

        $this->assertSame(4, $s['issues'], 'the 23:30 loan on the last day counts');
        $this->assertSame(2, $s['returns'], 'the 1 May return is outside the period');
        $this->assertSame(1, $s['renewals']);
        $this->assertSame(25.0, $s['renewal_rate']);
        $this->assertSame(2, $s['late_loans'], 'one returned late, one still out past due');
        $this->assertSame(50.0, $s['overdue_rate']);
        $this->assertSame(2, $s['active_borrowers']);
        $this->assertSame(3, $s['circulating_copies'], 'the withdrawn copy is not stock');
        $this->assertSame(1.33, $s['turnover']);
        $this->assertSame(2, $s['holds_placed']);
        $this->assertSame(1, $s['holds_cancelled']);
    }

    public function test_branch_filter_limits_every_section(): void
    {
        $r = $this->stats->build('2026-03-01', '2026-04-30', [20]);

        $this->assertSame(1, $r['summary']['issues']);
        $this->assertSame(1, $r['summary']['circulating_copies']);
        $this->assertSame(1, $r['summary']['holds_placed']);
        $this->assertCount(1, $r['by_branch']);
        $this->assertSame(['EUR'], array_column($r['fines'], 'currency'));
        $this->assertSame(0, $r['dead_stock']['total']);
    }

    public function test_monthly_series_includes_empty_months(): void
    {
        $r = $this->stats->build('2026-02-01', '2026-04-30');

        $this->assertSame(['2026-02', '2026-03', '2026-04'], array_column($r['monthly'], 'month'));
        $this->assertSame([0, 2, 2], array_column($r['monthly'], 'issues'));
    }

    public function test_dead_stock_and_busiest_titles(): void
    {
        $r = $this->stats->build('2026-03-01', '2026-04-30');

        $this->assertSame(1, $r['dead_stock']['total']);
        $this->assertSame('C3', $r['dead_stock']['rows'][0]['barcode']);
        $this->assertSame('2025-12-01', $r['dead_stock']['rows'][0]['last_loan']);
        $this->assertSame(4, $r['top_titles'][0]['issues']);
    }

    public function test_patron_activity_bands(): void
    {
        $bands = array_column($this->stats->build('2026-03-01', '2026-04-30')['patron_activity'], 'patrons', 'band');

        $this->assertSame(['0' => 1, '1' => 1, '2 to 5' => 1, '6 to 10' => 0, '11+' => 0], $bands);
    }

    public function test_fines_are_never_summed_across_currencies(): void
    {
        $fines = collect($this->stats->build('2026-03-01', '2026-04-30')['fines'])->keyBy('currency');

        $this->assertSame('17.00', $fines['ZAR']['assessed']);
        $this->assertSame('11.00', $fines['ZAR']['paid']);
        $this->assertSame('6.00', $fines['ZAR']['outstanding']);
        $this->assertSame('3.00', $fines['EUR']['waived']);
    }

    public function test_holds_awaiting_collection_is_not_reported(): void
    {
        DB::table('library_hold')->insert(['library_item_id' => 1, 'patron_id' => 1, 'branch_id' => 10, 'hold_date' => '2026-03-07 09:00:00', 'status' => 'ready', 'queue_position' => 1]);

        $report = $this->stats->build('2026-03-01', '2026-04-30');

        $this->assertArrayNotHasKey('holds_ready', $report['summary']);
        $this->assertSame(3, $report['summary']['holds_placed']);
        $this->assertStringNotContainsString('awaiting collection', $this->stats->toCsv($report));
    }

    public function test_paid_and_waived_are_hidden_until_records_carry_them(): void
    {
        DB::table('library_fine')->update(['paid_amount' => 0, 'status' => 'outstanding']);

        $report = $this->stats->build('2026-03-01', '2026-04-30');
        $csv = $this->stats->toCsv($report);

        $this->assertFalse($report['fines_settled']);
        $this->assertStringContainsString('Currency,Assessed,Outstanding', $csv);
        $this->assertStringContainsString('does not yet record fine payments', $csv);
        $this->assertStringStartsWith('%PDF', $this->stats->toPdf($report));
    }

    public function test_paid_and_waived_show_when_records_carry_them(): void
    {
        $report = $this->stats->build('2026-03-01', '2026-04-30');

        $this->assertTrue($report['fines_settled']);
        $this->assertStringContainsString('Currency,Assessed,Paid,Waived,Outstanding', $this->stats->toCsv($report));
    }

    public function test_a_later_withdrawal_does_not_change_a_past_period(): void
    {
        DB::table('library_copy')->insert(['id' => 5, 'library_item_id' => 2, 'barcode' => 'C5', 'branch_id' => 10,
            'status' => 'withdrawn', 'acquisition_date' => '2026-01-01', 'withdrawal_date' => '2026-05-20']);

        $past = $this->stats->build('2026-03-01', '2026-04-30');
        $this->assertSame(4, $past['summary']['circulating_copies'], 'held until 20 May');
        $this->assertSame(2, $past['dead_stock']['total']);
        $this->assertNull($past['stock_note'], 'a dated withdrawal needs no caveat');

        $after = $this->stats->build('2026-05-21', '2026-05-31');
        $this->assertSame(3, $after['summary']['circulating_copies']);
    }

    public function test_an_undated_lost_copy_is_held_in_the_past_with_the_limit_stated(): void
    {
        DB::table('library_copy')->insert(['id' => 6, 'library_item_id' => 2, 'barcode' => 'C6', 'branch_id' => 10,
            'status' => 'lost', 'acquisition_date' => '2026-01-01']);

        $past = $this->stats->build('2026-03-01', '2026-04-30');
        $this->assertSame(4, $past['summary']['circulating_copies']);
        $this->assertNotNull($past['stock_note']);
        $this->assertStringContainsString('no recorded date', $this->stats->toCsv($past));

        $current = $this->stats->build('2026-06-01', '2026-06-30');
        $this->assertSame(3, $current['summary']['circulating_copies'], 'the period reaches today');
        $this->assertNull($current['stock_note']);
    }

    public function test_a_patron_expired_before_the_period_is_not_an_inactive_member(): void
    {
        $patron = ['card_number' => 'P9', 'patron_type' => 'adult', 'branch_id' => 10, 'first_name' => 'Secret9', 'last_name' => 'Person',
            'membership_start' => '2025-01-01', 'max_checkouts' => 5, 'max_renewals' => 2, 'max_holds' => 3, 'borrowing_status' => 'active', 'total_fines_owed' => 0];
        DB::table('library_patron')->insert(['id' => 9, 'membership_expiry' => '2026-02-28'] + $patron);
        DB::table('library_patron')->insert(['id' => 10, 'card_number' => 'P10', 'membership_expiry' => '2026-03-01'] + $patron);

        $bands = array_column($this->stats->build('2026-03-01', '2026-04-30')['patron_activity'], 'patrons', 'band');

        $this->assertSame(2, $bands['0'], 'patron 3, and patron 10 whose membership reached the first day');
    }

    public function test_csv_neutralises_formulas_and_names_no_patron(): void
    {
        $csv = $this->stats->toCsv($this->stats->build('2026-03-01', '2026-04-30'));

        $this->assertStringContainsString("'=SUM(A1)", $csv);
        $this->assertStringNotContainsString('Secret', $csv);
    }

    public function test_pdf_renders(): void
    {
        $pdf = $this->stats->toPdf($this->stats->build('2026-03-01', '2026-04-30'));

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_named_periods_are_the_last_complete_one(): void
    {
        $now = CarbonImmutable::parse('2026-05-31 12:00:00');

        $this->assertSame(['2026-04-01', '2026-04-30'], $this->stats->resolvePeriod('monthly', null, null, $now));
        $this->assertSame(['2026-01-01', '2026-03-31'], $this->stats->resolvePeriod('quarterly', null, null, $now));
        $this->assertSame(['2025-01-01', '2025-12-31'], $this->stats->resolvePeriod('yearly', null, null, $now));
    }

    public function test_routes_require_auth_and_update_permission(): void
    {
        foreach (['library.report-circulation', 'library.report-circulation-export'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('auth', $middleware);
            $this->assertContains('acl:update', $middleware);
        }
        $this->get('/library-manage/reports/circulation')->assertRedirect();
    }

    public function test_cron_entry_ships_disabled_and_is_not_natively_scheduled(): void
    {
        $row = collect((new CronSchedulerService())->getDefaultSchedules())->firstWhere('slug', 'library-circulation-report');

        $this->assertNotNull($row);
        $this->assertFalse($row['is_enabled']);

        $native = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains((string) $e->command, 'library-email-circulation-report'));
        $this->assertCount(0, $native);
    }

    private function settings(array $values): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS ahg_settings (id INTEGER PRIMARY KEY, setting_key TEXT UNIQUE, setting_value TEXT, setting_group TEXT)');
        foreach ($values as $key => $value) {
            DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value, 'setting_group' => 'library']);
        }
        (new \ReflectionClass(AhgSettingsService::class))->setStaticPropertyValue('cache', null);
    }

    public function test_email_is_off_by_default(): void
    {
        Event::fake([MessageSent::class]);
        $this->settings(['circulation_report_email_recipients' => 'stats@example.org']);

        $this->artisan('ahg:library-email-circulation-report')->assertSuccessful();

        Event::assertNotDispatched(MessageSent::class);
    }

    public function test_email_sends_once_per_period_with_both_attachments(): void
    {
        config()->set('mail.default', 'array');
        $this->settings([
            'circulation_report_email_enabled' => '1',
            'circulation_report_email_recipients' => 'stats@example.org, not-an-address',
        ]);
        $sent = [];
        Event::listen(MessageSent::class, function ($e) use (&$sent) {
            $sent[] = $e->message;
        });

        $this->artisan('ahg:library-email-circulation-report')->assertSuccessful();
        $this->artisan('ahg:library-email-circulation-report')->assertSuccessful();

        $this->assertCount(1, $sent, 'a period is not sent twice');
        $names = array_map(fn ($a) => $a->getFilename(), $sent[0]->getAttachments());
        $this->assertSame(['circulation-2026-05-01-2026-05-31.csv', 'circulation-2026-05-01-2026-05-31.pdf'], $names);
        $this->assertSame(['stats@example.org'], array_map(fn ($a) => $a->getAddress(), $sent[0]->getTo()));
    }

    public function test_quarterly_report_waits_for_a_new_quarter(): void
    {
        Event::fake([MessageSent::class]);
        $this->settings([
            'circulation_report_email_enabled' => '1',
            'circulation_report_email_recipients' => 'stats@example.org',
            'circulation_report_email_period' => 'quarterly',
        ]);

        $this->artisan('ahg:library-email-circulation-report')->assertSuccessful();

        Event::assertNotDispatched(MessageSent::class);
    }
}
