<?php

/**
 * ExhibitionEventController - heratio#1192 - live virtual openings (ticketed events).
 *
 * Admin: schedule + manage openings on an exhibition space, including pricing a
 *   paid opening and a "mark as paid" action that settles a pending ticket.
 * Public: an event landing page with a capacity-checked RSVP form and a
 *   "Join the walkthrough" link that goes live at event time and routes into the
 *   existing 3D walkthrough. Real-time multi-user presence is a later slice (#1150).
 *
 * Slice 2b: PAID ticketing. Paid RSVPs are created 'pending' (seat held, ticket not
 * usable) and only become joinable once payment is confirmed - either by the admin
 * markPaid action below or, later, by a gateway callback via $events->confirmPayment().
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Controllers;

use AhgExhibition\Mail\OpeningTicketMail;
use AhgExhibition\Services\ExhibitionEventService;
use AhgExhibition\Services\ExhibitionSpaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExhibitionEventController extends Controller
{
    protected ExhibitionSpaceService $spaces;

    protected ExhibitionEventService $events;

    public function __construct()
    {
        $this->spaces = new ExhibitionSpaceService;
        $this->events = new ExhibitionEventService;
    }

    /** Admin: list + schedule openings for a space. */
    public function index(string $slug)
    {
        $space = $this->spaces->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        $events = $this->events->listForSpace((int) $space->id);

        // For paid openings, surface their tickets so the curator can mark pending ones
        // as paid. Free openings carry no rsvps payload (their tickets are confirmed on
        // RSVP and need no settling), keeping the page identical for the free path.
        $rsvpsByEvent = [];
        foreach ($events as $ev) {
            if ($this->events->isPaid($ev)) {
                $rsvpsByEvent[(int) $ev->id] = $this->events->listRsvps((int) $ev->id);
            }
        }

        return view('ahg-exhibition::exhibition-space.openings', [
            'space' => $space,
            'events' => $events,
            'statuses' => ExhibitionEventService::STATUSES,
            'rsvpsByEvent' => $rsvpsByEvent,
        ]);
    }

    /** Admin: persist a newly scheduled opening. */
    public function store(Request $request, string $slug)
    {
        $space = $this->spaces->getBySlug($slug);
        if (! $space) {
            abort(404);
        }

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'host_name' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:5000',
            'starts_at' => 'required|date',
            'duration_minutes' => 'required|integer|min:5|max:1440',
            'capacity' => 'required|integer|min:1|max:100000',
            // Slice 2b: optional price. Blank / 0 keeps the opening FREE.
            'price' => 'nullable|numeric|min:0|max:99999999.99',
            'currency' => 'nullable|string|size:3|alpha',
        ]);

        try {
            $this->events->schedule((int) $space->id, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('exhibition-space.openings', ['slug' => $space->slug])
            ->with('success', 'Opening scheduled.');
    }

    /** Admin: change an opening's status (scheduled / live / ended / cancelled). */
    public function updateStatus(Request $request, string $slug, int $eventId)
    {
        $space = $this->spaces->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $event = $this->events->getById($eventId);
        if (! $event || (int) $event->exhibition_space_id !== (int) $space->id) {
            abort(404);
        }

        $data = $request->validate([
            'status' => 'required|string|in:scheduled,live,ended,cancelled',
        ]);

        try {
            $this->events->updateStatus($eventId, $data['status']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()
            ->route('exhibition-space.openings', ['slug' => $space->slug])
            ->with('success', 'Opening updated.');
    }

    /** Admin: delete an opening and all its RSVPs. */
    public function destroy(string $slug, int $eventId)
    {
        $space = $this->spaces->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $event = $this->events->getById($eventId);
        if (! $event || (int) $event->exhibition_space_id !== (int) $space->id) {
            abort(404);
        }

        $this->events->delete($eventId);

        return redirect()
            ->route('exhibition-space.openings', ['slug' => $space->slug])
            ->with('success', 'Opening deleted.');
    }

    /**
     * Admin: mark a pending paid ticket as paid (the honest, self-contained settle
     * path that makes paid events work end-to-end now, without ahg-cart). Confirms the
     * RSVP via the service, then emails the now-usable ticket to the attendee.
     *
     * Expects route: exhibition-space.openings.mark-paid
     *   POST /exhibition-space/{slug}/openings/{eventId}/rsvp/{rsvpId}/mark-paid
     */
    public function markPaid(Request $request, string $slug, int $eventId, int $rsvpId)
    {
        $space = $this->spaces->getBySlug($slug);
        if (! $space) {
            abort(404);
        }
        $event = $this->events->getById($eventId);
        if (! $event || (int) $event->exhibition_space_id !== (int) $space->id) {
            abort(404);
        }
        $rsvp = $this->events->getRsvpById($rsvpId);
        if (! $rsvp || (int) $rsvp->event_id !== (int) $event->id) {
            abort(404);
        }

        try {
            $paid = $this->events->markRsvpPaid($rsvpId);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        if ($paid) {
            $this->sendTicketEmail($event, $space, $paid);
        }

        return redirect()
            ->route('exhibition-space.openings', ['slug' => $space->slug])
            ->with('success', 'Payment recorded - ticket confirmed and emailed.');
    }

    /** Public: event landing page (RSVP + join link). Reached via a tokenised URL. */
    public function publicShow(string $token)
    {
        $event = $this->events->getByToken($token);
        if (! $event) {
            abort(404);
        }
        $space = $this->spaces->getById((int) $event->exhibition_space_id);
        if (! $space) {
            abort(404);
        }

        return view('ahg-exhibition::exhibition-space.opening-public', [
            'space' => $space,
            'event' => $event,
            'remaining' => $this->events->remainingSeats($event),
            'reserved' => $this->events->reservedSeats((int) $event->id),
            'joinable' => $this->events->isJoinable($event),
            'joinWindow' => ExhibitionEventService::JOIN_WINDOW_BEFORE_MIN,
            'ticket' => session('ticket'),
            // Slice 2b paid context for the public page.
            'isPaid' => $this->events->isPaid($event),
            'ticketPending' => (bool) session('ticket_pending'),
        ]);
    }

    /** Public: RSVP / claim a ticket, capacity-checked. */
    public function rsvp(Request $request, string $token)
    {
        $event = $this->events->getByToken($token);
        if (! $event) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'required|string|max:160',
            'email' => 'required|email|max:190',
            'party_size' => 'required|integer|min:1|max:20',
        ]);

        try {
            $rsvp = $this->events->rsvp($event, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput();
        }

        // Paid event -> RSVP is 'pending': seat held, ticket NOT yet usable. Tell the
        // visitor payment is required and that their ticket unlocks once it's settled.
        // Free event -> 'confirmed': issue the ticket and email it right away (slice 1).
        if (($rsvp->status ?? '') === 'pending') {
            return redirect()
                ->route('exhibition-space.opening-public', ['token' => $event->public_token])
                ->with('success', 'Seat reserved. Payment is required to confirm your ticket - your join link unlocks once payment is recorded.')
                ->with('ticket', $rsvp->ticket_code)
                ->with('ticket_pending', true);
        }

        $space = $this->spaces->getById((int) $event->exhibition_space_id);
        if ($space) {
            $this->sendTicketEmail($event, $space, $rsvp);
        }

        return redirect()
            ->route('exhibition-space.opening-public', ['token' => $event->public_token])
            ->with('success', 'You are booked in. Your ticket code is below.')
            ->with('ticket', $rsvp->ticket_code);
    }

    /**
     * Best-effort ticket email: sends the ticket code + join link on confirmation
     * (free RSVP or settled payment). Mirrors the ahg-research booking-mail pattern.
     * Mail failures are logged, never fatal - the on-page ticket code is the source of
     * truth, the email is a convenience.
     */
    private function sendTicketEmail(object $event, object $space, object $rsvp): void
    {
        if (empty($rsvp->email)) {
            return;
        }
        try {
            $joinUrl = route('exhibition-space.opening-public', ['token' => $event->public_token]);
            Mail::to($rsvp->email)->send(new OpeningTicketMail($event, $space, $rsvp, $joinUrl));
        } catch (\Throwable $e) {
            Log::warning('Opening ticket email failed', [
                'event_id' => $event->id ?? null,
                'rsvp_id' => $rsvp->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Public: bind a ticket to the live walkthrough.
     *
     * Resolves the event by public token, takes the ticket code from `?t=` (the
     * public page passes the session-held ticket through) or, failing that, from
     * the session itself. joinEvent() validates the join window + the ticket; on
     * success we stash the verified attendee (display name + ticket + event token)
     * in the session so the walkthrough can auto-identify them in its presence
     * beat, then redirect into the walkthrough carrying the event token. Failure
     * sends the visitor back to the public opening page with an error flash.
     *
     * No auth: attendees are unauthenticated ticket holders.
     */
    public function join(Request $request, string $token)
    {
        $event = $this->events->getByToken($token);
        if (! $event) {
            abort(404);
        }
        $space = $this->spaces->getById((int) $event->exhibition_space_id);
        if (! $space) {
            abort(404);
        }

        $ticketCode = trim((string) ($request->query('t') ?? session('ticket') ?? ''));

        $result = $this->events->joinEvent($event, $ticketCode);
        if (! $result['ok']) {
            return redirect()
                ->route('exhibition-space.opening-public', ['token' => $event->public_token])
                ->with('error', $result['reason'] ?? 'You cannot join this opening.');
        }

        $rsvp = $result['rsvp'];

        // Pin the verified attendee so the walkthrough's presence beat can auto-identify
        // this ticket holder without re-prompting for a name.
        $request->session()->put('exhibition_event_attendee', [
            'event_token' => $event->public_token,
            'ticket_code' => $rsvp->ticket_code,
            'name' => $rsvp->name,
        ]);

        // #1193 promoted: the walkthrough route now serves the ESM/r169 view with live co-presence.
        return redirect()->route('exhibition-space.walkthrough', [
            'slug' => $space->slug,
            'event' => $event->public_token,
        ]);
    }
}
