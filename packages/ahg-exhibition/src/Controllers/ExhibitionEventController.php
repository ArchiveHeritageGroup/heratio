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
}
