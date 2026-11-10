<?php

/**
 * DocumentFieldRulesTest - the rules that run before the model's answer is
 * kept: on the real (fictitious) stand samples, the page heading gives the
 * document type and the order number printed in the document is found, typed
 * or scanned. Guards the PO match against a prompt change that makes the model
 * drop po_number (10 Nov 2026, v1.164.86).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgAiServices\Services\DocumentFieldExtractor as X;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentFieldRulesTest extends TestCase
{
    public static function samples(): array
    {
        return [
            'invoice INV-2026-0412' => ['inv-2026-0412', 'INV-2026-0412', 'invoice', 'PO-77810'],
            'invoice INV-HC-88213' => ['inv-hc-88213', 'INV-HC-88213', 'invoice', 'PO-77826'],
            'invoice INV-HC-88247' => ['inv-hc-88247', 'INV-HC-88247', 'invoice', 'PO-77826'],
            'invoice INV-LS-2026-118' => ['inv-ls-2026-118', 'INV-LS-2026-118', 'invoice', 'PO-77841'],
            'invoice INV-LS-2026-131' => ['inv-ls-2026-131', 'INV-LS-2026-131', 'invoice', 'PO-77999'],
            'delivery note DN-55021' => ['dn-55021', 'DN-55021', 'delivery_note', 'PO-77826'],
            'delivery note DN-55021, OCR of the scan' => ['dn-55021-ocr', 'DN-55021', 'delivery_note', 'PO-77826'],
            'purchase order PO-77841' => ['po-77841', 'PO-77841', 'purchase_order', null],
        ];
    }

    #[DataProvider('samples')]
    public function test_type_and_order_number_come_from_the_text(string $fixture, string $number, string $type, ?string $po): void
    {
        $text = (string) file_get_contents(__DIR__.'/../fixtures/documents/'.$fixture.'.txt');
        // As if the model returned only the number, the way mistral:7b did.
        $f = X::applyRules(['number' => $number], $text);

        $this->assertSame($type, $f['document_type']);
        $this->assertSame($po, $f['po_number'] ?? null);
        $this->assertTrue($po === null || X::grounded($po, $text, false), 'the order number is in the document');
    }

    public function test_a_model_answer_is_kept_when_it_already_has_the_order(): void
    {
        $text = (string) file_get_contents(__DIR__.'/../fixtures/documents/inv-hc-88247.txt');
        $this->assertSame('PO-77826', X::applyRules(['number' => 'INV-HC-88247', 'po_number' => 'PO-77826'], $text)['po_number']);
    }
}
