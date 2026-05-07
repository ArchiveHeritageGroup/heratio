<?php

/**
 * LibraryPatronService - patron CRUD with settings-driven defaults
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

namespace AhgLibrary\Services;

use AhgLibrary\Support\LibrarySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LibraryPatronService
{
    /**
     * List patrons with optional search + status filter.
     */
    public function list(array $filters = []): array
    {
        $q = DB::table('library_patron');

        if (!empty($filters['search'])) {
            $needle = '%' . $filters['search'] . '%';
            $q->where(function ($w) use ($needle) {
                $w->where('first_name', 'LIKE', $needle)
                    ->orWhere('last_name', 'LIKE', $needle)
                    ->orWhere('email', 'LIKE', $needle)
                    ->orWhere('card_number', 'LIKE', $needle);
            });
        }
        if (!empty($filters['status'])) {
            $q->where('borrowing_status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $q->where('patron_type', $filters['type']);
        }

        return $q->orderBy('last_name')->orderBy('first_name')->get()->all();
    }

    public function get(int $id): ?object
    {
        return DB::table('library_patron')->where('id', $id)->first() ?: null;
    }

    public function getByCardNumber(string $card): ?object
    {
        return DB::table('library_patron')->where('card_number', $card)->first() ?: null;
    }

    /**
     * Create a patron, defaulting type / max_* / membership_expiry from
     * library_* settings when the caller hasn't supplied them.
     */
    public function create(array $data): int
    {
        $months = LibrarySettings::patronMembershipMonths();

        $row = [
            'card_number' => $data['card_number'] ?? $this->generateCardNumber(),
            'patron_type' => $data['patron_type'] ?? LibrarySettings::patronDefaultType(),
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'institution' => $data['institution'] ?? null,
            'department' => $data['department'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'membership_start' => $data['membership_start'] ?? date('Y-m-d'),
            'membership_expiry' => $data['membership_expiry']
                ?? date('Y-m-d', strtotime("+{$months} months")),
            'max_checkouts' => (int) ($data['max_checkouts'] ?? LibrarySettings::patronMaxCheckouts()),
            'max_renewals' => (int) ($data['max_renewals'] ?? LibrarySettings::patronMaxRenewals()),
            'max_holds' => (int) ($data['max_holds'] ?? LibrarySettings::patronMaxHolds()),
            'borrowing_status' => $data['borrowing_status'] ?? 'active',
            'actor_id' => $data['actor_id'] ?? null,
            'created_at' => now(),
        ];

        return (int) DB::table('library_patron')->insertGetId($row);
    }

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'first_name', 'last_name', 'email', 'phone', 'address',
            'institution', 'department', 'id_number', 'date_of_birth',
            'patron_type', 'membership_expiry', 'max_checkouts', 'max_renewals',
            'max_holds', 'borrowing_status', 'suspension_reason', 'suspension_until',
        ];
        $row = ['updated_at' => now()];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $row[$f] = $data[$f];
            }
        }
        if (count($row) === 1) {
            return false;
        }
        return DB::table('library_patron')->where('id', $id)->update($row) > 0;
    }

    public function suspend(int $id, string $reason, ?string $until = null): bool
    {
        return $this->update($id, [
            'borrowing_status' => 'suspended',
            'suspension_reason' => $reason,
            'suspension_until' => $until,
        ]);
    }

    /**
     * Drop expired patrons to status='expired'. Run from the auto-expire
     * cron command. Honours the grace-period setting so a freshly-lapsed
     * patron isn't kicked out on day 1.
     */
    public function expireLapsed(): int
    {
        $grace = LibrarySettings::patronExpiryGraceDays();
        $cutoff = date('Y-m-d', strtotime("-{$grace} days"));

        return DB::table('library_patron')
            ->where('borrowing_status', 'active')
            ->whereNotNull('membership_expiry')
            ->where('membership_expiry', '<', $cutoff)
            ->update([
                'borrowing_status' => 'expired',
                'updated_at' => now(),
            ]);
    }

    public function getActiveLoans(int $patronId): array
    {
        return DB::table('library_checkout as c')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('c.patron_id', $patronId)
            ->where('c.status', 'active')
            ->select(
                'c.id', 'c.copy_id', 'c.checkout_date', 'c.due_date', 'c.renewed_count',
                'cp.barcode', 'cp.shelf_location',
                'li.call_number', 'li.isbn',
                'i18n.title',
            )
            ->orderBy('c.due_date')
            ->get()
            ->all();
    }

    public function getActiveHolds(int $patronId): array
    {
        return DB::table('library_hold as h')
            ->leftJoin('library_item as li', 'h.library_item_id', '=', 'li.id')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('h.patron_id', $patronId)
            ->whereIn('h.status', ['pending', 'ready'])
            ->select(
                'h.id', 'h.library_item_id', 'h.hold_date', 'h.expiry_date',
                'h.queue_position', 'h.status', 'h.pickup_branch',
                'li.call_number',
                'i18n.title',
            )
            ->orderBy('h.hold_date')
            ->get()
            ->all();
    }

    /**
     * Generate a card number when the operator didn't supply one. Format:
     * LIB-{YY}-{6 random hex}; uniqueness enforced via UNIQUE index on
     * library_patron.card_number, so a collision falls through to a retry.
     */
    protected function generateCardNumber(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = sprintf('LIB-%02d-%s', date('y'), strtoupper(Str::random(6)));
            if (!DB::table('library_patron')->where('card_number', $candidate)->exists()) {
                return $candidate;
            }
        }
        Log::warning('[library] generateCardNumber exhausted retries; falling back to uniqid');
        return 'LIB-' . uniqid();
    }
}
