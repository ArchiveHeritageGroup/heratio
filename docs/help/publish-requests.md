# Publish Requests

Token-anchored workflow for external researchers to request that an archival
record be published, and for curators to review and decide on those
requests. Lives alongside the legacy AtoM-port Request-to-Publish workflow;
the two are intentionally independent.

## Overview

A researcher (anonymous, no login required) submits a request from an
archival-record page. The system writes a row to `ahg_publish_request`,
generates a 40-char hex token, and returns a receipt URL of the form
`/publish-request/receipt/{token}`. The submitter can return to that URL at
any time to see status + curator notes - no account, no password.

Curators see incoming requests in an inbox panel at
`/admin/publish-requests` and decide each one: **approved**, **rejected**,
**edited**, or leave **pending**. A best-effort email goes to the submitter
on submission and on decision.

## Public researcher flow

1. From an archival-record page, click **Request to publish**.
2. Submit name (optional), email (required), and a free-text message.
3. The server returns to a receipt page that shows current status and any
   curator notes. Bookmark this URL - it is the only way to track status
   without contacting an archivist.

The submit endpoint is `POST /publish-request` and is CSRF-exempt because
it has no session; anti-abuse protection should be wired by adding a
captcha rule to `PublishRequestController::submit()` before public rollout.

## Curator inbox

`/admin/publish-requests` lists every request newest first, filterable by
status tab (All / Pending / Approved / Rejected / Edited). Each row links
to a per-request panel:

- **Submission** - the original message, submitter contact, archival item.
- **Decision** - status dropdown, curator notes (visible to the submitter),
  optional rewritten message when status is set to "edited".

Submitting the form records `decided_at` + `decided_by_user_id` and emails
the submitter via `PublishRequestDecisionNotification`.

## Status values

`pending`, `approved`, `rejected`, `edited`. Stored in `ahg_dropdown` under
taxonomy `publish_request_status` so an operator can rename labels via the
Dropdown Manager without code changes. Auto-seeded by
`AhgRequestPublishServiceProvider` on first boot.

## Database

`ahg_publish_request` (id, information_object_id, submitter_email,
submitter_name, message_text, status, token, created_at, decided_at,
decided_by_user_id, curator_notes). Schema in
`packages/ahg-request-publish/database/install_publish_request.sql`.

## References

- Issue: [GH #745](https://github.com/ArchiveHeritageGroup/heratio/issues/745)
- Source: `packages/ahg-request-publish/`
- Legacy AtoM-port flow (separate): `RequestPublishController` +
  `request_to_publish` / `request_to_publish_i18n` tables.
