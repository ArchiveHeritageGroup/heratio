<?php

namespace App\Auth;

use AhgCore\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Custom UserProvider for Heratio's dual-layer password scheme.
 *
 * Passwords are stored as:
 *   password_hash = password_hash(sha1(salt . plaintext), PASSWORD_DEFAULT)
 *
 * The inner SHA1 layer is legacy; the outer layer uses PHP's password_hash()
 * which may be Bcrypt or Argon2i.
 */
class AtomUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return User::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Heratio does not use remember tokens
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Heratio does not use remember tokens
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $emailOrUsername = $credentials['email'] ?? $credentials['username'] ?? null;

        if (! $emailOrUsername) {
            return null;
        }

        // Try email first, then username
        $user = User::where('email', $emailOrUsername)->first();

        if (! $user) {
            $user = User::where('username', $emailOrUsername)->first();
        }

        return $user;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (! $user->active) {
            return false;
        }

        $password = $credentials['password'] ?? '';

        // Dual-layer: SHA1(salt + password) -> password_verify(sha1Hash, storedHash)
        $sha1Hash = sha1($user->salt . $password);

        return password_verify($sha1Hash, $user->password_hash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // Heratio manages its own password hashing; no rehashing needed
    }
}
