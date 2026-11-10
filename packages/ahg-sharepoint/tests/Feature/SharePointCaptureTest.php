<?php

/**
 * SharePointCaptureTest - a file synced from a SharePoint library becomes a
 * record under the drive's parent, through ahg-ingest's commit runner; the
 * event records the record, so the write-back can find its item again. A drive
 * that allows "*" captures files without a retention label. Graph is faked.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use AhgSharePoint\Services\SharePointIngestAdapter;
use AhgSharePoint\Services\SharePointWriteBackService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharePointCaptureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_synced_file_becomes_a_record_under_the_drive_parent(): void
    {
        if (! Schema::hasColumn('sharepoint_drive', 'auto_ingest_labels')) {
            $this->markTestSkipped('sharepoint tables missing');
        }
        $uploads = sys_get_temp_dir().'/sp-capture-'.uniqid();
        mkdir($uploads, 0777, true);
        config(['heratio.uploads_path' => $uploads]);
        $repo = app(SharePointTenantRepository::class);
        $tenant = $repo->create(['name' => 't', 'tenant_id' => '77777777-7777-7777-7777-777777777777', 'client_id' => '88888888-8888-8888-8888-888888888888',
            'client_secret_ref' => 'pending', 'webhook_client_state' => 'x', 'status' => 'active']);
        $repo->persistSecret($tenant, 'secret-value-123');

        $parent = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $parent, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $parent, 'culture' => 'en', 'title' => 'Capture parent']);

        $drive = (int) DB::table('sharepoint_drive')->insertGetId(['tenant_id' => $tenant, 'site_id' => 'SITE', 'site_url' => 'https://t.sharepoint.com/sites/demo',
            'drive_id' => 'DRIVE', 'drive_name' => 'Supplier invoices', 'ingest_enabled' => 1, 'auto_ingest_labels' => '["*"]',
            'default_parent_id' => $parent, 'default_parent_placement' => 'existing']);
        $event = (int) DB::table('sharepoint_event')->insertGetId(['subscription_id' => 0, 'drive_id' => $drive, 'sp_item_id' => 'ITEM9', 'sp_etag' => 'E1',
            'change_type' => 'updated', 'raw_payload' => '{}', 'status' => 'received', 'received_at' => now()]);

        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            '*/items/ITEM9/listItem*' => Http::response(['fields' => ['Title' => 'ZZ captured invoice']]),
            '*/items/ITEM9/content' => Http::response('%PDF-1.4 sample', 200, ['Content-Type' => 'application/pdf']),
            '*/items/ITEM9*' => Http::response(['id' => 'ITEM9', 'name' => 'INV-ZZ.pdf', 'eTag' => 'E1', 'file' => ['mimeType' => 'application/pdf'],
                'createdDateTime' => '2026-11-10T07:00:00Z', 'lastModifiedDateTime' => '2026-11-10T07:00:00Z']),
        ]);

        $status = app(SharePointIngestAdapter::class)->ingest($event);
        $ev = DB::table('sharepoint_event')->find($event);
        $this->assertSame('completed', $status, (string) $ev->last_error);
        $this->assertNotNull($ev->information_object_id);
        $this->assertSame($parent, (int) DB::table('information_object')->where('id', $ev->information_object_id)->value('parent_id'), 'under the drive parent');
        $this->assertSame('ITEM9', app(SharePointWriteBackService::class)->locate((int) $ev->information_object_id)['item']);
        $this->assertTrue(DB::table('digital_object')->where('object_id', $ev->information_object_id)->exists(), 'the PDF is attached');
        if (\Illuminate\Support\Facades\Schema::hasTable('information_object_title_sort')) {
            $this->assertTrue(DB::table('information_object_title_sort')->where('object_id', $ev->information_object_id)->exists(), 'listed in sorted browse at once');
        }
    }
}
