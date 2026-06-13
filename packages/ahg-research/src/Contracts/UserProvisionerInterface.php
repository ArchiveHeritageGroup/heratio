<?php

namespace AhgResearch\Contracts;

interface UserProvisionerInterface
{
    /**
     * Create a new application user (actor + user + slug) and return the new id.
     * Must leave the account inactive by default (caller activates when appropriate).
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @return int new user id
     */
    public function createUser(string $username, string $email, string $password): int;

    /**
     * Update an existing user row. Returns true on success.
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateUser(int $userId, array $data): bool;

    /**
     * Add a user to an ACL group.
     *
     * @param int $userId
     * @param int $groupId
     * @return bool
     */
    public function addToGroup(int $userId, int $groupId): bool;

    /**
     * Deactivate (set active = 0) the given user id.
     *
     * @param int $userId
     * @return bool
     */
    public function deactivateUser(int $userId): bool;

    /**
     * Find a user record by email. Returns object or null.
     *
     * @param string $email
     * @return object|null
     */
    public function findByEmail(string $email): ?object;

    /**
     * Whether the user is a member of the given ACL group.
     *
     * @param int $userId
     * @param int $groupId
     * @return bool
     */
    public function isInGroup(int $userId, int $groupId): bool;

    /**
     * Reset a user's password using the canonical auth scheme (salt + sha1 +
     * argon2), so callers never hand-roll hashing. Returns true on success.
     *
     * @param int $userId
     * @param string $password plaintext
     * @return bool
     */
    public function setPassword(int $userId, string $password): bool;
}
