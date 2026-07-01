<?php

/**
 * ProvenanceService - Service for Heratio
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



namespace AhgInformationObjectManage\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Service for provenance chain operations.
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgProvenancePlugin/
 *
 * Table: provenance_entry
 */
class ProvenanceService
{
    /** Per-IO governance header columns (merged in from the retired ahg/provenance stack). */
    private const OVERVIEW_FIELDS = [
        'current_status', 'custody_type', 'acquisition_type', 'acquisition_date',
        'acquisition_date_text', 'acquisition_price', 'acquisition_currency',
        'certainty_level', 'has_gaps', 'gap_description', 'research_status',
        'research_notes', 'nazi_era_provenance_checked', 'nazi_era_provenance_clear',
        'nazi_era_notes', 'cultural_property_status', 'cultural_property_notes',
        'provenance_summary', 'is_complete', 'is_public',
    ];

    /**
     * Get the provenance governance header for an information object (or null).
     */
    public function getOverview(int $objectId): ?object
    {
        return DB::table('provenance_overview')
            ->where('information_object_id', $objectId)
            ->first();
    }

    /**
     * Create or update the governance header for an information object.
     */
    public function saveOverview(int $objectId, array $data): void
    {
        $payload = [];
        foreach (self::OVERVIEW_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }
        $payload['updated_at'] = now();

        $existing = $this->getOverview($objectId);
        if ($existing) {
            DB::table('provenance_overview')->where('id', $existing->id)->update($payload);
            \AhgCore\Support\AuditLog::captureEdit((int) $existing->id, 'provenance_overview', [], $payload);
        } else {
            $payload['information_object_id'] = $objectId;
            $payload['created_by'] = auth()->id();
            $payload['created_at'] = now();
            $newId = DB::table('provenance_overview')->insertGetId($payload);
            \AhgCore\Support\AuditLog::captureCreate((int) $newId, 'provenance_overview', $payload);
        }
    }

    /**
     * Research-status options for the governance header.
     */
    public function getResearchStatuses(): array
    {
        return [
            'not_started' => 'Not started',
            'in_progress' => 'In progress',
            'complete'    => 'Complete',
            'blocked'     => 'Blocked',
        ];
    }

    /**
     * Cultural-property / restitution status options for the governance header.
     */
    public function getCulturalPropertyStatuses(): array
    {
        return [
            'none'      => 'None — no concern',
            'flagged'   => 'Flagged for review',
            'claimed'   => 'Subject to a restitution claim',
            'restituted' => 'Restituted / repatriated',
            'cleared'   => 'Reviewed — cleared',
        ];
    }

    /**
     * Acquisition-type options for the governance header.
     */
    public function getAcquisitionTypes(): array
    {
        return [
            ''            => '— Select —',
            'purchase'    => 'Purchase',
            'gift'        => 'Gift / Donation',
            'bequest'     => 'Bequest',
            'exchange'    => 'Exchange',
            'transfer'    => 'Transfer',
            'field_collection' => 'Field collection',
            'excavation'  => 'Excavation',
            'commission'  => 'Commission',
            'loan'        => 'Loan',
            'found'       => 'Found in collection',
            'unknown'     => 'Unknown',
        ];
    }

    /**
     * Current-status options (where the object sits now).
     */
    public function getCurrentStatuses(): array
    {
        return [
            'owned'     => 'Owned',
            'on_loan'   => 'On loan',
            'deposited' => 'Deposited',
            'disputed'  => 'Disputed',
            'unknown'   => 'Unknown',
        ];
    }

    /**
     * Custody-type options.
     */
    public function getCustodyTypes(): array
    {
        return [
            'permanent' => 'Permanent',
            'temporary' => 'Temporary',
            'loan'      => 'Loan',
            'deposit'   => 'Deposit',
        ];
    }

    /**
     * Currency options shared by acquisition + sale-price fields.
     */
    public function getCurrencies(): array
    {
        return [
            ''    => '—',
            'ZAR' => 'ZAR — South African Rand',
            'USD' => 'USD — US Dollar',
            'GBP' => 'GBP — British Pound',
            'EUR' => 'EUR — Euro',
        ];
    }

    /**
     * Supporting-document type options (mirrors the AtoM plugin taxonomy).
     */
    public function getDocumentTypes(): array
    {
        return [
            'deed_of_gift'       => 'Deed of Gift',
            'bill_of_sale'       => 'Bill of Sale',
            'invoice'            => 'Invoice',
            'receipt'            => 'Receipt',
            'auction_catalog'    => 'Auction Catalogue',
            'exhibition_catalog' => 'Exhibition Catalogue',
            'inventory'          => 'Inventory',
            'insurance_record'   => 'Insurance Record',
            'photograph'         => 'Photograph',
            'correspondence'     => 'Correspondence',
            'certificate'        => 'Certificate',
            'customs_document'   => 'Customs Document',
            'export_license'     => 'Export Licence',
            'import_permit'      => 'Import Permit',
            'appraisal'          => 'Appraisal',
            'condition_report'   => 'Condition Report',
            'newspaper_clipping' => 'Newspaper Clipping',
            'publication'        => 'Publication',
            'oral_history'       => 'Oral History',
            'affidavit'          => 'Affidavit',
            'legal_document'     => 'Legal Document',
            'other'              => 'Other',
        ];
    }

