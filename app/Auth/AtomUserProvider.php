<?php

namespace App\Auth;

use AhgCore\Models\QubitUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Custom UserProvider for AtoM's dual-layer password scheme.
 *
 * AtoM stores passwords as:
 *   password_hash = password_hash(sha1(salt . plaintext), PASSWORD_DEFAULT)
 *
 * The inner SHA1 layer is legacy; the outer layer uses PHP's password_hash()
 * which may be Bcrypt or Argon2i depending on the AtoM version.
 */
class AtomUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return QubitUser::find($identifier);
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // AtoM does not use remember tokens
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // AtoM does not use remember tokens
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $emailOrUsername = $credentials['email'] ?? $credentials['username'] ?? null;

        if (! $emailOrUsername) {
            return null;
        }

        // Try email first, then username (matching AtoM's QubitUser::checkCredentials order)
        $user = QubitUser::where('email', $emailOrUsername)->first();

        if (! $user) {
            $user = QubitUser::where('username', $emailOrUsername)->first();
        }

        return $user;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! $user instanceof QubitUser) {
            return false;
        }

        if (! $user->active) {
            return false;
        }

        $password = $credentials['password'] ?? '';

        // AtoM dual-layer: SHA1(salt + password) → password_verify(sha1Hash, storedHash)
        $sha1Hash = sha1($user->salt . $password);

        return password_verify($sha1Hash, $user->password_hash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // AtoM manages its own password hashing; no rehashing needed
    }
}
