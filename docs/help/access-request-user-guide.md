> Heratio Help Center article. Category: Security and Access Control.

# Access Request Module

The Access Request module lets authenticated users ask for permission to view restricted or classified material, and gives administrators a queue to review, approve, or deny those requests with automatic email notifications. It is supplied by the `ahg-access-request` package and is distinct from the broader researcher-portal access workflow documented in the companion "Access Requests and Researcher Portal" guide.

---

## Overview

Restricted records in Heratio can carry a security classification. When a user needs access to material above their current clearance, they submit an access request describing what they need and why. The request lands in a pending queue. Designated approvers (or any administrator) review the queue, then approve or deny each request, optionally attaching notes or a reason. The requester is notified by email at submission, on approval, and on denial; approvers are notified when a new request enters the queue.

The module covers three concerns:

1. **Request capture** - a general request form and an object-specific request form.
2. **Review workflow** - a pending queue, a per-request detail view, and approve/deny/cancel actions.
3. **Approver administration** - a small admin screen for nominating which users may act as approvers.

The module is jurisdiction-neutral. Classification levels are read from the `security_classification` table and are entirely site-defined; the module does not assume any particular national classification scheme.

---

## Key features

| Feature | Description |
|---|---|
| General access request | A form (`subject`, `request_type`, `description`, optional `justification`, `urgency`, requested classification level) for asking for clearance or research access. |
| Object access request | A reason-and-access-type form launched from a specific record, pre-bound to that record's slug. |
| My requests | A self-service list of the signed-in user's own requests. |
| Pending queue | An admin/approver list of all requests still awaiting review. |
| Browse all | An admin list of every request, newest first, paginated 25 per page. |
| Request detail view | A single-request page showing the requester, status, and review notes. |
| Approve / deny | Admin actions that set status, record the reviewer and timestamp, and store review notes or a denial reason. |
| Cancel | A requester action that withdraws their own pending or approved request. |
| Approver management | Add or deactivate the users who appear as designated approvers. |
| Email notifications | Submission acknowledgement to the requester, queue heads-up to approvers, and approval/denial notices, all gated by a single settings toggle. |
| Legacy URL redirects | Old `/security/request*` and `/admin/accessRequests` URLs redirect to the current routes (301) for backward compatibility. |

---

## How to use

### For requesters

#### Submit a general access request

1. Sign in.
2. Go to **My Requests** (`/access-request/my-requests`) and start a new request, or go directly to **New Access Request** at `/access-request/new`.
3. Complete the form:
   - **Subject** (required) - a short title for the request.
   - **Request Type** (required) - one of: View restricted material, Request copies, Permission to publish, Research access, Other.
   - **Description** (required) - what materials you need and the purpose.
   - **Justification** (optional) - any supporting reasoning.
   - **Urgency** (optional) - Low, Normal, High, or Urgent. Normal is the default and helps reviewers prioritise the queue.
   - **Requested classification level** (optional) - shown only when classification levels are configured on the site. Choose the level you need, or leave on the default (lowest) level.
4. Submit. You are returned to **My Requests** with a confirmation, and (if notifications are enabled) you receive an acknowledgement email.

#### Request access to a specific record

1. From a restricted record, follow the request-access link, which opens `/access-request/request/{slug}`.
2. The form shows which record you are requesting and asks for:
   - **Reason for Access** (required).
   - **Access Type** (optional) - View only, Download, or Physical copy.
3. Submit. The request is created and you are returned to **My Requests**.

#### Track or cancel your requests

- View your requests at `/access-request/my-requests`.
- Open any request to see its current status and any reviewer notes.
- Cancel a request that is still **pending** or **approved** from its detail page; this sets the status to **cancelled**. (Requests already denied or cancelled cannot be cancelled again.)

### For administrators and approvers

#### Review the pending queue

1. Sign in as an administrator.
2. Open the pending queue at `/access-request/pending`. It lists every request with status **pending**, newest first, showing the requester's name (from `actor_i18n`).
3. Open a request to review the full detail at `/access-request/{id}`.

#### Approve or deny

- **Approve**: submit the approve action (`POST /access-request/{id}/approve`). You may include **notes**. The request status becomes **approved**, the reviewer id and timestamp are recorded, and the requester is emailed.
- **Deny**: submit the deny action (`POST /access-request/{id}/deny`). You may include a **reason**. The request status becomes **denied**, the reviewer id and timestamp are recorded, and the requester is emailed the reason.

