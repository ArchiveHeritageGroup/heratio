<?php

/**
 * SharePointRemoveCaptureTest - a dropped document is removed from SharePoint
 * (file + redacted copy) and from Heratio (record, slug), by part of its name.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgSharePoint\Services\GraphClientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharePointRemoveCaptureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_removes_the_file_its_redacted_copy_and_the_record(): void
    {
        if (! Schema::hasTable('sharepoint_event')) {
            $this->markTestSkipped('sharepoint tables missing');
        }
        Http::fake();
        $tenant = (int) DB::table('sharepoint_tenant')->insertGetId(['name' => 'rm-test', 'tenant_id' => '77777777-7777-7777-7777-777777777777',
            'client_id' => '88888888-8888-8888-8888-888888888888', 'client_secret_ref' => 'pending', 'webhook_client_state' => 'x', 'status' => 'active']);
        $drive = (int) DB::table('sharepoint_drive')->insertGetId(array_intersect_key(['tenant_id' => $tenant, 'site_id' => 'site-x', 'drive_id' => 'drive-x', 'site_url' => 'https://example.test/sites/x', 'drive_name' => 'Supplier invoices', 'site_title' => 'X', 'ingest_enabled' => 0, 'created_at' => now(), 'updated_at' => now()], array_flip(Schema::getColumnListing('sharepoint_drive'))));
        $io = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'Visitor invoice ZZ-9911']);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'visitor-invoice-zz-9911-'.$io]);
        $cols = Schema::getColumnListing('sharepoint_event');
        $ev = array_intersect_key(['drive_id' => $drive, 'subscription_id' => 0, 'sp_item_id' => 'item-1', 'information_object_id' => $io, 'status' => 'completed',
            'raw_payload' => '{"item":{"name":"Scan ZZ-9911.pdf"}}', 'change_type' => 'created'], array_flip($cols));
        DB::table('sharepoint_event')->insert($ev);

        $fake = new class extends GraphClientService {
            public array $deleted = [];
            public function __construct() {}
            public function get(int $tenantId, string $path, array $headers = []): array
            {
                return str_contains($path, 'Redacted')
                    ? ['value' => [['id' => 'item-2', 'name' => 'Scan ZZ-9911 (redacted).pdf', 'file' => []]]]
                    : ['value' => [['id' => 'item-1', 'name' => 'Scan ZZ-9911.pdf', 'file' => []], ['id' => 'item-9', 'name' => 'Other.pdf', 'file' => []]]];
            }
            public function delete(int $tenantId, string $path, array $headers = []): void { $this->deleted[] = $path; }
        };
        $this->app->instance(GraphClientService::class, $fake);

        $this->artisan('sharepoint:remove-capture', ['name' => 'ZZ-9911', '--drive' => $drive, '--dry-run' => true])->assertSuccessful();
        $this->assertSame([], $fake->deleted, 'a dry run deletes nothing');
        $this->assertTrue(DB::table('information_object')->where('id', $io)->exists());

        $this->artisan('sharepoint:remove-capture', ['name' => 'ZZ-9911', '--drive' => $drive, '--force' => true])->assertSuccessful();
        $this->assertEqualsCanonicalizing(['/sites/site-x/drives/drive-x/items/item-1', '/sites/site-x/drives/drive-x/items/item-2'], $fake->deleted);
        $this->assertFalse(DB::table('information_object')->where('id', $io)->exists());
        $this->assertFalse(DB::table('slug')->where('object_id', $io)->exists());
        $this->assertSame('removed', DB::table('sharepoint_event')->where('sp_item_id', 'item-1')->value('status'));
    }
}
