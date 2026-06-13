<?php

namespace AhgResearch\Services\UserProvisioner;

use AhgResearch\Contracts\UserProvisionerInterface;
use Illuminate\Support\Facades\DB;

class EloquentUserProvisioner implements UserProvisionerInterface
{
    public function createUser(string $username, string $email, string $password): int
    {
        $salt = md5(rand(100000, 999999) . $email);
        $sha1Hash = sha1($salt . $password);
        $passwordHash = password_hash($sha1Hash, PASSWORD_ARGON2I);
        $now = date('Y-m-d H:i:s');

        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitUser',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        DB::table('actor')->insert([
            'id' => $objectId,
            'corporate_body_identifiers' => null,
            'entity_type_id' => null,
            'description_status_id' => null,
            'description_detail_id' => null,
            'description_identifier' => null,
            'source_standard' => null,
            'source_culture' => 'en',
        ]);

        DB::table('user')->insert([
            'id' => $objectId,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'salt' => $salt,
            'active' => 0,
        ]);

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => preg_replace('/[^a-zA-Z0-9-]/', '-', $username),
        ]);

        return $objectId;
    }

    public function updateUser(int $userId, array $data): bool
    {
        // NB: the AtoM `user` table has no `updated_at` column (timestamps live
        // on `object`), so we must not inject one - doing so 1054-errors.
        return DB::table('user')->where('id', $userId)->update($data) > 0;
    }

    public function addToGroup(int $userId, int $groupId): bool
    {
        if (!DB::table('acl_user_group')->where('user_id', $userId)->where('group_id', $groupId)->exists()) {
            DB::table('acl_user_group')->insert(['user_id' => $userId, 'group_id' => $groupId]);
        }
        return true;
    }

    public function deactivateUser(int $userId): bool
    {
        return DB::table('user')->where('id', $userId)->update(['active' => 0]) > 0;
    }

    public function findByEmail(string $email): ?object
    {
        return DB::table('user')->where('email', $email)->first();
    }

    public function isInGroup(int $userId, int $groupId): bool
    {
        return DB::table('acl_user_group')
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->exists();
    }

    public function setPassword(int $userId, string $password): bool
    {
        // Same scheme as createUser(): per-user salt, sha1 pre-hash, argon2.
        $salt = md5(rand(100000, 999999) . $userId . microtime());
        $sha1Hash = sha1($salt . $password);
        $passwordHash = password_hash($sha1Hash, PASSWORD_ARGON2I);

        return DB::table('user')->where('id', $userId)->update([
            'password_hash' => $passwordHash,
            'salt' => $salt,
        ]) > 0;
    }
}