Both actions return you to the pending queue with a confirmation message.

#### Browse the full history

- The browse view at `/access-request/browse` lists every request regardless of status, paginated 25 per page, newest first.

#### Manage approvers

1. Open **Approvers** at `/access-request/approvers`.
2. **Add an approver** by supplying a user id; the user is inserted into `access_request_approver` and marked active.
3. **Remove an approver** by submitting the delete action against their approver row; this sets `active = 0` (a soft deactivate, not a hard delete).

Active approvers with an email address receive the new-request heads-up email each time a request is submitted.

### Status lifecycle

| Status | Set by | Meaning |
|---|---|---|
| `pending` | created on submission | Awaiting review. Appears in the pending queue. |
| `approved` | approve action | Access granted. Reviewer and timestamp recorded. |
| `denied` | deny action | Access refused. Reviewer, timestamp, and reason recorded. |
| `cancelled` | requester cancel | Withdrawn by the requester (from pending or approved). |
| `expired` | (schema-supported) | Reserved status for lapsed requests; the column allows it. |

---

## Routes

All routes are registered under the `web` middleware group. The `auth` middleware requires a signed-in user; `admin` additionally requires administrator rights.

### Requester routes (`auth`)

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/access-request/new` | `create` | `accessRequest.create` |
| POST | `/access-request/new` | `store` | `accessRequest.store` |
| GET | `/access-request/my-requests` | `myRequests` | `accessRequest.myRequests` |
| GET | `/access-request/request/{slug}` | `requestObject` | `accessRequest.requestObject` |
| GET | `/access-request/{id}` | `view` | `accessRequest.view` |
| POST | `/access-request/{id}/cancel` | `cancel` | `accessRequest.cancel` |
| POST | `/access-request/request-object/create` | `storeObjectRequest` | `accessRequest.storeObjectRequest` |

### Admin / approver routes (`auth` + `admin`)

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/access-request/browse` | `browse` | `accessRequest.browse` |
| GET | `/access-request/pending` | `pending` | `accessRequest.pending` |
| POST | `/access-request/{id}/approve` | `approve` | `accessRequest.approve` |
| POST | `/access-request/{id}/deny` | `deny` | `accessRequest.deny` |
| GET | `/access-request/approvers` | `approvers` | `accessRequest.approvers` |
| POST | `/access-request/approvers/add` | `addApprover` | `accessRequest.addApprover` |
| DELETE | `/access-request/approvers/{id}` | `removeApprover` | `accessRequest.removeApprover` |

### Legacy redirects and aliases

For compatibility with the earlier plugin URLs, these paths are still served:

- `/admin/accessRequests` -> redirects to `/security/access-requests` (301).
- `/accessRequest`, `/security/request`, `/security/requests` -> redirect to the pending queue (301).
- `/security/request-access`, `/security/request-object` -> redirect to the new-request form (301).
- `/security/request-access/create`, `/security/request-object/create` -> map to `store` and `storeObjectRequest`.
- `/security/request/{id}`, `/security/request/{id}/review` -> map to the detail/review view.
- `/security/request/{id}/approve`, `/security/request/{id}/deny`, `/security/request/{id}/cancel` -> map to the matching actions.
- `/security/approvers`, `/security/approvers/add`, `/security/approvers/{id}/remove` -> map to the approver-management actions.

---

## Views

| View | Used by | Purpose |
|---|---|---|
| `new.blade.php` | `create` | General access-request form. |
| `request-object.blade.php` | `requestObject` | Object-specific request form (shows the record slug). |
| `my-requests.blade.php` | `myRequests` | The signed-in user's request list. |
| `pending.blade.php` | `pending` | Approver queue of pending requests. |
| `browse.blade.php` | `browse` | Full paginated list of all requests. |
| `view.blade.php` | `view` | Single-request detail page. |
| `approvers.blade.php` | `approvers` | Approver management screen. |
| `partials/_request-button.blade.php` | embedded | The request-access button shown on records. |
| `emails/submitted.blade.php` | submission | Acknowledgement to the requester. |
| `emails/pending.blade.php` | submission | Heads-up to each active approver. |
| `emails/approved.blade.php` | approval | Approval notice to the requester. |
| `emails/denied.blade.php` | denial | Denial notice (with reason) to the requester. |

