<?php

/**
 * WritesDisposalAuditLog - shared ahg_audit_log writer for disposal services.
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

namespace AhgRecordsManage\Services\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Insert a records-manage audit row into ahg_audit_log (no-op if the table is
 * absent). Shared by the disposal services and the record export.
 *
 * heratio#1740: the row joins the signed hash chain (ChainedAuditWriter), so a
 * disposal event is tamper-evident and checked by auditlog:verify-chain. It used
 * to be a plain insert outside the chain. Like AuditLogger, it stores a keyed
 * hash of the session id (never the live session cookie) and honours the
 * audit_ip_anonymize setting.
 */
trait WritesDisposalAuditLog
{
    private function auditLog(int $userId, string $action, string $entityType, int $entityId, string $title, array $metadata = []): void
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return;
        }

        $user = DB::table('user')
            ->where('user.id', $userId)
            ->select('user.username', 'user.email')
            ->first();

        $ip = request()->ip();
        if ($ip && \AhgCore\Services\AhgSettingsService::get('audit_ip_anonymize', '1') === '1') {
            $ip = preg_replace('/\.\d+$/', '.0', $ip);
        }
        $rawSession = request()->hasSession() ? request()->session()->getId() : null;

        $row = [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $userId,
            'username' => $user->username ?? null,
            'user_email' => $user->email ?? null,
            'ip_address' => $ip,
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'session_id' => $rawSession ? hash_hmac('sha256', $rawSession, (string) config('app.key')) : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_title' => $title,
            'module' => 'records-manage',
            'action_name' => $action,
            'request_method' => request()->method(),
            'request_uri' => substr((string) request()->getRequestUri(), 0, 2000),
            'metadata' => ! empty($metadata) ? json_encode($metadata) : null,
            'status' => 'success',
            'created_at' => Carbon::now(),
        ];

        if (class_exists(\AhgAuditTrail\Services\ChainedAuditWriter::class)) {
            app(\AhgAuditTrail\Services\ChainedAuditWriter::class)->append($row);

            return;
        }

        DB::table('ahg_audit_log')->insert($row);
    }
}
