> Heratio Help Center article. Category: Research / API.

# Research API Keys

Approved researchers can generate personal API keys to access their own research data programmatically over a REST interface. Keys are self-service: you create, view, and revoke them from your researcher profile. Each key carries a scope (read / write / search), an optional expiry date, and a rate limit. The raw key value is shown only once at creation time and stored as a one-way hash thereafter, so it can never be recovered or displayed again.

## Overview

The Research API lets you work with your research workspace from scripts, notebooks, reference managers, or your own applications instead of clicking through the web interface. Typical uses include pulling your project list into an external tool, syncing saved searches, exporting bibliographies, or checking your booking schedule.

Access is controlled by an API key tied to your researcher account. You must be a registered researcher whose account has been **approved** before you can generate a key. Keys inherit the data boundaries of your own account: the API surfaces *your* projects, *your* evidence sets, *your* bookings, and so on, not other researchers' data.

Under the hood the key is stored in the shared `ahg_api_key` table (the same key store used by the platform REST API). Your selected permissions are translated into scopes, and the platform's API authentication layer validates every request against that scope set, the active flag, and the expiry date.

## Key features

- **Self-service key management** from your researcher profile - no administrator action required.
- **Named keys** so you can tell apart the key used by, for example, your thesis script from one used by a reference manager.
- **Scoped permissions** - choose any combination of Read, Write, and Search when you generate a key.
- **Optional expiry date** - leave it blank for a key that never expires, or set a date after which the key is automatically rejected.
- **One-time display** - the full key value appears once, immediately after generation. It is hashed (SHA-256) in storage, so it cannot be shown again.
- **Key prefix shown in the list** - the first 8 characters are retained in plain text so you can identify a key in the table without exposing the secret.
- **Last-used tracking** - each successful API request updates the key's "last used" timestamp, helping you spot stale or unused keys.
- **Per-key rate limit** - keys are issued with a request rate limit (1000 by default).
- **Instant revocation** - revoking a key deactivates it immediately; subsequent requests with that key are rejected.

## How to use

### Generate a key

1. Sign in as an approved researcher.
2. Open your research area and go to **Profile -> API Keys** (route name `research.apiKeys`, URL `/research/apiKeys`).
3. Click **Generate Key**. A dialog opens.
4. Fill in the form:
   - **Key Name** (required) - a descriptive label, for example "My Research App".
   - **Permissions** (optional) - tick any of:
     - **Read** - read collections, annotations, and bibliographies. Selecting Read grants the `read` and `search` scopes.
     - **Write** - create and update collections and annotations. Selecting Write grants the `write`, `create`, and `update` scopes.
     - **Search** - query the catalogue. Selecting Search grants the `search` scope.
     - Read is ticked by default. If you tick none, the key is created with an empty scope set.
   - **Expiry Date** (optional) - a date after which the key stops working. Leave empty for no expiration.
5. Click **Generate**.
6. The full key value is displayed once, on a green confirmation banner, in the form `rk_` followed by a long hexadecimal string. **Copy it immediately** - it will not be shown again. If you lose it, revoke the key and generate a new one.

### Authenticate your requests

Include your key with every request in one of these ways:

- **Header (recommended):** `X-API-Key: YOUR_API_KEY`
- **Bearer token:** `Authorization: Bearer YOUR_API_KEY`
- **Query parameter:** append `?api_key=YOUR_API_KEY`

Example:

```
curl -H "X-API-Key: rk_xxxxxxxxxxxxxxxx" https://your-site.example/api/research/profile
```

The API base URL is `/api/research` (shown on the API Keys page as the API Base URL for your installation).

### Available endpoints

All endpoints are relative to the `/api/research` base URL.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profile` | Get your researcher profile |
| GET | `/projects` | List your projects |
| POST | `/projects` | Create a project |
| GET | `/collections` | List your evidence sets |
| POST | `/collections` | Create an evidence set |
| GET | `/searches` | List your saved searches |
| GET | `/bookings` | List your bookings |
| POST | `/bookings` | Create a booking |
| GET | `/bibliographies` | List your bibliographies |
| GET | `/annotations` | List your annotations |
| GET | `/stats` | Get your usage statistics |

GET endpoints require a key with the `read` (or `search`) scope; POST endpoints require the `write` family of scopes. A request with a key that lacks the required scope is rejected.

### View and identify your keys

On the **API Keys** page each key is listed with:

- **Name** - the label you gave it.
- **Key** - the 8-character prefix followed by `...` (the secret itself is never shown again).
- **Created** - the date the key was generated.
- **Last Used** - the timestamp of the most recent successful request, or "Never".
- **Expires** - the expiry date, or "Never". An expired date is shown in red.
- **Status** - **Active**, **Expired**, or **Revoked**.
- **Action** - a **Revoke** button for active keys.

### Revoke a key

1. On the **API Keys** page, find the key in the table.
2. Click **Revoke** in its **Action** column.
3. Confirm the prompt. The key is deactivated immediately and its status changes to **Revoked**.

Revocation cannot be undone. A revoked key can no longer authenticate any request. If you still need API access, generate a new key.

## Configuration

There is no separate configuration screen for researchers - key creation, scoping, and expiry are all set on the **Generate New API Key** dialog. Notes on behaviour:

- **Approval gate.** Only researchers whose account status is `approved` can reach the API Keys page; others are redirected back to the research dashboard with a notice. Registration and approval are covered in the "Access Requests and Researcher Portal" guide.
- **Scope mapping.** The Read / Write / Search checkboxes are mapped to underlying scopes when the key is created (Read -> `read`, `search`; Write -> `write`, `create`, `update`; Search -> `search`). The stored scope list is what the API enforces.
- **Rate limit.** Keys are created with a default rate limit of 1000. This is set at generation time and is not editable from the researcher profile.
- **Expiry enforcement.** If an expiry date is set and has passed, authentication fails with an "API key has expired" message even though the key row still exists.
- **Storage and security.** The key is stored as a SHA-256 hash plus an 8-character prefix; the plain-text secret is never persisted. Treat your key like a password: do not commit it to source control, embed it in shared notebooks, or paste it into public channels.
- **Key store.** Researcher keys live in the same `ahg_api_key` table used by the platform-wide REST API, so the platform's API authentication and logging apply uniformly.

## References

Source packages and files used to document this article (verified against current code):

- `packages/ahg-research/src/Controllers/ResearchApiKeysController.php` - the auth-gated `apiKeys` endpoint handling list, generate, and revoke actions.
- `packages/ahg-research/src/Services/ResearchService.php` - `getApiKeys`, `generateApiKey` (key format `rk_` + 32 random bytes, SHA-256 hash, scope mapping, default rate limit), and `revokeApiKey`.
- `packages/ahg-research/routes/web.php` - the `research.apiKeys` route (`/research/apiKeys`, GET and POST).
- `packages/ahg-research/resources/views/research/api-keys.blade.php` - the API Keys page: key table, generate modal, permissions checkboxes, endpoint list, and the `X-API-Key` / `api_key` authentication notes.
- `packages/ahg-api/src/Middleware/ApiAuthenticate.php` - validation of `X-API-Key` / `X-REST-API-Key` / Bearer tokens, expiry check, `last_used_at` update, and scope enforcement against the `ahg_api_key` table.
- `packages/ahg-api/database/install.sql` - the `ahg_api_key` table schema (name, hashed key, prefix, scopes, rate_limit, expires_at, last_used_at, is_active).
