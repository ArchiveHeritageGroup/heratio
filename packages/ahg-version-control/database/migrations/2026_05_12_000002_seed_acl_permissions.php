<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase K — seed default ACL permissions for the four version.* actions.
 *
 * Idempotent: each row is guarded by a NOT EXISTS check on (group_id, action).
 * Administrator (group 100) is intentionally NOT seeded — base AtoM grants
 * administrator allow-all via action=NULL which AclCheck honours.
 */
return new class extends Migration {
    public function up(): void
    {
        $seeds = [
            [101, 'version.list'],
            [101, 'version.diff'],
            [101, 'version.restore'],
            [101, 'version.restore_classified'],
            [102, 'version.list'],
            [102, 'version.diff'],
            [103, 'version.list'],
        ];

        foreach ($seeds as [$groupId, $action]) {
            $exists = DB::table('acl_permission')
                ->where('group_id', $groupId)
                ->where('action', $action)
                ->exists();
            if (!$exists) {
                DB::table('acl_permission')->insert([
                    'user_id'    => null,
                    'group_id'   => $groupId,
                    'object_id'  => null,
                    'action'     => $action,
                    'grant_deny' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('acl_permission')
            ->whereIn('group_id', [101, 102, 103])
            ->where('action', 'like', 'version.%')
            ->delete();
    }
};
