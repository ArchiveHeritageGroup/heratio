<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Mint a real administrator for API feature tests.
 *
 * The #1395(A) hardening only grants the write/delete/publish/batch API
 * scopes to users whose ACL actually authorises those actions. A freshly
 * seeded CI test DB contains no administrator, so looking one up returns
 * null and every write 403s. This trait instead CREATES an admin through the
 * Qubit class-table-inheritance chain (object -> actor -> user) so
 * acl_user_group's FK (acl_user_group_FK_1 -> user.id) holds, then joins the
 * ADMINISTRATOR group. Rolled back with DatabaseTransactions.
 *
 * Mirrors LibraryAcquisitionsApiTest::makeUser(), which is the CI-proven
 * reference for this pattern.
 */
trait MintsApiAdmin
{
    protected function mintApiAdmin(): User
    {
        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('actor')->insert([
            'id'             => $id,
            'source_culture' => 'en',
        ]);
        DB::table('user')->insert([
            'id'            => $id,
            'username'      => 'api-admin-'.$id,
            'email'         => uniqid('api-admin-', true).'@example.test',
            'password_hash' => Hash::make('secret'),
            'active'        => 1,
        ]);
        // AclGroup::ADMINISTRATOR_ID = 100 -> AclService grants every action.
        // (AclService reads acl_user_group via DB::table on the default
        // connection, so this row is what actually authorises the scopes.)
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        // Return a lightweight model carrying the id — actingAs()/Auth::id()
        // and the ACL lookups only need the id, and hydrating via the Eloquent
        // User model can miss the just-inserted row (model connection/scope).
        $user = new User();
        $user->id = $id;
        $user->exists = true;

        return $user;
    }
}
