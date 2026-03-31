<?php

/**
 * LoanService - Service for Heratio
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
use Illuminate\Support\Facades\Log;

/**
 * Service for managing loans: agreements, objects, documents, extensions,
 * status transitions, condition reports, and statistics.
 */
class LoanService
{
    /**
     * Valid status transitions for loans.
     */
    public const STATUS_TRANSITIONS = [
        'draft'            => ['submitted', 'cancelled'],
        'submitted'        => ['under_review', 'cancelled'],
        'under_review'     => ['approved', 'rejected', 'cancelled'],
        'approved'         => ['preparing', 'cancelled'],
        'preparing'        => ['dispatched', 'cancelled'],
        'dispatched'       => ['in_transit', 'cancelled'],
        'in_transit'       => ['received', 'cancelled'],
        'received'         => ['on_loan', 'cancelled'],
        'on_loan'          => ['return_requested', 'cancelled'],
        'return_requested' => ['returned', 'cancelled'],
        'returned'         => ['closed', 'cancelled'],
        'rejected'         => ['cancelled'],
        'closed'           => [],
        'cancelled'        => [],
    ];

    /**
     * Status badge colour map.
     */
    public const STATUS_COLOURS = [
        'draft'            => 'secondary',
        'submitted'        => 'info',
        'under_review'     => 'warning',
        'approved'         => 'success',
        'rejected'         => 'danger',
        'preparing'        => 'primary',
        'dispatched'       => 'primary',
        'in_transit'       => 'primary',
        'received'         => 'info',
        'on_loan'          => 'success',
        'return_requested' => 'warning',
        'returned'         => 'info',
        'closed'           => 'dark',
        'cancelled'        => 'danger',
    ];

    /**
     * Paginated loan browse with filters.
     */
    public function browse(array $params): object
    {
        $query = DB::table('ahg_loan as l')
            ->select(
                'l.*',
                DB::raw('(SELECT COUNT(*) FROM ahg_loan_object WHERE loan_id = l.id) as objects_count')
            );

        // Filter: loan_type
        if (!empty($params['type'])) {
            $query->where('l.loan_type', $params['type']);
        }

        // Filter: status
        if (!empty($params['status'])) {
            $query->where('l.status', $params['status']);
        }

        // Filter: search (loan_number, title, partner_institution)
        if (!empty($params['search'])) {
            $search = '%' . $params['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('l.loan_number', 'LIKE', $search)
                  ->orWhere('l.title', 'LIKE', $search)
                  ->orWhere('l.partner_institution', 'LIKE', $search);
            });
        }

        // Filter: overdue
        if (!empty($params['overdue'])) {
            $query->where('l.end_date', '<', now()->toDateString())
                  ->whereIn('l.status', ['on_loan', 'dispatched', 'in_transit', 'received']);
        }

        // Filter: sector
        if (!empty($params['sector'])) {
            $query->where('l.sector', $params['sector']);
        }

        $sort = $params['sort'] ?? 'updated_at';
        $dir  = $params['dir'] ?? 'desc';
        $allowedSorts = ['loan_number', 'title', 'partner_institution', 'start_date', 'end_date', 'status', 'created_at', 'updated_at'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'updated_at';
        }
        $query->orderBy("l.{$sort}", $dir === 'asc' ? 'asc' : 'desc');

