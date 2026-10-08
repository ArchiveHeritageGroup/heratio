<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1535. Data migration, ingest and the export screens move from the
 * hard-wired admin gate (Administrator or Editor) to the new `import` and
 * `export` ACL actions, so any role can be granted CSV work. Editors held
 * that access through the old gate; they keep it through these two grants.
 * Administrators have every action already.
 */
return new class extends Migration
{
    private const GRANTS = [[101, 'import'], [101, 'export']];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('acl_permission')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (self::GRANTS as [$groupId, $action]) {
            $exists = DB::table('acl_permission')->where('group_id', $groupId)->whereNull('user_id')
                ->whereNull('object_id')->where('action', $action)->exists();
            if (! $exists) {
                DB::table('acl_permission')->insert([
                    'user_id' => null, 'group_id' => $groupId, 'object_id' => null,
                    'action' => $action, 'grant_deny' => 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('acl_permission')) {
            return;
        }
        foreach (self::GRANTS as [$groupId, $action]) {
            DB::table('acl_permission')->where('group_id', $groupId)->whereNull('user_id')
                ->whereNull('object_id')->where('action', $action)->delete();
        }
    }
};
