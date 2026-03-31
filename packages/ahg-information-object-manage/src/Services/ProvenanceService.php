<?php

/**
 * ProvenanceService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Facades\DB;

/**
 * Service for provenance chain operations.
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgProvenancePlugin/
 *
 * Table: provenance_entry
 */
class ProvenanceService
{
    /**
     * Get the full provenance chain for an information object, ordered by sequence.
     */
    public function getChain(int $objectId): \Illuminate\Support\Collection
    {
        return DB::table('provenance_entry')
            ->where('information_object_id', $objectId)
            ->orderBy('sequence', 'asc')
            ->get();
    }

    /**
     * Get a single provenance entry by ID.
     */
    public function getEntry(int $id): ?object
    {
        return DB::table('provenance_entry')
            ->where('id', $id)
            ->first();
    }

    /**
     * Create a new provenance entry. Sequence is auto-set to max+1 for the object.
     *
     * @return int The new entry ID
     */
    public function createEntry(array $data): int
    {
        // Determine next sequence number
        $maxSeq = DB::table('provenance_entry')
            ->where('information_object_id', $data['information_object_id'])
            ->max('sequence') ?? 0;

        return DB::table('provenance_entry')->insertGetId([
            'information_object_id' => $data['information_object_id'],
            'sequence'              => $maxSeq + 1,
            'owner_name'            => $data['owner_name'],
            'owner_type'            => $data['owner_type'] ?? 'unknown',
            'owner_actor_id'        => $data['owner_actor_id'] ?? null,
            'owner_location'        => $data['owner_location'] ?? null,
            'owner_location_tgn'    => $data['owner_location_tgn'] ?? null,
            'start_date'            => $data['start_date'] ?? null,
            'start_date_qualifier'  => $data['start_date_qualifier'] ?? null,
            'end_date'              => $data['end_date'] ?? null,
            'end_date_qualifier'    => $data['end_date_qualifier'] ?? null,
            'transfer_type'         => $data['transfer_type'] ?? 'unknown',
            'transfer_details'      => $data['transfer_details'] ?? null,
            'sale_price'            => $data['sale_price'] ?? null,
            'sale_currency'         => $data['sale_currency'] ?? null,
            'auction_house'         => $data['auction_house'] ?? null,
            'auction_lot'           => $data['auction_lot'] ?? null,
            'certainty'             => $data['certainty'] ?? 'unknown',
            'sources'               => $data['sources'] ?? null,
            'notes'                 => $data['notes'] ?? null,
            'is_gap'                => $data['is_gap'] ?? 0,
            'gap_explanation'       => $data['gap_explanation'] ?? null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /**
     * Update an existing provenance entry.
     */
    public function updateEntry(int $id, array $data): bool
    {
        $update = [];

        $fields = [
            'owner_name', 'owner_type', 'owner_actor_id', 'owner_location',
            'owner_location_tgn', 'start_date', 'start_date_qualifier',
            'end_date', 'end_date_qualifier', 'transfer_type', 'transfer_details',
            'sale_price', 'sale_currency', 'auction_house', 'auction_lot',
            'certainty', 'sources', 'notes', 'is_gap', 'gap_explanation', 'sequence',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        $update['updated_at'] = now();

        return DB::table('provenance_entry')
            ->where('id', $id)
            ->update($update) >= 0;
    }

    /**
     * Delete a provenance entry and resequence the remaining entries.
     */
    public function deleteEntry(int $id): bool
    {
        $entry = $this->getEntry($id);
        if (!$entry) {
            return false;
        }

        $deleted = DB::table('provenance_entry')
            ->where('id', $id)
            ->delete() > 0;

        if ($deleted) {
            $this->resequence($entry->information_object_id);
        }

        return $deleted;
    }

    /**
     * Resequence provenance entries for an object (1, 2, 3, ...).
     */
    private function resequence(int $objectId): void
    {
        $entries = DB::table('provenance_entry')
            ->where('information_object_id', $objectId)
            ->orderBy('sequence', 'asc')
            ->select('id')
            ->get();

        foreach ($entries as $i => $entry) {
            DB::table('provenance_entry')
                ->where('id', $entry->id)
                ->update(['sequence' => $i + 1]);
        }
    }

    /**
     * Format provenance chain data for D3.js timeline visualization.
     *
     * @return string JSON-encoded timeline items
     */
    public function getTimelineData(int $objectId): string
    {
        $chain = $this->getChain($objectId);
        $timeline = [];

        foreach ($chain as $entry) {
            $startDate = $entry->start_date;
            $dateDisplay = $startDate ?: 'Unknown date';
            if ($entry->start_date_qualifier) {
                $dateDisplay = $entry->start_date_qualifier . ' ' . $dateDisplay;
            }

            $timeline[] = [
                'id'          => $entry->id,
                'type'        => $this->getTransferTypeLabel($entry->transfer_type),
                'label'       => $entry->owner_name,
                'startDate'   => $startDate,
                'endDate'     => $entry->end_date,
                'description' => $entry->notes ?? $entry->transfer_details ?? '',
                'category'    => $this->categorizeTransferType($entry->transfer_type),
                'certainty'   => $entry->certainty,
                'from'        => null,
                'to'          => $entry->owner_name,
                'location'    => $entry->owner_location,
            ];
        }

        return json_encode($timeline);
    }

    /**
     * Get transfer type options grouped for dropdowns.
     */
    public function getTransferTypes(): array
    {
        return [
            'Ownership Changes' => [
                'sale'        => 'Sale',
                'purchase'    => 'Purchase',
                'auction'     => 'Auction Sale',
                'gift'        => 'Gift',
                'donation'    => 'Donation',
                'bequest'     => 'Bequest',
                'inheritance' => 'Inheritance',
                'descent'     => 'By Descent',
                'transfer'    => 'Transfer',
                'exchange'    => 'Exchange',
            ],
            'Loans & Deposits' => [
                'loan_out'   => 'Loan Out',
                'loan_return' => 'Loan Return',
                'deposit'    => 'Deposit',
                'withdrawal' => 'Withdrawal',
            ],
            'Creation & Discovery' => [
                'creation'   => 'Creation',
                'commission' => 'Commission',
                'discovery'  => 'Discovery',
                'excavation' => 'Excavation',
            ],
            'Loss & Recovery' => [
                'theft'        => 'Theft',
                'recovery'     => 'Recovery',
                'confiscation' => 'Confiscation',
                'restitution'  => 'Restitution',
                'repatriation' => 'Repatriation',
            ],
            'Institutional' => [
                'accessioning'   => 'Accessioning',
                'deaccessioning' => 'Deaccessioning',
            ],
            'Other' => [
                'unknown' => 'Unknown',
                'other'   => 'Other',
            ],
        ];
    }

    /**
     * Get owner type options.
     */
    public function getOwnerTypes(): array
    {
        return [
            'person'       => 'Person',
            'family'       => 'Family',
            'organization' => 'Organization',
            'institution'  => 'Institution',
            'dealer'       => 'Dealer',
            'auction_house' => 'Auction House',
            'government'   => 'Government',
            'unknown'      => 'Unknown',
        ];
    }

    /**
     * Get certainty level options.
     */
    public function getCertaintyLevels(): array
    {
        return [
            'certain'   => 'Certain - Documented evidence',
            'probable'  => 'Probable - Strong circumstantial evidence',
            'possible'  => 'Possible - Some supporting evidence',
            'uncertain' => 'Uncertain - Limited evidence',
            'unknown'   => 'Unknown - No evidence',
        ];
    }

    /**
     * Get a human-readable label for a transfer type.
     */
    private function getTransferTypeLabel(string $type): string
    {
        $flat = [];
        foreach ($this->getTransferTypes() as $group) {
            $flat = array_merge($flat, $group);
        }

        return $flat[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /**
     * Categorize a transfer type for D3.js timeline coloring.
     */
    private function categorizeTransferType(string $type): string
    {
        $type = strtolower($type);

        if (in_array($type, ['creation', 'commission', 'discovery', 'excavation'])) {
            return 'creation';
        }
        if (in_array($type, ['sale', 'purchase'])) {
            return 'sale';
        }
        if (in_array($type, ['gift', 'donation'])) {
            return 'gift';
        }
        if (in_array($type, ['bequest', 'inheritance', 'descent'])) {
            return 'inheritance';
        }
        if ($type === 'auction') {
            return 'auction';
        }
        if (in_array($type, ['transfer', 'exchange', 'accessioning', 'deaccessioning'])) {
            return 'transfer';
        }
        if (in_array($type, ['loan_out', 'loan_return', 'deposit', 'withdrawal'])) {
            return 'loan';
        }
        if (in_array($type, ['theft', 'confiscation'])) {
            return 'theft';
        }
        if (in_array($type, ['recovery', 'restitution', 'repatriation'])) {
            return 'recovery';
        }

        return 'event';
    }
}
