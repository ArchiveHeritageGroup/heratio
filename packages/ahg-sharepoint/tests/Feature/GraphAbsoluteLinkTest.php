<?php

/**
 * GraphAbsoluteLinkTest - a delta or next link Graph hands back is an absolute
 * URL and is requested as it is, not glued onto the API base (10 Nov 2026:
 * every follow-up sync failed with 400 "Url specified is invalid").
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use AhgSharePoint\Services\GraphClientService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GraphAbsoluteLinkTest extends TestCase
{
    use DatabaseTransactions;

    public function test_an_absolute_link_is_requested_verbatim_and_a_path_gets_the_base(): void
    {
        if (! Schema::hasTable('sharepoint_tenant')) {
            $this->markTestSkipped('sharepoint_tenant missing');
        }
        $repo = app(SharePointTenantRepository::class);
        $t = $repo->create(['name' => 't', 'tenant_id' => '55555555-5555-5555-5555-555555555555', 'client_id' => '66666666-6666-6666-6666-666666666666',
            'client_secret_ref' => 'pending', 'webhook_client_state' => 'x', 'status' => 'active']);
        $repo->persistSecret($t, 'secret-value-123');
        Http::fake(['login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]), '*' => Http::response(['value' => []])]);

        $delta = 'https://graph.microsoft.com/v1.0/sites/s/drives/d/root/delta?token=NDslMjM0';
        app(GraphClientService::class)->get($t, $delta);
        app(GraphClientService::class)->get($t, '/sites/s/drives/d/root/delta');

        Http::assertSent(fn (Request $r) => $r->url() === $delta);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.microsoft.com/v1.0/sites/s/drives/d/root/delta');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'v1.0/https'));
    }
}
