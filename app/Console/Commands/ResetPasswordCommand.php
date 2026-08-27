<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Auth\LoginController;
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

        // Hash through the SAME implementation the login uses.
        //
        // This wrote a bare sha1($salt.$password), but AtomUserProvider
        // verifies with password_verify(sha1(salt . plaintext), password_hash)
        // - and password_verify against a raw SHA-1 can never return true. So
        // every account reset by this command was locked out permanently, with
        // no error at reset time and "credentials do not match our records" at
        // login. LoginController::hashPassword is the one definition of the
        // scheme; a fourth copy is how this drifted in the first place.
        ['salt' => $salt, 'password_hash' => $passwordHash] = LoginController::hashPassword($password);

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
            $this->error('Failed to reset password: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
