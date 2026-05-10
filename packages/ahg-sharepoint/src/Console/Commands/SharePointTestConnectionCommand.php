<?php

namespace AhgSharePoint\Console\Commands;

use Illuminate\Console\Command;

class SharePointTestConnectionCommand extends Command
{
    protected $signature = 'sharepoint:test-connection {--tenant= : sharepoint_tenant.id}';
    protected $description = 'Test Microsoft Graph connectivity for a configured tenant';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        if ($tenantId <= 0) {
            $this->error('--tenant=<id> required');
            return self::INVALID;
        }

        // TODO (Phase 1):
        //   1. Resolve tenant via SharePointTenantRepository.
        //   2. GraphClientService::acquireToken().
        //   3. GET /sites?search=* (list 5).
        //   4. Print: tenant name, token expiry, sites returned.

        $this->error('sharepoint:test-connection not implemented yet');
        return self::FAILURE;
    }
}
