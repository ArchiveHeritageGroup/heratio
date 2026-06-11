# Repatriation claims and virtual return

The repatriation engine traces displaced heritage and lets you record what happens
next. It has two layers:

1. **Displaced-heritage register** (detection): a read-only, conservative scan that
   flags catalogue items whose recorded place or community of origin appears to
   differ from where they are now held. This is a curatorial review aid, not a
   claim.
2. **Repatriation claims and virtual return** (this guide): a structured workflow
   you build on top of any traced item.

## What a claim is - and is not

A repatriation claim records a documented request and the stage its dialogue has
reached. It is **not** a legal determination, **not** a finding of wrongful
removal, and **not** advice. Origin, ownership and lawful-transfer history are for
the relevant communities, holding institutions and qualified staff to assess
together, case by case, under the applicable law. The framing is deliberately
factual and non-partisan throughout, because this is sensitive subject matter.

## Registering a claim (admin)

1. Go to **Repatriation claims** (`/repatriation/claims`).
2. Select **Register a claim**. To start from a traced item, open the form with
   `?item=<information-object-id>` - the origin and current-holding context are
   prefilled from the displaced-heritage register.
3. Fill in the claimant community, place of origin, current holder, a factual
   evidence summary, a point of contact, and any notes. Choose a status.
4. Save. You can edit a claim at any time; the item it concerns is fixed once set.

## Claim statuses

Status describes where a conversation stands, never an outcome:

- **Registered** - a claim has been recorded and awaits review.
- **Under review** - the claim and its evidence are being examined.
- **Acknowledged** - the holding institution has acknowledged the claim; dialogue
  is open.
- **Virtual return** - the object is made accessible in its origin context
  digitally, independent of any physical transfer.
- **Returned** - a physical return has been recorded.
- **Disputed** - the facts or the claim are contested and remain under discussion.

Statuses are stored as open text values (via the Dropdown Manager), so a site can
add its own without code changes.

## Virtual return (public)

Every claim has a public **virtual return** page at `/virtual-return/{id}`. It
presents the object in its place and community of origin - the origin place, the
claimant community, the documented evidence, and the stage of dialogue - so the
object can be re-encountered in its own context even when no physical return has
happened. A link to the object's own record (with any digital surrogate or 3D
viewer) is shown **only when that record is published**; unpublished items show
origin context only.

## Public dashboard

A public **repatriation dashboard** at `/repatriation` gives an open, at-a-glance
overview of every documented claim. It is a read-only aggregate of the same claims
register: big numbers (documented claims, dialogues in progress, items under
virtual return, items physically returned), simple bars showing where the dialogues
stand and the leading places and communities of origin, and a short recent-activity
list. Every entry links onward to that claim's `/virtual-return/{id}` page.

The dashboard is factual, non-partisan and jurisdiction-neutral. A status describes
where a dialogue stands, never a legal determination, and the standing disclaimer is
shown at the top. When no claims have been recorded it shows a dignified empty-state
("No repatriation claims recorded yet"); it never errors.

A machine-readable version is available at `/repatriation.json` (CORS-open, public
read-only data) so partner sites and external dashboards can re-use the same
aggregate. It exposes the totals, the status breakdown, the top origin places and
communities, the virtual-return / returned / in-dialogue split, and the recent
activity (each carrying its `virtual_return_url`).

## What it does not touch

The workflow writes only to its own `displaced_heritage_claim` table. It reads the
existing catalogue and the displaced-heritage register read-only. It never alters
existing records. The dashboard adds **no new table**: it is a read-only aggregate
(cheap COUNTs) over the existing claims register.
