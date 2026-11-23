<?php

/**
 * Invoicing sub-ledger (heratio#1475 item 5): tax, matching, credit notes,
 * payments, aged creditors, budget match, export and access control.
 */

namespace AhgLibrary\Tests\Feature\Ledger;

use AhgLibrary\Services\Ledger\LedgerException;
use AhgLibrary\Services\Ledger\LedgerMoney;
use AhgLibrary\Services\Ledger\LedgerTaxProvider;
use AhgLibrary\Services\Ledger\LibraryLedgerService;
use AhgLibrary\Services\Ledger\TableLedgerTaxProvider;
use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\MintsApiAdmin;
use Tests\TestCase;

class LibraryLedgerTest extends TestCase
{
    use DatabaseTransactions;
    use MintsApiAdmin;

    private LibraryLedgerService $ledger;
    private int $vendor;
    private int $order;
    private int $lineA;
    private int $lineB;
    private string $budget;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2031-06-30 10:00:00');
        DB::table('library_settings')->where('setting_key', TableLedgerTaxProvider::ROUNDING_SETTING)->delete();
        DB::table('library_tax_rate')->delete();
        DB::unprepared((string) file_get_contents(base_path('packages/ahg-library/database/seed_ledger_dropdowns.sql')));

        $suffix = uniqid();
        $this->budget = 'T-' . $suffix;
        DB::table('library_budget')->insert([
            'budget_code' => $this->budget, 'fund_name' => 'Test fund', 'fiscal_year' => '2031',
            'allocated_amount' => 5000, 'currency' => 'EUR', 'status' => 'active',
        ]);
        $this->vendor = (int) DB::table('library_vendor')->insertGetId([
            'vendor_code' => 'V-' . $suffix, 'name' => '=Test Supplier', 'currency' => 'EUR', 'is_active' => 1,
        ]);
        $this->order = (int) DB::table('library_order')->insertGetId([
            'order_number' => 'PO-' . $suffix, 'vendor_id' => $this->vendor, 'vendor_name' => 'Test Supplier',
            'order_date' => '2031-01-01', 'status' => 'received', 'budget_code' => $this->budget, 'currency' => 'EUR',
        ]);
        $this->lineA = $this->orderLine('Book A', 2, '100.00', 2);
        $this->lineB = $this->orderLine('Book B', 3, '33.33', 3);
        $this->addRate('STD', '15', '2020-01-01');
        $this->ledger = app(LibraryLedgerService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function orderLine(string $title, int $qty, string $unit, int $received): int
    {
        return (int) DB::table('library_order_line')->insertGetId([
            'order_id' => $this->order, 'title' => $title, 'quantity' => $qty, 'unit_price' => $unit,
            'line_total' => LedgerMoney::fromCents($qty * LedgerMoney::toCents($unit)),
            'quantity_received' => $received, 'status' => 'received',
        ]);
    }

    private function addRate(string $code, string $rate, string $from, ?string $to = null): void
    {
        DB::table('library_tax_rate')->insert([
            'tax_code' => $code, 'label' => $code, 'rate_percent' => $rate, 'valid_from' => $from, 'valid_to' => $to, 'is_active' => 1,
        ]);
    }

    private function invoice(array $lines = null, string $ref = 'INV-1', string $date = '2031-03-01', ?string $due = '2031-03-31'): int
    {
        $lines ??= [['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 2, 'unit_price' => '100.00', 'tax_code' => 'STD']];

        return $this->ledger->saveInvoiceDraft([
            'vendor_id' => $this->vendor, 'order_id' => $this->order, 'supplier_reference' => $ref,
            'document_date' => $date, 'due_date' => $due, 'lines' => $lines,
        ], null, null);
    }

    private function doc(int $id): object
    {
        return DB::table('library_ledger_document')->where('id', $id)->first();
    }

    private function credit(int $invoiceId, int $qty, string $unit, string $ref = 'CN-1', string $date = '2031-04-01', string $reason = 'return'): int
    {
        $line = $this->ledger->getLines($invoiceId)[0];

        return $this->ledger->createCreditDraft($invoiceId, [
            'credit_reason' => $reason, 'supplier_reference' => $ref, 'document_date' => $date, 'notes' => 'Returned',
            'lines' => [['credited_line_id' => $line->id, 'quantity' => $qty, 'unit_price' => $unit]],
        ], null);
    }

    public function test_posting_calculates_tax_and_assigns_a_number(): void
    {
        $id = $this->invoice();
        $this->assertNull($this->doc($id)->document_number);
        $this->ledger->post($id, null, null);

        $d = $this->doc($id);
        $this->assertSame('posted', $d->status);
        $this->assertMatchesRegularExpression('/^SINV-2031-\d{6}$/', $d->document_number);
        $this->assertSame(['200.00', '30.00', '230.00'], [$d->subtotal, $d->tax_total, $d->total]);
    }

    public function test_rounding_rule_changes_the_tax(): void
    {
        $lines = array_fill(0, 3, ['net_cents' => 33, 'tax_code' => 'STD']);
        $provider = new TableLedgerTaxProvider();

        // 33 cents at 15% = 4.95, so 5 per line.
        $this->assertSame(15, array_sum(array_column($provider->calculate($lines, '2031-01-01'), 'tax_cents')));

        $this->ledger->setRounding('document');
        // 99 cents at 15% = 14.85, so 15 on the total, shared 5/5/5.
        $this->assertSame(15, array_sum(array_column($provider->calculate($lines, '2031-01-01'), 'tax_cents')));

        $lines = array_fill(0, 3, ['net_cents' => 10, 'tax_code' => 'STD']);
        // Each line is 1.5: line rounding gives 6, document rounding gives 30 * 15% = 4.5 -> 5.
        $this->assertSame(5, array_sum(array_column($provider->calculate($lines, '2031-01-01'), 'tax_cents')));
        $this->ledger->setRounding('line');
        $this->assertSame(6, array_sum(array_column($provider->calculate($lines, '2031-01-01'), 'tax_cents')));
    }

    public function test_the_rate_in_force_on_the_document_date_applies(): void
    {
        DB::table('library_tax_rate')->delete();
        $this->addRate('STD', '10', '2020-01-01', '2030-12-31');
        $this->addRate('STD', '20', '2031-01-01');

        $old = $this->invoice(null, 'OLD', '2030-06-01', null);
        $this->ledger->post($old, 'Dated before the order', null);
        $this->assertSame('20.00', $this->doc($old)->tax_total);

        $this->ledger->void($old, 'test', null);
        $new = $this->invoice(null, 'NEW');
        $this->ledger->post($new, null, null);
        $this->assertSame('40.00', $this->doc($new)->tax_total);
    }

    public function test_a_missing_rate_refuses_to_post(): void
    {
        DB::table('library_tax_rate')->delete();
        $id = $this->invoice();
        $this->expectException(LedgerException::class);
        $this->ledger->post($id, null, null);
    }

    public function test_billing_more_than_received_needs_a_reason(): void
    {
        DB::table('library_order_line')->where('id', $this->lineA)->update(['quantity_received' => 1]);
        $id = $this->invoice();

        try {
            $this->ledger->post($id, null, null);
            $this->fail('Expected a variance');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('Billed 2 but received 1', $e->variances[0]);
        }
        $this->assertSame('draft', $this->doc($id)->status);

        $this->ledger->post($id, 'Agreed with supplier', null);
        $this->assertSame('Agreed with supplier', $this->doc($id)->variance_reason);
    }

    public function test_price_variance_is_caught_but_cent_rounding_is_not(): void
    {
        $id = $this->invoice([['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 2, 'unit_price' => '120.00', 'tax_code' => 'STD']]);
        $this->assertNotEmpty($this->ledger->matchVariances($this->doc($id), $this->ledger->getLines($id)));

        DB::table('library_order_line')->where('id', $this->lineB)->update(['line_total' => '100.00']);
        $ok = $this->invoice([['order_line_id' => $this->lineB, 'description' => 'Book B', 'quantity' => 3, 'unit_price' => '33.33', 'tax_code' => 'STD']], 'INV-2');
        $this->assertSame([], $this->ledger->matchVariances($this->doc($ok), $this->ledger->getLines($ok)));
    }

    public function test_an_order_cancelled_after_capture_is_a_variance(): void
    {
        $id = $this->invoice();
        DB::table('library_order')->where('id', $this->order)->update(['status' => 'cancelled']);
        $this->expectException(LedgerException::class);
        $this->ledger->post($id, null, null);
    }

    public function test_a_second_invoice_cannot_claim_the_same_receipt(): void
    {
        $this->ledger->post($this->invoice(), null, null);
        $second = $this->invoice(null, 'INV-2');
        $this->expectException(LedgerException::class);
        $this->ledger->post($second, null, null);
    }

    public function test_duplicate_supplier_reference_is_refused_until_voided(): void
    {
        $id = $this->invoice(null, 'DUP 1');
        try {
            $this->invoice(null, 'dup1');
            $this->fail('Expected a duplicate refusal');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('already captured', $e->getMessage());
        }
        $this->ledger->void($id, 'Keyed wrongly', null);
        $this->assertGreaterThan(0, $this->invoice(null, 'dup1'));
    }

    public function test_credit_note_keeps_the_original_rate_after_a_rate_change(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        DB::table('library_tax_rate')->update(['valid_to' => '2031-03-31']);
        $this->addRate('STD', '25', '2031-04-01');

        $cn = $this->credit($inv, 1, '100.00');
        $this->ledger->post($cn, null, null);

        $d = $this->doc($cn);
        $this->assertMatchesRegularExpression('/^SCN-2031-\d{6}$/', $d->document_number);
        $this->assertSame(['100.00', '15.00', '115.00'], [$d->subtotal, $d->tax_total, $d->total]);
    }

    public function test_over_crediting_is_refused_and_a_full_credit_reverses_exactly(): void
    {
        $inv = $this->invoice([['order_line_id' => $this->lineB, 'description' => 'Book B', 'quantity' => 3, 'unit_price' => '33.33', 'tax_code' => 'STD']]);
        $this->ledger->post($inv, null, null);
        $this->assertSame('15.00', $this->doc($inv)->tax_total);

        $a = $this->credit($inv, 1, '33.33', 'CN-A');
        $this->ledger->post($a, null, null);
        $b = $this->credit($inv, 2, '33.33', 'CN-B');
        $this->ledger->post($b, null, null);

        $this->assertSame(0, $this->ledger->outstandingCents($this->doc($inv)));

        $c = $this->credit($inv, 1, '0.01', 'CN-C');
        $this->expectException(LedgerException::class);
        $this->ledger->post($c, null, null);
    }

    public function test_a_full_price_billing_correction_frees_the_quantity_for_rebilling(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $cn = $this->credit($inv, 1, '100.00', 'CN-1', '2031-04-01', 'billing_correction');
        $this->ledger->post($cn, null, null);

        $again = $this->invoice([['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 1, 'unit_price' => '100.00', 'tax_code' => 'STD']], 'INV-2');
        $this->assertSame([], $this->ledger->matchVariances($this->doc($again), $this->ledger->getLines($again)));
    }

    public function test_a_return_does_not_free_the_quantity_for_rebilling(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $cn = $this->credit($inv, 1, '100.00');
        $this->ledger->post($cn, null, null);

        $prefill = collect($this->ledger->orderLinesForInvoice($this->order))->firstWhere('id', $this->lineA);
        $this->assertSame(0, $prefill->to_bill);

        $again = $this->invoice([['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 1, 'unit_price' => '100.00', 'tax_code' => 'STD']], 'INV-2');
        $this->assertStringContainsString('Billed 3 but received 2', $this->ledger->matchVariances($this->doc($again), $this->ledger->getLines($again))[0]);
    }

    public function test_a_credit_needs_a_known_reason(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $this->expectException(LedgerException::class);
        $this->credit($inv, 1, '100.00', 'CN-1', '2031-04-01', 'not-a-reason');
    }

    public function test_payments_cannot_exceed_outstanding_and_can_be_reversed(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $pay = $this->ledger->recordPayment($inv, ['payment_date' => '2031-04-01', 'amount' => '200.00', 'reference' => 'EFT1'], null);
        $this->assertSame(3000, $this->ledger->outstandingCents($this->doc($inv)));

        try {
            $this->ledger->recordPayment($inv, ['payment_date' => '2031-04-02', 'amount' => '30.01', 'reference' => 'EFT2'], null);
            $this->fail('Expected refusal');
        } catch (LedgerException) {
            $this->assertTrue(true);
        }

        $this->ledger->reversePayment($inv, $pay, null);
        $this->assertSame(23000, $this->ledger->outstandingCents($this->doc($inv)));
    }

    public function test_aged_creditors_buckets_and_as_of_date(): void
    {
        $inv = $this->invoice(null, 'INV-1', '2031-03-01', '2031-03-31');
        $this->ledger->post($inv, null, null);
        $this->ledger->recordPayment($inv, ['payment_date' => '2031-06-15', 'amount' => '30.00', 'reference' => 'P'], null);

        $row = fn (string $asOf) => collect($this->ledger->agedCreditors($asOf))->firstWhere('vendor_id', $this->vendor);

        $this->assertSame(23000, $row('2031-03-31')['current']);
        $this->assertSame(23000, $row('2031-04-15')['d30']);
        $this->assertSame(23000, $row('2031-05-15')['d60']);
        $this->assertSame(20000, $row('2031-06-20')['d90']);
        $this->assertSame(20000, $row('2031-07-15')['over90']);
        $this->assertNull($row('2031-02-01'));
    }

    public function test_a_payment_reversed_later_still_counts_on_an_earlier_report(): void
    {
        Carbon::setTestNow('2031-03-02 09:00:00');
        $inv = $this->invoice(null, 'INV-1', '2031-03-01', '2031-03-31');
        $this->ledger->post($inv, null, null);
        Carbon::setTestNow('2031-03-10 09:00:00');
        $pay = $this->ledger->recordPayment($inv, ['payment_date' => '2031-03-10', 'amount' => '230.00', 'reference' => 'P'], null);
        Carbon::setTestNow('2031-05-05 09:00:00');
        $this->ledger->reversePayment($inv, $pay, null);

        $row = fn (string $asOf) => collect($this->ledger->agedCreditors($asOf))->firstWhere('vendor_id', $this->vendor);
        $this->assertNull($row('2031-04-30'), 'Paid in full at that date');
        $this->assertSame(23000, $row('2031-05-05')['total'], 'The reversal counts from the end of its own day');
    }

    public function test_a_document_voided_later_still_counts_on_an_earlier_report(): void
    {
        Carbon::setTestNow('2031-03-02 09:00:00');
        $inv = $this->invoice(null, 'INV-1', '2031-03-01', '2031-03-31');
        $this->ledger->post($inv, null, null);
        $cn = $this->credit($inv, 1, '100.00', 'CN-1', '2031-03-05');
        Carbon::setTestNow('2031-03-05 09:00:00');
        $this->ledger->post($cn, null, null);

        Carbon::setTestNow('2031-05-01 09:00:00');
        $this->ledger->void($cn, 'Keyed wrongly', null);
        $row = fn (string $asOf) => collect($this->ledger->agedCreditors($asOf))->firstWhere('vendor_id', $this->vendor);
        $this->assertSame(11500, $row('2031-04-01')['total'], 'The credit stood at that date');
        $this->assertSame(23000, $row('2031-05-01')['total'], 'The void counts from its own day');

        $this->ledger->void($inv, 'Keyed wrongly', null);
        $this->assertSame(11500, $row('2031-04-01')['total'], 'The invoice and credit both stood at that date');
        $this->assertNull($row('2031-05-01'));
    }

    public function test_the_credit_screen_records_and_shows_the_reason(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $line = $this->ledger->getLines($inv)[0];
        $this->actingAs(User::findOrFail($this->mintApiAdmin()->id));
        $this->get(route('library.ledger.credit', $inv))->assertOk()->assertSee('Billing correction (goods kept)');

        $payload = ['supplier_reference' => 'CN-HTTP', 'document_date' => '2031-04-01', 'notes' => 'Keyed twice',
            'lines' => [['credited_line_id' => $line->id, 'quantity' => 1, 'unit_price' => '100.00']]];
        $this->post(route('library.ledger.credit-store', $inv), $payload)->assertSessionHasErrors('credit_reason');

        $this->post(route('library.ledger.credit-store', $inv), $payload + ['credit_reason' => 'billing_correction'])->assertRedirect();
        $id = (int) DB::table('library_ledger_document')->where('supplier_reference', 'CN-HTTP')->value('id');
        $this->assertSame('billing_correction', DB::table('library_ledger_credit_reason')->where('document_id', $id)->value('reason_code'));
        $this->get(route('library.ledger.show', $id))->assertOk()->assertSee('Billing correction (goods kept)');
    }

    public function test_a_document_captured_and_voided_after_the_date_never_counts(): void
    {
        $inv = $this->invoice(null, 'INV-1', '2031-03-01', '2031-03-31');
        $this->ledger->post($inv, null, null);
        $this->ledger->void($inv, 'Keyed wrongly', null);
        $this->assertNull(collect($this->ledger->agedCreditors('2031-04-01'))->firstWhere('vendor_id', $this->vendor));
    }

    public function test_budget_match_keeps_each_fiscal_year_apart(): void
    {
        DB::table('library_budget')->insert([
            'budget_code' => $this->budget, 'fund_name' => 'Test fund', 'fiscal_year' => '2030',
            'allocated_amount' => 1000, 'currency' => 'USD', 'status' => 'active',
        ]);
        $this->ledger->post($this->invoice(), null, null);

        $oldOrder = (int) DB::table('library_order')->insertGetId([
            'order_number' => 'PO-OLD-' . uniqid(), 'vendor_id' => $this->vendor, 'vendor_name' => 'Test Supplier',
            'order_date' => '2030-05-01', 'status' => 'received', 'budget_code' => $this->budget, 'currency' => 'USD',
        ]);
        $oldLine = (int) DB::table('library_order_line')->insertGetId([
            'order_id' => $oldOrder, 'title' => 'Old', 'quantity' => 1, 'unit_price' => '40.00', 'line_total' => '40.00', 'quantity_received' => 1, 'status' => 'received',
        ]);
        DB::table('library_vendor')->where('id', $this->vendor)->update(['currency' => 'USD']);
        $old = $this->ledger->saveInvoiceDraft([
            'vendor_id' => $this->vendor, 'order_id' => $oldOrder, 'supplier_reference' => 'OLD-1', 'document_date' => '2030-05-02',
            'lines' => [['order_line_id' => $oldLine, 'description' => 'Old', 'quantity' => 1, 'unit_price' => '40.00', 'tax_code' => 'STD']],
        ], null, null);
        $this->ledger->post($old, null, null);

        $rows = collect($this->ledger->budgetMatch())->where('budget_code', $this->budget)->keyBy('fiscal_year');
        $this->assertSame(20000, $rows['2031']->invoiced);
        $this->assertSame(29999, $rows['2031']->committed);
        $this->assertSame(0, $rows['2031']->excluded_orders);
        $this->assertSame(4000, $rows['2030']->invoiced);
        $this->assertSame(4000, $rows['2030']->received);
        $this->assertSame(0, $rows['2030']->received_not_invoiced);
        $this->assertSame(0, $rows['2030']->excluded_orders);
    }

    public function test_budget_match_leaves_an_ambiguous_split_year_unplaced(): void
    {
        DB::table('library_budget')->where('budget_code', $this->budget)->update(['fiscal_year' => '2030/31']);
        DB::table('library_budget')->insert([
            'budget_code' => $this->budget, 'fund_name' => 'Test fund', 'fiscal_year' => '2031/32',
            'allocated_amount' => 1000, 'currency' => 'EUR', 'status' => 'active',
        ]);
        $this->ledger->post($this->invoice(), null, null);

        $rows = collect($this->ledger->budgetMatch())->where('budget_code', $this->budget)->values();
        $this->assertCount(2, $rows);
        foreach ($rows as $r) {
            $this->assertSame(0, $r->invoiced);
            $this->assertSame(1, $r->unattributed_orders);
        }
    }

    public function test_budget_match_counts_a_part_received_line_pro_rata(): void
    {
        DB::table('library_order_line')->where('id', $this->lineA)->update(['quantity' => 10, 'line_total' => '1000.00', 'quantity_received' => 5, 'status' => 'partial']);
        DB::table('library_order_line')->where('id', $this->lineB)->update(['quantity_received' => 0, 'status' => 'pending']);
        $inv = $this->invoice([['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 5, 'unit_price' => '100.00', 'tax_code' => 'STD']]);
        $this->ledger->post($inv, null, null);

        $row = collect($this->ledger->budgetMatch())->firstWhere('budget_code', $this->budget);
        $this->assertSame(50000, $row->received);
        $this->assertSame(50000, $row->invoiced);
        $this->assertSame(0, $row->received_not_invoiced);
    }

    public function test_a_row_with_figures_but_no_description_is_refused(): void
    {
        $this->actingAs(User::findOrFail($this->mintApiAdmin()->id));
        $this->post(route('library.ledger.store'), [
            'order_id' => $this->order, 'vendor_id' => $this->vendor, 'supplier_reference' => 'BLANK-1', 'document_date' => '2031-03-01',
            'lines' => [
                ['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 2, 'unit_price' => '100.00', 'tax_code' => 'STD'],
                ['order_line_id' => $this->lineB, 'description' => '', 'quantity' => 3, 'unit_price' => '33.33', 'tax_code' => 'STD'],
            ],
        ])->assertSessionHasErrors('lines.1.description');
        $this->assertFalse(DB::table('library_ledger_document')->where('supplier_reference', 'BLANK-1')->exists());
    }

    public function test_an_untitled_order_line_is_prefilled_with_a_description(): void
    {
        DB::table('library_order_line')->where('id', $this->lineA)->update(['title' => '', 'isbn' => null]);
        $this->actingAs(User::findOrFail($this->mintApiAdmin()->id));
        $this->get(route('library.ledger.create', ['order_id' => $this->order]))->assertOk()->assertSee('Order line ' . $this->lineA);
    }

    public function test_budget_match_reads_commitments_and_does_not_write_them(): void
    {
        DB::table('library_budget')->where('budget_code', $this->budget)->update(['committed_amount' => 300, 'spent_amount' => 250]);
        $other = 'S-' . uniqid();
        DB::table('library_budget')->insert(['budget_code' => $other, 'fund_name' => 'Split', 'fiscal_year' => '2031', 'allocated_amount' => 100, 'currency' => 'EUR', 'status' => 'active']);
        DB::table('library_order_line_fund')->insert([
            ['order_line_id' => $this->lineA, 'fund_code' => $this->budget, 'amount' => 150],
            ['order_line_id' => $this->lineA, 'fund_code' => $other, 'amount' => 50],
        ]);

        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $cn = $this->credit($inv, 1, '10.00');
        $this->ledger->post($cn, null, null);

        $rows = collect($this->ledger->budgetMatch())->keyBy('budget_code');
        // 200 net less a 10 credit = 190, split 75/25.
        $this->assertSame(14250, $rows[$this->budget]->invoiced);
        $this->assertSame(4750, $rows[$other]->invoiced);
        // Book A 150 of its split plus Book B 99.99, all received.
        $this->assertSame(24999, $rows[$this->budget]->received);
        $this->assertSame(24999 - 14250, $rows[$this->budget]->received_not_invoiced);
        $this->assertSame('300.00', DB::table('library_budget')->where('budget_code', $this->budget)->value('committed_amount'));
    }

    public function test_export_signs_credits_guards_formulas_and_blocks_void(): void
    {
        $inv = $this->invoice();
        $this->ledger->post($inv, null, null);
        $cn = $this->credit($inv, 1, '100.00');
        $this->ledger->post($cn, null, null);
        $this->invoice(null, 'DRAFT-NOT-EXPORTED');

        $batch = $this->ledger->createExportBatch();
        $this->assertNotNull($batch);
        $this->assertNull($this->ledger->createExportBatch());

        $rows = iterator_to_array($this->ledger->exportRows($batch), false);
        $this->assertCount(2, $rows);
        $header = array_flip(LibraryLedgerService::EXPORT_HEADER);
        $this->assertSame("'=Test Supplier", $rows[0][$header['supplier_name']]);
        $this->assertSame('200.00', $rows[0][$header['net_amount']]);
        $this->assertSame('-100.00', $rows[1][$header['net_amount']]);
        $this->assertSame('-115.00', $rows[1][$header['gross_amount']]);

        $this->expectException(LedgerException::class);
        $this->ledger->void($inv, 'too late', null);
    }

    public function test_a_market_module_can_replace_the_tax_provider(): void
    {
        $this->app->bind(LedgerTaxProvider::class, fn () => new class implements LedgerTaxProvider {
            public function key(): string { return 'market-x'; }
            public function calculate(array $lines, string $documentDate): array
            {
                return array_map(fn ($l) => ['rate' => 100000, 'tax_cents' => LedgerMoney::tax($l['net_cents'], 100000)], $lines);
            }
            public function nextNumber(string $docType, string $documentDate): string { return 'X-' . uniqid(); }
            public function requiredFields(string $docType): array { return ['due_date']; }
        });
        $ledger = app(LibraryLedgerService::class);

        $noDue = $ledger->saveInvoiceDraft(['vendor_id' => $this->vendor, 'order_id' => $this->order, 'supplier_reference' => 'M1', 'document_date' => '2031-03-01',
            'lines' => [['order_line_id' => $this->lineA, 'description' => 'A', 'quantity' => 2, 'unit_price' => '100.00', 'tax_code' => 'ANY']]], null, null);
        try {
            $ledger->post($noDue, null, null);
            $this->fail('Expected required field refusal');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('due_date', $e->getMessage());
        }

        DB::table('library_ledger_document')->where('id', $noDue)->update(['due_date' => '2031-04-01']);
        $ledger->post($noDue, null, null);
        $d = $this->doc($noDue);
        $this->assertSame(['market-x', '20.00'], [$d->tax_provider, $d->tax_total]);
    }

    public function test_money_helpers_are_exact(): void
    {
        $this->assertSame(1999, LedgerMoney::toCents('19.99'));
        $this->assertSame(1990, LedgerMoney::toCents('19.9'));
        $this->assertSame('-0.05', LedgerMoney::fromCents(-5));
        $this->assertSame(150000, LedgerMoney::rateToInt('15.0000'));
        $this->expectException(LedgerException::class);
        LedgerMoney::rateToInt('100.0001');
    }

    public function test_staff_screens_and_full_flow_over_http(): void
    {
        $admin = User::findOrFail($this->mintApiAdmin()->id);
        $this->actingAs($admin);

        $this->post(route('library.ledger.rate-store'), ['tax_code' => 'RED', 'label' => 'Reduced', 'rate_percent' => '5', 'valid_from' => '2030-01-01'])
            ->assertRedirect(route('library.ledger.rates'));
        $this->get(route('library.ledger.rates'))->assertOk()->assertSee('Reduced');

        $this->get(route('library.ledger.create', ['order_id' => $this->order]))->assertOk()->assertSee('Book A');
        $this->post(route('library.ledger.store'), [
            'order_id' => $this->order, 'vendor_id' => $this->vendor, 'supplier_reference' => 'HTTP-1',
            'document_date' => '2031-03-01', 'due_date' => '2031-03-31',
            'lines' => [
                ['order_line_id' => $this->lineA, 'description' => 'Book A', 'quantity' => 2, 'unit_price' => '100.00', 'tax_code' => 'STD'],
                ['order_line_id' => '', 'description' => '', 'quantity' => '', 'unit_price' => '', 'tax_code' => ''],
            ],
        ])->assertRedirect();

        $id = (int) DB::table('library_ledger_document')->where('supplier_reference', 'HTTP-1')->value('id');
        $this->assertCount(1, $this->ledger->getLines($id));
        $this->get(route('library.ledger.show', $id))->assertOk()->assertSee('HTTP-1');
        $this->get(route('library.ledger.edit', $id))->assertOk();
        $this->post(route('library.ledger.post', $id))->assertRedirect(route('library.ledger.show', $id));
        $this->assertSame('posted', $this->doc($id)->status);

        $this->get(route('library.ledger.credit', $id))->assertOk();
        $this->post(route('library.ledger.payment', $id), ['payment_date' => '2031-04-01', 'amount' => '10.00', 'reference' => 'R1'])->assertRedirect();
        foreach (['index', 'aged', 'budget-match', 'exports'] as $page) {
            $this->get(route('library.ledger.' . $page))->assertOk();
        }

        $this->post(route('library.ledger.export-create'))->assertRedirect();
        $batch = $this->doc($id)->export_batch;
        $csv = $this->get(route('library.ledger.export-download', $batch))->assertOk()->streamedContent();
        $this->assertStringContainsString('HTTP-1', $csv);
    }

    public function test_an_account_without_the_update_permission_is_refused(): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'ledger-' . $id, 'email' => uniqid('ledger-') . '@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        $this->actingAs(User::findOrFail($id));
        $this->get(route('library.ledger.index'))->assertForbidden();
        $this->get(route('library.ledger.aged'))->assertForbidden();
        $this->post(route('library.ledger.export-create'))->assertForbidden();
        $this->post(route('library.ledger.rate-store'), [])->assertStatus(403);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('library.ledger.index'))->assertRedirect();
    }
}
