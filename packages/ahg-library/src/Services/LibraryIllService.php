<?php

/**
 * LibraryIllService — ISO 10160 / ISO 10161 ILL state machine + CRUD
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 * Email: johan@theahg.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Backs the /library-manage/ill surface and the patron-facing ILL request
 * form. Tolerates a missing `library_ill_request` table so the page stays
 * addressable on a fresh install before the migration has run.
 *
 * State machine follows ISO 10160 (ILL Functional Standard) and ISO 10161-1
 * (Open Systems Interconnection profile) with the following principal states
 * for a borrowing (BORROW) request:
 *
 *   [idle] → PENDING → REQUESTED → SHIPPED → RECEIVED → RETURNED → [done]
 *                    ↘ LOST → [done]
 *                    ↘ CANCELLED → [done]
 *   (any state may move → OVERDUE when due_date is past and item not returned)
 *
 * The lender (LEND) side uses: PENDING → SHIPPED → RETURNED → RECEIVED → [done]
 *
 * Transitions are validated before apply(). Invalid transitions are rejected
 * and logged.
 */
class LibraryIllService
{
    // ─── Request type ────────────────────────────────────────────────────────
    public const TYPE_BORROW = 'borrow';   // we request from another library
    public const TYPE_LEND   = 'lend';     // another library requests from us

    // ─── ISO 10160 statuses (named as plain words for UI friendliness) ────────
    public const STATUS_PENDING    = 'pending';
    public const STATUS_REQUESTED  = 'requested';   // Requester-Initiated-1 sent to lender
    public const STATUS_SHIPPED     = 'shipped';     // Item-Shipped notification received
    public const STATUS_LOST        = 'lost';
    public const STATUS_RECEIVED    = 'received';    // Borrower received and checked in
    public const STATUS_RETURNED    = 'returned';    // Borrower returned to lender
    public const STATUS_CANCELLED   = 'cancelled';
    public const STATUS_OVERDUE     = 'overdue';     // escalation, due_date passed
    public const STATUS_UNFULFILLED = 'unfulfilled'; // lender could not supply

