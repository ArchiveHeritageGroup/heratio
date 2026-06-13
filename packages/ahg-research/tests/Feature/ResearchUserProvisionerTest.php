<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ResearchUserProvisionerTest extends TestCase
{
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
}
