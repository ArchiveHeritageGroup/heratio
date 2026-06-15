# Share Link

> Share links let an authenticated curator generate a secure, expiring URL that gives an outside recipient read-only access to a single archival description, with optional access caps, recipient details, clearance enforcement for classified records, full audit logging and an admin console to monitor and revoke links.

## Overview

The Share Link module (`ahg-share-link`) issues tokenised public URLs of the form `/share/{token}`. The token itself is the credential, so recipients need no account; every access is validated and logged. Curators issue links from a record, an admin console lists and revokes them, and a console command prunes old tokens and access-log rows.

The module enforces several guards at issuance and at access time: ACL permissions, an expiry cap, per-link access quotas, security-classification clearance, revocation and expiry. Both issuance and access are dual-written to the central audit log.

## Key features

- **Tokenised recipient access** at `/share/{token}` with no login required; the 32-64 character token is the only credential.
- **Authenticated issuance** via a POST endpoint and a bookmarkable issuance form, returning the public URL, token and expiry.
- **Expiry control** - a default expiry (14 days) and a maximum cap (90 days), both configurable; issuing beyond the cap requires a dedicated permission.
- **Access quotas** - an optional maximum access count per link; once reached, the link denies further access as "exhausted".
- **Recipient metadata** - optional recipient email and note stored with the token.
- **Classification clearance** - for security-classified records, issuance additionally requires the classified-create permission and sufficient clearance.
- **Admin console** - list, filter (by status, issuer or search term), inspect (including the access log) and revoke links.
- **Audit and access logging** - issuance, each view, and every denial (revoked, expired, quota) are recorded with IP and user agent and dual-written to the audit log.
- **Retention pruning** - a console command applies retention rules to tokens and access-log rows.
- **Privacy headers** - recipient and denied pages send a `no-referrer` referrer policy.

## How to use

### Issue a share link (curator)

1. Sign in. Issuance requires an authenticated user with the share-link create permission.
2. From an archival description, open the share form, or visit `/share-link/new?information_object_id=<id>` to land on the bookmarkable form for that record.
3. Set the expiry date (within the configured cap), and optionally a recipient email, a note and a maximum access count.
4. Submit. The success page (`/share-link/issued/{tokenId}`) shows the public URL to copy and send to your recipient. The same endpoint also accepts JSON and returns the token, token id, expiry and absolute public URL.

### Open a share link (recipient)

1. Visit the `/share/{token}` URL you were sent.
2. If the link is valid, you see the record's title, identifier, scope and the issuer's name. The view is read-only and the access is counted.
3. If the link has been revoked, has expired, or has reached its access cap, you see a denial page explaining why.

### Manage links (administrator)

1. Go to **Admin -> Share Links** (`/admin/share-links`). This requires the `share_link.list_all` permission (administrators bypass).
2. Filter by status (active, expired, revoked, exhausted or all), by issuer, or by a search term matching token, recipient email or record title.
3. Open a link (`/admin/share-links/{id}`) to see its details, status and the full access log (up to the most recent 200 entries).
4. Use **Revoke** to disable an active link immediately, optionally recording a reason.

### Prune old links (operator)

Run `php artisan share-link:prune` (add `--dry-run` to preview). It deletes tokens and access-log rows older than the configured retention windows.

## Configuration

Settings are read from `ahg_settings`:

- `share_link.default_expiry_days` - default link lifetime (default 14).
- `share_link.max_expiry_days` - maximum lifetime; issuing beyond it needs the unlimited-expiry permission (default 90).
- `share_link.token_retain_days` - how long to keep tokens before pruning (default 365).
- `share_link.access_log_retain_days` - how long to keep access-log rows (default 180).

Permissions (seeded as ACL entries) gate creating links, creating links for classified records, issuing beyond the expiry cap, and listing all links in the admin console.

## Known issues

- Recipient access is read-only; share links surface the record's descriptive metadata and do not grant download by themselves.
- Pruning is a manual or scheduled console task rather than an automatic background sweep.

## References

- Source: packages/ahg-share-link/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues (see the ahg-share-link tracker)
