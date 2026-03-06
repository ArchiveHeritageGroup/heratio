<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddSuperuserCommand extends Command
{
    protected $signature = 'heratio:user:add-superuser';

    protected $description = 'Create a new admin (superuser) account';

    public function handle(): int
    {
        $email = $this->ask('Email address');
        $username = $this->ask('Username');
        $password = $this->secret('Password');

        if (! $email || ! $username || ! $password) {
            $this->error('Email, username, and password are all required.');

            return self::FAILURE;
        }

        // Check if email already exists
        $existing = DB::table('user')->where('email', $email)->first();
        if ($existing) {
            $this->error("A user with email '{$email}' already exists.");

            return self::FAILURE;
        }

        // Generate password hash (SHA-1 with salt)
        $salt = bin2hex(random_bytes(16)); // 32-char hex salt
        $passwordHash = sha1($salt . $password);

        try {
            DB::beginTransaction();

            // 1. Insert into object table
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitUser',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Insert into actor table
            DB::table('actor')->insert([
                'id' => $objectId,
                'entity_type_id' => null,
            ]);

            // 3. Insert into actor_i18n table
            DB::table('actor_i18n')->insert([
                'id' => $objectId,
                'culture' => 'en',
                'authorized_form_of_name' => $username,
            ]);

            // 4. Insert into user table
            DB::table('user')->insert([
                'id' => $objectId,
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'salt' => $salt,
                'active' => 1,
            ]);

            // 5. Generate slug
            $slug = Str::slug($username);
            $baseSlug = $slug;
            $counter = 2;

            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            DB::table('slug')->insert([
                'object_id' => $objectId,
                'slug' => $slug,
            ]);

            // 6. Add to admin group (group_id=100 is the administrator group)
            DB::table('acl_user_group')->insert([
                'user_id' => $objectId,
                'group_id' => 100,
            ]);

            DB::commit();

            $this->info("Superuser '{$username}' created successfully (ID: {$objectId}).");

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Failed to create superuser: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
