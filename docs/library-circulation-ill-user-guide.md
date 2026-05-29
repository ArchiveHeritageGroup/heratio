# Library Circulation and Interlibrary Loan (ILL) - User Guide

**Summary:** Heratio's library module provides a full circulation desk (checkout,
return, renewal, holds, fines), patron management, tiered overdue notices, and
ISO 10160/10161 interlibrary loan with EDI (EANCOM / UN-EDIFACT / X12 / CUSTOM)
message exchange. This guide covers day-to-day operation for librarians plus the
scheduled background jobs and the data model that backs them. Everything is
jurisdiction-neutral; fine currency, loan periods and limits are configurable
per install via AHG Settings and the Dropdown Manager.

---

## 1. Data model

| Table | Purpose |
|---|---|
| `library_patron` | Borrower records. Borrowing limits + status, fines owed. |
| `library_patron_category` | Code-keyed catalogue of patron categories with default loan limits. Codes mirror the `patron_type` dropdown taxonomy. |
| `library_copy` | Physical, barcode-addressable copies of a `library_item`. |
| `library_item` | Bibliographic record (catalogue), owns `material_type` and `call_number`. |
| `library_checkout` | Active and historical loans. |
| `library_hold` | Hold (reservation) queue against a `library_item`. |
| `library_fine` | Overdue and manual fines. Financial data may be encrypted at rest. |
| `library_loan_rule` | Loan policy per `(material_type, patron_type)`: period, renewals, fine/day, cap, grace. `patron_type = '*'` is the wildcard fallback. |
| `library_notice_template` | Editable subject/body templates for each notice tier. |
| `library_overdue_notice_log` | One row per notice sent; prevents re-sending the same tier for a loan. |
| `library_ill_request` | ILL request (borrow or lend), ISO 10160 status. |
| `library_ill_audit` | ILL status-transition audit trail. |
| `library_trading_partner` | EDI partner config: type, message profile, endpoint, credentials. |

All status/type columns are `VARCHAR` (never `ENUM`); enumerated option lists
(patron type, etc.) come from the `ahg_dropdown` Dropdown Manager.

---

## 2. Circulation desk

Reachable at `/library-manage/circulation`.

- **Scan**: enter a copy barcode or a patron card number. The desk auto-detects
  which it is and shows the relevant panel (item status + hold count, or patron
  loans/holds/fines summary).
- **Checkout**: blocked when the copy is not `available`, the patron is
  suspended/expired, the patron is at their checkout cap, or outstanding fines
  exceed the configured threshold. The loan period is resolved from the matching
  `library_loan_rule` (exact `(material_type, patron_type)` wins over the `*`
  wildcard, then the global default).
- **Return**: marks the loan returned. If a fine is due (auto-fine on) it is
  generated first. If another patron is queued, the next hold is promoted to
  `ready` and the copy flips to `on_hold`; otherwise the copy returns to
  `available`.
