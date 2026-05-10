<?php

namespace AhgSharePoint\Console\Commands;

use AhgSharePoint\Repositories\SharePointTenantRepository;
use AhgSharePoint\Services\GraphClientService;
use Illuminate\Console\Command;

/**
 * Test Graph connectivity for a configured tenant.
 *
 * @phase 1
 */
class SharePointTestConnectionCommand extends Command
{
    protected $signature = 'sharepoint:test-connection {--tenant= : sharepoint_tenant.id}';
    protected $description = 'Test Microsoft Graph connectivity for a configured tenant';

    public function handle(SharePointTenantRepository $tenants, GraphClientService $graph): int
    {
        $tenantId = (int) $this->option('tenant');
        if ($tenantId <= 0) {
            $this->error('--tenant=<id> required');
            return self::INVALID;
        }

        $tenant = $tenants->find($tenantId);
        if ($tenant === null) {
            $this->error("Tenant {$tenantId} not found");
            return self::FAILURE;
        }
        $this->info("Testing tenant: {$tenant->name} ({$tenant->tenant_id})");

        try {
            $token = $graph->acquireToken($tenantId);
            $this->info('Token acquired (length ' . strlen($token) . ')');

            $sites = $graph->get($tenantId, '/sites?search=*&$top=5');
            $count = count($sites['value'] ?? []);
            $this->info("GET /sites returned {$count} site(s)");
            foreach (($sites['value'] ?? []) as $site) {
                $this->line(sprintf('  - %s (%s)', $site['displayName'] ?? '?', $site['webUrl'] ?? '?'));
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Connection OK');
        return self::SUCCESS;
    }
}
