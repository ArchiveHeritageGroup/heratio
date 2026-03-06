<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetPasswordCommand extends Command
{
    protected $signature = 'heratio:user:reset-password
                            {identifier : Email address or username}
                            {--password= : New password (will prompt if not provided)}';

    protected $description = 'Reset a user\'s password';

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        // Find user by email or username
        $user = DB::table('user')
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (! $user) {
            $this->error("No user found with email or username: {$identifier}");

            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Enter new password');

        if (! $password) {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        // Generate new salt and hash (SHA-1 with salt)
        $salt = bin2hex(random_bytes(16)); // 32-char hex salt
        $passwordHash = sha1($salt . $password);

        try {
            DB::table('user')
                ->where('id', $user->id)
                ->update([
                    'password_hash' => $passwordHash,
                    'salt' => $salt,
                ]);

            $this->info("Password reset successfully for user: {$user->username} ({$user->email})");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to reset password: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
