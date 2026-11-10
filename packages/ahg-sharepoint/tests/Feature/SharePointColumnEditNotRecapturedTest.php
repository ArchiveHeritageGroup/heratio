<?php

/**
 * SharePointColumnEditNotRecapturedTest - a file whose columns changed but
 * whose content did not (same cTag, new eTag) is not captured again; new
 * content (new cTag) is. On 10 Nov 2026 Heratio's write-back of the read
 * fields changed every eTag, and the next sync made a duplicate record of
 * each document. Graph is faked.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharePointColumnEditNotRecapturedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_column_edit_is_not_a_new_capture_but_new_content_is(): void
    {
        if (! Schema::hasTable('sharepoint_event') || ! Schema::hasTable('sharepoint_sync_state')) {
            $this->markTestSkipped('sharepoint tables missing');
        }
        Queue::fake();
        $repo = app(SharePointTenantRepository::class);
        $tenant = $repo->create(['name' => 't', 'tenant_id' => '99999999-9999-9999-9999-999999999999', 'client_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'client_secret_ref' => 'pending', 'webhook_client_state' => 'x', 'status' => 'active']);
        $repo->persistSecret($tenant, 'secret-value-123');
        $drive = (int) DB::table('sharepoint_drive')->insertGetId(['tenant_id' => $tenant, 'site_id' => 'SITE', 'site_url' => 'https://t.sharepoint.com/sites/demo',
            'drive_id' => 'DRIVE', 'drive_name' => 'Supplier invoices', 'ingest_enabled' => 1, 'auto_ingest_labels' => '["*"]']);
        // Captured earlier with content C1.
        DB::table('sharepoint_event')->insert(['subscription_id' => 0, 'drive_id' => $drive, 'sp_item_id' => 'ITEMA', 'sp_etag' => 'E1',
            'change_type' => 'updated', 'raw_payload' => json_encode(['source' => 'sync', 'item' => ['id' => 'ITEMA', 'cTag' => 'C1']]),
            'status' => 'completed', 'received_at' => now()]);

        $file = fn (string $id, string $etag, string $ctag) => ['id' => $id, 'name' => $id.'.pdf', 'eTag' => $etag, 'cTag' => $ctag,
            'file' => ['mimeType' => 'application/pdf'], 'parentReference' => ['path' => '/drives/DRIVE/root:']];
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/root/delta*' => Http::response(['value' => [
                $file('ITEMA', 'E2', 'C1'),  // columns written back: skip
                $file('ITEMB', 'E1', 'C9'),  // a new file: capture
            ], '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/sites/SITE/drives/DRIVE/root/delta?token=T']),
        ]);

        $this->artisan('sharepoint:sync', ['--drive' => $drive, '--full' => true])->assertSuccessful();

        $this->assertSame(1, DB::table('sharepoint_event')->where('drive_id', $drive)->where('sp_item_id', 'ITEMA')->count(), 'column edit not captured again');
        $this->assertSame(1, DB::table('sharepoint_event')->where('drive_id', $drive)->where('sp_item_id', 'ITEMB')->count(), 'new file captured');
    }
}
