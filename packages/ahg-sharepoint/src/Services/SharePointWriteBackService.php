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
        if (isset($r['error']) && stripos((string) $r['error'], 'json') !== false) {
            $r = $x->extract($ioId, null);   // an unreadable model reply is usually a one-off: try once more
        }
        if (isset($r['error'])) {
            // Still file it from its name, marked Incomplete, so it shows in the
            // views as needing attention instead of sitting blank.
            $written = [];
            try {
                $written = $this->writeRecordColumns($ioId, [], [], false);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SharePoint records columns for '.$ioId.' failed: '.substr($e->getMessage(), 0, 300));
            }

            return ['result' => $r, 'written' => $written];
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

        $written = $this->writeFields($ioId, $r['fields'] ?? [], $matchText);
        try {
            $written += $this->writeRecordColumns($ioId, $r['fields'] ?? [], $m);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SharePoint records columns for '.$ioId.' failed: '.substr($e->getMessage(), 0, 300));
        }

        return ['result' => $r, 'written' => $written];
    }

    /**
     * Find the personal information in a captured document and, if any is
     * found, put the redacted copy in the library's Redacted copies folder -
     * what "Find personal information" does, run without a click.
     * ponytail: mirrors POST /privacy/redaction/{slug}/suggest-pii; fold that
     * route onto this method after the 12 Nov stand.
     *
     * @return array{findings:int, added:int, uploaded:?string}|null null when not a capture or the tools are missing
     */
    public function autoRedact(int $ioId): ?array
    {
        if (! $this->locate($ioId) || ! class_exists(\AhgPrivacy\Services\PiiRedactionSuggester::class)
            || ! class_exists(\AhgInformationObjectManage\Services\RedactionRenderService::class)) {
            return null;
        }
        $r = app(\AhgPrivacy\Services\PiiRedactionSuggester::class)->suggest($ioId, null);
        if (isset($r['error'])) {
            return ['findings' => 0, 'added' => 0, 'uploaded' => null, 'error' => (string) $r['error']];
        }
        $uploaded = null;
        if (($r['findings'] ?? 0) > 0 && ($out = app(\AhgInformationObjectManage\Services\RedactionRenderService::class)->render($ioId))) {
            $uploaded = $this->uploadRedacted($ioId, $out);
        }

        return ['findings' => (int) ($r['findings'] ?? 0), 'added' => (int) ($r['added'] ?? 0), 'uploaded' => $uploaded];
    }

    /**
     * Records metadata after a read: Record Type, Record Class,
     * Function/Directorate, Record Date, Status, and the File plan term
     * (Managed Metadata). Only the columns the item's library has are written,
     * so Records and Supplier invoices each get their own set.
     *
     * @return array<string,string> column written => value
     */
    public function writeRecordColumns(int $ioId, array $fields, array $match, bool $readOk = true): array
    {
        $at = $this->locate($ioId);
        if (! $at) {
            return [];
        }
        $title = (string) DB::table('information_object_i18n')->where('id', $ioId)->where('culture', 'en')->value('title');
        $type = SharePointRecordsClassifier::typeFor($fields['document_type'] ?? null, $title);
        if ($type === null) {
            return [];
        }
        $date = ! empty($fields['date']) && ($ts = strtotime((string) $fields['date'])) ? gmdate('Y-m-d\T00:00:00\Z', $ts) : null;
        $status = SharePointRecordsClassifier::status($readOk, $match['status'] ?? null, $type, ! empty($fields['po_number']));
        $values = SharePointRecordsClassifier::columnsFor($type, $date, $status);

        $base = "/sites/{$at['site']}/drives/{$at['drive']}";
        $list = $this->graph->get($at['tenant'], "{$base}/list?\$select=id")['id'];
        $cols = [];
        foreach ($this->graph->get($at['tenant'], "/sites/{$at['site']}/lists/{$list}/columns")['value'] ?? [] as $c) {
            $cols[self::norm((string) ($c['displayName'] ?? ''))] = $c;
        }
        $patch = $written = [];
        foreach ($values as $display => $v) {
            $col = $cols[self::norm($display)] ?? null;
            if ($col === null) {
                continue;
            }
            if (isset($col['term'])) {
                // Managed Metadata: Graph cannot set it directly; its hidden
                // note field ("File plan_0") takes "Label|TermGuid".
                $note = $cols[self::norm($display.'_0')] ?? null;
                $termId = $note ? $this->termId($at, $v) : null;
                if ($termId) {
                    $patch[$note['name']] = $v.'|'.$termId;
                    $written[$display] = $v;
                }

                continue;
            }
            if (isset($col['choice']) && ! in_array($v, $col['choice']['choices'] ?? [], true)) {
                continue;   // not one of the column's choices: leave it rather than fail the update
            }
            $patch[$col['name']] = $v;
            $written[$display] = $v;
        }
        if ($patch) {
            $listItem = $this->graph->get($at['tenant'], "{$base}/items/{$at['item']}/listItem?\$select=id")['id'];
            $this->graph->patch($at['tenant'], "/sites/{$at['site']}/lists/{$list}/items/{$listItem}/fields", $patch);
        }

        return $written;
    }

    /** The id of the file plan term labelled $label in the term set named by sharepoint_fileplan_set (cached a day). */
    private function termId(array $at, string $label): ?string
    {
        $setName = (string) (\AhgCore\Services\AhgSettingsService::get('sharepoint_fileplan_set') ?: 'Kopano file plan');
        $map = \Illuminate\Support\Facades\Cache::remember('sp_fileplan_terms_'.md5($at['site'].$setName), 86400, function () use ($at, $setName) {
            $base = "/sites/{$at['site']}/termStore";
            foreach ($this->graph->get($at['tenant'], "{$base}/groups")['value'] ?? [] as $g) {
                foreach ($this->graph->get($at['tenant'], "{$base}/groups/{$g['id']}/sets")['value'] ?? [] as $set) {
                    if (! collect($set['localizedNames'] ?? [])->contains('name', $setName)) {
                        continue;
                    }
                    $map = [];
                    $this->collectTerms($at, "{$base}/sets/{$set['id']}", "{$base}/sets/{$set['id']}/children", $map);

                    return $map;
                }
            }

            return [];
        });

        return $map[$label] ?? null;
    }

    /** Depth-first: every term under $childrenPath into $map (label => id). */
    private function collectTerms(array $at, string $setPath, string $childrenPath, array &$map): void
    {
        foreach ($this->graph->get($at['tenant'], $childrenPath)['value'] ?? [] as $term) {
            $map[$term['labels'][0]['name'] ?? ''] = $term['id'];
            if (! empty($term['childrenCount']) || ! array_key_exists('childrenCount', $term)) {
                $this->collectTerms($at, $setPath, "{$setPath}/terms/{$term['id']}/children", $map);
            }
        }
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
