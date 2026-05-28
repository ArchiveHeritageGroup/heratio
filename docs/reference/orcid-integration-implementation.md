# ORCID Integration Implementation Reference

**Package:** `packages/ahg-research/`
**Status:** Code complete (shipped v1.120.x). Inert until ORCID app credentials are set in `.env`.

## Surface

| Component | Path |
|---|---|
| Service | `src/Services/OrcidService.php` |
| Controller methods | `src/Controllers/ResearchController.php` (orcid* methods) |
| Sync command | `src/Console/Commands/OrcidSyncCommand.php` (`ahg:orcid-sync`, daily 01:30) |
| Link table | `researcher_orcid_link` (+ `last_profile_synced_at` migration) |
| Profile column | `research_researcher.orcid_id` |
| Register/profile JS | `public/vendor/ahg-research/js/orcid-fetch.js` |
| Views | `research/orcid-link.blade.php`, `research/register.blade.php`, `research/public-register.blade.php`, `research/profile.blade.php` |

## OrcidService API

| Method | Purpose |
|---|---|
| `isConfigured()` | true when CLIENT_ID + SECRET + REDIRECT_URI all set |
| `authorizeUrl()` | 3-legged OAuth authorize URL (scope `/authenticate /read-limited /activities/update`) |
| `exchangeCode($code)` | OAuth code -> token |
| `linkResearcher($id, $token)` | store encrypted token in researcher_orcid_link |
| `publicReadToken()` | 2-legged client-credentials `/read-public` token (cached 19 days) |
| `fetchPublicRecord($orcidId)` | GET /v3.0/{id}/record -> {first_name,last_name,credit_name,institution,department,position,research_interests,emails} |
| `pullProfile($researcherId)` | apply public record to research_researcher |
| `pullWorks($researcherId)` | GET /v3.0/{id}/works (needs OAuth token) |
| `pushWork($researcherId,$citation)` | POST a work (Member API + write scope) |
| `normaliseOrcidId($raw)` | accept bare iD or URL -> canonical 0000-0000-0000-0000 |

## Routes

```
POST research/orcid/fetch-public   research.orcidFetchPublic  (public, rate-limited 20/min/IP)
POST research/orcid/pull-profile   research.orcidPullProfile  (auth)
GET  research/orcid                research.orcid
GET  research/orcid/authorize      research.orcidAuthorize
GET  research/orcid/callback       research.orcidCallback
POST research/orcid/sync           research.orcidSync         (pull works)
POST research/orcid/unlink         research.orcidUnlink
```

## Field mapping (ORCID record -> research_researcher)

| ORCID | column |
|---|---|
| person.name.given-names | first_name |
| person.name.family-name | last_name |
| employments[0].organization.name | institution |
| employments[0].department-name | department |
| employments[0].role-title | position |
| person.keywords.keyword[] | research_interests (comma-joined) |

## Config (.env)

```
ORCID_CLIENT_ID=APP-XXXXXXXX        # orcid.org/developer-tools (not an ORCID iD)
ORCID_CLIENT_SECRET=<uuid>          # not a password
ORCID_REDIRECT_URI=https://<host>/research/orcid/callback
ORCID_BASE=https://orcid.org        # sandbox.orcid.org for testing
ORCID_API_BASE=https://pub.orcid.org # api.orcid.org for Member API
```

`isConfigured()` returns false on empty CLIENT_ID/SECRET, so the Fetch/Connect endpoints return a clean "not configured" message rather than erroring.

## Security

- Personal ORCID passwords are never received by Heratio (entered only at orcid.org during OAuth).
- Access tokens stored encrypted (`access_token_encrypted`, AES-256-CBC keyed off app.key).
- Unlink deletes the token.
