# Endangered heritage and the capture-priority list

Some heritage can be lost before it is ever captured - to conflict, to a changing
climate, to the slow decay of fragile materials, to lost funding, to displacement,
or simply because no durable digital copy was ever made. The endangered-heritage
register lets you flag the records most at risk and work through them in priority
order, so the most vulnerable heritage is captured first. This is part of the North
Star "race against loss".

## What a flag is - and is not

An at-risk flag records that a curator judges an item should be captured sooner
rather than later, and the documented reason why. It is **not** a prediction that
the item will be lost, **not** a statement about any institution's stewardship, and
**not** advice. Risk to heritage, and the order in which to act, are matters for
qualified staff to assess against the evidence in every case. The framing is
deliberately factual and non-alarmist throughout.

## Flagging a record (admin)

1. Go to **Capture-priority worklist** (`/endangered/priority`).
2. Select **Flag a record**. To start from a record you are viewing, open the form
   with `?item=<information-object-id>` - the record reference and any existing flag
   are prefilled, so re-flagging the same item amends it rather than creating a
   duplicate.
3. Choose a **risk category**, an **urgency**, and a **capture status**, and add a
   factual **reason**.
4. Save. Each item carries a single flag; flagging it again updates that flag.

## Risk categories

All risk categories are managed values (the Dropdown Manager can extend them):

- **Conflict or unrest** - armed conflict, civil unrest or instability threatens
  the item or its holding site.
- **Climate or environment** - flood, fire, drought, sea-level rise or other
  environmental pressure.
- **Material decay** - fragile media, obsolete formats, mould, corrosion or
  embrittlement.
- **Funding or stewardship risk** - loss of funding or custodial capacity.
- **Displacement** - the item or community of origin is displaced.
- **Digitisation gap** - no durable digital surrogate exists yet, so the only
  record is the vulnerable original.
- **Other risk** - another documented risk that warrants prioritised capture.

## Urgency and the priority score

Urgency bands are **Critical**, **High**, **Medium** and **Low**. The worklist
orders items by a simple, legible priority score:

- the urgency band's base weight (critical highest), plus
- a small bonus when the risk is a digitisation gap (no durable surrogate yet),
  which is the case the race against loss most wants surfaced.

Captured and unflagged items drop out of the worklist - the race is won (or set
aside) for them. Within an urgency band, the longest-waiting flag sorts first.

## Capture statuses

Capture status describes where the capture effort stands:

- **Unflagged** - no longer treated as at-risk for capture purposes.
- **Flagged** - identified as at-risk and awaiting capture.
- **Capture in progress** - digitisation or capture work is under way.
- **Captured** - a durable digital surrogate has been produced.

You can advance the capture status straight from the worklist using the dropdown on
each row.

## The public at-risk register

The public register lives at `/at-risk`. It shows **published** items only that are
still awaiting capture, ordered most-urgent first, and frames why heritage is
endangered and the race to capture it. Captured items and unpublished records never
appear publicly. Browse by risk category using the chips at the top.

## Notes

- Everything is read-only over the existing catalogue. The only data written is the
  at-risk flag itself, in its own table.
- Every screen has an empty-state and never errors when nothing is flagged.
