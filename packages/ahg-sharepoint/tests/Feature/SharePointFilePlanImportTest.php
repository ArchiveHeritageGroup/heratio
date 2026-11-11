<?php

/**
 * SharePointFilePlanImportTest - the file plan becomes a nested term set:
 * group and set created once, terms nested by code, re-runs reuse terms.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgSharePoint\Services\GraphClientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SharePointFilePlanImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_file_plan_becomes_a_nested_term_set_and_reruns_reuse_terms(): void
    {
        if (! Schema::hasTable('sharepoint_drive')) {
            $this->markTestSkipped('sharepoint tables missing');
        }
        $driveId = $this->makeDrive();
        $csv = tempnam(sys_get_temp_dir(), 'fp');
        file_put_contents($csv, "code,title,retention_period,disposal_action,document_types\n5,Supply chain,,,\n5/2,Procurement,,,\n5/2/2,Purchase orders,5 years,Destroy,purchase_order\n");

        $fake = new class extends GraphClientService {
            public array $posts = [];
            public array $children = [];
            private int $n = 0;
            public function __construct() {}
            public function get(int $tenantId, string $path, array $headers = []): array
            {
                if (str_ends_with($path, '/termStore')) {
                    return ['id' => 'store', 'defaultLanguageTag' => 'en-US'];
                }
                if (str_ends_with($path, '/termStore/groups')) {
                    return ['value' => array_values(array_filter($this->posts, fn ($p) => str_ends_with($p['path'], '/groups')))
                        ? [['id' => 'g1', 'displayName' => 'Heratio', 'scope' => 'siteCollection']] : []];
                }
                if (str_ends_with($path, '/sets')) {
                    return ['value' => array_values(array_filter($this->posts, fn ($p) => str_ends_with($p['path'], '/termStore/sets')))
                        ? [['id' => 's1', 'localizedNames' => [['name' => 'Kopano file plan']]]] : []];
                }

                return ['value' => $this->children[$path] ?? []];
            }
            public function post(int $tenantId, string $path, array $body, array $headers = []): array
            {
                $this->posts[] = ['path' => $path, 'body' => $body];
                if (str_ends_with($path, '/groups')) {
                    return ['id' => 'g1'];
                }
                if (str_ends_with($path, '/termStore/sets')) {
                    return ['id' => 's1'];
                }
                $id = 't'.(++$this->n);
                $this->children[$path][] = ['id' => $id, 'labels' => $body['labels']];

                return ['id' => $id];
            }
        };
        $this->app->instance(GraphClientService::class, $fake);

        $this->artisan('sharepoint:fileplan-import', ['csv' => $csv, '--drive' => $driveId])->assertSuccessful();
        $terms = array_values(array_filter($fake->posts, fn ($p) => str_ends_with($p['path'], '/children')));
        $this->assertCount(3, $terms);
        $this->assertSame('5 Supply chain', $terms[0]['body']['labels'][0]['name']);
        $this->assertStringEndsWith('/sets/s1/children', $terms[0]['path']);
        $this->assertStringEndsWith('/terms/t1/children', $terms[1]['path']);   // 5/2 under 5
        $this->assertStringEndsWith('/terms/t2/children', $terms[2]['path']);   // 5/2/2 under 5/2
        $this->assertContains(['key' => 'retention', 'value' => '5 years'], $terms[2]['body']['properties']);

        $before = count($fake->posts);
        $this->artisan('sharepoint:fileplan-import', ['csv' => $csv, '--drive' => $driveId])->assertSuccessful();
        $this->assertSame($before, count($fake->posts), 'a re-run must not create anything again');
        @unlink($csv);
    }

    private function makeDrive(): int
    {
        $tenant = (int) DB::table('sharepoint_tenant')->insertGetId(['name' => 'fp-test', 'tenant_id' => '55555555-5555-5555-5555-555555555555',
            'client_id' => '66666666-6666-6666-6666-666666666666', 'client_secret_ref' => 'pending', 'webhook_client_state' => 'x', 'status' => 'active']);
        $cols = Schema::getColumnListing('sharepoint_drive');
        $row = ['tenant_id' => $tenant, 'site_id' => 'site-x', 'drive_id' => 'drive-x'];
        foreach (['site_url' => 'https://example.test/sites/x', 'drive_name' => 'Records', 'site_title' => 'X', 'ingest_enabled' => 0, 'created_at' => now(), 'updated_at' => now()] as $k => $v) {
            if (in_array($k, $cols, true)) {
                $row[$k] = $v;
            }
        }

        return (int) DB::table('sharepoint_drive')->insertGetId($row);
    }
}
