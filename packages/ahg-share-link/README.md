# ahg-share-link

Time-limited, auditable share links for `information_object` records in Heratio. Curators issue a tokenised URL that lets an anonymous (or named) recipient view a single archival description for a bounded window, with full audit trail and admin revocation.

## Purpose

- Anonymous bearer-token access without granting an account
- HMAC-derived URL-safe tokens (32 to 64 chars)
- Classified-record gating via `ClearanceCheck`
- Per-share expiry cap (`ExpiryCapExceededException`)
- Admin revoke + per-action audit dual-write
- Cron-driven prune of expired rows

## Install

The package is auto-discovered through Heratio's composer path repositories. On first boot the ServiceProvider runs `database/install.sql` (gated by `Schema::hasTable`) and registers routes, views, and the `PruneCommand`.

```bash
# Optional manual prune
php artisan share-link:prune
```

## Routes

- `GET /share/{token}` - recipient view (no auth, token is the credential)
- `GET /share-link/new` - curator issuance form
- `POST /share-link/issue` - issue a token (auth)
- `GET /share-link/issued/{tokenId}` - post-issue success page
- `GET /admin/share-links` - admin index (ACL: `share_link.list_all`)
- `POST /admin/share-links/{id}/revoke` - admin revoke

## Key classes

| Class | Role |
|---|---|
| `Services\TokenService` | HMAC token issue + verify |
| `Services\AccessService` | Recipient-side validation pipeline (returns `AccessResult`) |
| `Services\IssueService` | Curator-side issuance with cap enforcement |
| `Services\RevokeService` | Admin revoke + audit row |
| `Services\PruneService` | Background expiry sweep |
| `Http\Middleware\ShareLinkInjector` | Adds "Share this record" UI hook |
| `Controllers\ShareLinkAdminController` | `/admin/share-links` |
| `Controllers\ShareLinkRecipientController` | `/share/{token}` |
| `Controllers\ShareLinkIssueController` | Issuance form + POST |

## Configuration

Knobs live in `ahg_settings` (Settings dashboard, `share_link.*` keys): default expiry, maximum expiry cap, token length, audit retention.

## Database

`database/install.sql` ships the `share_link` token table + audit dual-write. `database/seed-acl-permissions.sql` seeds the `share_link.list_all` ACL row.
