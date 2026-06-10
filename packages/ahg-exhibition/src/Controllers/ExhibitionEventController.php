<?php

/**
 * ExhibitionEventController - heratio#1192 - live virtual openings (ticketed events).
 *
 * Admin: schedule + manage openings on an exhibition space.
 * Public: an event landing page with a capacity-checked RSVP form and a
 * "Join the walkthrough" link that goes live at event time and routes into the
 * existing 3D walkthrough. Real-time multi-user presence is a later slice (#1150).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgExhibition\Controllers;

use AhgExhibition\Services\ExhibitionEventService;
use AhgExhibition\Services\ExhibitionSpaceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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

        return view('ahg-exhibition::exhibition-space.openings', [
            'space' => $space,
            'events' => $this->events->listForSpace((int) $space->id),
            'statuses' => ExhibitionEventService::STATUSES,
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

        return redirect()
            ->route('exhibition-space.opening-public', ['token' => $event->public_token])
            ->with('success', 'You are booked in. Your ticket code is below.')
            ->with('ticket', $rsvp->ticket_code);
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

        // #1153/#1193 BETA: route ticket holders into the ESM/r169 beta walkthrough so the full
        // event flow is testable on the new base. Flip to 'exhibition-space.walkthrough' when the
        // beta is promoted to the live route.
        return redirect()->route('exhibition-space.walkthrough-next', [
            'slug' => $space->slug,
            'event' => $event->public_token,
        ]);
    }
}
