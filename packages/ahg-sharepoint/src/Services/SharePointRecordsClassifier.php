<?php

/**
 * SharePointRecordsClassifier - after a document is read, decide its records
 * metadata for the SharePoint library: Record Type, File plan entry (code from
 * the Kopano sample file plan), Record Class, Function/Directorate and Status.
 *
 * The reader only tells invoice / purchase order / delivery note apart; the
 * rest are recognised from the document title (the SAMPLE files name their
 * type). Status says whether the document checks out: Complete when it reads
 * cleanly and (for an invoice) matches its order; Under Review when totals
 * differ, the order is missing or not quoted; Incomplete when the read failed.
 *
 * ponytail: title keywords and a fixed type table, enough for the 12 Nov
 * stand. Upgrade path: take the type from the page classifier (#1584) and the
 * table from the file plan CSV's document_types column.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgSharePoint\Services;

class SharePointRecordsClassifier
{
    /** type => [Record Type, file plan code and title, Record Class, Function/Directorate, supplier document?] */
    public const TYPES = [
        'invoice' => ['Invoice', '4/3/1 Supplier invoices', 'SCM/Procurement', 'Finance', true],
        'credit_note' => ['Credit note', '4/3/2 Credit notes', 'SCM/Procurement', 'Finance', true],
        'statement' => ['Reconciliation', '4/3/3 Supplier statements', 'SCM/Procurement', 'Finance', true],
        'purchase_order' => ['Purchase order', '5/2/2 Purchase orders', 'SCM/Procurement', 'Finance', true],
        'quotation' => ['Quotation', '5/2/1 Quotations', 'SCM/Procurement', 'Finance', true],
        'delivery_note' => ['Delivery note', '5/3/1 Delivery notes', 'SCM/Procurement', 'Finance', true],
        'goods_received_note' => ['Goods received note', '5/3/2 Goods received notes', 'SCM/Procurement', 'Finance', true],
        'supplier_record' => ['Supplier record', '5/1 Supplier registration and vetting', 'SCM/Procurement', 'Finance', true],
        'bank_letter' => ['Supplier record', '4/2/1 Bank account changes and confirmations', 'SCM/Procurement', 'Finance', true],
        'claim' => ['Claim', '2/3 Leave overtime and travel claims', 'HR/Payroll', 'Corporate Services', false],
        'incident_report' => ['Incident report', '3/1 Incident reports', 'Health and Safety', 'Corporate Services', false],
        'register' => ['Register', '3/2 Site access and visitor registers', 'Health and Safety', 'Corporate Services', false],
        'site_access' => ['Register', '3/3 Contractor site access and induction', 'Health and Safety', 'Corporate Services', false],
    ];

    /** Title keyword => type, checked in order (first match wins). */
    private const TITLE = [
        // Personal-information documents first: their titles also mention
        // "bank details" or "ID", which would otherwise send them to banking.
        'overtime' => 'claim', 'travel claim' => 'claim', 'incident report' => 'incident_report',
        'visitor register' => 'register', 'site access' => 'site_access', 'induction' => 'site_access',
        'credit note' => 'credit_note', 'statement' => 'statement', 'quotation' => 'quotation',
        'goods received' => 'goods_received_note', 'grn-' => 'goods_received_note',
        'purchase order' => 'purchase_order', 'delivery note' => 'delivery_note',
        'bank confirmation' => 'bank_letter', 'bank details' => 'bank_letter',
        'supplier registration' => 'supplier_record', 'affidavit' => 'supplier_record',
        'invoice' => 'invoice',
    ];

    /** The type for a read document: its title first (more specific), then what the reader said. */
    public static function typeFor(?string $readerType, string $title): ?string
    {
        $t = mb_strtolower($title);
        foreach (self::TITLE as $needle => $type) {
            if (str_contains($t, $needle)) {
                return $type;
            }
        }

        return isset(self::TYPES[(string) $readerType]) ? (string) $readerType : null;
    }

    /** Status from the read and the order match. */
    public static function status(bool $readOk, ?string $matchStatus, ?string $type, bool $hasPo): string
    {
        if (! $readOk) {
            return 'Incomplete';
        }
        if ($type === 'invoice') {
            return ($matchStatus === 'matched') ? 'Complete' : 'Under Review';   // differs, order missing, or no order quoted
        }
        if ($type === 'delivery_note' && $hasPo && $matchStatus === 'delivered_po_not_found') {
            return 'Under Review';
        }

        return 'Complete';
    }

    /**
     * The column values for a type, by column display name.
     *
     * @return array<string,string>
     */
    public static function columnsFor(string $type, ?string $date, string $status): array
    {
        [$recordType, $fileplan, $class, $function] = self::TYPES[$type];

        return array_filter([
            'Record Type' => $recordType,
            'Record Class' => $class,
            'Function/Directorate' => $function,
            'Record Date' => $date,
            'Status' => $status,
            'File plan' => $fileplan,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Choices each choice column needs. $supplierOnly: just the supplier
     * document types (the Supplier invoices library).
     *
     * @return array<string, string[]>
     */
    public static function choicesNeeded(bool $supplierOnly = false): array
    {
        $rows = array_filter(self::TYPES, fn ($r) => ! $supplierOnly || $r[4]);
        $out = [
            'Record Type' => array_values(array_unique(array_column($rows, 0))),
            'Status' => ['Complete', 'Incomplete', 'Under Review'],
        ];
        if (! $supplierOnly) {
            $out['Record Class'] = array_values(array_unique(array_column($rows, 2)));
            $out['Function/Directorate'] = array_values(array_unique(array_column($rows, 3)));
        }
        $out['File plan'] = self::fileplanEntries($supplierOnly);

        return $out;
    }

    /**
     * The file plan entries ("5/2/2 Purchase orders") from the sample file plan,
     * in code order. $supplierOnly: series 4 and 5 (finance, supply chain).
     * ponytail: a Choice column stands in for Managed Metadata, which Graph
     * cannot write; move to the term set once SharePoint REST (certificate
     * app-only sign-in) is in place.
     *
     * @return string[]
     */
    public static function fileplanEntries(bool $supplierOnly = false): array
    {
        $path = base_path('packages/ahg-sharepoint/resources/fileplans/kopano-sample-file-plan.csv');
        if (! is_file($path) || ! ($fh = fopen($path, 'r'))) {
            return [];
        }
        fgetcsv($fh);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (! empty($r[0]) && (! $supplierOnly || in_array(explode('/', $r[0])[0], ['4', '5'], true))) {
                $rows[$r[0]] = $r[0].' '.$r[1];
            }
        }
        fclose($fh);
        uksort($rows, fn ($a, $b) => version_compare(str_replace('/', '.', $a), str_replace('/', '.', $b)));   // 4 < 4/1 < 4/2/1 < 5

        return array_values($rows);
    }
}
