---
category: User Guide
title: Artwork requests - placing works in offices and shared spaces
---

# Artwork requests

Staff ask to hang a work in an office, a boardroom or a corridor; the people
responsible for the collection decide; the system records what was asked and what
was agreed. Often called "Art Across Campus".

This is deliberately **not** a loan. A loan goes to another institution and drags
couriers, customs, facility reports and fees behind it. A colleague hanging a
painting on their office wall is none of those things, and filling in a required
partner-institution field with something untrue on every internal booking is how a
collection database stops being evidence of anything. If a placement later becomes
a genuine institutional loan, approval hands it over to the loans module - by a
button, never automatically.

## The screens

| Screen | Who | What it is for |
|---|---|---|
| `/artwork-request` | any signed-in user | My requests and where each one stands |
| `/artwork-request/new` | any signed-in user | Ask for one or more works |
| `/artwork-request/{id}` | requester, reviewers | One request: its works, the decision, the log |
| `/artwork-request/review` | editor, administrator | The review queue, with a decision per work |
| `/artwork-request/placements` | editor, administrator | What is currently out, filterable by overdue |
| `/artwork-request/approvers` | administrator | Who gets notified and who may decide |

## Making a request

Open **Artwork requests → New request**. Search for the works you want by title or
identifier and add them; one request can carry several. Say where the work is
going - building, floor and room - and for how long. Add anything the reviewer
needs to weigh, such as an event date or who will be in the room.

A request covers **several works at once**, but each work is decided
**individually**. Two of the four you asked for can be approved while a third is
declined because it is already promised to an exhibition, and the request as a
whole stays open until each work has an answer.

## Availability: a warning, not a barrier

When you add a work, the system checks whether it is already committed - to
another placement, to a loan, or to an exhibition in the same period - and **warns
you rather than stopping you**.

That is deliberate. Availability data is only ever as good as the last person to
update it, and a system confident enough to refuse a request on stale data blocks
real work and teaches people to route around it. The warning puts the conflict in
front of the reviewer, who knows what the records do not.

## Deciding

Reviewers work from **Artwork requests → Review**. Each work gets its own
decision - approve, decline, or approve with conditions - and a note explaining
why. The note matters more than the verdict: in a year's time "declined, light
levels in that corridor" is useful and "declined" is not.

A decision reached in a corridor or over coffee can be **recorded as having been
made offline**. The system does not pretend it ran a process it did not; it
records that a decision happened elsewhere and who made it.

## Tracking what is out

**Artwork requests → Placements** lists everything currently placed, with an
**overdue** filter. A placement is overdue when its agreed end date has passed and
the work has not been booked back in.

Reminders go out automatically: a **due-soon** notice before the end date and an
**overdue** notice after it. These are scheduled, so they need the scheduler
running on the instance - see the cron and scheduling guide if reminders are not
arriving.

## Who is notified

Administrators set the approver list under **Artwork requests → Approvers**. Those
people receive new requests and may decide them. Keep it current: an approver list
pointing at someone who has left is the usual reason requests sit unanswered.

## Handing over to a loan

If a placement turns out to be an institutional loan - going off-site, to another
organisation, needing a courier or a facility report - an approver can hand it over
to the **loans module** from the request. This creates the loan record with the
work and dates carried across, and it is always an explicit action. Nothing is
converted behind your back.

## Permissions

- **Any signed-in user** may make a request and see their own.
- **Editors and administrators** review, decide, and see placements.
- **Administrators** additionally manage the approver list.

## When something is not working

**My request has had no response.** Check `/artwork-request/approvers` - if it is
empty or lists someone who has left, nobody is being notified.

**No reminder emails.** Reminders are a scheduled task. Confirm the scheduler is
running on this instance and that mail is configured; the mail settings are held in
the application, not only in the environment file.

**A work I know is free shows a conflict.** The check looks at placements, loans
and exhibitions. An exhibition booking that was never closed off is the usual
culprit. The warning never blocks you - read it, and proceed if you know better.

## Related

- Loans - institutional lending, couriers, facility reports, fees
- Exhibitions and the exhibition digital twin - planning what hangs where
- Cron and scheduled tasks - what drives the reminders
