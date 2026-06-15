> Heratio Help Center article. Category: Research / Bookings.

# Reading Room Booking

## A Guide for Researchers and Reading Room Staff

---

## What is reading room booking?

Reading room booking lets an approved researcher reserve a visit to consult materials in person. You pick a room, choose a date and time, state your purpose, and optionally pre-request the archival items you want pulled before you arrive. Staff confirm the booking, check you in on the day, and check you out when you leave.

```
Researcher books -> Staff confirm -> Check in (arrival) -> Check out (departure)
```

Only researchers whose account status is **approved** can make a booking.

---

## For Researchers

### Making a booking

Open the booking form at `/research/book` from the Research sidebar ("Book Reading Room").

```
+------------------+
|  Choose a room   |
+--------+---------+
         |
         v
+------------------+
|  Pick date and   |
|  start/end time  |
+--------+---------+
         |
         v
+------------------+
|  State your      |
|  purpose         |
+--------+---------+
         |
         v
+------------------+
|  Pre-request     |
|  materials       |
|  (optional)      |
+--------+---------+
         |
         v
+------------------+
|  Submit          |
+------------------+
```

### The booking form

| Field | Notes |
|-------|-------|
| **Select room** | Radio buttons. Each room shows its capacity, any equipment, and a description. |
| **Date** | Required. Cannot be earlier than today. |
| **Start time / End time** | Required. |
| **Purpose of visit** | Required free-text description of what you intend to do. |
| **Additional notes** | Optional. |
| **Material requests** | Optional. A search box (type-ahead) lets you find and add archival items so they are ready when you arrive. Up to 20 items. |

The sidebar shows your own details (name, email, institution) and any reading-room notices, such as bringing identification and the cancellation window.

### After you book

When you submit, the booking is created with status **pending** and (if email notifications are enabled) you receive a confirmation email. The booking appears in your research workspace under your upcoming visits. You can open it again at `/research/viewBooking/{id}`.

Your booking moves through these states:

```
pending -> confirmed -> checked_in -> checked_out
                              \
                               -> no_show / cancelled
```

| Status | Meaning |
|--------|---------|
| pending     | Submitted, awaiting staff confirmation |
| confirmed   | Staff have approved the visit |
| checked_in  | You have arrived and signed in |
| checked_out | Your visit is complete |
| no_show     | You did not arrive |
| cancelled   | The booking was cancelled |

---

## For Reading Room Staff

### Managing bookings

Open the bookings queue at `/research/bookings` (also reachable at `/research/admin/bookings`). From there you act on each booking:

| Action | What it does |
|--------|--------------|
| **Confirm** | Approves a pending booking and emails the researcher. |
| **Check in** | Records arrival and timestamps the visit. |
| **Check out** | Records departure, completes the booking, and marks all requested materials as returned. |
| **No show** | Marks a confirmed booking where the researcher did not arrive. |
| **Cancel** | Cancels the booking and emails the researcher. |

### Material requests

Each item a researcher pre-requests is tracked alongside the booking. Requests start as **pending**, can be marked **retrieved** when the item is ready, and are set to **returned** automatically at check-out.

---

## Configuration

- **Reading rooms** (name, description, capacity, equipment, advance-booking days, maximum booking hours) are maintained as records that drive the room selection list.
- **Email notifications** for booking created, confirmed, and cancelled are controlled by the `research_email_notifications` setting in AHG Settings.

---

## Troubleshooting

| Problem | Likely cause |
|---------|--------------|
| No "Book Reading Room" link | Your researcher account is not yet approved. |
| Cannot select a date | The date is in the past; pick today or later. |
| No rooms shown | No reading rooms have been configured yet. |
| Did not receive a confirmation email | Email notifications may be disabled in settings. |

---

## References

- Source: `packages/ahg-research/`
- Stored in: `research_booking`, `research_material_request`, `research_reading_room`
