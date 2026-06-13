<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ResearchUserProvisionerTest extends TestCase
{
    // Runs against the pre-built heratio_test DB (core SQL + install.sql) and
    // rolls back each test. NOT RefreshDatabase - that drops the ~995 base
    // tables (object/actor/slug/user) this provisioner writes to. See #1136.
    use DatabaseTransactions;

    /**
     * A basic test to check the UserProvisioner create/add/find flows.
     */
    public function test_create_user_and_add_to_group()
    {
        $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);

        $username = 'testuser_' . Str::random(6);
        $email = $username . '@example.test';
        $password = 'Password123!';

        // Create user
        $userId = $provisioner->createUser($username, $email, $password);
        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);

        // Find by email
        $found = $provisioner->findByEmail($email);
        $this->assertNotNull($found);
        $this->assertEquals($email, $found->email);

        // Add to group (use a test group id 99 if present)
        $added = $provisioner->addToGroup($userId, 99);
        $this->assertTrue($added);

        // Verify ACL entry exists
        $exists = DB::table('acl_user_group')->where('user_id', $userId)->where('group_id', 99)->exists();
        $this->assertTrue($exists);
    }

    /**
     * Test update and deactivate operations
     */
    public function test_update_and_deactivate_user()
    {
        $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);

        $username = 'testuser_' . Str::random(6);
        $email = $username . '@example.test';
        $password = 'Password123!';

        $userId = $provisioner->createUser($username, $email, $password);
        $this->assertIsInt($userId);

        $ok = $provisioner->updateUser($userId, ['active' => 1]);
        $this->assertTrue($ok);

        $row = DB::table('user')->where('id', $userId)->first();
        $this->assertEquals(1, (int) $row->active);

        $deact = $provisioner->deactivateUser($userId);
        $this->assertTrue($deact);

        $row2 = DB::table('user')->where('id', $userId)->first();
        $this->assertEquals(0, (int) $row2->active);
    }

    /**
     * isInGroup reflects membership after addToGroup.
     */
    public function test_is_in_group()
    {
        $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);

        $username = 'testuser_' . Str::random(6);
        $userId = $provisioner->createUser($username, $username . '@example.test', 'Password123!');

        $this->assertFalse($provisioner->isInGroup($userId, 100));
        $provisioner->addToGroup($userId, 100);
        $this->assertTrue($provisioner->isInGroup($userId, 100));
    }

    /**
     * setPassword writes a salt + argon2 hash that verifies against the
     * canonical scheme (salt + sha1 pre-hash), matching login.
     */
    public function test_set_password_uses_canonical_scheme()
    {
        $provisioner = app(\AhgResearch\Contracts\UserProvisionerInterface::class);

        $username = 'testuser_' . Str::random(6);
        $userId = $provisioner->createUser($username, $username . '@example.test', 'initial');

        $newPassword = 'Rotated123!';
        $this->assertTrue($provisioner->setPassword($userId, $newPassword));

        $row = DB::table('user')->where('id', $userId)->first();
        $this->assertNotEmpty($row->salt);
        // Login verifies password_hash(sha1(salt . password)) against the stored argon2 hash.
        $this->assertTrue(password_verify(sha1($row->salt . $newPassword), $row->password_hash));
    }
}
