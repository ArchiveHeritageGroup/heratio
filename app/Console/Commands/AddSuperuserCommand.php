<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Auth\LoginController;
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
            $this->error(__('Email, username, and password are all required.'));

            return self::FAILURE;
        }

        // Check if email already exists
        $existing = DB::table('user')->where('email', $email)->first();
        if ($existing) {
            $this->error(__('A user with email \'' . $email . '\' already exists.'));

            return self::FAILURE;
        }

        // Hash through the SAME implementation the login uses - see the note in
        // ResetPasswordCommand. A bare sha1 here produced superuser accounts
        // that could never sign in, because AtomUserProvider verifies with
        // password_verify() and a raw SHA-1 is not a crypt hash.
        ['salt' => $salt, 'password_hash' => $passwordHash] = LoginController::hashPassword($password);

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
                $slug = $baseSlug.'-'.$counter;
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

            $this->info(__('Superuser \'' . $username . '\' created successfully (ID: ' . $objectId . ').'));

            return self::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error(__('Failed to create superuser: ' . $e->getMessage()));

            return self::FAILURE;
        }
    }
}