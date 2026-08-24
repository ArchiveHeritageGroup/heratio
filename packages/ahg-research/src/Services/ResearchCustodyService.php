<?php

/**
 * ResearchCustodyService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chain of custody for reading-room material - #1478.
 *
 * The one reader and writer of research_custody_handoff. Every movement of a
 * requested item is appended here; the row is never updated, because a custody
 * log that can be edited in place is not evidence of anything. A correction is
 * a further row.
 *
 * The handoff types are the three the custody-chain view already colours:
 * `checkout`, `return` and `transfer`. They are stored verbatim in
 * handoff_type and read back as `action`, which is the name the view uses.
 */
class ResearchCustodyService
{
    private const TABLE = 'research_custody_handoff';

    /**
     * Where an item physically lives when nobody has it out. The store is not
     * recorded per-item on this schema, so the constant names it honestly
     * rather than inventing a shelf reference.
     */
    private const STORE_LOCATION = 'Repository store';

    /**
     * One material request, with everything the custody screens name it by.
     *
     * `current_location` and `current_holder` are DERIVED from the latest
     * handoff rather than stored: a location column and a movement log that
     * disagree is worse than either alone, and the log is the record of truth.
     */
    public function getRequestForCustody(int $requestId): ?object
    {
        $item = DB::table('research_material_request as m')
            ->leftJoin('information_object as io', 'm.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('m.id', $requestId)
            ->select(
                'm.*',
                'i18n.title',
                'io.identifier'
            )
            ->first();

        if (! $item) {
            return null;
        }

        $latest = $this->latestHandoff($requestId);

        // Fall back to the request's own recorded location before the store
        // default, so an item that was placed somewhere specific and has no
        // handoff yet still reads correctly.
        $item->current_location = $latest?->to_location
            ?? $item->location_current
            ?? $item->shelf_location
            ?? self::STORE_LOCATION;

        $item->current_holder = ($latest && $latest->handoff_type !== 'return')
            ? ($latest->to_handler_name ?? 'Unknown')
            : 'Repository';

        return $item;
    }

    /**
     * The researcher a request belongs to, via its booking. A material request
     * carries no researcher of its own.
     */
    public function getResearcherForRequest(int $requestId): ?object
    {
        return DB::table('research_material_request as m')
            ->join('research_booking as b', 'm.booking_id', '=', 'b.id')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->where('m.id', $requestId)
            ->select('r.id', 'r.first_name', 'r.last_name', 'r.email')
            ->first();
    }

    /**
     * The custody log for one request, oldest first - a chain reads forwards.
     *
     * `staff_name` is the member of staff who RECORDED the handoff (created_by),
     * which is what the column means on the screen. It falls back through the
     * actor name to the username, because a user row need not have an
     * actor_i18n name and a blank staff column in a custody log is useless.
     */
    public function getChain(int $requestId): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable(self::TABLE)) {
            return collect();
        }

