<?php

/**
 * LegalHoldService - Legal hold management for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgIntegrity\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgCore\Support\I18nFallback;

class LegalHoldService
{
    /**
     * Place a legal hold on an information object.
     */
    public function placeHold(int $ioId, string $reason, int $userId): int
    {
        if (! Schema::hasTable('integrity_legal_hold')) {
            throw new \RuntimeException('Table integrity_legal_hold does not exist.');
        }

        $username = DB::table('user')
            ->join('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->value('actor_i18n.authorized_form_of_name') ?? 'User #'.$userId;

        $holdId = DB::table('integrity_legal_hold')->insertGetId([
            'information_object_id' => $ioId,
            'reason' => $reason,
            'placed_by' => $username,
            'placed_at' => now(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        $this->audit($userId, 'create', $holdId, 'Legal hold placed', [
            'information_object_id' => $ioId,
            'reason' => $reason,
        ]);

        return $holdId;
    }

    /**
     * Release a legal hold.
     */
    public function releaseHold(int $holdId, int $userId, string $reason): bool
    {
        if (! Schema::hasTable('integrity_legal_hold')) {
            return false;
        }

        $username = DB::table('user')
            ->join('actor_i18n', function ($join) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->where('user.id', $userId)
            ->value('actor_i18n.authorized_form_of_name') ?? 'User #'.$userId;

        $hold = DB::table('integrity_legal_hold')->where('id', $holdId)->first();

        $update = [
            'released_by' => $username,
            'released_at' => now(),
            'status' => 'released',
        ];
        // heratio#1740: the release reason was asked for but never stored.
        if (Schema::hasColumn('integrity_legal_hold', 'release_reason')) {
            $update['release_reason'] = $reason;
        }

        $updated = DB::table('integrity_legal_hold')
            ->where('id', $holdId)
            ->where('status', 'active')
            ->update($update);

        if ($updated > 0) {
            $this->audit($userId, 'update', $holdId, 'Legal hold released', [
                'information_object_id' => (int) ($hold->information_object_id ?? 0),
                'release_reason' => $reason,
            ]);
        }

        return $updated > 0;
    }

    /**
     * heratio#1740: placing and releasing a hold joins the signed audit chain
     * (ChainedAuditWriter, checked by auditlog:verify-chain), with the same
     * privacy rules as the other writers: a keyed hash of the session id,
     * never the id itself, and audit_ip_anonymize honoured.
     */
    private function audit(int $userId, string $action, int $holdId, string $title, array $metadata): void
    {
        if (! Schema::hasTable('ahg_audit_log')) {
            return;
        }

        $user = $userId > 0 ? DB::table('user')->where('id', $userId)->first(['username', 'email']) : null;
        $ip = request()->ip();
        if ($ip && \AhgCore\Services\AhgSettingsService::get('audit_ip_anonymize', '1') === '1') {
            $ip = preg_replace('/\.\d+$/', '.0', $ip);
        }
        $rawSession = request()->hasSession() ? request()->session()->getId() : null;

        $row = [
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'user_id' => $userId > 0 ? $userId : null,
            'username' => $user->username ?? null,
            'user_email' => $user->email ?? null,
            'ip_address' => $ip,
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'session_id' => $rawSession ? hash_hmac('sha256', $rawSession, (string) config('app.key')) : null,
            'action' => $action,
            'entity_type' => 'integrity_legal_hold',
            'entity_id' => $holdId,
            'entity_title' => $title,
            'module' => 'integrity',
            'action_name' => $action === 'create' ? 'legal_hold_place' : 'legal_hold_release',
            'request_method' => request()->method(),
            'request_uri' => substr((string) request()->getRequestUri(), 0, 2000),
            'metadata' => json_encode($metadata),
            'status' => 'success',
            'created_at' => now(),
        ];

        if (class_exists(\AhgAuditTrail\Services\ChainedAuditWriter::class)) {
            app(\AhgAuditTrail\Services\ChainedAuditWriter::class)->append($row);

            return;
        }

        DB::table('ahg_audit_log')->insert($row);
    }

    /**
     * Check if an information object is under active hold.
     */
    public function isUnderHold(int $ioId): bool
    {
        if (! Schema::hasTable('integrity_legal_hold')) {
            return false;
        }

        return DB::table('integrity_legal_hold')
            ->where('information_object_id', $ioId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get paginated active holds, optionally filtered by repository.
     */
    public function getActiveHolds(?int $repositoryId = null, int $page = 1, int $perPage = 25): array
    {
        if (! Schema::hasTable('integrity_legal_hold')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage];
        }

        $culture = app()->getLocale();

        $query = DB::table('integrity_legal_hold')
            ->leftJoin('information_object', 'integrity_legal_hold.information_object_id', '=', 'information_object.id')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n', 'information_object.id', $culture))
            ->where('integrity_legal_hold.status', 'active');

        if ($repositoryId) {
            $query->where('information_object.repository_id', $repositoryId);
        }

        $total = $query->count();

        $data = (clone $query)
            ->select([
                'integrity_legal_hold.*',
                'information_object_i18n.title as io_title',
                'information_object.repository_id',
            ])
            ->orderBy('integrity_legal_hold.placed_at', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Get all holds (active and released) for a given information object.
     */
    public function getHoldHistory(int $ioId): array
    {
        if (! Schema::hasTable('integrity_legal_hold')) {
            return [];
        }

        return DB::table('integrity_legal_hold')
            ->where('information_object_id', $ioId)
            ->orderBy('placed_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get counts of holds by status.
     */
    public function getHoldCounts(): array
    {
        if (! Schema::hasTable('integrity_legal_hold')) {
            return ['active' => 0, 'released' => 0, 'total' => 0];
        }

        $counts = DB::table('integrity_legal_hold')
            ->select(
                DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count"),
                DB::raw("SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released_count"),
                DB::raw('COUNT(*) as total_count')
            )
            ->first();

        return [
            'active' => (int) ($counts->active_count ?? 0),
            'released' => (int) ($counts->released_count ?? 0),
            'total' => (int) ($counts->total_count ?? 0),
        ];
    }
}
