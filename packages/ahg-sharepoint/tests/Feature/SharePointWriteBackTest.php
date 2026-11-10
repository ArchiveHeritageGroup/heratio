<?php

/**
 * SharePointWriteBackTest - a record captured from SharePoint has its extracted
 * fields written into the library's columns (found by display name, a currency
 * column as a number) and its redacted copy uploaded to "Redacted copies".
 * Microsoft Graph is faked.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use AhgSharePoint\Services\SharePointWriteBackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharePointWriteBackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fields_go_to_the_columns_and_the_redacted_copy_to_its_folder(): void
    {
        foreach (['sharepoint_tenant', 'sharepoint_drive', 'ingest_session', 'ingest_row'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("{$t} missing");
            }
        }
        $tenants = app(SharePointTenantRepository::class);
        $tenant = $tenants->create(['name' => 'test', 'tenant_id' => '11111111-1111-1111-1111-111111111111', 'client_id' => '22222222-2222-2222-2222-222222222222',
            'client_secret_ref' => 'pending', 'webhook_client_state' => 'x', 'status' => 'active']);
        $tenants->persistSecret($tenant, 'test-secret-value');
        $drive = (int) DB::table('sharepoint_drive')->insertGetId(['tenant_id' => $tenant, 'site_id' => 'SITE', 'site_url' => 'https://t.sharepoint.com/sites/demo',
            'drive_id' => 'DRIVE', 'drive_name' => 'Supplier invoices', 'ingest_enabled' => 1]);
        $io = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $session = (int) DB::table('ingest_session')->insertGetId(['user_id' => 1, 'title' => 'sp', 'source' => 'sharepoint_auto',
            'source_metadata' => json_encode(['sp_drive_pk' => $drive]), 'created_at' => now()]);
        DB::table('ingest_row')->insert(['session_id' => $session, 'row_number' => 1, 'title' => 'inv', 'data' => json_encode(['_sp_item_id' => 'ITEM']),
            'created_atom_id' => $io, 'created_at' => now()]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/drives/DRIVE/list*' => Http::response(['id' => 'LIST']),
            '*/lists/LIST/columns*' => Http::response(['value' => [
                ['displayName' => 'Invoice no', 'name' => 'Invoiceno'], ['displayName' => 'Total', 'name' => 'Total', 'currency' => ['locale' => 'en-ZA']],
                ['displayName' => 'PO match', 'name' => 'POmatch'], ['displayName' => 'Supplier', 'name' => 'Supplier'],
            ]]),
            '*/items/ITEM/listItem*' => Http::response(['id' => '7']),
            '*/lists/LIST/items/7/fields' => Http::response(['ok' => true]),
            '*/items/ITEM?*' => Http::response(['name' => 'INV-1.pdf']),
            '*/root:/*' => Http::response(['webUrl' => 'https://t.sharepoint.com/redacted.pdf']),
        ]);

        $wb = app(SharePointWriteBackService::class);
        $written = $wb->writeFields($io, ['number' => 'INV-1', 'total' => 'R 153,617.00', 'supplier' => 'Zz Ltd', 'po_number' => 'PO-1'], 'Matches PO-1');
        $this->assertSame(['Invoice no' => 'INV-1', 'Supplier' => 'Zz Ltd', 'Total' => 'R 153,617.00', 'PO match' => 'Matches PO-1'], $written, 'PO no is skipped: the library has no such column');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && str_ends_with($r->url(), '/lists/LIST/items/7/fields')
            && $r['Invoiceno'] === 'INV-1' && $r['Total'] === 153617.0 && $r['POmatch'] === 'Matches PO-1');

        $pdf = tempnam(sys_get_temp_dir(), 'red');
        file_put_contents($pdf, '%PDF-1.4 test');
        $this->assertSame('https://t.sharepoint.com/redacted.pdf', $wb->uploadRedacted($io, $pdf));
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_contains($r->url(), 'root:/Redacted%20copies/INV-1%20%28redacted%29.pdf:/content'));
        @unlink($pdf);

        $this->assertNull($wb->locate($io + 999999), 'a record not captured from SharePoint has no item');

        // A file captured by sync: the event links the record to its item.
        $io2 = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sharepoint_event')->insert(['subscription_id' => 0, 'drive_id' => $drive, 'sp_item_id' => 'ITEM2', 'change_type' => 'updated',
            'raw_payload' => '{}', 'status' => 'completed', 'information_object_id' => $io2, 'received_at' => now()]);
        $this->assertSame(['tenant' => $tenant, 'site' => 'SITE', 'drive' => 'DRIVE', 'item' => 'ITEM2'], $wb->locate($io2));
    }
}
