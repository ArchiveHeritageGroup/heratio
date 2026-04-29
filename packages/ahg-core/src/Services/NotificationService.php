<?php

/**
 * NotificationService — generic, user_id-keyed in-app notifications.
 *
 * Canonical writer for the ahg_notification table. Replaces the AtoM
 * registry-side registry_notification + ahgRegistryPlugin\NotificationService
 * with a cross-package implementation. Domain-specific tables that already
 * exist (research_notification, spectrum_notification) keep their own
 * service classes for now; new code should prefer this one.
 *
 * Admin recipients resolve through acl_user_group (group_id 100 = administrator
 * per the canonical Heratio convention; matches SpectrumNotificationService).
 * Group 99 is "authenticated" (every logged-in user) — never use that for admin lookups.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    protected const TABLE = 'ahg_notification';
    protected const ADMIN_GROUP_ID = 100;

    /**
     * Insert one notification row for a single user.
     * Returns the new notification id, or 0 if the table is missing.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
        ?string $relatedType = null,
        $relatedId = null,
        ?int $actorUserId = null,
        ?string $actorName = null
    ): int {
        if (! Schema::hasTable(self::TABLE)) return 0;
        return (int) DB::table(self::TABLE)->insertGetId([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'actor_user_id' => $actorUserId,
            'actor_name' => $actorName,
            'created_at' => now(),
        ]);
    }

    /**
     * Notify every active administrator. Returns rows inserted.
     * If $actorUserId is the admin who triggered the event, they are skipped.
     */
    public function notifyAdmins(
        string $type,
        string $title,
        ?string $message = null,
        ?string $link = null,
        ?string $relatedType = null,
        $relatedId = null,
        ?int $actorUserId = null,
        ?string $actorName = null
    ): int {
        if (! Schema::hasTable(self::TABLE)) return 0;
        $admins = DB::table('acl_user_group as g')
            ->join('user as u', 'u.id', '=', 'g.user_id')
            ->where('g.group_id', self::ADMIN_GROUP_ID)
            ->where('u.active', 1)
            ->distinct()
            ->pluck('u.id')
            ->all();

        $count = 0;
        foreach ($admins as $adminId) {
            if ($actorUserId && (int) $adminId === (int) $actorUserId) continue;
            $this->notify((int) $adminId, $type, $title, $message, $link, $relatedType, $relatedId, $actorUserId, $actorName);
            $count++;
        }
        return $count;
    }

    public function unreadCount(int $userId): int
    {
        if (! Schema::hasTable(self::TABLE)) return 0;
        return DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_read', 0)
            ->where('is_dismissed', 0)
            ->count();
    }

    public function listForUser(int $userId, int $limit = 20, bool $unreadOnly = false): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable(self::TABLE)) return collect();
        $q = DB::table(self::TABLE)
            ->where('user_id', $userId)
            ->where('is_dismissed', 0)
            ->orderByDesc('created_at')
            ->limit($limit);
        if ($unreadOnly) $q->where('is_read', 0);
        return $q->get();
    }

    public function markRead(int $notificationId, ?int $userId = null): bool
    {
        if (! Schema::hasTable(self::TABLE)) return false;
        $q = DB::table(self::TABLE)->where('id', $notificationId);
        if ($userId) $q->where('user_id', $userId);
        return (bool) $q->update(['is_read' => 1, 'read_at' => now()]);
    }

    public function markAllRead(int $userId, ?string $type = null): int
    {
        if (! Schema::hasTable(self::TABLE)) return 0;
        $q = DB::table(self::TABLE)->where('user_id', $userId)->where('is_read', 0);
        if ($type) $q->where('type', $type);
        return (int) $q->update(['is_read' => 1, 'read_at' => now()]);
    }

    public function dismiss(int $notificationId, ?int $userId = null): bool
    {
        if (! Schema::hasTable(self::TABLE)) return false;
        $q = DB::table(self::TABLE)->where('id', $notificationId);
        if ($userId) $q->where('user_id', $userId);
        return (bool) $q->update(['is_dismissed' => 1]);
    }
}
