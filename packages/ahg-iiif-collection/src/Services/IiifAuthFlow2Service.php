<?php

/**
 * IiifAuthFlow2Service - Service for Heratio
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

namespace AhgIiifCollection\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IIIF Authorization Flow 2.0 service (issue #696).
 *
 * Spec: https://iiif.io/api/auth/2.0/
 *
 * Auth 2.0 reworks the 1.0 flow into a "probe first" model:
 *
 *   1. The viewer hits the ProbeService for a resource. The service
 *      returns 200 with status=ok if access is allowed (the viewer
 *      proceeds), or 401 + a service description telling the viewer
 *      how to obtain credentials (AccessService).
 *   2. The viewer drives the AccessService (active / external / kiosk).
 *      On completion the AccessService redirects back with a session.
 *   3. The viewer requests an access token from the AccessTokenService.
 *      The token rides as a Bearer header on subsequent probe / image
 *      requests.
 *
 * Heratio's session auth is the source of truth - we don't issue an
 * out-of-band access token; we reflect the session cookie back as the
 * Bearer token so existing infrastructure (CSRF, IP throttling, the
 * AdminMiddleware redirect-loop guard) keeps working.
 *
 * Clearance integration: when a resource demands a clearance level (via
 * iiif_auth_resource.classification_id_required, populated by the
 * ahg-security-clearance package), the ProbeService consults
 * SecurityClearanceService and only returns 200 when the logged-in user's
 * level meets or exceeds the required level. Anonymous + insufficient
 * levels get the spec-correct 401 with the AccessService description.
 */
class IiifAuthFlow2Service
{
    /**
     * Build a ProbeService response for the given resource URI. The
     * resource URI is opaque to the spec - we use the manifest IRI here,
     * but it could be any IIIF resource (image, canvas, etc.).
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function probe(string $resourceUri): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $probeId = $baseUrl . '/iiif/auth/2/probe?resource=' . urlencode($resourceUri);
        $accessId = $baseUrl . '/iiif/auth/2/access';
        $tokenId = $baseUrl . '/iiif/auth/2/token';

        $required = $this->resolveClearanceRequirement($resourceUri);
        $allowed = $this->isAccessAllowed($required);

        if ($allowed) {
            return [
                'status' => 200,
                'body' => [
                    '@context' => 'http://iiif.io/api/auth/2/context.json',
                    'id' => $probeId,
                    'type' => 'AuthProbeResult2',
                    'status' => 200,
                    'heading' => ['en' => ['Access granted']],
                ],
            ];
        }

        // 401 + service block telling the viewer where to log in.
        return [
            'status' => 401,
            'body' => [
                '@context' => 'http://iiif.io/api/auth/2/context.json',
                'id' => $probeId,
                'type' => 'AuthProbeResult2',
                'status' => 401,
                'heading' => ['en' => ['Authorization required']],
                'note' => ['en' => [$required
                    ? 'This resource requires security clearance level ' . $required . '.'
                    : 'Sign in to access this resource.']],
                'service' => [
                    $this->buildAccessService($accessId, $tokenId, 'active'),
                ],
            ],
        ];
    }

    /**
     * Build the AccessService block. `profile` is active / external /
     * kiosk per Auth 2.0 spec.
     *
     *   active   - viewer pops a window, user signs in interactively
     *   external - credentials live in another site (we redirect)
     *   kiosk    - shared-device access, no per-user credentials
     *
     * @return array<string,mixed>
     */
    public function buildAccessService(string $accessId, string $tokenId, string $profile): array
    {
        if (!in_array($profile, ['active', 'external', 'kiosk'], true)) {
            $profile = 'active';
        }
        return [
            'id' => $accessId,
            'type' => 'AuthAccessService2',
            'profile' => $profile,
            'label' => ['en' => [match ($profile) {
                'external' => 'Sign in via external provider',
                'kiosk' => 'Kiosk access',
                default => 'Sign in to view this resource',
            }]],
            'service' => [[
                'id' => $tokenId,
                'type' => 'AuthAccessTokenService2',
            ]],
        ];
    }

    /**
     * Build the AccessToken response. The spec mandates a JSON document
     * carrying `accessToken`, `expiresIn`, `type=AuthAccessToken2`. We
     * mint an opaque token bound to the current session - it's only a
     * mirror of the session cookie, but the spec doesn't constrain
     * the token format.
     *
     * @return array{status:int,body:array<string,mixed>}
     */
    public function issueAccessToken(?string $origin, ?string $messageId): array
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $tokenId = $baseUrl . '/iiif/auth/2/token';

        if (!Auth::check()) {
            return [
                'status' => 401,
                'body' => [
                    '@context' => 'http://iiif.io/api/auth/2/context.json',
                    'id' => $tokenId,
                    'type' => 'AuthAccessTokenError2',
                    'profile' => 'missingCredentials',
                    'heading' => ['en' => ['Authentication required']],
                    'note' => ['en' => ['Sign in before requesting a token.']],
                    'messageId' => $messageId,
                ],
            ];
        }

