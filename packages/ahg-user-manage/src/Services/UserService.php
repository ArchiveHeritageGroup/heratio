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

        // Attach contact information
        $user->contact = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('contact_information.actor_id', $user->id)
            ->select(
                'contact_information.id',
                'contact_information.telephone',
                'contact_information.fax',
                'contact_information.street_address',
                'contact_information.postal_code',
                'contact_information.country_code',
                'contact_information.website',
                'contact_information.contact_note',
                'contact_information_i18n.city',
                'contact_information_i18n.region',
                'contact_information_i18n.note'
            )
            ->first();

        // Attach translate languages
        $user->translateLanguages = $this->getTranslateLanguages((int) $user->id);

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
     * Get allowed translate languages for a user (from acl_permission).
     */
    public function getTranslateLanguages(int $userId): array
    {
        $row = DB::table('acl_permission')
            ->where('user_id', $userId)
            ->where('action', 'translate')
            ->first();

        if (!$row) {
            return [];
        }

        // Languages stored in constants as serialized array: a:1:{s:9:"languages";a:1:{i:0;s:2:"af";}}
        $source = $row->constants ?: $row->conditional;
        if (empty($source)) {
            return [];
        }

        $data = @unserialize($source);
        if (is_array($data) && isset($data['languages'])) {
            return $data['languages'];
        }
        if (is_array($data)) {
            return $data;
        }

        $data = json_decode($source, true);
        return is_array($data) ? ($data['languages'] ?? $data) : [];
    }

    /**
     * Get available languages from the i18n_languages setting.
     */
    public function getAvailableLanguages(): array
    {
        $row = DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'i18n_languages')
            ->first();

        if (!$row || empty($row->value)) {
            // Fallback: check source_culture from setting
            return [$this->culture];
        }

        $data = @unserialize($row->value);
        if (is_array($data)) {
            return $data;
        }
        $data = json_decode($row->value, true);
        return is_array($data) ? $data : [$this->culture];
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

            // Save contact information
            $this->saveContact($id, $data);

            // Save translate languages
            $this->saveTranslateLanguages($id, $data['translate'] ?? []);

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

            // Save contact information
            $this->saveContact($id, $data);

            // Save translate languages
            if (isset($data['translate'])) {
                $this->saveTranslateLanguages($id, $data['translate']);
            }

            // Touch object timestamp
            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    /**
     * Save contact information for a user.
     */
    protected function saveContact(int $userId, array $data): void
    {
        $contactFields = [
            'contact_telephone' => 'telephone',
            'contact_fax' => 'fax',
            'contact_street_address' => 'street_address',
            'contact_postal_code' => 'postal_code',
            'contact_country_code' => 'country_code',
            'contact_website' => 'website',
            'contact_note' => 'contact_note',
        ];
        $i18nFields = [
            'contact_city' => 'city',
            'contact_region' => 'region',
        ];

        // Check if any contact field is provided
        $hasContact = false;
        foreach (array_keys($contactFields) as $formField) {
            if (!empty($data[$formField])) {
                $hasContact = true;
                break;
            }
        }
        foreach (array_keys($i18nFields) as $formField) {
            if (!empty($data[$formField])) {
                $hasContact = true;
                break;
            }
        }

        if (!$hasContact) {
            return;
        }

        $existing = DB::table('contact_information')->where('actor_id', $userId)->first();

        $baseData = [];
        foreach ($contactFields as $formField => $dbField) {
            $baseData[$dbField] = $data[$formField] ?? null;
        }

        $i18nData = [];
        foreach ($i18nFields as $formField => $dbField) {
            $i18nData[$dbField] = $data[$formField] ?? null;
        }

        if ($existing) {
            $baseData['updated_at'] = now();
            DB::table('contact_information')->where('id', $existing->id)->update($baseData);
            DB::table('contact_information_i18n')->updateOrInsert(
                ['id' => $existing->id, 'culture' => $this->culture],
                $i18nData
            );
        } else {
            $baseData['actor_id'] = $userId;
            $baseData['source_culture'] = $this->culture;
            $baseData['created_at'] = now();
            $baseData['updated_at'] = now();
            $contactId = DB::table('contact_information')->insertGetId($baseData);
            DB::table('contact_information_i18n')->insert(array_merge(
                ['id' => $contactId, 'culture' => $this->culture],
                $i18nData
            ));
        }
    }

    /**
     * Save translate language permissions for a user.
     */
    protected function saveTranslateLanguages(int $userId, array $languages): void
    {
        // Remove existing translate permission
        DB::table('acl_permission')
            ->where('user_id', $userId)
            ->where('action', 'translate')
            ->delete();

        if (!empty($languages)) {
            DB::table('acl_permission')->insert([
                'user_id' => $userId,
                'group_id' => null,
                'object_id' => null,
                'action' => 'translate',
                'grant_deny' => 1,
                'conditional' => 'in_array(%p[language], %k[languages])',
                'constants' => serialize(['languages' => $languages]),
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);
        }
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
