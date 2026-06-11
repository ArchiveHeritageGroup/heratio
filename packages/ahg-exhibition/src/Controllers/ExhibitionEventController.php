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

    /**
     * Public: "What's on" - upcoming + live openings across every exhibition space,
     * in start-time order (deepens heratio#1192). Read-only: no DB writes, no auth.
     *
     * Selection: status in (scheduled, live), not cancelled, and not already past
     * (starts_at within the last 24h so a live event mid-run still shows; fully
     * elapsed scheduled rows are then dropped in PHP). For each event we resolve its
     * space (name + slug) for the link, the free-or-priced flag, and the remaining
     * seats via the service's seat logic.
     *
     * Resilient: a missing table or any error degrades to an empty list and the
     * view's empty-state, so this page can never 500.
     */
    public function whatsOn()
    {
        $events = [];
        $now = time();

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_exhibition_event')) {
                // Pull the candidate window in SQL, then filter the live/scheduled
                // tail in PHP using the event's own duration so a running event
                // (start passed, not yet ended) is still surfaced.
                $cutoff = date('Y-m-d H:i:s', $now - (24 * 3600));
                $rows = \Illuminate\Support\Facades\DB::table('ahg_exhibition_event')
                    ->whereIn('status', ['scheduled', 'live'])
                    ->where('starts_at', '>=', $cutoff)
                    ->orderBy('starts_at')
                    ->get();

                // Resolve spaces in one pass; cache so repeated spaces cost one query.
                $spaceCache = [];

                foreach ($rows as $ev) {
                    // Drop anything that has already ended (scheduled rows whose window
                    // has fully elapsed). A status='live' row is always kept.
                    $start = strtotime((string) $ev->starts_at);
                    $end = $start + ((int) ($ev->duration_minutes ?? 0) * 60);
                    if (($ev->status ?? '') !== 'live' && $end < $now) {
                        continue;
                    }

                    $spaceId = (int) $ev->exhibition_space_id;
                    if (! array_key_exists($spaceId, $spaceCache)) {
                        $spaceCache[$spaceId] = $this->spaces->getById($spaceId);
                    }
                    $space = $spaceCache[$spaceId];
                    if (! $space) {
                        continue;   // orphaned event with no resolvable space
                    }

                    $remaining = $this->events->remainingSeats($ev);

                    $events[] = [
                        'token' => (string) $ev->public_token,
                        'title' => (string) ($ev->title ?: __('Untitled opening')),
                        'host_name' => $ev->host_name ?: null,
                        'description' => $ev->description ?: null,
                        'starts_at' => (string) $ev->starts_at,
                        'starts_ts' => $start,
                        'duration_minutes' => (int) ($ev->duration_minutes ?? 0),
                        'status' => (string) ($ev->status ?? 'scheduled'),
                        'is_live' => ($ev->status ?? '') === 'live',
                        'is_paid' => $this->events->isPaid($ev),
                        'price' => isset($ev->price) ? (float) $ev->price : null,
                        'currency' => $ev->currency ?? ExhibitionEventService::DEFAULT_CURRENCY,
                        'capacity' => (int) ($ev->capacity ?? 0),
                        'remaining' => $remaining,
                        'sold_out' => $remaining <= 0,
                        'space_name' => (string) ($space->name ?? __('Exhibition space')),
                        'space_slug' => $space->slug ?? null,
                        'date_label' => date('l, j F Y', $start),
                        'time_label' => date('H:i', $start),
                    ];
                }
            }
        } catch (\Throwable $e) {
            $events = [];   // never 500 - fall through to the empty state
        }

        // Group by calendar date for the view (preserves the start-time ordering).
        $grouped = [];
        foreach ($events as $row) {
            $grouped[$row['date_label']][] = $row;
        }

        return view('ahg-exhibition::exhibition-space.whats-on', [
            'events' => $events,
            'grouped' => $grouped,
            // Gate links only when both the route and a real slug/token exist.
            'hasSpaceIndex' => \Illuminate\Support\Facades\Route::has('exhibition-space.index'),
            'hasOpeningPublic' => \Illuminate\Support\Facades\Route::has('exhibition-space.opening-public'),
            'hasSpaceShow' => \Illuminate\Support\Facades\Route::has('exhibition-space.show'),
        ]);
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
