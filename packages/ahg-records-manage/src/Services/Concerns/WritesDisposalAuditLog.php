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
 * Insert a records-manage disposal audit row into ahg_audit_log (no-op if
 * the table is absent). Was byte-identical in DisposalExecutionService and
 * DisposalWorkflowService.
 */
trait WritesDisposalAuditLog
{
    private function auditLog(int $userId, string $action, string $entityType, int $entityId, string $title, array $metadata = []): void
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return;
        }

        $user = DB::table('user')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->select('user.username', 'user.email', 'actor_i18n.authorized_form_of_name')
            ->first();

        DB::table('ahg_audit_log')->insert([
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $userId,
            'username' => $user->username ?? null,
            'user_email' => $user->email ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_title' => $title,
            'module' => 'records-manage',
            'action_name' => $action,
            'request_method' => request()->method(),
            'request_uri' => request()->getRequestUri(),
            'metadata' => ! empty($metadata) ? json_encode($metadata) : null,
            'status' => 'success',
            'created_at' => Carbon::now(),
        ]);
    }
}