        // The token itself is opaque to the spec. We hash the session id
        // so that revoking the session also invalidates every token
        // minted against it.
        $session = session()->getId();
        $token = hash('sha256', $session . '|' . Auth::id());
        $expiresIn = 3600;

        // Persist in iiif_auth_token (existing table) for audit trail
        // when present. Best-effort - the spec response is what matters
        // for compatibility.
        try {
            if (Schema::hasTable('iiif_auth_token')) {
                DB::table('iiif_auth_token')->insertOrIgnore([
                    'token_hash' => $token,
                    'user_id' => Auth::id(),
                    'service_id' => $this->ensureAuth2Service(),
                    'session_id' => $session,
                    'ip_address' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 500),
                    'issued_at' => now(),
                    'expires_at' => now()->addSeconds($expiresIn),
                    'is_revoked' => 0,
                ]);
            }
        } catch (\Throwable $e) {
            // Audit-trail failure must not break the auth flow.
        }

        return [
            'status' => 200,
            'body' => [
                '@context' => 'http://iiif.io/api/auth/2/context.json',
                'id' => $tokenId,
                'type' => 'AuthAccessToken2',
                'accessToken' => $token,
                'expiresIn' => $expiresIn,
                'messageId' => $messageId,
            ],
        ];
    }

    /**
     * Look up the IIIF auth service row for the Auth 2.0 active profile,
     * inserting it if missing. Returns service.id for FK use.
     */
    private function ensureAuth2Service(): int
    {
        $row = DB::table('iiif_auth_service')
            ->where('name', 'login-v2')
            ->first();
        if ($row) {
            return (int) $row->id;
        }
        return (int) DB::table('iiif_auth_service')->insertGetId([
            'name' => 'login-v2',
            'profile' => 'login',
            'auth_version' => '2.0',
            'access_profile' => 'active',
            'label' => 'Login Required',
            'is_active' => 1,
        ]);
    }

    /**
     * Look up the clearance level required for a resource. The Auth 2.0
     * spec is silent on application-level access policy; we map our
     * iiif_auth_resource sidecar table onto the spec response.
     *
     * Returns null when the resource has no clearance requirement
     * (anonymous access permitted).
     */
    public function resolveClearanceRequirement(string $resourceUri): ?int
    {
        try {
            if (!Schema::hasTable('iiif_auth_resource')) {
                return null;
            }
            // The manifest IRI ends in /iiif-manifest/<slug>. Resolve
            // the slug back to information_object.id, then look up the
            // sidecar row.
            if (!preg_match('#/iiif-manifest/([^/?#]+)#', $resourceUri, $m)) {
                return null;
            }
            $slug = $m[1];
            $ioId = DB::table('slug')->where('slug', $slug)->value('object_id');
            if (!$ioId) {
                return null;
            }
            // classification_id_required is added by ahg-security-clearance
            // when the module is installed. Schema::hasColumn keeps us
            // safe for installs that don't have the column yet.
            if (Schema::hasColumn('iiif_auth_resource', 'classification_id_required')) {
                $level = DB::table('iiif_auth_resource as r')
                    ->leftJoin('security_classification as sc', 'r.classification_id_required', '=', 'sc.id')
                    ->where('r.object_id', $ioId)
                    ->value('sc.level');
                if ($level !== null) {
                    return (int) $level;
                }
            }
            // Fallback: any iiif_auth_resource row means "auth required",
            // without a specific clearance level.
            $exists = DB::table('iiif_auth_resource')->where('object_id', $ioId)->exists();
            if ($exists) {
                return 0;
            }
        } catch (\Throwable $e) {
            // Probe-time DB errors should not 500 the auth flow; treat
            // as unknown -> no requirement -> public.
        }
        return null;
    }

    /**
     * Check whether the current request has access given a required
     * clearance level (null = public).
     */
    public function isAccessAllowed(?int $requiredLevel): bool
    {
        if ($requiredLevel === null) {
            return true;
        }
        if (!Auth::check()) {
            return false;
        }
        if ($requiredLevel === 0) {
            // Authentication is sufficient; no specific level required.
            return true;
        }
        // Defer to SecurityClearanceService when the package is installed.
        if (class_exists(\AhgSecurityClearance\Services\SecurityClearanceService::class)) {
            try {
                $svc = app(\AhgSecurityClearance\Services\SecurityClearanceService::class);
                $userLevel = (int) $svc->getUserClearanceLevel((int) Auth::id());
                return $userLevel >= $requiredLevel;
            } catch (\Throwable $e) {
                // Fail closed: if the clearance service can't tell us,
                // don't grant access.
                return false;
            }
        }
        return false;
    }
}
