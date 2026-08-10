# ahg-artwork-request

Staff requests to place artworks in offices and shared spaces (Heratio #1459).
Ported from the AtoM `ahgArtworkRequestPlugin` (0.3.1).

Captures a request, checks availability, notifies the responsible staff and
records the decision. The conversation itself stays with people - the system
records what was asked and what was decided, and can note that a decision was
made offline rather than pretending it ran it.

## Why this is separate from ahg-loan

`ahg-loan` models a loan to another institution: `partner_institution` is NOT
NULL, and there are couriers, customs states, facility reports and loan fees
beside it. A colleague hanging a painting in their office is none of those, and
filling a required partner column with something untrue on every internal
booking is how a collection database stops being evidence of anything. Approval
hands off to `ahg-loan` (a button, never automatic) when a placement genuinely
becomes an institutional loan.

## Screens

- `/artwork-request` - my requests
- `/artwork-request/new` - ask for one or more works (any authenticated user)
- `/artwork-request/{id}` - one request, its works, decision and log
- `/artwork-request/review` - review queue, per-work decisions (editor/admin)
- `/artwork-request/placements` - what is out on campus, with an overdue filter (editor/admin)
- `/artwork-request/approvers` - who is notified and may decide (administrator only)
- `/artwork-request/availability` - JSON clash check the request form calls

## Availability

A work is free only when three sources agree: other placement requests,
institutional loans (`ahg-loan`, when present) and exhibition commitments
(`ahg-exhibition`, when present). A clash is a **warning, not a block** - the
curator may still say yes, and the clash is recorded against the work.

## Reminders

`artwork:remind` sends overdue and due-soon reminders, logged as distinct
`reminded` / `reminded_due_soon` events so a courtesy nudge never suppresses the
overdue chase. Registered in `CronSchedulerService` under "Artwork Placement"
(daily 08:00); no-ops cleanly when nothing is out.

## Tables

`artwork_request`, `artwork_request_object` (status is per work, not per
request), `artwork_request_approver`, `artwork_request_log`. No ENUM columns:
VARCHAR with a COMMENT listing the valid values, so adding a state later is not a
migration.

## Wiring

Registered in `bootstrap/providers.php` with a PSR-4 autoload entry in the root
`composer.json` (no composer require, so `composer.lock` is untouched). Routes
register via `callAfterResolving('router')` in the provider's `register()`
because `/artwork-request` is a single top-level segment and must beat the
locked `/{slug}` catch-all.
