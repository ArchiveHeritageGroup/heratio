<?php

/**
 * SharePointRecordsClassifierTest - each sample document gets the right Record
 * Type, file plan entry and Status (12 Nov 2026 stand: batch 2 and day pack).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgSharePoint\Services\SharePointRecordsClassifier as C;
use Tests\TestCase;

class SharePointRecordsClassifierTest extends TestCase
{
    public function test_sample_titles_get_type_and_file_plan(): void
    {
        $cases = [
            ['invoice', 'SAMPLE - Invoice INV-KI-5501 (Komati Instrumentation) - matches PO-77852', '4/3/1 Supplier invoices'],
            ['invoice', 'SAMPLE - Credit note CN-HC-0091 (Highveld Cable) - corrects INV-HC-88247', '4/3/2 Credit notes'],
            ['invoice', 'SAMPLE - Supplier statement (Highveld Cable) - November 2026', '4/3/3 Supplier statements'],
            ['purchase_order', 'SAMPLE - Purchase order PO-77852 (Komati Instrumentation)', '5/2/2 Purchase orders'],
            ['invoice', 'SAMPLE - Quotation Q-2026-441 (Nkangala Industrial Cleaning)', '5/2/1 Quotations'],
            ['delivery_note', 'SAMPLE - Delivery note DN-60114 (Komati Instrumentation) - part delivery', '5/3/1 Delivery notes'],
            ['invoice', 'SAMPLE - Goods received note GRN-40877 (Lowveld Safety and PPE)', '5/3/2 Goods received notes'],
            ['invoice', 'SAMPLE - Bank confirmation letter (Lowveld Safety and PPE) - matches registration', '4/2/1 Bank account changes and confirmations'],
            ['invoice', 'SAMPLE - Sworn affidavit, B-BBEE status (supplier director) - ID number', '5/1 Supplier registration and vetting'],
            ['invoice', 'SAMPLE - Overtime and travel claim (employee) - ID and bank details', '2/3 Leave overtime and travel claims'],
            ['invoice', 'SAMPLE - Incident report IR-2026-077 - injury and medical information', '3/1 Incident reports'],
            ['invoice', 'SAMPLE - Visitor register, Gate 2, 10 November 2026 - personal information - SCANNED', '3/2 Site access and visitor registers'],
        ];
        foreach ($cases as [$reader, $title, $fileplan]) {
            $type = C::typeFor($reader, $title);
            $this->assertNotNull($type, $title);
            $this->assertSame($fileplan, C::columnsFor($type, null, 'Complete')['File plan'], $title);
        }
        $this->assertSame('invoice', C::typeFor('invoice', 'Scan 0042'));    // no keyword: the reader decides
    }

    public function test_status_flags_invoices_that_do_not_check_out(): void
    {
        $this->assertSame('Complete', C::status(true, 'matched', 'invoice', true));
        $this->assertSame('Under Review', C::status(true, 'totals_differ', 'invoice', true));
        $this->assertSame('Under Review', C::status(true, 'po_not_found', 'invoice', true));
        $this->assertSame('Under Review', C::status(true, 'no_po_number', 'invoice', false));
        $this->assertSame('Incomplete', C::status(false, null, 'invoice', false));
        $this->assertSame('Complete', C::status(true, null, 'purchase_order', false));
    }

    public function test_supplier_library_gets_only_supplier_choices(): void
    {
        $s = C::choicesNeeded(true);
        $this->assertArrayNotHasKey('Record Class', $s);
        $this->assertContains('Invoice', $s['Record Type']);
        $this->assertContains('Incident report', $s['Record Type']);   // personal-information samples land there too
        $this->assertNotContains('1/1 Policies and procedures', $s['File plan']);
        $this->assertContains('Health and Safety', C::choicesNeeded()['Record Class']);
    }
}