    /**
     * Get supporting documents for an information object, newest first.
     */
    public function getDocuments(int $objectId): \Illuminate\Support\Collection
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('provenance_document')) {
            return collect();
        }

        return DB::table('provenance_document')
            ->where('information_object_id', $objectId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Fetch a single supporting document by id.
     */
    public function getDocument(int $id): ?object
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('provenance_document')) {
            return null;
        }

        return DB::table('provenance_document')->where('id', $id)->first();
    }

    /**
     * Attach a supporting document to an information object. Either an uploaded
     * file (stored privately) or an external_url reference — the caller
     * validates that at least one is present.
     *
     * @return int The new document id
     */
    public function createDocument(int $objectId, array $data, ?UploadedFile $file = null): int
    {
        $row = [
            'information_object_id' => $objectId,
            'provenance_entry_id'   => $data['provenance_entry_id'] ?? null,
            'document_type'         => $data['document_type'] ?? 'other',
            'title'                 => $data['title'] ?? null,
            'description'           => $data['description'] ?? null,
            'document_date'         => $data['document_date'] ?? null,
            'document_date_text'    => $data['document_date_text'] ?? null,
            'external_url'          => $data['external_url'] ?? null,
            'archive_reference'     => $data['archive_reference'] ?? null,
            'is_public'             => (int) ($data['is_public'] ?? 0),
            'created_by'            => auth()->id(),
            'created_at'            => now(),
            'updated_at'            => now(),
        ];

        if ($file) {
            $dir = 'provenance-docs/' . $objectId;
            $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientOriginalName());
            $stored = $file->storeAs($dir, uniqid('doc_') . '_' . $safe, 'local');

            $row['filename']          = basename($stored);
            $row['original_filename'] = $file->getClientOriginalName();
            $row['file_path']         = $stored;
            $row['mime_type']         = $file->getClientMimeType();
            $row['file_size']         = $file->getSize() ?: null;
            if (empty($row['title'])) {
                $row['title'] = $file->getClientOriginalName();
            }
        }

        $newId = DB::table('provenance_document')->insertGetId($row);
        \AhgCore\Support\AuditLog::captureCreate((int) $newId, 'provenance_document', $row);

        return (int) $newId;
    }

    /**
     * Delete a supporting document (and its stored file, if any).
     */
    public function deleteDocument(int $id): bool
    {
        $doc = $this->getDocument($id);
        if (!$doc) {
            return false;
        }

        \AhgCore\Support\AuditLog::captureDelete($id, 'provenance_document', (array) $doc);

        if (!empty($doc->file_path) && Storage::disk('local')->exists($doc->file_path)) {
            Storage::disk('local')->delete($doc->file_path);
        }

        return DB::table('provenance_document')->where('id', $id)->delete() > 0;
    }

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
    /**
     * Snapshot for the security_audit_log before/after diff.
     */
    private function entrySnapshot(int $id): array
    {
        $row = DB::table('provenance_entry')->where('id', $id)->first();
        if (!$row) return [];
        $arr = (array) $row;
        unset($arr['id'], $arr['created_at'], $arr['updated_at']);
        return $arr;
    }

    public function createEntry(array $data): int
    {
        // Determine next sequence number
        $maxSeq = DB::table('provenance_entry')
            ->where('information_object_id', $data['information_object_id'])
            ->max('sequence') ?? 0;

        $newId = DB::table('provenance_entry')->insertGetId([
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
            'evidence_type'         => $data['evidence_type'] ?? null,
            'evidence_description'  => $data['evidence_description'] ?? null,
            'notes'                 => $data['notes'] ?? null,
            'is_gap'                => $data['is_gap'] ?? 0,
            'gap_explanation'       => $data['gap_explanation'] ?? null,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
        \AhgCore\Support\AuditLog::captureCreate((int) $newId, 'provenance_entry', $this->entrySnapshot((int) $newId));
        return (int) $newId;
    }

    /**
     * Update an existing provenance entry.
     */
    public function updateEntry(int $id, array $data): bool
    {
        $before = $this->entrySnapshot($id);

        $update = [];
        $fields = [
            'owner_name', 'owner_type', 'owner_actor_id', 'owner_location',
            'owner_location_tgn', 'start_date', 'start_date_qualifier',
            'end_date', 'end_date_qualifier', 'transfer_type', 'transfer_details',
            'sale_price', 'sale_currency', 'auction_house', 'auction_lot',
            'certainty', 'sources', 'evidence_type', 'evidence_description',
            'notes', 'is_gap', 'gap_explanation', 'sequence',
        ];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        $update['updated_at'] = now();

        $result = DB::table('provenance_entry')
            ->where('id', $id)
            ->update($update) >= 0;

        \AhgCore\Support\AuditLog::captureEdit($id, 'provenance_entry', $before, $this->entrySnapshot($id));
        return $result;
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

        \AhgCore\Support\AuditLog::captureDelete($id, 'provenance_entry', $this->entrySnapshot($id));

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
