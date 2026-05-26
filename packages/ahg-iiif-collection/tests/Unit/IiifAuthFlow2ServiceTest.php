<?php

/**
 * IiifAuthFlow2ServiceTest - Unit tests for the Auth Flow 2.0 probe shape.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgIiifCollection\Tests\Unit;

use AhgIiifCollection\Services\IiifAuthFlow2Service;
use Tests\TestCase;

/**
 * Unit tests for IiifAuthFlow2Service. We exercise the probe response
 * envelope (id / context / type / status), the AccessService builder,
 * and clearance-gating fallbacks. The "allowed" branch is exercised
 * for a public resource (no iiif_auth_resource row) where the service
 * returns 200 + AuthProbeResult2 with status=200.
 *
 * The DB-touching probe paths use the framework TestCase so config(),
 * Auth, DB facades all resolve. We don't seed iiif_auth_resource here;
 * the "no requirement" branch is the dominant production case and
 * the test focuses on response shape.
 */
class IiifAuthFlow2ServiceTest extends TestCase
{
    public function test_probe_for_public_resource_returns_200_auth_probe_result(): void
    {
        $svc = new IiifAuthFlow2Service();
        // Use a manifest IRI whose slug almost certainly doesn't resolve
        // to an information_object on the test DB - the resolver falls
        // through to "no requirement", and the probe returns 200.
        $resource = config('app.url') . '/iiif-manifest/__non_existent_slug_for_test__';
        $result = $svc->probe($resource);

        $this->assertSame(200, $result['status']);
        $body = $result['body'];
        $this->assertSame('http://iiif.io/api/auth/2/context.json', $body['@context']);
        $this->assertSame('AuthProbeResult2', $body['type']);
        $this->assertSame(200, $body['status']);
        $this->assertArrayHasKey('id', $body);
        $this->assertStringContainsString('/iiif/auth/2/probe', $body['id']);
        $this->assertStringContainsString(urlencode($resource), $body['id']);
        // Public branch must NOT carry an AccessService block.
        $this->assertArrayNotHasKey('service', $body);
    }

    public function test_build_access_service_emits_spec_active_profile(): void
    {
        $svc = new IiifAuthFlow2Service();
        $accessId = 'https://heratio.example/iiif/auth/2/access';
        $tokenId = 'https://heratio.example/iiif/auth/2/token';
        $block = $svc->buildAccessService($accessId, $tokenId, 'active');

        $this->assertSame('AuthAccessService2', $block['type']);
        $this->assertSame('active', $block['profile']);
        $this->assertSame($accessId, $block['id']);
        $this->assertArrayHasKey('label', $block);
        $this->assertArrayHasKey('en', $block['label']);
        $this->assertSame('AuthAccessTokenService2', $block['service'][0]['type']);
        $this->assertSame($tokenId, $block['service'][0]['id']);
    }

    public function test_build_access_service_rejects_unknown_profile_and_defaults_to_active(): void
    {
        $svc = new IiifAuthFlow2Service();
        $block = $svc->buildAccessService('a', 'b', 'wat');
        $this->assertSame('active', $block['profile']);
    }

    public function test_build_access_service_supports_external_and_kiosk_profiles(): void
    {
        $svc = new IiifAuthFlow2Service();
        foreach (['external', 'kiosk', 'active'] as $profile) {
            $block = $svc->buildAccessService('a', 'b', $profile);
            $this->assertSame($profile, $block['profile']);
        }
    }

    public function test_is_access_allowed_for_null_requirement_is_public(): void
    {
        $svc = new IiifAuthFlow2Service();
        $this->assertTrue($svc->isAccessAllowed(null));
    }

    public function test_is_access_allowed_for_anonymous_with_requirement_denies(): void
    {
        $svc = new IiifAuthFlow2Service();
        // Anonymous (no Auth::check()) + any clearance requirement -> false.
        $this->assertFalse($svc->isAccessAllowed(0));
        $this->assertFalse($svc->isAccessAllowed(3));
    }
}
