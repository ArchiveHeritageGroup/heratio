<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * LibraryUsageServiceTest - COUNTER R5 usage service (heratio#1096).
 *
 * Boots a minimal Laravel container (Capsule + Facades) over an in-memory
 * SQLite database seeded from __fixtures__/schema.sql, then exercises the real
 * LibraryUsageService against it. Coverage:
 *   - recordAccess writes both the aggregate counter and the per-event log
 *   - recordAccess('search') is platform-level (no item id required)
 *   - countSearches (PR1 successful searches)
 *   - countUniqueItems (BR1/DR1 session-deduplicated unique items)
 *   - buildCounterReport envelope shape (PR/TR/IR)
 *   - getReportRows / getReportCsv column layout
 */

namespace AhgLibrary\Tests\Unit;

use AhgLibrary\Services\LibraryUsageService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

class LibraryUsageServiceTest extends AhgLibraryTestCase
{
    private LibraryUsageService $service;
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer();
        $this->service = new LibraryUsageService();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    /**
     * Stand up just enough Laravel plumbing for the service's DB::/Schema::/Log::
     * facade calls + now()/app() helpers to resolve against SQLite.
     */
    private function bootContainer(): void
    {
        // Container subclass that also answers getLocale()/runningInConsole(),
        // since the app() helper returns the container itself and the service
        // calls app()->getLocale() in the title-level report branches.
        $container = new class extends Container {
            public function getLocale(): string { return 'en'; }
            public function runningInConsole(): bool { return true; }
        };
        Container::setInstance($container);

        $capsule = new Capsule($container);
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $capsule->setEventDispatcher(new Dispatcher($container));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->capsule = $capsule;

        // Bind the bits the helpers/facades reach for.
        $container->instance('db', $capsule->getDatabaseManager());
        $container->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        $container->bind('log', fn () => new class {
            public function warning($m = null) {}
            public function error($m = null) {}
            public function info($m = null) {}
        });
        $container->instance('app', $container);

        Facade::setFacadeApplication($container);

        // Load the SQLite schema.
        $schema = $this->loadFixture('schema.sql');
        $capsule->getConnection()->getPdo()->exec($schema);
    }

    // ─── recordAccess writes both surfaces ──────────────────────────────────

    public function test_record_access_writes_aggregate_and_event_log(): void
    {
        $this->seedItem(10);
        $this->service->recordAccess(10, 'request', 'sess-A', 5);

        $agg = $this->capsule->table('library_usage_stats')
            ->where('library_item_id', 10)
            ->where('metric_type', LibraryUsageService::METRIC_TOTAL_REQUESTS)
            ->count();
        $this->assertSame(1, $agg, 'Aggregate counter row written');

        $log = $this->capsule->table('library_counter_log')
            ->where('resource_id', 10)
            ->where('event', 'request')
            ->first();
        $this->assertNotNull($log, 'Per-event log row written');
        $this->assertSame('item', $log->resource_type);
        $this->assertSame('Controlled', $log->access_type);
        // session_id is the sha256 of the supplied token, never the raw value.
        $this->assertNotSame('sess-A', $log->session_id);
        $this->assertSame(substr(hash('sha256', 'sess-A'), 0, 64), $log->session_id);
        $this->assertSame(5, (int) $log->user_id);
    }

    public function test_record_access_open_access_sets_oa_gold(): void
    {
        $this->seedItem(11);
        $this->service->recordAccess(11, 'open_access', 'sess-B');
        $log = $this->capsule->table('library_counter_log')->where('resource_id', 11)->first();
        $this->assertSame('OA_Gold', $log->access_type);
    }

    public function test_record_search_event_is_platform_level(): void
    {
        // No item id required for a search.
        $this->service->recordAccess(0, 'search', 'sess-C');
        $log = $this->capsule->table('library_counter_log')->where('event', 'search')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->resource_id, 'Search carries no item id');
        $this->assertSame('search', $log->resource_type);

        $agg = $this->capsule->table('library_usage_stats')
            ->where('metric_type', LibraryUsageService::METRIC_SEARCHES)
            ->first();
        $this->assertNotNull($agg);
        $this->assertNull($agg->library_item_id);
    }

    // ─── PR1 search count ───────────────────────────────────────────────────

    public function test_count_searches(): void
    {
        $this->service->recordAccess(0, 'search', 'sess-1');
        $this->service->recordAccess(0, 'search', 'sess-2');
        $this->service->recordAccess(0, 'search', 'sess-1');
        $today = date('Y-m-d');
        $this->assertSame(3, $this->service->countSearches($today, $today));
        // Out-of-range window returns zero.
        $this->assertSame(0, $this->service->countSearches('2000-01-01', '2000-01-02'));
    }

    // ─── BR1 / DR1 unique items (session-deduplicated) ──────────────────────

    public function test_count_unique_items_dedups_per_session_per_day(): void
    {
        $this->seedItem(20);
        $today = date('Y-m-d');
        // Same session, same item, same day, three requests = ONE unique.
        $this->service->recordAccess(20, 'request', 'sessX');
        $this->service->recordAccess(20, 'request', 'sessX');
        $this->service->recordAccess(20, 'request', 'sessX');
        // Different session, same item = a second unique.
        $this->service->recordAccess(20, 'request', 'sessY');

        $unique = $this->service->countUniqueItems($today, $today, 'request');
        $this->assertSame(2, $unique, 'Two distinct (session,item,day) tuples');

        // Total (non-unique) requests aggregate should be 4.
        $total = (int) $this->capsule->table('library_usage_stats')
            ->where('metric_type', LibraryUsageService::METRIC_TOTAL_REQUESTS)
            ->sum('count');
        $this->assertSame(4, $total);
    }

    public function test_count_unique_items_investigation_includes_all_item_events(): void
    {
        $this->seedItem(21);
        $today = date('Y-m-d');
        $this->service->recordAccess(21, 'investigation', 'sessZ');
        $this->service->recordAccess(21, 'request', 'sessZ');
        // Both events are same (session,item,day) so a single unique investigation.
        $this->assertSame(1, $this->service->countUniqueItems($today, $today, 'investigation'));
    }

    // ─── Report envelope ────────────────────────────────────────────────────

    public function test_build_counter_report_pr_envelope_includes_session_metrics(): void
    {
        $this->seedItem(30);
        $today = date('Y-m-d');
        $this->service->recordAccess(30, 'request', 'sess-pr');
        $this->service->recordAccess(0, 'search', 'sess-pr');

        $report = $this->service->buildCounterReport('PR', $today, $today);

        $this->assertSame('PR', $report['Report_ID']);
        $this->assertSame('5', $report['Release']);
        $this->assertArrayHasKey('Report_Items', $report);
        $this->assertArrayHasKey('Reporting_Period', $report);

        $metrics = array_column($report['Report_Items'], 'Count', 'Metric_Type');
        $this->assertArrayHasKey(LibraryUsageService::METRIC_UNIQUE_REQUESTS, $metrics);
        $this->assertArrayHasKey(LibraryUsageService::METRIC_SEARCHES, $metrics);
        $this->assertSame(1, $metrics[LibraryUsageService::METRIC_SEARCHES]);
        $this->assertSame(1, $metrics[LibraryUsageService::METRIC_UNIQUE_REQUESTS]);
    }

    public function test_build_counter_report_empty_yields_zero_rows(): void
    {
        $report = $this->service->buildCounterReport('PR', '2099-01-01', '2099-01-31');
        $this->assertNotEmpty($report['Report_Items']);
        // Every placeholder row has a zero count.
        foreach ($report['Report_Items'] as $item) {
            $this->assertSame(0, (int) $item['Count']);
        }
    }

    // ─── Export row layout ──────────────────────────────────────────────────

    public function test_get_report_rows_pr_header(): void
    {
        $today = date('Y-m-d');
        $rows = $this->service->getReportRows('PR', $today, $today);
        $this->assertSame(
            ['Report_ID', 'Report_Name', 'Created', 'Begin_Date', 'End_Date', 'Metric_Type', 'Count'],
            $rows[0]
        );
    }

    public function test_get_report_csv_is_tab_delimited(): void
    {
        $today = date('Y-m-d');
        $tsv = $this->service->getReportCsv('PR', $today, $today);
        $this->assertStringContainsString("\t", $tsv);
        $this->assertStringContainsString('Report_ID', $tsv);
    }

    public function test_get_report_rows_ir_has_item_id_column(): void
    {
        $today = date('Y-m-d');
        $rows = $this->service->getReportRows('IR', $today, $today);
        $this->assertContains('Item_ID', $rows[0]);
    }

    public function test_get_report_xlsx_produces_valid_workbook(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet not installed');
        }
        $today = date('Y-m-d');
        $binary = $this->service->getReportXlsx('PR', $today, $today);
        // XLSX is a ZIP archive - first bytes are the PK signature.
        $this->assertSame("PK", substr($binary, 0, 2), 'XLSX should be a ZIP (PK) container');
        $this->assertGreaterThan(200, strlen($binary));
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function seedItem(int $id): void
    {
        $this->capsule->table('information_object')->insert(['id' => $id, 'slug' => 'item-' . $id]);
        $this->capsule->table('information_object_i18n')->insert(['id' => $id, 'title' => 'Title ' . $id, 'culture' => 'en']);
        $this->capsule->table('library_item')->insert(['id' => $id, 'information_object_id' => $id, 'material_type' => 'monograph']);
    }
}
