# Library Serials - User Guide

**Summary.** Heratio's serials module tracks periodicals (journals, magazines,
newspapers, annuals) as serial titles with per-issue holdings. It predicts when
the next issue is due, raises and emails claims when an expected issue fails to
arrive, warns staff before a subscription lapses, and records the binding of
issue runs into physical bound volumes. A JSON:API surface exposes serials,
issues and subscriptions for integration. Everything is jurisdiction-neutral and
works for any market.

This guide covers the staff workflow, the prediction engine, claims, expiry
alerts, binding, the scheduled commands, and the API.

---

## 1. Concepts

- **Serial title** - the periodical itself (title, ISSN, publisher, frequency,
  status). Status is `active`, `ceased` or `suspended`.
- **Issue** - a single received (or expected) issue of a serial: volume, issue
  number, issue date, received date, status (`received`, `claimed`, `missing`).
- **Subscription** - the commercial terms for a title: start/end dates, cost,
  the notification email for claims and expiry warnings, and the auto-claim
  ceiling.
- **Prediction** - the forecast of upcoming issues, derived from the publication
  frequency plus intelligent enumeration roll-forward.
- **Claim** - a record that an expected issue is overdue and the supplier should
  be chased. Claims can be raised manually by staff or automatically by the
  daily claim-alert sweep.
- **Binding** - a physical bound volume covering a run of issues (a volume
  range), with a shelf location. Individual issues link back to their binding.

## 2. Managing serials

Open **Library management -> Serials**. From the index you can create, edit,
clone and delete serial titles, and drill into a title to manage its issues,
subscription, predictions, coverage and claims.

When adding an issue, supply the volume, issue number and issue date. Mark it
`received` when it arrives. The received date drives prediction and claim logic.

Status and other enumerated values are taken from the Dropdown Manager
(`ahg_dropdown`) - the serial claim and binding statuses live under the
`library_claim_status` and `library_binding_status` taxonomies.

## 3. Prediction engine

The prediction engine forecasts upcoming issues for the next several months.

Two parts work together:

1. **Date math** - from the latest received issue date, the engine advances by
   the frequency interval (weekly +7 days, monthly to the first of next month,
   quarterly +3 months, and so on) to compute each expected date.
2. **Enumeration parser** - the volume and issue numbers are advanced
   intelligently rather than just incremented. The parser understands holdings
   strings such as `Vol. 1-no.12 (Jan-Dec 2025)`, `Vol. 12, No. 3 (Mar 2025)`,
   `v.5 no.2 (2024)` and `No. 145 (Spring 2025)`. It knows how many issues a
   frequency yields per year, so a monthly serial at issue 12 rolls over to the
   next volume, issue 1, instead of continuing to issue 13.

Predictions can be persisted to `library_serial_prediction` so the claim sweep
and the UI read a stable forecast rather than recomputing on every request.

## 4. Claims

A claim flags an expected issue that has not arrived. The overdue threshold is
1.5x the publication interval past the predicted date, and only titles with an
**active, unexpired subscription** are considered.

- **Manual** - staff can claim a specific issue from the serial detail page.
- **Automatic** - the daily claim-alert command finds overdue titles, raises a
  claim and emails the subscription contact. It de-duplicates against existing
  open claims, so a long gap is alerted once, not every day.

Claim status flows `open -> sent -> resolved` (or `cancelled`).

## 5. Subscription expiry alerts

The expiry-alert command warns the subscription contact when a subscription is
within N days of its end date (default 30, configurable). This gives staff time
to renew before a gap opens in the holdings.

## 6. Binding

When a run of issues is sent for binding, record a binding unit: the volume
range, a shelf location and the bound date. The issues in that range are linked
back to the binding and stamped with the shelf location, so the catalogue shows
where the bound volume lives. Binding status flows
`pending -> at_bindery -> bound -> shelved`.

## 7. Scheduled commands

Two console commands run daily (registered in the scheduler):

| Command | Purpose | Schedule |
|---|---|---|
| `ahg:library-serial-claim-alerts` | Raise + email claims for overdue issues | Daily |
| `ahg:library-serial-expiry-alerts` | Warn before a subscription lapses | Daily |

Both accept `--dry-run` to preview without sending. The expiry command accepts
`--days=N` to override the warning window for a one-off run.

Email is delivered through the application mailer. SMS is **opt-in** behind a
configuration flag (`ahg-library.serials.sms_enabled`); with no SMS gateway
configured nothing is sent over SMS - intent is logged only, never sent
silently.

Configuration keys (all optional, sensible defaults apply):

- `ahg-library.serials.default_notification_email` - fallback recipient when a
  subscription has no notification email.
- `ahg-library.serials.expiry_warning_days` - default expiry warning window
  (30).
- `ahg-library.serials.sms_enabled` - gate for SMS notifications (off).

## 8. JSON:API

The serials JSON:API mirrors the acquisitions API conventions: JSON:API resource
objects (`{type, id, attributes, relationships}`), `?include=` eager-loading,
`page[number]`/`page[size]` (or `page`/`per_page`) pagination, and the same
permission gate as the rest of the library. Authentication is by session or API
key; write operations require the corresponding library permission.

Resource types: `library-serials`, `library-serial-issues`,
`library-serial-subscriptions`.

Endpoints (under `/api/library`):

```
GET    /serials                          list (filters: status, frequency, search)
GET    /serials/{id}                      show (?include=issues,subscription)
POST   /serials                           create
PUT    /serials/{id}                      update
DELETE /serials/{id}                      delete

GET    /serials/{id}/issues               list issues for a serial
POST   /serials/{id}/issues               add an issue
GET    /serial-issues/{id}                show an issue
PUT    /serial-issues/{id}                update an issue
DELETE /serial-issues/{id}                delete an issue

GET    /serials/{id}/subscription         list/show the subscription for a serial
POST   /serials/{id}/subscription         create or update (upsert) the subscription
GET    /serial-subscriptions/{id}         show a subscription
PUT    /serial-subscriptions/{id}         update a subscription
DELETE /serial-subscriptions/{id}         delete a subscription
```

A serial has at most one subscription, so posting a subscription against a
serial that already has one updates it (returns 200) rather than creating a
duplicate (201 on first create).

## 9. Data model

- `library_serial` - serial titles.
- `library_serial_issue` - per-issue holdings, with binding link fields
  (`binding_id`, `shelf_location`, `bound_at`).
- `library_serial_subscription` - subscription terms (one per serial).
- `library_serial_prediction` - persisted issue forecast.
- `library_claim` - raised claims.
- `library_binding` - bound-volume units.

All status columns are `VARCHAR` backed by Dropdown Manager taxonomies - no
database `ENUM` types are used.
