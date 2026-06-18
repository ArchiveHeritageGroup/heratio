# Repatriation claims, dialogue and shared records

Heratio's repatriation engine helps a holding institution and a community of
origin trace, discuss and (virtually or physically) return displaced heritage.
It is built from three pillars that sit on top of the displaced-heritage
register. The engine is international and jurisdiction-neutral: it records where
a dialogue stands, never a legal determination, never a finding of wrongful
removal, and never advice. Origin, ownership and lawful-transfer history are
matters for the relevant communities, holding institutions and qualified staff
to assess together, case by case, under the applicable law.

## The three pillars

### 1. Provenance trace

For any item the displaced-heritage register has traced (its recorded place or
community of origin differs from where it is held), the engine surfaces a
provenance trace: the origin-vs-holding context from the register, links to the
item's chain-of-custody and provenance timeline (the existing provenance record
on the object), and the object's own record where any digital surrogate or 3D
view lives. The engine reuses the existing provenance surfaces - it does not
duplicate them.

### 2. Claims and dialogue workflow

An origin community (or their representative) can have a repatriation/return
**claim** lodged against a displaced item. Staff register the claim from the
admin claims register, optionally pre-filled from a traced item.

**Self-service public lodging.** A community can also lodge a claim **directly,
with no staff account**. From any object in the public displaced-heritage
register, a "Lodge a repatriation claim" form lets the claimant give their
community, a contact email and the grounds for the claim. The submission lands as
a normal **Registered** claim with no staff author and a note flagging its public
origin, and it fires the usual staff notification for review (and a receipt email
to the claimant). The form is item-scoped (you reach it from a traced object, so
a claim always attaches to a real register entry) and is protected from
automated abuse by a honeypot field, a minimum-dwell check and request
throttling - no third-party captcha and no account required, which is the whole
point of lodging "before any staff account exists".

Each claim carries a status that moves through a neutral state machine:

- **Registered** - a claim has been recorded and is awaiting review.
- **Under review** - the claim and its documented evidence are being examined.
- **Acknowledged** - the institution has acknowledged the claim; dialogue is open.
- **Virtual return** - the object is made accessible in its origin context
  digitally, independent of any physical transfer.
- **Returned** - a physical return has been recorded.
- **Disputed** - the facts or the claim are contested and remain in discussion.

Statuses are stored as open VARCHAR values (the Dropdown Manager idiom), so the
set can grow without a schema change. Every status change is recorded in an
**append-only audit trail** (who changed it, when, from and to, and an optional
note), so the history of a claim is never lost.

**Notifications (both sides).** The two moments that matter are pushed to the
people who need them, so nobody has to poll the register:

- When a claim is **lodged**, every administrator gets an in-app notification
  (the holding-institution side), and the claimant receives an email receipt
  when a contact email is on the claim (the community side).
- When a claim's **status changes**, administrators and the staff member who
  originally logged the claim get an in-app notification, and the claimant
  receives an email naming the from -> to transition.

Each notification deep-links to the claim's staff workspace. The claimant email
is drawn from the claim's free-text contact field (the first email address found
there); a claim with no contact email simply gets no email, and a no-op status
save sends nothing. Every notification path is fail-soft - a mail or in-app
hiccup never blocks the claim write - and the whole feature can be turned off
with the `repatriation_notifications` setting (default on). All claim emails
carry the standing neutral disclaimer: a claim is a documented request and its
status, never a legal determination.

Around the claim runs a **two-way threaded dialogue** between the holding
institution and the claimant. Staff post messages from the claim's dialogue
workspace and choose each message's visibility:

- **Shared** - appears on the joint shared record (below).
- **Internal** - staff-only; never shown to the claimant.

This dialogue is direct between named parties and is not moderated, in contrast
to the separate community-knowledge feed (oral history, provenance notes,
corrections), which is moderated before it appears publicly.

### 3. Shared record with the origin community

Staff mint a private, permissioned **shared-record link** (a capability token)
for a named origin-community representative. With the link - and without a staff
account - the representative can open a scoped view of the claim: the object in
its origin context, the provenance-trace link (published records only), the
current status and its history, and the shared dialogue thread. If the grant
permits it, the representative can also add to the dialogue. Internal staff
notes and other staff-only fields are never exposed on this surface.

A link can be made read-only, given an expiry date, or revoked at any time. The
link is keyed off an opaque token, never the claim id, so it cannot be guessed
or enumerated; an unknown, revoked or expired link shows a neutral "link not
active" page that reveals nothing about the claim behind it.

## Where things live

| Surface | Path | Who |
|---|---|---|
| Claims register | `/repatriation/claims` | Staff (admin) |
| Register / amend a claim | `/repatriation/claims/create`, `/repatriation/claims/{id}/edit` | Staff (admin) |
| Claim dialogue workspace | `/repatriation/claims/{id}/dialogue` | Staff (admin) |
| Public virtual return | `/virtual-return/{id}` | Public |
| Public dashboard | `/repatriation` | Public |
| Shared record | `/repatriation/shared/{token}` | Token holder (origin community) |

## Data model

All tables are additive, use soft references (no foreign keys) into the claim and
catalogue tables, and never alter existing tables. They are created on first boot
behind a `Schema::hasTable` probe, so a fresh install never errors before the
tables exist.

- `displaced_heritage_claim` - one row per claim (the workflow record + current status).
- `repatriation_claim_message` - the two-way dialogue thread (role + visibility).
- `repatriation_claim_status_log` - the append-only status audit trail.
- `repatriation_claim_access` - the shared-record capability grants (tokens).
- `repatriation_knowledge_contribution` - the separate, moderated community-knowledge feed.

## Deferred follow-ups

This is the first increment of the claims/dialogue/shared-record pillars. The
following are deliberately out of scope and tracked for later:

- **Cross-peer federated shared record** - federating the shared record across
  institutions so a claim can be co-held by more than one holding peer. Today the
  shared record is a single-institution, single-claim surface.
- **Virtual-return 3D handoff** - a richer "virtual return" that hands the object's
  3D model / digital surrogate into an origin-context exhibition space from within
  the claim, beyond the current record link.
- **Dialogue-message notifications** - alerting both sides when a new dialogue
  message is posted. Claim *registration* and *status-change* notifications now
  ship (see "Notifications (both sides)" above); per-message dialogue alerts are
  the remaining piece.
- **Object not yet in the register** - self-service lodging is item-scoped, so a
  community can only lodge against an object already traced in the public
  displaced-heritage register. A general "claim about an object we cannot find
  here" intake (which staff would then link to a record) is the remaining piece;
  staff can still register such a claim directly today.
