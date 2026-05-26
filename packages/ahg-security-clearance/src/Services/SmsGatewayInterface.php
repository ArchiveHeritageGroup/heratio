<?php

/**
 * SmsGatewayInterface — pluggable SMS sender for OTP MFA (issue #722).
 *
 * Concrete implementations:
 *   - NullSmsGateway: logs to laravel.log only (development default).
 *   - HttpSmsGateway: POSTs to a configurable HTTP endpoint with `to` +
 *     `body` form params. Suitable for any HTTP-based SMS provider
 *     (Clickatell, BulkSMS, in-house gateway, etc.).
 *
 * The active driver is selected via the `sms_gateway` ahg_setting key
 * ('null' | 'http'). Phase 2 follow-up: per-provider drivers (Twilio,
 * Vonage, Clickatell native libraries).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Services;

interface SmsGatewayInterface
{
    /**
     * Send an SMS to a destination phone number (E.164 preferred).
     *
     * Implementations should return false on transport failure so the
     * controller surfaces the operator-side issue rather than silently
     * pretending the code went out.
     */
    public function send(string $to, string $body): bool;
}
