<?php

/**
 * SharePointWriteBackService - send what Heratio worked out about a document
 * back to the SharePoint item it came from (Eskom World Quality Day, 10 Nov
 * 2026): the extracted invoice / purchase-order fields into the library's
 * columns, and the redacted copy into a "Redacted copies" folder. The original
 * stays in Heratio and in SharePoint untouched.
 *
 * A record is linked to its SharePoint item by the ingest that created it:
 * ingest_row.created_atom_id = the record, ingest_row.data._sp_item_id = the
 * item, ingest_session.source_metadata.sp_drive_pk = the registered drive.
 * Columns are found by their display name, so the library can call them
 * "Invoice no" etc.; a column the library does not have is skipped.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSharePoint\Services;

use Illuminate\Support\Facades\DB;

class SharePointWriteBackService
{
    /** Library column display name => extracted field (see DocumentFieldExtractor). */
    public const COLUMNS = [
        'Invoice no' => 'number', 'Invoice date' => 'date', 'Supplier' => 'supplier',
        'VAT no' => 'supplier_vat', 'PO no' => 'po_number', 'Total' => 'total', 'PO match' => '_match',
    ];

    public const REDACTED_FOLDER = 'Redacted copies';

    public function __construct(private GraphClientService $graph)
    {
    }

    /** @return array{tenant:int, site:string, drive:string, item:string}|null */
    public function locate(int $ioId): ?array
    {
        // A file captured by sync or a webhook: its event names the drive, the
        // item and the record it created.
        $ev = DB::table('sharepoint_event as e')->join('sharepoint_drive as d', 'd.id', '=', 'e.drive_id')
            ->where('e.information_object_id', $ioId)->whereNotNull('e.sp_item_id')->orderByDesc('e.id')
            ->first(['d.tenant_id', 'd.site_id', 'd.drive_id', 'e.sp_item_id']);
        if ($ev) {
            return ['tenant' => (int) $ev->tenant_id, 'site' => $ev->site_id, 'drive' => $ev->drive_id, 'item' => (string) $ev->sp_item_id];
        }

        // A file captured by an auto-ingest rule: the ingest row holds the item.
        $row = DB::table('ingest_row as r')->join('ingest_session as s', 's.id', '=', 'r.session_id')
            ->where('r.created_atom_id', $ioId)->orderByDesc('r.id')->first(['r.data', 's.source_metadata']);
        if (! $row) {
            return null;
        }
        $item = json_decode((string) $row->data, true)['_sp_item_id'] ?? null;
        $drivePk = json_decode((string) $row->source_metadata, true)['sp_drive_pk'] ?? null;
        $drive = $drivePk ? DB::table('sharepoint_drive')->where('id', $drivePk)->first() : null;

        return $item && $drive ? ['tenant' => (int) $drive->tenant_id, 'site' => $drive->site_id, 'drive' => $drive->drive_id, 'item' => (string) $item] : null;
    }

    /**
     * Write the extracted fields (and the PO match, if any) into the item's columns.
     *
     * @param array<string,string> $fields extracted field => value
     * @return array<string,string> column written => value
     */
    /**
     * Read a captured document and write its fields, plus the purchase-order
     * match, back to its SharePoint columns - what "Read the document" does,
     * run without a click so a file dropped in the library fills its columns
     * by itself. Returns the reader's result and the columns written.
     * ponytail: mirrors the POST /admin/ai/document-fields/{slug} handler (a
     * locked path); fold that handler onto this method after the 12 Nov stand.
     */
    public function readAndWriteBack(int $ioId): array
    {
        if (! class_exists(\AhgAiServices\Services\DocumentFieldExtractor::class) || ! $this->locate($ioId)) {
            return ['result' => null, 'written' => []];
        }
        $x = app(\AhgAiServices\Services\DocumentFieldExtractor::class);
        $r = $x->extract($ioId, null);
        if (isset($r['error'])) {
            return ['result' => $r, 'written' => []];
        }
        $m = $x->match($ioId);
        $matchText = match ($m['status'] ?? null) {
            'matched' => 'Matches '.$m['po_number'].' ('.$m['invoice_total'].')',
            'totals_differ' => 'Differs from '.$m['po_number'].': invoice '.$m['invoice_total'].', order '.$m['po_total'],
            'po_not_found' => $m['po_number'].' not found',
            'delivered' => 'Delivered against '.$m['po_number'],
            'delivered_po_not_found' => 'Delivered against '.$m['po_number'].' (order not found)',
            default => null,
        };

        return ['result' => $r, 'written' => $this->writeFields($ioId, $r['fields'] ?? [], $matchText)];
    }

    public function writeFields(int $ioId, array $fields, ?string $matchText = null): array
    {
        $at = $this->locate($ioId);
        if (! $at) {
            return [];
        }
        $base = "/sites/{$at['site']}/drives/{$at['drive']}";
        $list = $this->graph->get($at['tenant'], "{$base}/list?\$select=id")['id'];
        $internal = [];
        foreach ($this->graph->get($at['tenant'], "/sites/{$at['site']}/lists/{$list}/columns")['value'] ?? [] as $c) {
            $internal[self::norm((string) ($c['displayName'] ?? ''))] = ['name' => $c['name'], 'currency' => isset($c['currency']) || isset($c['number'])];
        }
        $fields['_match'] = $matchText;
        $patch = $written = [];
        foreach (self::COLUMNS as $display => $field) {
            $col = $internal[self::norm($display)] ?? null;
            $v = $fields[$field] ?? null;
            if ($col === null || $v === null || $v === '') {
                continue;
            }
            // A currency or number column takes a number: "R 153,617.00" -> 153617.00.
            $patch[$col['name']] = $col['currency'] ? (float) preg_replace('/[^0-9.]/', '', str_replace(',', '', $v)) : $v;
            $written[$display] = (string) $v;
        }
        if ($patch) {
            $listItem = $this->graph->get($at['tenant'], "{$base}/items/{$at['item']}/listItem?\$select=id")['id'];
            $this->graph->patch($at['tenant'], "/sites/{$at['site']}/lists/{$list}/items/{$listItem}/fields", $patch);
        }

        return $written;
    }

    /** "Invoice  Date " and "invoice date" are the same column. */
    private static function norm(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    /** Upload the redacted copy beside the original, in the Redacted copies folder. Returns its web URL. */
    public function uploadRedacted(int $ioId, string $redactedPath): ?string
    {
        $at = $this->locate($ioId);
        if (! $at || ! is_file($redactedPath)) {
            return null;
        }
        $orig = $this->graph->get($at['tenant'], "/sites/{$at['site']}/drives/{$at['drive']}/items/{$at['item']}?\$select=name")['name'] ?? 'document.pdf';
        $name = preg_replace('/(\.[A-Za-z0-9]+)?$/', ' (redacted)$1', $orig, 1);
        $res = $this->graph->putContent($at['tenant'],
            "/sites/{$at['site']}/drives/{$at['drive']}/root:/".rawurlencode(self::REDACTED_FOLDER).'/'.rawurlencode($name).':/content',
            (string) file_get_contents($redactedPath), 'application/pdf');

        return $res['webUrl'] ?? null;
    }
}