        return DB::table(self::TABLE.' as h')
            ->leftJoin('user as u', 'u.id', '=', 'h.created_by')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')->where('ai.culture', '=', 'en');
            })
            ->where('h.material_request_id', $requestId)
            ->orderBy('h.created_at')
            ->orderBy('h.id')
            // Columns are named explicitly rather than taken from h.*, because
            // the Notes column has to fall back from the movement note to the
            // condition note - a return records its text in condition_notes,
            // paired with return_condition - and two columns called `notes` in
            // one h.* select would resolve by result order, which is not a
            // guarantee worth depending on.
            ->select(
                'h.id',
                'h.material_request_id',
                'h.handoff_type as action',
                'h.from_location',
                'h.to_location',
                'h.condition_at_handoff',
                'h.signature_confirmed',
                'h.confirmed_at',
                'h.created_at',
                DB::raw("COALESCE(NULLIF(h.notes, ''), h.condition_notes) as notes"),
                DB::raw('COALESCE(ai.authorized_form_of_name, u.username) as staff_name')
            )
            ->get();
    }

    /**
     * Record the item leaving the store for a researcher.
     *
     * Two writes, in a transaction: the append-only handoff row, and the
     * request's own current state. They must not be able to disagree.
     */
    public function recordCheckout(int $requestId, array $input, int $userId): void
    {
        $researcher = $this->getResearcherForRequest($requestId);
        $holder = $researcher
            ? trim(($researcher->first_name ?? '').' '.($researcher->last_name ?? ''))
            : 'Researcher';

        DB::transaction(function () use ($requestId, $input, $userId, $holder) {
            $this->append($requestId, [
                'handoff_type'         => 'checkout',
                'from_handler_id'      => $userId,
                'from_location'        => self::STORE_LOCATION,
                'to_location'          => $holder !== '' ? $holder : 'Reading room',
                'condition_at_handoff' => $input['condition'] ?? null,
                'notes'                => $input['notes'] ?? null,
            ], $userId);

            $update = [
                'status'                 => 'delivered',
                'checkout_confirmed_at'  => $input['checkout_date'] ?? now()->toDateString(),
                'checkout_confirmed_by'  => $userId,
                'location_current'       => $holder !== '' ? $holder : 'Reading room',
                'updated_at'             => now(),
            ];

            // Guarded so an instance that has not yet run the migration adding
            // this column still records the checkout rather than throwing.
            if (Schema::hasColumn('research_material_request', 'expected_return')) {
                $update['expected_return'] = $input['expected_return'] ?? null;
            }

            DB::table('research_material_request')->where('id', $requestId)->update($update);
        });
    }

    /**
     * Record the item coming back, and the condition it came back in.
     */
    public function recordReturn(int $requestId, array $input, int $userId): void
    {
        DB::transaction(function () use ($requestId, $input, $userId) {
            $this->append($requestId, [
                'handoff_type'         => 'return',
                'to_handler_id'        => $userId,
                'from_location'        => DB::table('research_material_request')
                    ->where('id', $requestId)->value('location_current') ?? 'Reading room',
                'to_location'          => self::STORE_LOCATION,
                'condition_at_handoff' => $input['return_condition'],
                'condition_notes'      => $input['return_notes'],
                'signature_confirmed'  => 1,
                'confirmed_at'         => now(),
                'confirmed_by'         => $userId,
            ], $userId);

            DB::table('research_material_request')->where('id', $requestId)->update([
                'status'             => 'returned',
                'returned_at'        => now(),
                'return_condition'   => $input['return_condition'],
                'condition_notes'    => $input['return_notes'],
                'return_verified_by' => $userId,
                'return_verified_at' => now(),
                'location_current'   => self::STORE_LOCATION,
                'updated_at'         => now(),
            ]);
        });
    }

    /**
     * Whether this request is currently out and so can be returned.
     */
    public function getCheckoutForVerification(int $requestId): ?object
    {
        $row = DB::table('research_material_request as m')
            ->join('research_booking as b', 'm.booking_id', '=', 'b.id')
            ->join('research_researcher as r', 'b.researcher_id', '=', 'r.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('m.object_id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('m.id', $requestId)
            ->select(
                'm.*',
                'r.first_name',
                'r.last_name',
                // The verify screen calls the title item_title, so it is
                // aliased here rather than renamed in the view - the view is
                // shared with nothing else and the alias keeps the query the
                // single place the name is decided.
                'i18n.title as item_title',
                // Shown beside Expected Return, which is a date, and read
                // only. The precise instant is not lost: the handoff row's
                // created_at carries it, and that is the evidentiary record.
                DB::raw('DATE(m.checkout_confirmed_at) as checkout_date')
            )
            ->first();

        return $row ?: null;
    }

    /**
     * Append one row to the custody log.
     */
    private function append(int $requestId, array $row, int $userId): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        DB::table(self::TABLE)->insert(array_merge([
            'material_request_id' => $requestId,
            'signature_confirmed' => 0,
            'created_by'          => $userId,
            'created_at'          => now(),
        ], $row));
    }

    /**
     * The most recent handoff for a request, with the receiving handler's name
     * resolved - null when the item has never moved.
     */
    private function latestHandoff(int $requestId): ?object
    {
        if (! Schema::hasTable(self::TABLE)) {
            return null;
        }

        return DB::table(self::TABLE.' as h')
            ->leftJoin('user as u', 'u.id', '=', 'h.to_handler_id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'u.id')->where('ai.culture', '=', 'en');
            })
            ->where('h.material_request_id', $requestId)
            ->orderByDesc('h.created_at')
            ->orderByDesc('h.id')
            ->select(
                'h.*',
                DB::raw('COALESCE(ai.authorized_form_of_name, u.username, h.to_location) as to_handler_name')
            )
            ->first();
    }
}
