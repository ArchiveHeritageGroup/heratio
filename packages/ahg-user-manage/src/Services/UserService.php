<?php

namespace AhgUserManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Get a user by slug.
     */
    public function getBySlug(string $slug): ?object
    {
        $row = DB::table('slug')
            ->join('object', 'slug.object_id', '=', 'object.id')
            ->where('slug.slug', $slug)
            ->where('object.class_name', 'QubitUser')
            ->select(['slug.object_id'])
            ->first();

        if (!$row) {
            return null;
        }

        return $this->getById((int) $row->object_id);
    }

    /**
     * Get a user by ID with full class table inheritance join.
     */
    public function getById(int $id): ?object
    {
        $user = DB::table('user')
            ->join('actor', 'user.id', '=', 'actor.id')
            ->join('object', 'user.id', '=', 'object.id')
            ->join('slug', 'user.id', '=', 'slug.object_id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->where('user.id', $id)
            ->select([
                'user.id',
                'user.username',
                'user.email',
                'user.active',
                'user.password_hash',
                'user.salt',
                'actor.entity_type_id',
                'actor.parent_id',
                'actor.source_culture',
                'actor_i18n.authorized_form_of_name',
                'object.created_at',
                'object.updated_at',
                'object.serial_number',
                'slug.slug',
            ])
            ->first();

        if (!$user) {
            return null;
        }

        // Attach groups
        $user->groups = $this->getUserGroups((int) $user->id);

        return $user;
    }

    /**
     * Get groups assigned to a user.
     */
    public function getUserGroups(int $userId): array
    {
        return DB::table('acl_user_group')
            ->join('acl_group', 'acl_user_group.group_id', '=', 'acl_group.id')
            ->leftJoin('acl_group_i18n', function ($join) {
                $join->on('acl_group.id', '=', 'acl_group_i18n.id')
                     ->where('acl_group_i18n.culture', '=', $this->culture);
            })
            ->where('acl_user_group.user_id', $userId)
            ->select(['acl_group.id', 'acl_group_i18n.name'])
            ->get()
            ->all();
    }

    /**
     * Get all assignable groups (ID > 99, excluding system groups).
     */
    public function getAssignableGroups(): array
    {
        return DB::table('acl_group')
            ->leftJoin('acl_group_i18n', function ($join) {
                $join->on('acl_group.id', '=', 'acl_group_i18n.id')
                     ->where('acl_group_i18n.culture', '=', $this->culture);
            })
            ->where('acl_group.id', '>', 99)
            ->select(['acl_group.id', 'acl_group_i18n.name'])
            ->orderBy('acl_group.id')
            ->get()
            ->all();
    }

    /**
     * Create a new user.
     */
    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            // Create object row
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitUser',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            // Generate unique slug
            $baseSlug = Str::slug($data['username'] ?? 'user');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

            // Actor row (user extends actor via class table inheritance)
            DB::table('actor')->insert([
                'id' => $id,
                'parent_id' => 3, // QubitActor::ROOT_ID
                'source_culture' => $this->culture,
            ]);

            // Actor i18n (display name)
            if (!empty($data['authorized_form_of_name'])) {
                DB::table('actor_i18n')->insert([
                    'id' => $id,
                    'culture' => $this->culture,
                    'authorized_form_of_name' => $data['authorized_form_of_name'],
                ]);
            }

            // Hash password using dual-layer approach (matching existing system)
            $salt = md5(rand(100000, 999999) . ($data['email'] ?? ''));
            $sha1Hash = sha1($salt . ($data['password'] ?? ''));
            $hashAlgo = defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_DEFAULT;
            $passwordHash = password_hash($sha1Hash, $hashAlgo);

            // User row
            DB::table('user')->insert([
                'id' => $id,
                'username' => $data['username'] ?? '',
                'email' => $data['email'] ?? '',
                'password_hash' => $passwordHash,
                'salt' => $salt,
                'active' => isset($data['active']) ? (int) $data['active'] : 1,
            ]);

            // Always assign 'Authenticated' group (99)
            DB::table('acl_user_group')->insert([
                'user_id' => $id,
                'group_id' => 99,
            ]);

            // Assign selected groups
            if (!empty($data['groups'])) {
                foreach ($data['groups'] as $groupId) {
                    $groupId = (int) $groupId;
                    if ($groupId > 99) {
                        DB::table('acl_user_group')->insert([
                            'user_id' => $id,
                            'group_id' => $groupId,
                        ]);
                    }
                }
            }

            return $id;
        });
    }

    /**
     * Update an existing user.
     */
    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $updateFields = [];

            if (isset($data['username'])) {
                $updateFields['username'] = $data['username'];
            }
            if (isset($data['email'])) {
                $updateFields['email'] = $data['email'];
            }
            if (isset($data['active'])) {
                $updateFields['active'] = (int) $data['active'];
            }

            // Update password only if provided
            if (!empty($data['password'])) {
                $salt = md5(rand(100000, 999999) . ($data['email'] ?? ''));
                $sha1Hash = sha1($salt . $data['password']);
                $hashAlgo = defined('PASSWORD_ARGON2I') ? PASSWORD_ARGON2I : PASSWORD_DEFAULT;
                $updateFields['password_hash'] = password_hash($sha1Hash, $hashAlgo);
                $updateFields['salt'] = $salt;
            }

            if (!empty($updateFields)) {
                DB::table('user')->where('id', $id)->update($updateFields);
            }

            // Update actor_i18n (display name)
            if (array_key_exists('authorized_form_of_name', $data)) {
                $exists = DB::table('actor_i18n')
                    ->where('id', $id)
                    ->where('culture', $this->culture)
                    ->exists();

                if ($exists) {
                    DB::table('actor_i18n')
                        ->where('id', $id)
                        ->where('culture', $this->culture)
                        ->update(['authorized_form_of_name' => $data['authorized_form_of_name']]);
                } else {
                    DB::table('actor_i18n')->insert([
                        'id' => $id,
                        'culture' => $this->culture,
                        'authorized_form_of_name' => $data['authorized_form_of_name'],
                    ]);
                }
            }

            // Sync groups if provided
            if (isset($data['groups'])) {
                // Remove existing non-system groups (keep group 99 = Authenticated)
                DB::table('acl_user_group')
                    ->where('user_id', $id)
                    ->where('group_id', '>', 99)
                    ->delete();

                foreach ($data['groups'] as $groupId) {
                    $groupId = (int) $groupId;
                    if ($groupId > 99) {
                        DB::table('acl_user_group')->insert([
                            'user_id' => $id,
                            'group_id' => $groupId,
                        ]);
                    }
                }
            }

            // Touch object timestamp
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Delete a user and all related records.
     */
    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            // Delete group assignments
            DB::table('acl_user_group')->where('user_id', $id)->delete();

            // Delete ACL permissions
            DB::table('acl_permission')->where('user_id', $id)->delete();

            // Delete properties and their i18n (API keys etc.)
            $propertyIds = DB::table('property')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($propertyIds)) {
                DB::table('property_i18n')->whereIn('id', $propertyIds)->delete();
                DB::table('property')->whereIn('id', $propertyIds)->delete();
            }

            // Delete user record
            DB::table('user')->where('id', $id)->delete();

            // Delete actor i18n
            DB::table('actor_i18n')->where('id', $id)->delete();

            // Delete actor record
            DB::table('actor')->where('id', $id)->delete();

            // Delete slug and object
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    /**
     * Get the slug for a user ID.
     */
    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }
}
