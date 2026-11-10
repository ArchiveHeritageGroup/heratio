<?php

/**
 * sharepoint:tenant-add - register a Microsoft 365 tenant (app registration)
 * for SharePoint capture. The client secret is asked for with hidden input and
 * stored encrypted (Crypt) in ahg_settings, so it never appears in a chat,
 * the shell history or a log. The token request is tried at once.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use AhgSharePoint\Services\GraphClientService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SharePointTenantAddCommand extends Command
{
    protected $signature = 'sharepoint:tenant-add {--name= : A label, e.g. "AHG demo"} {--tenant-id= : Directory (tenant) ID} {--client-id= : Application (client) ID}';

    protected $description = 'Register a Microsoft 365 tenant for SharePoint capture (the client secret is asked for, hidden)';

    public function handle(SharePointTenantRepository $tenants, GraphClientService $graph): int
    {
        $guid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        $name = (string) ($this->option('name') ?: $this->ask('Label for this tenant', 'SharePoint'));
        $tenantId = (string) ($this->option('tenant-id') ?: $this->ask('Directory (tenant) ID'));
        $clientId = (string) ($this->option('client-id') ?: $this->ask('Application (client) ID'));
        if (! preg_match($guid, $tenantId) || ! preg_match($guid, $clientId)) {
            $this->error('The tenant ID and client ID are GUIDs (8-4-4-4-12 hex), as shown on the app registration\'s Overview page.');

            return self::FAILURE;
        }
        $secret = (string) $this->secret('Client secret VALUE (not the secret ID; input is hidden)');
        if (strlen($secret) < 10) {
            $this->error('No secret given.');

            return self::FAILURE;
        }

        // The same app registration again (a re-run, or one cut short before its
        // secret was stored): update that tenant instead of failing on the
        // unique tenant+client key.
        $existing = \Illuminate\Support\Facades\DB::table('sharepoint_tenant')
            ->where('tenant_id', strtolower($tenantId))->where('client_id', strtolower($clientId))->value('id');
        if ($existing) {
            $id = (int) $existing;
            $tenants->update($id, ['name' => $name, 'status' => 'active', 'last_error' => null]);
            $this->line("Tenant {$id} already registered for this app; updating it.");
        } else {
            $id = $tenants->create([
                'name' => $name, 'tenant_id' => strtolower($tenantId), 'client_id' => strtolower($clientId),
                'client_secret_ref' => 'pending', 'webhook_client_state' => Str::random(40), 'status' => 'active',
            ]);
        }
        $tenants->persistSecret($id, $secret);
        unset($secret);
        $this->info("Tenant {$id} saved; the secret is stored encrypted.");

        try {
            $graph->acquireToken($id);
            $this->info('Microsoft Graph token received: the app registration and secret work.');
        } catch (\Throwable $e) {
            $this->error('Saved, but the token request failed: '.substr($e->getMessage(), 0, 300));
            $this->line('Check the IDs, that the secret VALUE was given, and that admin consent was granted.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