- **Renew**: extends the due date by another loan period. Blocked at the renewal
  cap (rule max, further capped by the patron's own `max_renewals`) or when
  another patron has a pending hold on the item.

### Holds

- `placeHold(item, patron)` appends to the queue (`queue_position = current
  pending count + 1`). Blocked when the patron is suspended, at their hold cap,
  or the queue is full.
- `cancelHold(hold, reason)` cancels a hold.
- Expired holds (pickup window passed) are swept nightly to `expired`.

### Fines

- An overdue fine is `fine_per_day x (overdue_days - grace_period_days)`, capped
  by `fine_cap` when set. Generation is idempotent: re-running updates the single
  outstanding overdue fine row for a loan rather than duplicating it.
- The patron's `total_fines_owed` running total is refreshed on each change.

---

## 3. Patrons

`/library-manage/patrons`. Create/edit applies settings-driven defaults
(category type, max checkouts/renewals/holds, membership length) when the
operator does not supply them. Card numbers auto-generate as
`LIB-{YY}-{6 hex}` when blank. Suspend / reactivate and lapse-expiry are
supported; `library_patron_category` carries the per-category default limits.

---

## 4. Overdue notices

Tiered email notices driven by `library_notice_template`:

| Tier | Default trigger (days overdue) |
|---|---|
| `overdue_1` | 1 |
| `overdue_2` | 7 |
| `overdue_final` | 21 |
| `hold_ready` | n/a (sent when a hold becomes ready) |

- **Editing templates**: `/library-manage/notice-templates`. Each template has a
  subject, a plain-text body with `{{token}}` placeholders, a trigger threshold
  and an active flag. A live preview renders the template against sample data.
- **Tokens**: `{{patron_name}}`, `{{title}}`, `{{barcode}}`, `{{due_date}}`,
  `{{days_overdue}}`, `{{currency}}`, `{{fine_per_day}}`, `{{fine_amount}}`,
  `{{library_name}}`, `{{expiry_date}}`.
- **Sending**: the daily `ahg:library-overdue-notices` command selects the
  highest tier each loan qualifies for, sends via Laravel Mail, and logs every
  send to `library_overdue_notice_log`. A loan is never sent the same tier
  twice. `--dry-run` renders + logs without sending. The whole job is gated by
  the `library_overdue_notices_enabled` setting.

---

## 5. Interlibrary loan (ILL)

`/library-manage/ill`. ILL follows ISO 10160 (functional standard) and ISO
10161-1 (OSI profile). A borrow request moves through:

```
PENDING -> REQUESTED -> SHIPPED -> RECEIVED -> RETURNED   (terminal)
        \-> CANCELLED / UNFULFILLED / LOST                (terminal)
```

Any non-terminal request whose `due_date` has passed is escalated to `OVERDUE`
by the nightly job. Every status change is recorded in `library_ill_audit`.

### Trading partners and EDI

`library_trading_partner` configures each counterparty: `edi_type`
(`EANCOM` / `UN/EDIFACT` / `X12` / `CUSTOM`), `message_profile`
(`EANCOM_S93` request, `EANCOM_S94` answer, `X12_850`, `CUSTOM`), endpoint
(`SFTP` / `AS2` / `HTTP_HTTPS` / `EMAIL` / `MANUAL`) and credentials.

- **Encoding** (`EdiEncoderService`): builds the outbound ILL message for the
  partner's declared profile - a full UNB/UNG/UNH...UNT/UNE/UNZ EDIFACT
  envelope for EANCOM (S93 message id 23, S94 message id 33/34), an ISA/GS/ST
  850 interchange for X12, or a JSON envelope for CUSTOM.
- **Transport** (`EdiAdapter`): sends the encoded message over the configured
  endpoint, honouring `test_mode`.
- **Decoding** (`EdiDecoderService`): parses inbound acknowledgements / status /
  answer messages, auto-detecting the profile, and normalizes the lender's
  native status to an ISO 10160 status word that can be fed straight into the
  state machine. Due dates in the inbound message are mapped onto the request.

---

## 6. Scheduled jobs

| Command | Schedule | Setting gate |
|---|---|---|
| `ahg:library-auto-expire-holds` | daily 02:30 | `library_auto_expire_holds` |
| `ahg:library-auto-expire-patrons` | daily 02:45 | `library_auto_expire_patrons` |
| `ahg:library-calculate-fines` | daily 03:15 | `library_auto_fine` |
| `ahg:library-overdue-notices` | daily 06:00 | `library_overdue_notices_enabled` |

Each command short-circuits when its setting is off, so toggling a switch in
AHG Settings is enough to silence it.

---

## 7. Settings reference

Configured under AHG Settings (library group). Key circulation/notice settings:

- `library_default_loan_days`, `library_max_renewals`, `library_auto_fine`,
  `library_currency`
- `library_hold_expiry_days`, `library_hold_max_queue`,
  `library_auto_expire_holds`
- `library_patron_*` (default type, max checkouts/renewals/holds, membership
  months, fine threshold, expiry grace days)
- `library_overdue_notices_enabled`, `library_name`
