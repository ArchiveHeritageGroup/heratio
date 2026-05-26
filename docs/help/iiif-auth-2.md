# IIIF Authorization 2.0

Heratio implements the IIIF Authorization Flow 2.0 protocol so that
third-party viewers (Mirador 4, Universal Viewer 4, federated harvesters)
can negotiate access to protected manifests without leaving the viewer.

## Overview

Auth 2.0 is a "probe first" flow:

1. The viewer asks the **ProbeService** whether the current user can see
   a resource.
2. If access is allowed, the probe returns HTTP 200 and the viewer
   proceeds.
3. If access is denied, the probe returns HTTP 401 plus an
   **AccessService** description telling the viewer where to send the
   user to sign in.
4. After the user signs in, the viewer fetches a token from the
   **AccessTokenService** and rides it on subsequent probe requests.

The legacy IIIF Auth 1.0 endpoints under `/iiif-auth/*` continue to
work; a single manifest can advertise both versions in its service block.

## Endpoints

| Endpoint | Method | Purpose |
|---|---|---|
| `/iiif/auth/2/probe?resource=<iri>` | GET | ProbeService. |
| `/iiif/auth/2/access` | GET | AccessService entry point. |
| `/iiif/auth/2/token` | GET | AccessTokenService. |

## Probe responses

**Access granted** (HTTP 200):

```json
{
  "@context": "http://iiif.io/api/auth/2/context.json",
  "id":       "<probe url>",
  "type":     "AuthProbeResult2",
  "status":   200,
  "heading":  { "en": ["Access granted"] }
}
```

**Access denied** (HTTP 401):

```json
{
  "@context": "http://iiif.io/api/auth/2/context.json",
  "id":       "<probe url>",
  "type":     "AuthProbeResult2",
  "status":   401,
  "heading":  { "en": ["Authorization required"] },
  "note":     { "en": ["Sign in to access this resource."] },
  "service":  [
    {
      "id":      "<access service url>",
      "type":    "AuthAccessService2",
      "profile": "active",
      "label":   { "en": ["Sign in to view this resource"] },
      "service": [{ "id": "<token url>", "type": "AuthAccessTokenService2" }]
    }
  ]
}
```

## Access profiles

The AccessService advertises one of three IIIF profiles:

- `active` - the viewer pops up a sign-in window. Standard interactive
  login.
- `external` - the user signs in on a different domain. The viewer
  redirects out and back.
- `kiosk` - shared-device access, no per-user credentials.

Heratio defaults to `active`. Site operators can change this per
auth-service row in `iiif_auth_service.access_profile`.

## Security clearance integration

When a resource is protected by a row in `iiif_auth_resource` (managed
by the `ahg-security-clearance` package), the probe consults the user's
clearance level via `SecurityClearanceService::getUserClearanceLevel()`.
The probe returns 200 only when the user's level meets or exceeds
`iiif_auth_resource.classification_id_required`. Anonymous users and
users with insufficient clearance get the 401 + AccessService response.

The clearance service is fail-closed: if it throws, the probe denies
access rather than granting it.

## Tokens

The AccessTokenService returns a JSON document on signed-in sessions:

```json
{
  "@context":   "http://iiif.io/api/auth/2/context.json",
  "id":         "<token url>",
  "type":       "AuthAccessToken2",
  "accessToken": "<opaque token>",
  "expiresIn":  3600
}
```

Tokens are opaque to the spec - Heratio derives the token from
`SHA-256(session_id | user_id)` so that revoking the underlying session
revokes every token minted under it. Tokens are persisted to
`iiif_auth_token` for audit but the audit step is best-effort and
doesn't block the response.

Anonymous requests to the token endpoint get HTTP 401 with an
`AuthAccessTokenError2` envelope (`profile = missingCredentials`).

## CORS

The AccessTokenService is called cross-origin by viewers, so the
controller emits `Access-Control-Allow-Origin: <request origin>` and
`Access-Control-Allow-Credentials: true` (the session cookie has to ride
along). `Vary: Origin` keeps caches honest.

## What to wire when adding a protected resource

1. Insert a row in `iiif_auth_service` describing the access profile
   (most sites only need the seeded `login-v2` row).
2. Insert a row in `iiif_auth_resource` mapping the information_object
   to that service.
3. (Optional) Set `iiif_auth_resource.classification_id_required` to a
   `security_classification.id` if the resource needs a clearance level.
4. Verify probe behaviour: an anonymous `curl` against
   `/iiif/auth/2/probe?resource=<manifest>` must return 401; the same
   curl with the user's session cookie returns 200.

## Related help articles

- [IIIF Content State](./iiif-content-state.md)
- [IIIF A/V Playback](./iiif-av-playback.md)
- [IIIF Content Search](./iiif-content-search.md)

## Reference

- IIIF Authorization Flow 2.0 - https://iiif.io/api/auth/2.0/
- IIIF Authorization Flow 1.0 (legacy, still supported) - https://iiif.io/api/auth/1.0/