        $perPage = (int) ($params['per_page'] ?? 25);
        $page    = (int) ($params['page'] ?? 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get full loan by ID with related data.
     */
    public function get(int $id): ?object
    {
        $loan = DB::table('ahg_loan')->where('id', $id)->first();
        if (!$loan) {
            return null;
        }

        $loan->objects          = $this->getObjects($id);
        $loan->documents        = $this->getDocuments($id);
        $loan->extensions       = $this->getExtensions($id);
        $loan->status_history   = $this->getStatusHistory($id);
        $loan->condition_reports = $this->getConditionReports($id);
        $loan->facility_reports = $this->getFacilityReports($id);
        $loan->shipments        = $this->getShipments($id);
        $loan->costs            = $this->getCosts($id);

        return $loan;
    }

    /**
     * Create a new loan.
     */
    public function create(array $data, int $userId): int
    {
        $loanNumber = $this->generateLoanNumber($data['loan_type'] ?? 'out');

        $id = DB::table('ahg_loan')->insertGetId([
            'loan_number'             => $loanNumber,
            'loan_type'               => $data['loan_type'] ?? 'out',
            'sector'                  => $data['sector'] ?? 'museum',
            'title'                   => $data['title'] ?? null,
            'description'             => $data['description'] ?? null,
            'purpose'                 => $data['purpose'] ?? 'exhibition',
            'partner_institution'     => $data['partner_institution'],
            'partner_contact_name'    => $data['partner_contact_name'] ?? null,
            'partner_contact_email'   => $data['partner_contact_email'] ?? null,
            'partner_contact_phone'   => $data['partner_contact_phone'] ?? null,
            'partner_address'         => $data['partner_address'] ?? null,
            'request_date'            => $data['request_date'] ?? now(),
            'start_date'              => $data['start_date'] ?? null,
            'end_date'                => $data['end_date'] ?? null,
            'insurance_type'          => $data['insurance_type'] ?? 'borrower',
            'insurance_value'         => $data['insurance_value'] ?? null,
            'insurance_currency'      => $data['insurance_currency'] ?? 'ZAR',
            'insurance_policy_number' => $data['insurance_policy_number'] ?? null,
            'insurance_provider'      => $data['insurance_provider'] ?? null,
            'loan_fee'                => $data['loan_fee'] ?? null,
            'loan_fee_currency'       => $data['loan_fee_currency'] ?? 'ZAR',
            'status'                  => 'draft',
            'repository_id'           => $data['repository_id'] ?? null,
            'notes'                   => $data['notes'] ?? null,
            'created_by'              => $userId,
            'updated_by'              => $userId,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        // Log initial status
        DB::table('ahg_loan_status_history')->insert([
            'loan_id'    => $id,
            'from_status' => null,
            'to_status'  => 'draft',
            'changed_by' => $userId,
            'comment'    => 'Loan created',
            'created_at' => now(),
        ]);

        return $id;
    }

    /**
     * Update a loan.
     */
    public function update(int $id, array $data): bool
    {
        $update = [];
        $allowed = [
            'loan_type', 'sector', 'title', 'description', 'purpose',
            'partner_institution', 'partner_contact_name', 'partner_contact_email',
            'partner_contact_phone', 'partner_address',
            'request_date', 'start_date', 'end_date',
            'insurance_type', 'insurance_value', 'insurance_currency',
            'insurance_policy_number', 'insurance_provider',
            'loan_fee', 'loan_fee_currency',
            'repository_id', 'notes',
        ];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }

        if (empty($update)) {
            return false;
        }

        $update['updated_at'] = now();
        if (isset($data['updated_by'])) {
            $update['updated_by'] = $data['updated_by'];
        }

        return DB::table('ahg_loan')->where('id', $id)->update($update) > 0;
    }

    /**
     * Delete a loan and all related records.
     */
    public function delete(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            // Delete condition images via condition reports
            $reportIds = DB::table('ahg_loan_condition_report')
                ->where('loan_id', $id)->pluck('id')->toArray();
            if ($reportIds) {
                DB::table('ahg_loan_condition_image')
                    ->whereIn('condition_report_id', $reportIds)->delete();
            }

            // Delete facility images via facility reports
            $facilityIds = DB::table('ahg_loan_facility_report')
                ->where('loan_id', $id)->pluck('id')->toArray();
            if ($facilityIds) {
                DB::table('ahg_loan_facility_image')
                    ->whereIn('facility_report_id', $facilityIds)->delete();
            }

            // Delete shipment events via shipments
            $shipmentIds = DB::table('ahg_loan_shipment')
                ->where('loan_id', $id)->pluck('id')->toArray();
            if ($shipmentIds) {
                DB::table('ahg_loan_shipment_event')
                    ->whereIn('shipment_id', $shipmentIds)->delete();
            }

            DB::table('ahg_loan_object')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_document')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_extension')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_status_history')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_condition_report')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_facility_report')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_shipment')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_notification_log')->where('loan_id', $id)->delete();
            DB::table('ahg_loan_cost')->where('loan_id', $id)->delete();

