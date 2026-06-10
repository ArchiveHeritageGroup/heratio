# Digital twin: live virtual openings (heratio#1192)

Scheduled, ticketed live "opening" events hosted inside an exhibition space's
3D walkthrough. This is the FIRST SLICE: scheduling + capacity-checked RSVP /
ticketing + a public event page with a timed "Join the walkthrough" link.

Live multi-user spatial presence, voice and a real-time docent are a separate,
later slice tracked under heratio#1150 - this slice does NOT attempt WebRTC,
presence, or audio. Ticket holders simply join the same public 3D walkthrough at
event time.

All code lives in `packages/ahg-exhibition`.

## Data model

Two tables, created idempotently in
`AhgExhibitionServiceProvider::migrateSpatialColumns()` (the same
`Schema::hasTable` + `CREATE TABLE IF NOT EXISTS` pattern as the rest of the
package, wrapped in one outer try so a missing base table during CI never fatals
the boot):

- `ahg_exhibition_event` - one scheduled opening per row.
  `exhibition_space_id`, unique `public_token` (the public URL slug), `title`,
  `host_name`, `description`, `starts_at` (DATETIME), `duration_minutes`,
  `capacity`, `status` (`scheduled` | `live` | `ended` | `cancelled`).
- `ahg_exhibition_event_rsvp` - one ticket per attendee. `event_id`, unique
  `ticket_code`, `name`, `email`, `party_size`, `status` (`confirmed`).
  Unique `(event_id, email)` enforces one ticket per email.

## Service - `ExhibitionEventService`

- `schedule(spaceId, data)` - validates title / start / duration (5-1440 min) /
  capacity (1-100000), mints a unique public token, inserts the event.
- `rsvp(event, data)` - capacity-checked inside a DB transaction with
  `lockForUpdate()` on the event row so concurrent RSVPs cannot oversell.
  Rejects: cancelled / ended events, duplicate email, party size that exceeds
  remaining seats, full events.
- `reservedSeats` / `remainingSeats` - sum of confirmed party sizes vs capacity.
- `isJoinable(event)` - true from `JOIN_WINDOW_BEFORE_MIN` (15) minutes before
  start until the scheduled end; cancelled events are never joinable.
- `updateStatus`, `delete`, `listForSpace`, `listRsvps`, `getByToken`.

## Controller / routes - `ExhibitionEventController`

Admin (auth + `acl` middleware, multi-segment URLs so they clear the
single-segment `/exhibition-space/{slug}` catch-all):

- `GET  /exhibition-space/{slug}/openings` - schedule form + list (`exhibition-space.openings`)
- `POST /exhibition-space/{slug}/openings` - store (`acl:create`)
- `POST /exhibition-space/{slug}/openings/{eventId}/status` (`acl:update`)
- `POST /exhibition-space/{slug}/openings/{eventId}/delete` (`acl:delete`)

Public (no login - the walkthrough it links into is itself public):

- `GET  /exhibition-space/opening/{token}` - event landing page + RSVP form +
  timed Join link (`exhibition-space.opening-public`)
- `POST /exhibition-space/opening/{token}/rsvp` - capacity-checked RSVP
  (`exhibition-space.opening-rsvp`)

A "Live openings" button is added to the exhibition nav bar
(`_nav-actions.blade.php`) but only for authenticated staff, since the manage
page is auth-gated.

## Views

- `exhibition-space/openings.blade.php` - admin schedule form + table (seats
  booked / remaining, status dropdown, public-page link, delete).
- `exhibition-space/opening-public.blade.php` - public page. Shows seats, a live
  JS countdown (inline script carries the CSP nonce), the RSVP form, the
  ticket-code confirmation, and the Join button which enables itself in-browser
  once inside the join window. The "Join the walkthrough" link targets the
  existing `exhibition-space.walkthrough` route for the space.

## Boundary note

This slice is honest about its scope on both the admin page and the public page:
spatial-audio, multi-user presence and a live docent are the next slice
(heratio#1150). Today the experience is "everyone joins the same walkthrough at
the set time with capacity-controlled ticketing".
