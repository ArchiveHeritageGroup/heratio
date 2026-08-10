<?php

/*
 * Artwork placement requests (#1459, ported from ahgArtworkRequestPlugin 0.3.1).
 *
 * The shape is deliberately small. A request is captured, the people responsible
 * are told, availability is reported, and whatever was decided is written down.
 * The decision itself is a conversation between people; this records it rather
 * than running it.
 *
 * Copyright (C) 2026 Johan Pieterse - The Archive Heritage Group (Pty) Ltd.
 * Part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgArtworkRequest\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ArtworkRequestService
{
    /**
     * Availability for one work over a date range.
     *
     * Three sources have to agree before a work is free: other placement
     * requests, institutional loans (ahg-loan), and exhibition commitments
     * (ahg-exhibition). The last two are checked only when their tables exist,
     * so a site without those packages still gets a useful answer. Returns a
     * list of clashes, empty when the work is free. Never throws on a missing
     * optional table.
     */
    public static function findConflicts(int $objectId, string $from, string $to, ?int $ignoreRequestId = null): array
    {
        $conflicts = [];

        // Other placement requests, live ones only.
        $query = DB::table('artwork_request_object as aro')
            ->join('artwork_request as ar', 'ar.id', '=', 'aro.request_id')
            ->where('aro.information_object_id', $objectId)
            ->whereIn('ar.status', ['submitted', 'approved', 'fulfilled'])
            ->whereIn('aro.status', ['requested', 'approved', 'issued'])
            ->where('ar.requested_from', '<=', $to)
            ->where('ar.requested_to', '>=', $from);

        if ($ignoreRequestId) {
            $query->where('ar.id', '!=', $ignoreRequestId);
        }

        foreach ($query->select('ar.id', 'ar.request_number', 'ar.requester_name', 'ar.requested_from', 'ar.requested_to', 'ar.status')->get() as $row) {
            $conflicts[] = [
                'source' => 'request',
                'reference' => $row->request_number,
                'who' => $row->requester_name,
                'from' => $row->requested_from,
                'to' => $row->requested_to,
                'detail' => sprintf('Requested by %s (%s)', $row->requester_name ?: 'unknown', $row->status),
            ];
        }

        // Institutional loans, when ahg-loan is installed.
        if (self::hasTable('ahg_loan_object') && self::hasTable('ahg_loan')) {
            $loans = DB::table('ahg_loan_object as lo')
                ->join('ahg_loan as l', 'l.id', '=', 'lo.loan_id')
                ->where('lo.information_object_id', $objectId)
                ->whereNotIn('l.status', ['cancelled', 'completed', 'returned'])
                ->where('l.start_date', '<=', $to)
                ->where('l.end_date', '>=', $from)
                ->select('l.loan_number', 'l.partner_institution', 'l.start_date', 'l.end_date')
                ->get();

            foreach ($loans as $row) {
                $conflicts[] = [
                    'source' => 'loan',
                    'reference' => $row->loan_number,
                    'who' => $row->partner_institution,
                    'from' => $row->start_date,
                    'to' => $row->end_date,
                    'detail' => 'On loan to '.$row->partner_institution,
                ];
            }
        }

        // Exhibition commitments, when ahg-exhibition is installed. Heratio's
        // exhibition table dates its run with opening_date / closing_date.
        if (self::hasTable('exhibition_object') && self::hasTable('exhibition')) {
            $shows = DB::table('exhibition_object as eo')
                ->join('exhibition as e', 'e.id', '=', 'eo.exhibition_id')
                ->where('eo.information_object_id', $objectId)
                ->whereNotNull('e.opening_date')
                ->whereNotNull('e.closing_date')
                ->where('e.opening_date', '<=', $to)
                ->where('e.closing_date', '>=', $from)
                ->select('e.title', 'e.opening_date', 'e.closing_date')
                ->get();

            foreach ($shows as $row) {
                $conflicts[] = [
                    'source' => 'exhibition',
                    'reference' => $row->title,
                    'who' => $row->title,
                    'from' => $row->opening_date,
                    'to' => $row->closing_date,
                    'detail' => 'Committed to exhibition: '.$row->title,
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Create a request and, if it is being submitted, tell the approvers.
     *
     * @param array $objectIds information object ids
     */
    public static function create(array $data, array $objectIds, bool $submit = true): int
    {
        $number = self::nextRequestNumber();

        $requestId = DB::table('artwork_request')->insertGetId([
            'request_number' => $number,
            'requester_user_id' => $data['requester_user_id'] ?? null,
            'requester_name' => $data['requester_name'] ?? null,
            'requester_email' => $data['requester_email'] ?? null,
            'department' => $data['department'] ?? null,
            'status' => $submit ? 'submitted' : 'draft',
            'purpose' => $data['purpose'] ?? null,
            'justification' => $data['justification'] ?? null,
            'requested_from' => $data['requested_from'] ?? null,
            'requested_to' => $data['requested_to'] ?? null,
            'placement_building' => $data['placement_building'] ?? null,
            'placement_floor' => $data['placement_floor'] ?? null,
            'placement_room' => $data['placement_room'] ?? null,
            'placement_occupant' => $data['placement_occupant'] ?? null,
            'placement_notes' => $data['placement_notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($objectIds as $objectId) {
            $objectId = (int) $objectId;

            if ($objectId < 1) {
                continue;
            }

            $conflicts = ($data['requested_from'] ?? null) && ($data['requested_to'] ?? null)
                ? self::findConflicts($objectId, $data['requested_from'], $data['requested_to'], $requestId)
                : [];

            DB::table('artwork_request_object')->insert([
                'request_id' => $requestId,
                'information_object_id' => $objectId,
                'object_title' => self::objectTitle($objectId),
                'object_identifier' => self::objectIdentifier($objectId),
                'status' => 'requested',
                'conflict_note' => $conflicts
                    ? implode('; ', array_column($conflicts, 'detail'))
                    : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        self::log($requestId, $submit ? 'submitted' : 'created', $data['requester_name'] ?? null, null, $data['requester_user_id'] ?? null);

        if ($submit) {
            self::notifyApprovers($requestId);
        }

        return $requestId;
    }

    /**
     * Record a decision, per work rather than per request. $decisions maps
     * artwork_request_object id to 'approved' or 'declined'.
     */
    public static function decide(int $requestId, array $decisions, ?string $notes, ?int $reviewerId, string $channel = 'system'): bool
    {
        $request = self::get($requestId);

        if (! $request) {
            return false;
        }

        foreach ($decisions as $objectRowId => $decision) {
            if (! in_array($decision, ['approved', 'declined'], true)) {
                continue;
            }

            DB::table('artwork_request_object')
                ->where('id', (int) $objectRowId)
                ->where('request_id', $requestId)
                ->update(['status' => $decision, 'updated_at' => date('Y-m-d H:i:s')]);
        }

        $anyApproved = DB::table('artwork_request_object')
            ->where('request_id', $requestId)
            ->where('status', 'approved')
            ->exists();

        DB::table('artwork_request')->where('id', $requestId)->update([
            'status' => $anyApproved ? 'approved' : 'declined',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
            'decision_channel' => 'offline' === $channel ? 'offline' : 'system',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::log($requestId, $anyApproved ? 'approved' : 'declined', null, $notes, $reviewerId);
        self::notifyRequester($requestId, $anyApproved ? 'approved' : 'declined');

        return true;
    }

    /**
     * What is out, and what is late.
     */
    public static function placements(bool $overdueOnly = false): array
    {
        $query = DB::table('artwork_request_object as aro')
            ->join('artwork_request as ar', 'ar.id', '=', 'aro.request_id')
            ->whereIn('aro.status', ['approved', 'issued'])
            ->whereIn('ar.status', ['approved', 'fulfilled']);

        if ($overdueOnly) {
            $query->where('ar.requested_to', '<', date('Y-m-d'));
        }

        return $query->select(
            'aro.id', 'aro.object_title', 'aro.object_identifier', 'aro.status', 'aro.issued_at',
            'ar.request_number', 'ar.requester_name', 'ar.department',
            'ar.placement_building', 'ar.placement_room', 'ar.placement_occupant',
            'ar.requested_from', 'ar.requested_to'
        )->orderBy('ar.requested_to')->get()->all();
    }

    /**
     * Create a loan record from an approved request. A button, never automatic:
     * approving decides whether a work may hang somewhere, creating the loan is
     * the moment it physically moves. Hands off to ahg-loan so condition reports
     * and movement are tracked where they belong. partner_institution is NOT NULL
     * on ahg_loan and an internal placement has no partner, so the department goes
     * there rather than something untrue - which is why this is a separate table.
     *
     * @return int|null the loan id, or null when ahg-loan is absent or nothing was approved
     */
    public static function createLoan(int $requestId, ?int $actorId = null): ?int
    {
        if (! self::hasTable('ahg_loan') || ! self::hasTable('ahg_loan_object')) {
            return null;
        }

        $request = self::get($requestId);

        if (! $request || $request->loan_id) {
            return null;
        }

        $approved = array_filter(
            self::objects($requestId),
            static fn ($w) => in_array($w->status, ['approved', 'issued'], true)
        );

        if (! $approved) {
            return null;
        }

        try {
            $number = (string) $request->request_number;

            $loanId = DB::table('ahg_loan')->insertGetId([
                'loan_number' => $number,
                'loan_type' => 'out',
                'sector' => 'gallery',
                'title' => 'Placement: '.trim(($request->placement_building ?? '').' '.($request->placement_room ?? '')),
                'description' => $request->justification,
                'purpose' => $request->purpose ?: 'office',
                'partner_institution' => $request->department ?: 'Internal placement',
                'partner_contact_name' => $request->placement_occupant ?: $request->requester_name,
                'partner_contact_email' => $request->requester_email,
                'request_date' => $request->created_at,
                'start_date' => $request->requested_from,
                'end_date' => $request->requested_to,
                'status' => 'approved',
                'internal_approver_id' => $request->reviewed_by,
                'approved_date' => $request->reviewed_at,
                'notes' => 'Created from artwork placement request '.$request->request_number,
                'created_by' => $actorId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            foreach ($approved as $work) {
                DB::table('ahg_loan_object')->insert([
                    'loan_id' => $loanId,
                    'information_object_id' => $work->information_object_id,
                    'object_title' => $work->object_title,
                    'object_identifier' => $work->object_identifier,
                    'status' => 'approved',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            DB::table('artwork_request')->where('id', $requestId)->update([
                'loan_id' => $loanId,
                'status' => 'fulfilled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            self::log($requestId, 'issued', null, "Loan {$number} created with ".count($approved).' work(s)', $actorId);

            return $loanId;
        } catch (\Throwable $e) {
            self::log($requestId, 'note', null, 'Could not create the loan record: '.$e->getMessage(), $actorId);

            return null;
        }
    }

    /**
     * Placements past their return date that have not been chased recently.
     *
     * @param int $everyDays do not chase the same request more often than this
     */
    public static function overdueNeedingReminder(int $everyDays = 7): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$everyDays} days"));

        return DB::table('artwork_request as ar')
            ->join('artwork_request_object as aro', 'aro.request_id', '=', 'ar.id')
            ->whereIn('ar.status', ['approved', 'fulfilled'])
            ->whereIn('aro.status', ['approved', 'issued'])
            ->whereDate('ar.requested_to', '<', date('Y-m-d'))
            ->whereNotExists(function ($q) use ($cutoff) {
                $q->select(DB::raw(1))
                    ->from('artwork_request_log')
                    ->whereColumn('artwork_request_log.request_id', 'ar.id')
                    ->where('artwork_request_log.event', 'reminded')
                    ->where('artwork_request_log.created_at', '>=', $cutoff);
            })
            ->select('ar.id', 'ar.request_number', 'ar.requester_name', 'ar.requester_email',
                'ar.department', 'ar.requested_to', 'ar.placement_building',
                'ar.placement_room', 'ar.placement_occupant')
            ->distinct()
            ->orderBy('ar.requested_to')
            ->get()
            ->all();
    }

    /**
     * Send the overdue reminders and record that they went. Every send is logged
     * as a `reminded` event, so the record shows what was chased and when.
     *
     * @return int how many were sent
     */
    public static function sendOverdueReminders(int $everyDays = 7): int
    {
        $sent = 0;

        foreach (self::overdueNeedingReminder($everyDays) as $r) {
            $days = (int) floor((time() - strtotime((string) $r->requested_to)) / 86400);

            $where = trim(($r->placement_building ?? '').' '.($r->placement_room ?? ''));
            $body = sprintf(
                "%s was due back on %s - %d day(s) ago.\n\nRequest: %s\nPlacement: %s\nWith: %s\n\n".
                "Please arrange its return, or ask the gallery to extend the placement.\n",
                $r->request_number,
                $r->requested_to,
                $days,
                $r->request_number,
                $where ?: 'not recorded',
                $r->placement_occupant ?: $r->requester_name
            );

            if ($r->requester_email) {
                self::sendEmail($r->requester_email, 'Artwork overdue: '.$r->request_number, $body);
            }

            foreach (self::approversFor($r->department) as $approver) {
                self::sendEmail($approver->email, 'Artwork overdue: '.$r->request_number, $body);
            }

            self::log((int) $r->id, 'reminded', null, "Overdue by {$days} day(s); reminder sent");
            ++$sent;
        }

        return $sent;
    }

    public static function get(int $id)
    {
        return DB::table('artwork_request')->where('id', $id)->first();
    }

    public static function objects(int $requestId): array
    {
        return DB::table('artwork_request_object')
            ->where('request_id', $requestId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public static function logEntries(int $requestId): array
    {
        return DB::table('artwork_request_log')
            ->where('request_id', $requestId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    public static function log(int $requestId, string $event, ?string $actorName = null, ?string $detail = null, ?int $actorId = null): void
    {
        try {
            DB::table('artwork_request_log')->insert([
                'request_id' => $requestId,
                'event' => $event,
                'actor_user_id' => $actorId,
                'actor_name' => $actorName,
                'detail' => $detail,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // A log write must never take down the action it is recording.
        }
    }

    // ------------------------------------------------------------------ internals

    protected static function approversFor(?string $department): array
    {
        $query = DB::table('artwork_request_approver as ara')
            ->join('user as u', 'ara.user_id', '=', 'u.id')
            ->where('ara.active', 1)
            ->where('ara.email_notifications', 1);

        if ($department) {
            $query->where(function ($q) use ($department) {
                $q->whereNull('ara.department')->orWhere('ara.department', $department);
            });
        }

        return $query->select('u.email', 'u.username')->get()->all();
    }

    protected static function notifyApprovers(int $requestId): void
    {
        $request = self::get($requestId);

        if (! $request) {
            return;
        }

        $works = self::objects($requestId);
        $titles = implode(', ', array_map(static fn ($w) => $w->object_title ?: ('#'.$w->information_object_id), $works));

        $body = sprintf(
            "%s has requested %d artwork(s) for placement.\n\n".
            "Request:  %s\nWorks:    %s\nPeriod:   %s to %s\nPlacement: %s %s %s\nPurpose:  %s\n\n%s\n",
            $request->requester_name ?: 'A member of staff',
            count($works),
            $request->request_number,
            $titles,
            $request->requested_from,
            $request->requested_to,
            $request->placement_building,
            $request->placement_room,
            $request->placement_occupant ? '('.$request->placement_occupant.')' : '',
            $request->purpose,
            $request->justification
        );

        foreach (self::approversFor($request->department) as $approver) {
            self::sendEmail($approver->email, 'Artwork request '.$request->request_number, $body);
        }
    }

    protected static function notifyRequester(int $requestId, string $outcome): void
    {
        $request = self::get($requestId);

        if (! $request || ! $request->requester_email) {
            return;
        }

        $lines = [];

        foreach (self::objects($requestId) as $work) {
            $lines[] = sprintf('  %-50s %s', $work->object_title ?: '#'.$work->information_object_id, $work->status);
        }

        self::sendEmail(
            $request->requester_email,
            sprintf('Artwork request %s - %s', $request->request_number, $outcome),
            sprintf("Your request %s has been %s.\n\n%s\n\n%s\n",
                $request->request_number, $outcome, implode("\n", $lines), $request->review_notes ?: '')
        );
    }

    /**
     * Send through Heratio's configured mailer. The SMTP settings from the
     * email_setting table are applied globally in AppServiceProvider::boot, so
     * Mail::raw reaches the same transport the rest of the app uses. A
     * notification failure must never roll back the request itself.
     */
    protected static function sendEmail(string $to, string $subject, string $body): void
    {
        if (! $to) {
            return;
        }

        try {
            Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            // Swallow: a request nobody was told about is bad, but a request that
            // rolled back because the mail server hiccuped is worse.
        }
    }

    protected static function nextRequestNumber(): string
    {
        $year = date('Y');
        $count = DB::table('artwork_request')->where('request_number', 'like', "AR-{$year}-%")->count();

        return sprintf('AR-%s-%04d', $year, $count + 1);
    }

    protected static function objectTitle(int $objectId): ?string
    {
        return DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->orderByRaw("culture = 'en' DESC")
            ->value('title');
    }

    protected static function objectIdentifier(int $objectId): ?string
    {
        return DB::table('information_object')->where('id', $objectId)->value('identifier');
    }

    protected static function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ------------------------------------------------------ approver settings

    public static function approvers(): array
    {
        return DB::table('artwork_request_approver as ara')
            ->join('user as u', 'ara.user_id', '=', 'u.id')
            ->select('ara.id', 'ara.user_id', 'ara.department', 'ara.email_notifications',
                'ara.active', 'ara.created_at', 'u.username', 'u.email')
            ->orderByDesc('ara.active')
            ->orderBy('u.username')
            ->get()
            ->all();
    }

    /**
     * Add an approver, identified by username or email. Returns null when no such
     * user exists, so the screen can say which of the two things went wrong.
     */
    public static function addApprover(string $userRef, ?string $department, bool $emailNotifications = true): ?int
    {
        $userRef = trim($userRef);

        if ('' === $userRef) {
            return null;
        }

        $user = DB::table('user')
            ->where('username', $userRef)
            ->orWhere('email', $userRef)
            ->select('id')
            ->first();

        if (! $user) {
            return null;
        }

        $department = ('' === trim((string) $department)) ? null : trim((string) $department);

        // The unique key is (user_id, department), and NULL never equals NULL in
        // MySQL, so the general queue would take the same person twice if this
        // relied on the constraint alone.
        $existing = DB::table('artwork_request_approver')
            ->where('user_id', $user->id)
            ->when(null === $department,
                static fn ($q) => $q->whereNull('department'),
                static fn ($q) => $q->where('department', $department))
            ->first();

        if ($existing) {
            DB::table('artwork_request_approver')
                ->where('id', $existing->id)
                ->update(['active' => 1, 'email_notifications' => $emailNotifications ? 1 : 0]);

            return (int) $existing->id;
        }

        return (int) DB::table('artwork_request_approver')->insertGetId([
            'user_id' => (int) $user->id,
            'department' => $department,
            'email_notifications' => $emailNotifications ? 1 : 0,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function setApproverActive(int $approverId, bool $active): void
    {
        DB::table('artwork_request_approver')
            ->where('id', $approverId)
            ->update(['active' => $active ? 1 : 0]);
    }

    public static function setApproverNotifications(int $approverId, bool $on): void
    {
        DB::table('artwork_request_approver')
            ->where('id', $approverId)
            ->update(['email_notifications' => $on ? 1 : 0]);
    }

    public static function removeApprover(int $approverId): void
    {
        DB::table('artwork_request_approver')->where('id', $approverId)->delete();
    }

    public static function candidateUsers(int $limit = 500): array
    {
        return DB::table('user')
            ->whereNotNull('username')
            ->select('id', 'username', 'email')
            ->orderBy('username')
            ->limit($limit)
            ->get()
            ->all();
    }

    // --------------------------------------------------- due-soon reminders

    /**
     * Placements coming up for return within $withinDays. Deliberately a
     * different log event from `reminded`, so a courtesy nudge cannot suppress
     * the overdue chase that actually matters.
     */
    public static function dueSoonNeedingReminder(int $withinDays = 7, int $everyDays = 7): array
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$everyDays} days"));
        $horizon = date('Y-m-d', strtotime("+{$withinDays} days"));

        return DB::table('artwork_request as ar')
            ->join('artwork_request_object as aro', 'aro.request_id', '=', 'ar.id')
            ->whereIn('ar.status', ['approved', 'fulfilled'])
            ->whereIn('aro.status', ['approved', 'issued'])
            ->whereDate('ar.requested_to', '>=', date('Y-m-d'))
            ->whereDate('ar.requested_to', '<=', $horizon)
            ->whereNotExists(function ($q) use ($cutoff) {
                $q->select(DB::raw(1))
                    ->from('artwork_request_log')
                    ->whereColumn('artwork_request_log.request_id', 'ar.id')
                    ->where('artwork_request_log.event', 'reminded_due_soon')
                    ->where('artwork_request_log.created_at', '>=', $cutoff);
            })
            ->select('ar.id', 'ar.request_number', 'ar.requester_name', 'ar.requester_email',
                'ar.department', 'ar.requested_to', 'ar.placement_building',
                'ar.placement_room', 'ar.placement_occupant')
            ->distinct()
            ->orderBy('ar.requested_to')
            ->get()
            ->all();
    }

    public static function sendDueSoonReminders(int $withinDays = 7, int $everyDays = 7): int
    {
        $sent = 0;

        foreach (self::dueSoonNeedingReminder($withinDays, $everyDays) as $r) {
            $days = (int) ceil((strtotime((string) $r->requested_to) - time()) / 86400);
            $where = trim(($r->placement_building ?? '').' '.($r->placement_room ?? ''));

            $body = sprintf(
                "%s is due back on %s, in %d day(s).\n\nRequest: %s\nPlacement: %s\nWith: %s\n\n".
                "No action is needed if it is coming back as arranged. If you would like to keep it ".
                "longer, ask the gallery to extend the placement before the date above.\n",
                $r->request_number,
                $r->requested_to,
                max(0, $days),
                $r->request_number,
                $where ?: 'not recorded',
                $r->placement_occupant ?: $r->requester_name
            );

            if ($r->requester_email) {
                self::sendEmail($r->requester_email, 'Artwork due back soon: '.$r->request_number, $body);
            }

            foreach (self::approversFor($r->department) as $approver) {
                self::sendEmail($approver->email, 'Artwork due back soon: '.$r->request_number, $body);
            }

            self::log((int) $r->id, 'reminded_due_soon', null, "Due in {$days} day(s); courtesy reminder sent");
            ++$sent;
        }

        return $sent;
    }

    /**
     * Resolve works for the request form, before any request exists. Unknown ids
     * are returned too, flagged, rather than silently dropped.
     *
     * @param int[] $ids
     *
     * @return array<int, object> {id, title, identifier, exists}
     */
    public static function worksByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (! $ids) {
            return [];
        }

        $rows = DB::table('information_object')
            ->whereIn('id', $ids)
            ->pluck('identifier', 'id')
            ->all();

        $out = [];

        foreach ($ids as $id) {
            $known = array_key_exists($id, $rows);

            $out[] = (object) [
                'id' => $id,
                'title' => $known ? self::objectTitle($id) : null,
                'identifier' => $known ? ($rows[$id] ?: null) : null,
                'exists' => $known,
            ];
        }

        return $out;
    }

    /**
     * Object ids from a submitted form, however they were entered - the picker's
     * object_ids[] plus the plain-text fallback field, so the form is completable
     * without JavaScript.
     */
    public static function objectIdsFromRequest(array $listed, ?string $typed): array
    {
        $ids = array_map('intval', $listed);

        foreach (preg_split('/[^0-9]+/', (string) $typed) ?: [] as $part) {
            if ('' !== $part) {
                $ids[] = (int) $part;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
