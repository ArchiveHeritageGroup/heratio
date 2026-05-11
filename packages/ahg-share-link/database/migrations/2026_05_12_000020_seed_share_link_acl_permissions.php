<?php

/**
 * F1 Phase I — seed default ACL permissions for share-link actions.
 *
 * Idempotent: each INSERT is guarded by NOT EXISTS, so re-running migrations
 * doesn't duplicate rows. Mirrors the install-time seed in
 * `packages/ahg-share-link/database/seed-acl-permissions.sql`.
 *
 * Default grant matrix (AtoM/Heratio standard group ids):
 *   100 administrator   — bypass in AclCheck, no row needed
 *   101 editor          — create + list_all + revoke_others
 *   102 contributor     — create only
 *   103 translator      — none
 *
 * `share_link.create_classified` and `share_link.create_unlimited_expiry`
 * are intentionally NOT granted to any default group — admin only.
 *
 * @phase I
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('acl_permission')) {
            // Heratio without legacy AtoM tables — silently skip.
            return;
        }

        $now = date('Y-m-d H:i:s');
        $grants = [
            [101, 'share_link.create'],
            [101, 'share_link.list_all'],
            [101, 'share_link.revoke_others'],
            [102, 'share_link.create'],
        ];

        foreach ($grants as [$groupId, $action]) {
            $exists = DB::table('acl_permission')
                ->where('group_id', $groupId)
                ->where('action', $action)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('acl_permission')->insert([
                'user_id'    => null,
                'group_id'   => $groupId,
                'object_id'  => null,
                'action'     => $action,
                'grant_deny' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('acl_permission')) {
            return;
        }
        DB::table('acl_permission')->where('action', 'like', 'share_link.%')->delete();
    }
};
