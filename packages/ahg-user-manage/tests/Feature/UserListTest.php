<?php

/**
 * UserListTest - the user list shows every user (#1558 follow-up).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgUserManage\Services\UserBrowseService;
use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserListTest extends TestCase
{
    use DatabaseTransactions;

    private function find(string $q, string $status): array
    {
        return (new UserBrowseService('en'))->browse(['subquery' => $q, 'status' => $status, 'sort' => 'alphabetic', 'page' => 1, 'limit' => 10]);
    }

    public function test_a_user_saved_without_a_name_is_listed_and_found_by_username_and_email(): void
    {
        $u = 'listprobe'.bin2hex(random_bytes(3));
        app(UserService::class)->create(['username' => $u, 'email' => "{$u}@test.invalid", 'password' => 'x'.bin2hex(random_bytes(8)),
            'authorized_form_of_name' => '', 'active' => 1, 'groups' => []]);

        $byName = $this->find($u, 'all');
        $this->assertSame(1, $byName['total']);
        $this->assertSame($u, $byName['hits'][0]['name']);
        $this->assertSame(1, $this->find("{$u}@test", 'all')['total']);
    }

    public function test_inactive_users_are_listed_only_under_inactive(): void
    {
        $u = 'listprobe'.bin2hex(random_bytes(3));
        app(UserService::class)->create(['username' => $u, 'email' => "{$u}@test.invalid", 'password' => 'x'.bin2hex(random_bytes(8)),
            'authorized_form_of_name' => "Probe {$u}", 'active' => 0, 'groups' => []]);

        $this->assertSame(0, $this->find($u, 'active')['total']);
        $this->assertSame(1, $this->find($u, 'inactive')['total']);
    }
}