The forms extend the central `theme::layouts.1col` layout and use Bootstrap 5 cards, badges, and the site theme colour (`--ahg-primary`), so they match the rest of the admin UI.

---

## Data model

The module reads and writes the following tables (created by `database/install.sql`, all idempotent `CREATE TABLE IF NOT EXISTS`).

### `security_access_request`

The table new requests are written to (`createRequest`). Key columns:

| Column | Notes |
|---|---|
| `user_id` | The requester. |
| `request_type` | e.g. view, download, print, clearance_upgrade, compartment_access, renewal. |
| `classification_id` | Requested classification (nullable). |
| `object_id` | Target object (nullable). |
| `justification` | Combined subject, description, and justification text. |
| `priority` | normal, urgent, immediate (mapped from the form's urgency). |
| `status` | pending, approved, denied, expired, cancelled. |
| `reviewed_by`, `reviewed_at`, `review_notes` | Review outcome. |
| `access_granted_until` | Optional expiry of granted access. |

### `access_request`

The legacy request table. The review actions (`approveRequest`, `denyRequest`, `cancelRequest`) and the list/browse queries operate on this table. Columns include `request_type`, `scope_type`, `requested_classification_id`, `current_classification_id`, `reason`, `justification`, `urgency`, `status`, `reviewed_by`, `reviewed_at`, `review_notes`, and `expires_at`. New submissions are routed to `security_access_request`; `access_request` is retained for existing data and the review workflow.

### `access_request_approver`

Designated approvers. Columns: `user_id` (unique), `min_classification_level`, `max_classification_level`, `email_notifications`, `active`. Removing an approver sets `active = 0`.

### Supporting tables

- `access_request_log` - audit trail of request actions (created, updated, approved, denied, cancelled, expired, escalated) with actor and IP.
- `access_request_justification` - justification text history, optionally tied to a template.
- `access_request_scope` - which objects a request covers (information_object, repository, actor) and whether descendants are included.

Classification levels themselves come from the `security_classification` table, which the new-request form reads (ordered by `level`) to populate its optional level selector.

---

## Configuration

### Email notifications

All four mailers are controlled by a single setting:

- **Setting key:** `access_request_email_notifications`
- **Default:** on (true) - a fresh install sends notifications without any operator action.
- **Where:** the email settings page at `/admin/ahgSettings/email`.

When the toggle is off, every mailer no-ops silently. Mail delivery is wrapped so that a send failure is logged (`[access-request] mail send failed`) but never rolls back the request, approval, or denial that triggered it. Outbound mail is queued, so a queue worker must be running for messages to leave the server.

The four notifications:

| Trigger | Recipient | Mailable |
|---|---|---|
| Request submitted | Requester | `AccessRequestSubmittedMail` |
| Request submitted | Each active approver with an email | `AccessRequestPendingMail` |
| Request approved | Requester | `AccessRequestApprovedMail` |
| Request denied | Requester | `AccessRequestDeniedMail` |

The requester's display name on the approver heads-up is taken from `actor_i18n.authorized_form_of_name` (English culture), falling back to the user's email, then to `User #id`.

### Classification levels

The optional classification selector on the new-request form only appears when the `security_classification` table contains rows. Define your site's levels there (code, name, level) to expose them in the form.

### Access control

Requester routes require `auth`; review, browse, pending, and approver routes additionally require the `admin` middleware. There is no separate granular permission layer in this module beyond the admin gate and the designated-approver list.

---

## Notes and current behaviour

- New submissions write to `security_access_request`, while the review and listing actions read from `access_request`. Operators integrating reporting should be aware these are two tables; the module retains both for backward compatibility with the earlier plugin's data.
- Cancellation is restricted to the requester's own requests and only from the `pending` or `approved` states.
- Removing an approver is a soft deactivate (`active = 0`), so historical attribution is preserved.

---

## References

- Source package: `packages/ahg-access-request/`
- Ported from the AtoM `ahgAccessRequestPlugin` on 2026-04-30 (Phase 1 standalone install).
- Related guide: `docs/help/access-requests-user-guide.md` (the broader researcher-portal access workflow).
- Issue: [GH #539](https://github.com/ArchiveHeritageGroup/heratio/issues/539)
