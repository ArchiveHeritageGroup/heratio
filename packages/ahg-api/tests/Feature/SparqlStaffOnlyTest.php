<?php

/**
 * SparqlStaffOnlyTest - raw SPARQL over the RiC triple store is for
 * administrators and editors only: the store is not filtered by publication,
 * embargo, classification or ICIP protocol.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgUserManage\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SparqlStaffOnlyTest extends TestCase
{
    use DatabaseTransactions;

    private const QUERY = 'SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }';

    private function key(?int $groupId): string
    {
        $user = app(UserService::class)->create(['username' => 'sq'.bin2hex(random_bytes(4)), 'email' => 'sq'.bin2hex(random_bytes(4)).'@test.invalid',
            'password' => 'x'.bin2hex(random_bytes(8)), 'authorized_form_of_name' => 'SPARQL caller', 'active' => 1, 'groups' => []]);
        if ($groupId) {
            DB::table('acl_user_group')->insert(['user_id' => $user, 'group_id' => $groupId]);
        }
        Cache::forget("acl_groups_{$user}");

        return app(\AhgApi\Services\ApiKeyService::class)->issue($user, 'sparql test', ['read'])['api_key'];
    }

    public function test_anonymous_callers_are_refused_on_both_endpoints(): void
    {
        foreach (['/api/ric/v1/sparql', '/api/sparql'] as $path) {
            $status = $this->getJson($path.'?query='.urlencode(self::QUERY))->getStatusCode();
            $this->assertContains($status, [401, 403], "{$path} answered {$status} to an anonymous caller");
        }
    }

    public function test_a_key_without_staff_rights_is_refused(): void
    {
        $key = $this->key(null);
        foreach (['/api/ric/v1/sparql', '/api/sparql'] as $path) {
            $this->withHeaders(['X-API-Key' => $key])->getJson($path.'?query='.urlencode(self::QUERY))->assertForbidden();
        }
    }

    public function test_an_editor_key_gets_through(): void
    {
        $key = $this->key(\AhgCore\Models\AclGroup::EDITOR_ID);
        foreach (['/api/ric/v1/sparql', '/api/sparql'] as $path) {
            // Past the lock: whatever the triple store answers here, it is not a refusal.
            $status = $this->withHeaders(['X-API-Key' => $key])->getJson($path.'?query='.urlencode(self::QUERY))->getStatusCode();
            $this->assertNotContains($status, [401, 403], "{$path} refused an editor ({$status})");
        }
    }
}
