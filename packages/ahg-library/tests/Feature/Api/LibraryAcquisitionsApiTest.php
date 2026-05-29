<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 *
 * LibraryAcquisitionsApiTest - acquisitions service + receive/write-off flows
 * (heratio#1091).
 *
 * Boots a minimal Laravel container (Capsule + Facades) over an in-memory
 * SQLite database with the acquisitions schema created inline, then exercises
 * the real LibraryAcquisitionService against it. The service is the engine
 * behind both the JSON:API receive() endpoint and the web acquisitions desk,
 * so testing it covers both surfaces' money math.
 *
 * Coverage:
 *   - full receive increases budget spent_amount
 *   - partial receipt sets line status 'partial' and spent reflects only
 *     received lines
 *   - cancelling an order releases its committed amount from the budget
 *   - write-off workflow (status -> cancelled, reason recorded, commitment freed)
 */

namespace AhgLibrary\Tests\Feature\Api;

use AhgLibrary\Services\LibraryAcquisitionService;
use AhgLibrary\Tests\AhgLibraryTestCase;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;

class LibraryAcquisitionsApiTest extends AhgLibraryTestCase
{
    private LibraryAcquisitionService $service;
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootContainer();
        $this->service = new LibraryAcquisitionService();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function bootContainer(): void
    {
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

        $container->instance('db', $capsule->getDatabaseManager());
        $container->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        $container->bind('log', fn () => new class {
            public function warning($m = null) {}
            public function error($m = null) {}
            public function info($m = null) {}
        });
        // auth() helper resolves the Auth\Factory contract; stub a guard whose
        // id() returns null so createBudget/writeOffOrder degrade gracefully.
        $authStub = new class implements \Illuminate\Contracts\Auth\Factory {
            public function id() { return null; }
            public function guard($name = null) { return $this; }
            public function shouldUse($name) {}
        };
        $container->instance(\Illuminate\Contracts\Auth\Factory::class, $authStub);
        $container->instance('auth', $authStub);
        $container->instance('app', $container);

        Facade::setFacadeApplication($container);

        // The service references the global \Schema facade alias (createCopiesForOrder).
        if (!class_exists('Schema')) {
            class_alias(\Illuminate\Support\Facades\Schema::class, 'Schema');
        }

        $this->createSchema();
    }

    private function createSchema(): void
    {
        $pdo = $this->capsule->getConnection()->getPdo();

        $pdo->exec("CREATE TABLE library_budget (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            budget_code TEXT, fund_name TEXT, fiscal_year TEXT,
            allocated_amount REAL DEFAULT 0, committed_amount REAL DEFAULT 0,
            spent_amount REAL DEFAULT 0, currency TEXT DEFAULT 'ZAR',
            category TEXT, department TEXT, notes TEXT, status TEXT,
            created_by INTEGER, created_at TEXT, updated_at TEXT
        )");

        $pdo->exec("CREATE TABLE library_order (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT, vendor_id INTEGER, vendor_reference TEXT, vendor_name TEXT,
            order_date TEXT, expected_date TEXT, received_date TEXT, status TEXT,
            order_type TEXT, budget_code TEXT, subtotal REAL DEFAULT 0, tax REAL DEFAULT 0,
            shipping REAL DEFAULT 0, total REAL DEFAULT 0, currency TEXT DEFAULT 'ZAR',
            invoice_number TEXT, invoice_date TEXT, payment_status TEXT, shipping_address TEXT,
            notes TEXT, approved_by INTEGER, approved_date TEXT, created_by INTEGER,
            written_off_reason TEXT, written_off_by TEXT, written_off_date TEXT,
            created_at TEXT, updated_at TEXT
        )");

        $pdo->exec("CREATE TABLE library_order_line (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER, library_item_id INTEGER, title TEXT, isbn TEXT, issn TEXT,
            author TEXT, publisher TEXT, pub_year INTEGER, edition TEXT, material_type TEXT,
            quantity INTEGER DEFAULT 1, unit_price REAL DEFAULT 0, discount_percent REAL DEFAULT 0,
            line_total REAL DEFAULT 0, quantity_received INTEGER DEFAULT 0, supplier_code TEXT,
            format TEXT, received_date TEXT, status TEXT, budget_code TEXT, fund_code TEXT,
            notes TEXT, created_at TEXT, updated_at TEXT
        )");

        // library_copy is optional; create it so createCopiesForOrder succeeds.
        $pdo->exec("CREATE TABLE library_copy (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT, isbn TEXT, author TEXT, publisher TEXT, pub_year INTEGER,
            format TEXT, barcode TEXT, order_id INTEGER, copy_number INTEGER,
            status TEXT, created_at TEXT, updated_at TEXT
        )");
    }

    /**
     * Seed a budget + an order with two lines (qty 2 @ 100, qty 1 @ 50).
     * Returns [budgetId, orderId, [lineId1, lineId2]].
     */
    private function seedOrder(): array
    {
        $budgetId = $this->service->createBudget([
            'budget_code'      => 'BUD-TEST',
            'fund_name'        => 'Test Fund',
            'fiscal_year'      => '2026',
            'allocated_amount' => 10000,
            'created_by'       => null,
        ]);

        $orderId = $this->service->createOrder([
            'order_number' => 'PO-TEST-1',
            'vendor_name'  => 'Acme Books',
            'budget_code'  => 'BUD-TEST',
            'status'       => 'ordered',
        ]);

        $l1 = $this->service->addLine($orderId, ['title' => 'Book A', 'quantity' => 2, 'unit_price' => 100]);
        $l2 = $this->service->addLine($orderId, ['title' => 'Book B', 'quantity' => 1, 'unit_price' => 50]);

        return [$budgetId, $orderId, [$l1, $l2]];
    }

    // ─── full receive increases spent ───────────────────────────────────────

    public function test_full_receive_increases_budget_spent(): void
    {
        [$budgetId, $orderId] = $this->seedOrder();

        $budget = $this->service->getBudget($budgetId);
        $this->assertSame(0.0, (float) $budget->spent_amount, 'No spend before receipt');
        $this->assertSame(250.0, (float) $budget->committed_amount, 'Committed = full order value');

        $this->service->receiveLines($orderId, [], true);
        $this->service->recalculateBudgetByCode('BUD-TEST');

        $budget = $this->service->getBudget($budgetId);
        $this->assertSame(250.0, (float) $budget->spent_amount, 'Spent = all received lines');

        $order = $this->service->getOrder($orderId);
        $this->assertSame('received', $order->status);
    }

    // ─── partial receipt ─────────────────────────────────────────────────────

    public function test_partial_receipt_sets_partial_status_and_spend(): void
    {
        [$budgetId, $orderId, $lines] = $this->seedOrder();

        // Receive only line 1 in full; leave line 2 untouched.
        $this->service->receiveLines($orderId, [$lines[0] => 2], false);
        $this->service->recalculateBudgetByCode('BUD-TEST');

        $l1 = $this->service->getLine($lines[0]);
        $l2 = $this->service->getLine($lines[1]);
        $this->assertSame('received', $l1->status);
        $this->assertSame('pending', $l2->status);

        $order = $this->service->getOrder($orderId);
        $this->assertSame('partial', $order->status, 'Order is partially received');

        $budget = $this->service->getBudget($budgetId);
        // Only line 1 (qty 2 @ 100 = 200) is received and thus spent.
        $this->assertSame(200.0, (float) $budget->spent_amount);
        $this->assertSame(250.0, (float) $budget->committed_amount, 'Commitment still full until cancel');
    }

    public function test_partial_quantity_marks_line_partial(): void
    {
        [, $orderId, $lines] = $this->seedOrder();

        // Receive 1 of the 2 units on line 1.
        $this->service->receiveLines($orderId, [$lines[0] => 1], false);

        $l1 = $this->service->getLine($lines[0]);
        $this->assertSame('partial', $l1->status, 'Receiving < quantity is partial');
        $this->assertSame(1, (int) $l1->quantity_received);
    }

    // ─── cancel releases commitment ──────────────────────────────────────────

    public function test_cancel_releases_committed_amount(): void
    {
        [$budgetId, $orderId] = $this->seedOrder();

        $budget = $this->service->getBudget($budgetId);
        $this->assertSame(250.0, (float) $budget->committed_amount);

        $this->service->transitionOrder($orderId, 'cancelled');

        $budget = $this->service->getBudget($budgetId);
        $this->assertSame(0.0, (float) $budget->committed_amount, 'Cancelled order frees commitment');
        $this->assertSame(0.0, (float) $budget->spent_amount);
    }

    // ─── write-off workflow ──────────────────────────────────────────────────

    public function test_write_off_records_reason_and_releases_commitment(): void
    {
        [$budgetId, $orderId] = $this->seedOrder();

        $ok = $this->service->writeOffOrder($orderId, 'damaged', 'tester', 'water damage');
        $this->assertTrue($ok);

        $order = $this->service->getOrder($orderId);
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('damaged', $order->written_off_reason);
        $this->assertSame('tester', $order->written_off_by);
        $this->assertNotNull($order->written_off_date);
        $this->assertStringContainsString('water damage', (string) $order->notes);

        $budget = $this->service->getBudget($budgetId);
        $this->assertSame(0.0, (float) $budget->committed_amount, 'Write-off frees commitment');
    }

    // ─── budget create/update persistence (column-name bug regression) ───────

    public function test_create_and_update_budget_persist_real_columns(): void
    {
        $id = $this->service->createBudget([
            'fund_name'        => 'Rare Books',
            'fiscal_year'      => '2026/27',
            'allocated_amount' => 5000,
            'currency'         => 'USD',
            'category'         => 'special',
        ]);

        $b = $this->service->getBudget($id);
        $this->assertSame('Rare Books', $b->fund_name);
        $this->assertSame('2026/27', $b->fiscal_year);
        $this->assertSame(5000.0, (float) $b->allocated_amount);
        $this->assertSame('USD', $b->currency);

        // Update via legacy aliases should still map onto real columns.
        $this->service->updateBudget($id, [
            'name'      => 'Rare Books Renamed',
            'allocated' => 7500,
        ]);
        $b = $this->service->getBudget($id);
        $this->assertSame('Rare Books Renamed', $b->fund_name);
        $this->assertSame(7500.0, (float) $b->allocated_amount);
    }
}
