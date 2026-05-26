<?php

/**
 * NullSmsGateway — dev/CI default. Logs the outbound SMS instead of
 * dispatching it to a real provider. Used when ahg_setting.sms_gateway
 * is 'null', unset, or the operator has not yet picked a driver.
 *
 * The log line carries the recipient + body verbatim so a developer can
 * verify the code locally without provisioning a paid SMS service.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Services;

class NullSmsGateway implements SmsGatewayInterface
{
    public function send(string $to, string $body): bool
    {
        \Log::info('sms.null.send', [
            'to' => $to,
            'body' => $body,
        ]);

        return true;
    }
}
