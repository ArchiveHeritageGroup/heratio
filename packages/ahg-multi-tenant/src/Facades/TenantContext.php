<?php

/**
 * TenantContext facade - sugar over the `tenant.context` container binding.
 *
 *   use AhgMultiTenant\Facades\TenantContext;
 *
 *   $tenant   = TenantContext::current();
 *   $tenantId = TenantContext::currentId();
 *
 *   TenantContext::scope($otherId, function () {
 *       // code that runs under a different tenant for a moment
 *   });
 *
 * The underlying service is bound as a Laravel singleton by
 * AhgMultiTenantServiceProvider::register().
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 *
 * @method static \AhgMultiTenant\Models\Tenant|null current()
 * @method static int|null currentId()
 * @method static mixed scope(int $tenantId, callable $fn)
 * @method static void forget()
 * @method static int scopeDepth()
 */

namespace AhgMultiTenant\Facades;

use Illuminate\Support\Facades\Facade;

class TenantContext extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'tenant.context';
    }
}