            return DB::table('ahg_loan')->where('id', $id)->delete() > 0;
        });
    }

    /**
     * Add an object to a loan.
     */
    public function addObject(int $loanId, int $objectId, array $data = []): int
    {
        // Fetch title from information_object_i18n
        $io = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', 'en')
            ->first();

        $ioBase = DB::table('information_object')
            ->where('id', $objectId)
            ->first();

        return DB::table('ahg_loan_object')->insertGetId([
            'loan_id'               => $loanId,
            'information_object_id' => $objectId,
            'object_title'          => $io->title ?? ($data['object_title'] ?? null),
            'object_identifier'     => $ioBase->identifier ?? ($data['object_identifier'] ?? null),
            'object_type'           => $data['object_type'] ?? null,
            'insurance_value'       => $data['insurance_value'] ?? null,
            'special_requirements'  => $data['special_requirements'] ?? null,
            'display_requirements'  => $data['display_requirements'] ?? null,
            'status'                => 'pending',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    /**
     * Remove an object from a loan.
     */
    public function removeObject(int $loanId, int $objectId): bool
    {
        return DB::table('ahg_loan_object')
            ->where('loan_id', $loanId)
            ->where('id', $objectId)
            ->delete() > 0;
    }

    /**
     * Get objects for a loan with IO titles.
     */
    public function getObjects(int $loanId): array
    {
        return DB::table('ahg_loan_object as lo')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('lo.information_object_id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', 'en');
            })
            ->where('lo.loan_id', $loanId)
            ->select('lo.*', 'ioi.title as io_title')
            ->orderBy('lo.created_at')
            ->get()
            ->all();
    }

    /**
     * Transition loan status with validation.
     */
    public function transition(int $loanId, string $newStatus, int $userId, ?string $comment = null): bool
    {
        $loan = DB::table('ahg_loan')->where('id', $loanId)->first();
        if (!$loan) {
            return false;
        }

        $currentStatus = $loan->status;
        $allowed = self::STATUS_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed)) {
            return false;
        }

        DB::table('ahg_loan')->where('id', $loanId)->update([
            'status'     => $newStatus,
            'updated_at' => now(),
            'updated_by' => $userId,
        ]);

        // If approved, set approved_date and approver
        if ($newStatus === 'approved') {
            DB::table('ahg_loan')->where('id', $loanId)->update([
                'internal_approver_id' => $userId,
                'approved_date'        => now(),
            ]);
        }

        DB::table('ahg_loan_status_history')->insert([
            'loan_id'     => $loanId,
            'from_status' => $currentStatus,
            'to_status'   => $newStatus,
            'changed_by'  => $userId,
            'comment'     => $comment,
            'created_at'  => now(),
        ]);

        return true;
    }

    /**
     * Extend a loan's end date.
     */
    public function extend(int $loanId, string $newEndDate, string $reason, int $userId): bool
    {
        $loan = DB::table('ahg_loan')->where('id', $loanId)->first();
        if (!$loan) {
            return false;
        }

        DB::table('ahg_loan_extension')->insert([
            'loan_id'           => $loanId,
            'previous_end_date' => $loan->end_date,
            'new_end_date'      => $newEndDate,
            'reason'            => $reason,
            'approved_by'       => $userId,
            'created_at'        => now(),
        ]);

        DB::table('ahg_loan')->where('id', $loanId)->update([
            'end_date'   => $newEndDate,
            'updated_at' => now(),
            'updated_by' => $userId,
        ]);

        return true;
    }

    /**
     * Record a loan return.
     */
    public function recordReturn(int $loanId, string $returnDate, ?string $notes, int $userId): bool
    {
        DB::table('ahg_loan')->where('id', $loanId)->update([
            'return_date' => $returnDate,
            'updated_at'  => now(),
            'updated_by'  => $userId,
        ]);

        $this->transition($loanId, 'returned', $userId, $notes ?? 'Loan returned');

        return true;
    }

    /**
     * Get loan statistics.
     */
    public function getStatistics(): array
    {
        $active = DB::table('ahg_loan')
            ->whereIn('status', ['approved', 'preparing', 'dispatched', 'in_transit', 'received', 'on_loan'])
            ->count();

        $overdue = DB::table('ahg_loan')
            ->where('end_date', '<', now()->toDateString())
            ->whereIn('status', ['on_loan', 'dispatched', 'in_transit', 'received'])
            ->count();

        $totalInsurance = DB::table('ahg_loan')
            ->whereIn('status', ['approved', 'preparing', 'dispatched', 'in_transit', 'received', 'on_loan'])
            ->sum('insurance_value');

        $byStatus = DB::table('ahg_loan')
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $byPurpose = DB::table('ahg_loan')
            ->select('purpose', DB::raw('COUNT(*) as cnt'))
            ->groupBy('purpose')
            ->pluck('cnt', 'purpose')
            ->toArray();

        $dueSoon = DB::table('ahg_loan')
            ->where('end_date', '>=', now()->toDateString())
            ->where('end_date', '<=', now()->addDays(14)->toDateString())
            ->whereIn('status', ['on_loan', 'dispatched', 'in_transit', 'received'])
            ->count();

        return [
            'active'           => $active,
            'overdue'          => $overdue,
            'due_soon'         => $dueSoon,
            'total_insurance'  => $totalInsurance,
            'by_status'        => $byStatus,
            'by_purpose'       => $byPurpose,
            'total'            => DB::table('ahg_loan')->count(),
        ];
    }

    /**
     * Get overdue loans.
     */
    public function getOverdue(): array
    {
        return DB::table('ahg_loan')
            ->where('end_date', '<', now()->toDateString())
            ->whereIn('status', ['on_loan', 'dispatched', 'in_transit', 'received'])
            ->orderBy('end_date')
            ->get()
            ->all();
    }

    /**
     * Get loans due within N days.
     */
    public function getDueSoon(int $days = 14): array
    {
        return DB::table('ahg_loan')
            ->where('end_date', '>=', now()->toDateString())
            ->where('end_date', '<=', now()->addDays($days)->toDateString())
            ->whereIn('status', ['on_loan', 'dispatched', 'in_transit', 'received'])
            ->orderBy('end_date')
            ->get()
            ->all();
    }

    /**
     * Generate unique loan number. Format: MUS-LO-YYYY-NNNN or MUS-LI-YYYY-NNNN
     */
    public function generateLoanNumber(string $type = 'out'): string
    {
        $prefix = $type === 'in' ? 'MUS-LI' : 'MUS-LO';
        $year   = date('Y');
        $pattern = "{$prefix}-{$year}-%";

        $last = DB::table('ahg_loan')
            ->where('loan_number', 'LIKE', $pattern)
            ->orderByDesc('loan_number')
            ->value('loan_number');

        if ($last) {
            $parts = explode('-', $last);
            $seq   = (int) end($parts) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    /**
     * Get documents for a loan.
     */
    public function getDocuments(int $loanId): array
    {
        return DB::table('ahg_loan_document')
            ->where('loan_id', $loanId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * Upload and store a document.
     */
    public function uploadDocument(int $loanId, $file, string $type, int $userId): int
    {
        $loan = DB::table('ahg_loan')->where('id', $loanId)->first();
        $dir  = '/mnt/nas/heratio/archive/loans/' . $loan->loan_number;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $originalName = $file->getClientOriginalName();
        $extension    = $file->getClientOriginalExtension();
        $storedName   = $type . '_' . time() . '.' . $extension;

        $file->move($dir, $storedName);

        return DB::table('ahg_loan_document')->insertGetId([
            'loan_id'       => $loanId,
            'document_type' => $type,
            'file_path'     => $dir . '/' . $storedName,
            'file_name'     => $originalName,
            'mime_type'     => $file->getClientMimeType() ?? mime_content_type($dir . '/' . $storedName),
            'file_size'     => filesize($dir . '/' . $storedName),
            'uploaded_by'   => $userId,
            'created_at'    => now(),
        ]);
    }

    /**
     * Get status history for a loan.
     */
    public function getStatusHistory(int $loanId): array
    {
        return DB::table('ahg_loan_status_history as sh')
            ->leftJoin('user as u', 'sh.changed_by', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('u.id', '=', 'ai.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->where('sh.loan_id', $loanId)
            ->select('sh.*', 'ai.authorized_form_of_name as changed_by_name')
            ->orderByDesc('sh.created_at')
            ->get()
            ->all();
    }

    /**
     * Get condition reports for a loan.
     */
    public function getConditionReports(int $loanId): array
    {
        return DB::table('ahg_loan_condition_report as cr')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('cr.examiner_id', '=', 'ai.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->where('cr.loan_id', $loanId)
            ->select('cr.*', 'ai.authorized_form_of_name as examiner_display_name')
            ->orderByDesc('cr.examination_date')
            ->get()
            ->all();
    }

    /**
     * Get extensions for a loan.
     */
    public function getExtensions(int $loanId): array
    {
        return DB::table('ahg_loan_extension as ex')
            ->leftJoin('user as u', 'ex.approved_by', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('u.id', '=', 'ai.id')
                     ->where('ai.culture', '=', 'en');
            })
            ->where('ex.loan_id', $loanId)
            ->select('ex.*', 'ai.authorized_form_of_name as approved_by_name')
            ->orderByDesc('ex.created_at')
            ->get()
            ->all();
    }

    /**
     * Get facility reports for a loan.
     */
    public function getFacilityReports(int $loanId): array
    {
        return DB::table('ahg_loan_facility_report')
            ->where('loan_id', $loanId)
            ->orderByDesc('assessment_date')
            ->get()
            ->all();
    }

    /**
     * Get shipments for a loan.
     */
    public function getShipments(int $loanId): array
    {
        $shipments = DB::table('ahg_loan_shipment as s')
            ->leftJoin('ahg_loan_courier as c', 's.courier_id', '=', 'c.id')
            ->where('s.loan_id', $loanId)
            ->select('s.*', 'c.company_name as courier_name')
            ->orderByDesc('s.created_at')
            ->get()
            ->all();

        foreach ($shipments as &$shipment) {
            $shipment->events = DB::table('ahg_loan_shipment_event')
                ->where('shipment_id', $shipment->id)
                ->orderByDesc('event_time')
                ->get()
                ->all();
        }

        return $shipments;
    }

    /**
     * Get costs for a loan.
     */
    public function getCosts(int $loanId): array
    {
        return DB::table('ahg_loan_cost')
            ->where('loan_id', $loanId)
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * Search information objects for autocomplete.
     */
    public function searchObjects(string $query, int $limit = 20): array
    {
        $search = '%' . $query . '%';

        return DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                     ->where('ioi.culture', '=', 'en');
            })
            ->where(function ($q) use ($search) {
                $q->where('ioi.title', 'LIKE', $search)
                  ->orWhere('io.identifier', 'LIKE', $search);
            })
            ->select('io.id', 'ioi.title', 'io.identifier')
            ->orderBy('ioi.title')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * Get valid next statuses from current status.
     */
    public function getValidTransitions(string $currentStatus): array
    {
        return self::STATUS_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Get colour class for a status badge.
     */
    public static function getStatusColour(string $status): string
    {
        return self::STATUS_COLOURS[$status] ?? 'secondary';
    }
}