    // ─── All valid statuses (for validation / enum display) ─────────────────
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
        self::STATUS_SHIPPED,
        self::STATUS_LOST,
        self::STATUS_RECEIVED,
        self::STATUS_RETURNED,
        self::STATUS_CANCELLED,
        self::STATUS_OVERDUE,
        self::STATUS_UNFULFILLED,
    ];

    // ─── ISO 10160 transition matrix ─────────────────────────────────────────
    // Keyed by request TYPE first, then current_status => allowed next statuses.
    // The lend and borrow lanes share several status names (pending, shipped,
    // received) but with DIFFERENT allowed transitions, so they MUST live in
    // separate sub-arrays — a single flat array silently collapses the duplicate
    // keys (PHP keeps the last literal) and the borrow lane disappears.
    private const TRANSITIONS = [
        // BORROW lane — we request an item from another library (requester side).
        self::TYPE_BORROW => [
            self::STATUS_PENDING     => [self::STATUS_REQUESTED, self::STATUS_CANCELLED],
            self::STATUS_REQUESTED   => [self::STATUS_SHIPPED, self::STATUS_UNFULFILLED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED     => [self::STATUS_RECEIVED, self::STATUS_LOST],
            self::STATUS_RECEIVED    => [self::STATUS_RETURNED],
            self::STATUS_RETURNED    => [],                                          // terminal
            self::STATUS_LOST        => [],                                          // terminal
            self::STATUS_CANCELLED   => [],                                          // terminal
            self::STATUS_UNFULFILLED => [],                                          // terminal
            // OVERDUE is a side-constraint (due_date < now, not terminal); from it
            // the borrower can still receive / lose / return the item.
            self::STATUS_OVERDUE     => [self::STATUS_RECEIVED, self::STATUS_LOST, self::STATUS_RETURNED],
        ],
        // LEND lane — another library requests an item from us (responder side).
        self::TYPE_LEND => [
            self::STATUS_PENDING     => [self::STATUS_SHIPPED, self::STATUS_UNFULFILLED, self::STATUS_CANCELLED],
            self::STATUS_SHIPPED     => [self::STATUS_RECEIVED, self::STATUS_LOST],
            self::STATUS_RECEIVED    => [],                                          // terminal (lender side)
            self::STATUS_RETURNED    => [],                                          // terminal
            self::STATUS_LOST        => [],                                          // terminal
            self::STATUS_CANCELLED   => [],                                          // terminal
            self::STATUS_UNFULFILLED => [],                                          // terminal
            self::STATUS_OVERDUE     => [self::STATUS_RECEIVED, self::STATUS_LOST],
        ],
    ];

    // ─── Transition labels (for audit trail / UI) ────────────────────────────
    private const TRANSITION_LABELS = [
        self::STATUS_PENDING    . '→' . self::STATUS_REQUESTED  => 'ILL request transmitted to lender',
        self::STATUS_PENDING    . '→' . self::STATUS_CANCELLED   => 'Request cancelled (no lender found)',
        self::STATUS_REQUESTED  . '→' . self::STATUS_SHIPPED     => 'Item shipped by lender',
        self::STATUS_REQUESTED  . '→' . self::STATUS_UNFULFILLED  => 'Lender unable to supply (unfulfilled)',
        self::STATUS_REQUESTED  . '→' . self::STATUS_CANCELLED    => 'Request cancelled after lender reply',
        self::STATUS_SHIPPED    . '→' . self::STATUS_RECEIVED    => 'Item received by borrower',
        self::STATUS_SHIPPED    . '→' . self::STATUS_LOST         => 'Item lost in transit',
        self::STATUS_OVERDUE     . '→' . self::STATUS_RECEIVED    => 'Overdue item returned',
        self::STATUS_OVERDUE     . '→' . self::STATUS_LOST         => 'Overdue item declared lost',
        self::STATUS_OVERDUE     . '→' . self::STATUS_RETURNED    => 'Overdue item returned',
        self::STATUS_RECEIVED   . '→' . self::STATUS_RETURNED    => 'Item returned to lender',
    ];

    // ─── Tipasa partner codes (NAZ / SABINET / DALS) ────────────────────────
    public const TIPASA_PARTNER_NAZ     = 'naz';
    public const TIPASA_PARTNER_SABINET = 'sabinet';
    public const TIPASA_PARTNER_DALS    = 'dals';

    public const TIPASA_PARTNERS = [
        self::TIPASA_PARTNER_NAZ,
        self::TIPASA_PARTNER_SABINET,
        self::TIPASA_PARTNER_DALS,
    ];

    // ─── OCLC ILL system identifiers ─────────────────────────────────────────
    public const OCLC_SYSTEM_OCLC      = 'oclc';
    public const OCLC_SYSTEM_FEDORO    = 'fedoro';   // Koha/Fedora-based
    public const OCLC_SYSTEM_CUSTOM    = 'custom';

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD — list
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List ILL requests with optional filters.
     *
     * @param  array $filters  keys: status, type, search, overdue_only, limit
     * @return array
     */
    public function list(array $filters = []): array
    {
        if (!Schema::hasTable('library_ill_request')) {
            return [];
        }

        $q = DB::table('library_ill_request');

        if (!empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $q->where('type', $filters['type']);
        }
        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('ill_number', 'LIKE', $needle)
                    ->orWhere('title', 'LIKE', $needle)
                    ->orWhere('author', 'LIKE', $needle)
                    ->orWhere('isbn', 'LIKE', $needle)
                    ->orWhere('library_name', 'LIKE', $needle);
            });
        }
        if (!empty($filters['overdue_only'])) {
            $q->where('due_date', '<', now()->toDateString())
              ->whereNotIn('status', $this->terminalStatuses());
        }
        if (!empty($filters['patron_id'])) {
            $q->where('patron_id', $filters['patron_id']);
        }

        $q->orderByDesc('request_date')->orderByDesc('id');

        if (!empty($filters['limit'])) {
            $q->limit((int) $filters['limit']);
        }

        return $q->get()->all();
    }

    /**
     * Count requests by status — used by the staff dashboard badges.
     *
     * @param  string|null $type  filter by TYPE_BORROW or TYPE_LEND
     * @return array   [status => count]
     */
    public function countByStatus(?string $type = null): array
    {
        if (!Schema::hasTable('library_ill_request')) {
            return [];
        }
        $q = DB::table('library_ill_request')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status');
        if ($type) {
            $q->where('type', $type);
        }
        $rows = $q->get()->all();
        return array_column($rows, 'cnt', 'status');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD — single record
    // ─────────────────────────────────────────────────────────────────────────

    public function get(int $id): ?object
    {
        if (!Schema::hasTable('library_ill_request')) {
            return null;
        }
        return DB::table('library_ill_request')->where('id', $id)->first() ?: null;
    }

    /**
     * Create a new ILL request. Sets ill_number, request_date, status, type.
     *
     * @param  array $data  keys: type, title, author, isbn, issn, volume, issue,
     *                      pages, edition, publication_year, library_name,
     *                      library_symbol, patron_id, requester_note, due_date
     * @return int  new row id (0 on missing table)
     */
    public function create(array $data): int
    {
        if (!Schema::hasTable('library_ill_request')) {
            return 0;
        }

        $now = now();

        $row = [
            'ill_number'       => $data['ill_number'] ?? $this->generateIllNumber(),
            'type'             => $data['type'] ?? self::TYPE_BORROW,
            'title'            => $data['title'] ?? '',
            'author'           => $data['author'] ?? '',
            'isbn'             => $data['isbn'] ?? null,
            'issn'             => $data['issn'] ?? null,
            'volume'           => $data['volume'] ?? null,
            'issue'            => $data['issue'] ?? null,
            'pages'            => $data['pages'] ?? null,
            'edition'          => $data['edition'] ?? null,
            'publication_year' => $data['publication_year'] ?? null,
            'library_name'     => $data['library_name'] ?? '',
            'library_symbol'   => $data['library_symbol'] ?? null,
            'patron_id'        => $data['patron_id'] ?? null,
            'requester_note'   => $data['requester_note'] ?? null,
            'due_date'         => $data['due_date'] ?? null,
            'status'           => self::STATUS_PENDING,
            'opac_suppress'    => !empty($data['opac_suppress']) ? 1 : 0,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];

        // Log audit event
        $this->logTransition($row['ill_number'], null, self::STATUS_PENDING,
            'ILL request created');

        return (int) DB::table('library_ill_request')->insertGetId($row);
    }

    /**
     * Advance an ILL request to a new status. Validates the transition via the
     * ISO 10160 matrix before applying.
     *
     * @param  int    $id          row id
     * @param  string $newStatus   target status constant
     * @param  string|null $note    optional staff note to append
     * @return bool   true = applied, false = rejected (invalid transition)
     */
    public function transitionTo(int $id, string $newStatus, ?string $note = null): bool
    {
        $current = $this->get($id);
        if (!$current) {
            return false;
        }

        $currentStatus = $current->status;
        if ($currentStatus === $newStatus) {
            return true; // no-op
        }

        if (!$this->isValidTransition($currentStatus, $newStatus, $current->type ?? self::TYPE_BORROW)) {
            Log::warning("ILL invalid transition rejected", [
                'ill_number' => $current->ill_number,
                'from'       => $currentStatus,
                'to'         => $newStatus,
            ]);
            return false;
        }

        $payload = [
            'status'     => $newStatus,
            'updated_at' => now(),
        ];

        // Append audit note if provided
        if ($note !== null) {
            $existing = $current->staff_note ?? '';
            $payload['staff_note'] = trim($existing . "\n[" . now()->toDateTimeString() . "] " . $note);
        }

        $affected = DB::table('library_ill_request')
            ->where('id', $id)
            ->update($payload);

        if ($affected > 0) {
            $this->logTransition(
                $current->ill_number,
                $currentStatus,
                $newStatus,
                $this->getTransitionLabel($currentStatus, $newStatus) ?? ('Transition: ' . $currentStatus . ' → ' . $newStatus)
            );
        }

        return $affected > 0;
    }

    /**
     * Update non-status fields on an ILL request.
     */
    public function update(int $id, array $data): bool
    {
        if (!Schema::hasTable('library_ill_request')) {
            return false;
        }

        // Guard against accidental status changes through this method
        unset($data['status'], $data['ill_number'], $data['id']);

        if (!$data) {
            return false;
        }

        $data['updated_at'] = now();

        return DB::table('library_ill_request')
            ->where('id', $id)
            ->update($data) > 0;
    }

    public function delete(int $id): bool
    {
        if (!Schema::hasTable('library_ill_request')) {
            return false;
        }
        return DB::table('library_ill_request')
            ->where('id', $id)
            ->delete() > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Patron self-service
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Patron submits an ILL request. Mirrors create() but scopes to a specific
     * patron and forces opac_suppress=0 (patron requests are public record).
     *
     * @param  int   $patronId   library_patron.id
     * @param  array $data       same as create()
     * @return int   new row id
     */
    public function patronCreate(int $patronId, array $data): int
    {
        $data['patron_id']    = $patronId;
        $data['opac_suppress'] = 0;
        $data['type']          = self::TYPE_BORROW; // patrons only borrow
        // Force no staff note from patron
        unset($data['staff_note']);

        return $this->create($data);
    }

    /**
     * Fetch ILL requests belonging to a specific patron (for patron account
     * history display in OPAC).
     */
    public function forPatron(int $patronId, array $filters = []): array
    {
        $filters['patron_id'] = $patronId;
        return $this->list($filters);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Overdue escalation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark all past-due requests as overdue (called by nightly cron).
     * Overdue is not a terminal state — it coexists with whatever state
     * the request was in (REQUESTED, SHIPPED, RECEIVED …).
     *
     * @return int  rows affected
     */
    public function escalateOverdue(): int
    {
        if (!Schema::hasTable('library_ill_request')) {
            return 0;
        }
        return DB::table('library_ill_request')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', $this->terminalStatuses())
            ->where(function ($q) {
                // Only set OVERDUE if not already overdue
                $q->where('status', '!=', self::STATUS_OVERDUE)
                  ->orWhereNull('status');
            })
            ->update(['status' => self::STATUS_OVERDUE, 'updated_at' => now()]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // State machine helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check if a transition is valid under ISO 10160 rules for the given type.
     */
    public function isValidTransition(string $from, string $to, string $type): bool
    {
        // Pick the lane for this request type; unknown types fall back to BORROW.
        $lane    = self::TRANSITIONS[$type] ?? self::TRANSITIONS[self::TYPE_BORROW];
        $allowed = $lane[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * Return available next statuses for a given current status.
     * Used by the staff dashboard to show valid action buttons.
     *
     * @return string[]
     */
    public function availableTransitions(string $currentStatus, string $type): array
    {
        $lane = self::TRANSITIONS[$type] ?? self::TRANSITIONS[self::TYPE_BORROW];

        return $lane[$currentStatus] ?? [];
    }

    /**
     * Human-readable label for a specific transition.
     */
    public function getTransitionLabel(string $from, string $to): ?string
    {
        return self::TRANSITION_LABELS[$from . '→' . $to] ?? null;
    }

    /**
     * Terminal statuses (no further transitions possible).
     */
    public function terminalStatuses(): array
    {
        return [
            self::STATUS_RETURNED,
            self::STATUS_LOST,
            self::STATUS_CANCELLED,
            self::STATUS_UNFULFILLED,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ILL number generation
    // ─────────────────────────────────────────────────────────────────────────

    public function generateIllNumber(): string
    {
        $base = 'ILL-' . date('Ymd');
        if (!Schema::hasTable('library_ill_request')) {
            return $base . '-0001';
        }
        $count = (int) DB::table('library_ill_request')
            ->where('request_number', 'LIKE', $base . '-%')
            ->count();
        return $base . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OPAC suppression — for filtering from public catalogue views
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List requests NOT suppressed (for staff / OPAC patron account views).
     */
    public function listPublic(?int $patronId = null): array
    {
        $filters = ['opac_suppress' => 0];
        if ($patronId) {
            $filters['patron_id'] = $patronId;
        }
        return $this->list($filters);
    }

    /**
     * Suppress / unsuppress a request from OPAC listings.
     */
    public function setOpacSuppress(int $id, bool $suppress): bool
    {
        return $this->update($id, ['opac_suppress' => $suppress ? 1 : 0]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function logTransition(string $illNumber, ?string $from, string $to, string $description): void
    {
        // Record an audit entry. library_ill_audit table may not exist on a
        // fresh install — fail silently rather than break the request pipeline.
        try {
            if (!Schema::hasTable('library_ill_audit')) {
                return;
            }
            DB::table('library_ill_audit')->insert([
                'ill_number'   => $illNumber,
                'from_status'  => $from,
                'to_status'    => $to,
                'description'  => $description,
                'changed_by'   => auth()->user()->name ?? 'system',
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('ILL audit log failed', ['ill_number' => $illNumber, 'error' => $e->getMessage()]);
        }
    }
}