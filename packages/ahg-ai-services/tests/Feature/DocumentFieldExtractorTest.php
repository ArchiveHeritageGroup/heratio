<?php

/**
 * DocumentFieldExtractorTest - invoice and purchase-order fields read from a
 * record's PDF are kept only when they appear in the document; an invented
 * value is dropped; an invoice matches the purchase order it quotes when the
 * totals agree, and is flagged when they differ. The gateway is faked.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAiServices\Services\DocumentFieldExtractor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DocumentFieldExtractorTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_ai_suggestion') || ! Schema::hasTable('ahg_llm_config') || (new Process(['python3', '-c', 'import fitz']))->run() !== 0) {
            $this->markTestSkipped('ahg_ai_suggestion / ahg_llm_config / PyMuPDF missing.');
        }
        $this->dir = sys_get_temp_dir().'/docfields-'.uniqid();
        mkdir($this->dir.'/t', 0777, true);
        config(['heratio.uploads_path' => $this->dir]);
        DB::table('ahg_llm_config')->update(['is_default' => 0]);
        DB::table('ahg_llm_config')->insert(['provider' => 'ollama', 'name' => 'test', 'model' => 'test-model', 'endpoint_url' => 'https://gateway.test/ai/v1',
            'is_default' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        // setUp() may skip before $dir is set (PyMuPDF or a table missing).
        if (isset($this->dir)) {
            (new Process(['rm', '-rf', $this->dir]))->run();
        }
        parent::tearDown();
    }

    private function record(string $file, array $lines): int
    {
        $py = 'import fitz, sys, json; d = fitz.open(); p = d.new_page(); y = 72'."\n"
            .'for l in json.loads(sys.argv[2]):'."\n".'    p.insert_text((72, y), l, fontsize=11); y += 20'."\n".'d.save(sys.argv[1])';
        (new Process(['python3', '-c', $py, $this->dir.'/t/'.$file, json_encode($lines)]))->mustRun();
        $obj = fn (string $c) => (int) DB::table('object')->insertGetId(['class_name' => $c, 'created_at' => now(), 'updated_at' => now()]);
        $io = $obj('QubitInformationObject');
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('digital_object')->insert(['id' => $obj('QubitDigitalObject'), 'object_id' => $io, 'usage_id' => 140,
            'name' => $file, 'path' => '/uploads/r/t/', 'mime_type' => 'application/pdf']);

        return $io;
    }

    /** The gateway's answers, one per call, in order. */
    private function answer(array ...$answers): void
    {
        $seq = Http::sequence();
        foreach ($answers as $fields) {
            $json = json_encode($fields);
            $seq->push(['choices' => [['message' => ['content' => $json]]], 'message' => ['content' => $json],
                'response' => $json, 'model' => 'test-model', 'usage' => ['total_tokens' => 10]]);
        }
        Http::fake(['*' => $seq]);
    }

    public function test_fields_are_grounded_and_the_invoice_matches_its_order(): void
    {
        $x = app(DocumentFieldExtractor::class);
        $po = $this->record('po.pdf', ['PURCHASE ORDER PO-55501', 'Zz Valve Supplies (Pty) Ltd', 'Total R 1,150.00']);
        $inv = $this->record('inv.pdf', ['TAX INVOICE INV-9001', 'Zz Valve Supplies (Pty) Ltd', 'VAT 4999000222', 'Your order PO-55501', 'Total due R 1,150.00']);
        $dn = $this->record('dn.pdf', ['DELIVERY NOTE DN-1', 'Your order PO-55501', 'Delivered 420']);
        $this->answer(
            ['document_type' => 'purchase_order', 'number' => 'PO-55501', 'supplier' => 'Zz Valve Supplies (Pty) Ltd', 'total' => 'R 1,150.00'],
            ['document_type' => 'invoice', 'number' => 'INV-9001', 'supplier' => 'Zz Valve Supplies (Pty) Ltd', 'supplier_vat' => 'VAT 4999000222',
                'po_number' => 'PO-55501', 'total' => '1150', 'date' => '1 January 2026'],   // the date is not in the document
            ['document_type' => 'delivery_note', 'number' => 'DN-1', 'po_number' => 'PO-55501'],
        );
        $this->assertSame([], $x->extract($po)['dropped']);
        $r = $x->extract($inv);

        $this->assertSame(['date' => '1 January 2026'], $r['dropped'], 'an invented value is dropped');
        $this->assertSame('4999000222', $r['fields']['supplier_vat'], 'the label is not part of the VAT number');
        $this->assertSame('pending', DB::table('ahg_ai_suggestion')->where('object_id', $inv)->where('field_name', 'invoice.total')->value('status'));
        $this->assertSame('matched', $x->match($inv)['status']);

        // The order changes: the totals no longer agree.
        DB::table('ahg_ai_suggestion')->where('object_id', $po)->where('field_name', 'po.total')->update(['suggested_value' => 'R 1,200.00']);
        $this->assertSame('totals_differ', $x->match($inv)['status']);

        // A delivery note quotes the order but has no total: it is not compared on totals.
        $this->assertSame('delivery_note', $x->extract($dn)['fields']['document_type']);
        $this->assertSame('pending', DB::table('ahg_ai_suggestion')->where('object_id', $dn)->where('field_name', 'dn.number')->value('status'));
        $this->assertSame('delivered', $x->match($dn)['status']);
    }

    public function test_the_page_lists_the_fields_for_an_administrator(): void
    {
        $inv = $this->record('inv2.pdf', ['TAX INVOICE INV-9002', 'Total due R 99.00']);
        DB::table('slug')->insert(['object_id' => $inv, 'slug' => 'docfields-'.$inv]);
        DB::table('information_object_i18n')->insert(['id' => $inv, 'culture' => 'en', 'title' => 'Zz invoice record']);
        $this->answer(['document_type' => 'invoice', 'number' => 'INV-9002', 'total' => 'R 99.00']);
        $uid = (int) DB::table('acl_user_group')->where('group_id', 100)->value('user_id');
        $admin = \AhgCore\Models\User::findOrFail($uid);

        $this->actingAs($admin)->post(route('admin.ai.document-fields.extract', 'docfields-'.$inv))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->get(route('admin.ai.document-fields', 'docfields-'.$inv))->assertOk()->assertSee('INV-9002')->assertSee('R 99.00');
    }
}
