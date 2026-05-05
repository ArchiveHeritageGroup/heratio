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

        self::stash($objectId, $objectType, $payload);
    }

    /**
     * Record the snapshot of a newly-created entity. There's no "before"
     * (the row didn't exist), so the payload carries `after` only and the
     * audit row's action stays as whatever the middleware classified it
     * (typically 'create' on POST / 'api_POST' on /api/* paths).
     */
    public static function captureCreate(int $objectId, string $objectType, array $after): void
    {
        $payload = [
            'after' => $after,
            'created' => true,
        ];
        self::stash($objectId, $objectType, $payload);
    }

    /**
     * Record the snapshot of an entity about to be deleted. Call this
     * BEFORE the delete fires — once the row is gone the snapshot is
     * unrecoverable. Payload carries `before` only.
     */
    public static function captureDelete(int $objectId, string $objectType, array $before): void
    {
        $payload = [
            'before'  => $before,
            'deleted' => true,
        ];
        self::stash($objectId, $objectType, $payload);
    }

    /**
     * Internal: stash payload onto request attributes (read by the audit
     * middleware) or write directly when no request is bound.
     */
    private static function stash(int $objectId, string $objectType, array $payload): void
    {
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
        $action = !empty($payload['created']) ? 'create'
                : (!empty($payload['deleted']) ? 'delete' : 'update');
        DB::table('security_audit_log')->insert([
            'object_id'       => $objectId,
            'object_type'     => $objectType,
            'user_id'         => $userId,
            'user_name'       => $userName,
            'action'          => $action,
            'action_category' => 'admin',
            'details'         => json_encode($payload),
            'ip_address'      => null,
            'user_agent'      => null,
            'created_at'      => now(),
        ]);
    }
}
