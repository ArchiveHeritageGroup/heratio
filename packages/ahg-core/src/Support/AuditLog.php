<?php

/**
 * AuditLog — capture before/after diffs from service-layer update() calls
 * so the audit-log row written by app/Http/Middleware/AuditLog.php carries
 * a real field-level diff instead of just `{path: ...}`.
 *
 * Pattern (in any service::update()):
 *
 *   $before = $this->snapshot($id);
 *   // … existing update work …
 *   $after  = $this->snapshot($id);
 *   \AhgCore\Support\AuditLog::captureEdit($id, 'information_object', $before, $after);
 *
 * The helper computes which keys changed, picks just those keys out of
 * the two arrays, and stashes the result on the active request via
 * `audit.diff` attribute. The middleware reads that attribute when it
 * builds its row and merges {changed_fields, before, after} into details.
 *
 * If no request is bound (CLI / queue context), the helper writes a
 * stand-alone row directly into security_audit_log so the diff is still
 * captured.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLog
{
    public static function captureEdit(int $objectId, string $objectType, array $before, array $after): void
    {
        $changed = self::diffFields($before, $after);
        if (empty($changed)) return;

        $payload = [
            'changed_fields' => array_values($changed),
            'before' => self::pickKeys($before, $changed),
            'after'  => self::pickKeys($after, $changed),
        ];

        try {
            $req = app('request');
            $req->attributes->set('audit.diff', $payload);
            $req->attributes->set('audit.object_id', $objectId);
            $req->attributes->set('audit.object_type', $objectType);
        } catch (\Throwable $e) {
            self::writeDirect($objectId, $objectType, $payload);
        }
    }

    private static function diffFields(array $before, array $after): array
    {
        $changed = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        foreach ($keys as $k) {
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;
            // Normalise both sides to strings for comparison so '5' and 5
            // count as equal, but distinct array shapes stay distinct.
            if (is_array($b)) $b = json_encode($b);
            if (is_array($a)) $a = json_encode($a);
            if ((string) $b !== (string) $a) $changed[] = $k;
        }
        return $changed;
    }

    private static function pickKeys(array $arr, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr)) $out[$k] = $arr[$k];
        }
        return $out;
    }

    private static function writeDirect(int $objectId, string $objectType, array $payload): void
    {
        if (!Schema::hasTable('security_audit_log')) return;
        $userId = auth()->id();
        $userName = $userId ? DB::table('user')->where('id', $userId)->value('username') : null;
        DB::table('security_audit_log')->insert([
            'object_id'       => $objectId,
            'object_type'     => $objectType,
            'user_id'         => $userId,
            'user_name'       => $userName,
            'action'          => 'update',
            'action_category' => 'admin',
            'details'         => json_encode($payload),
            'ip_address'      => null,
            'user_agent'      => null,
            'created_at'      => now(),
        ]);
    }
}
