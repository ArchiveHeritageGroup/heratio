<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Write an audit-log entry for a successful mutation. Logging itself is
 * wrapped in try/catch — a logging failure must never break the mutation.
 */

namespace AhgRic\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditLog
{
    public static function record(Request $request, string $action, string $entityType, int $entityId, ?array $payload = null): void
    {
        try {
            DB::table('openric_audit_log')->insert([
                'action'       => $action,
                'entity_type'  => $entityType,
                'entity_id'    => $entityId,
                'api_key_id'   => $request->attributes->get('api_key_id'),
                'user_id'      => $request->attributes->get('api_user_id'),
                'requester_ip' => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 255),
                'payload_json' => $payload ? json_encode(self::redact($payload), JSON_UNESCAPED_UNICODE) : null,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[openric] audit-log insert failed: ' . $e->getMessage());
        }
    }

    /** Strip obviously-sensitive keys before persisting. */
    private static function redact(array $payload): array
    {
        $bad = ['password', 'api_key', 'x-api-key', 'token', 'secret'];
        foreach ($payload as $k => $v) {
            if (in_array(strtolower((string) $k), $bad, true)) {
                $payload[$k] = '[redacted]';
            }
        }
        return $payload;
    }
}
