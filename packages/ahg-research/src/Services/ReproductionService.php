<?php

/**
 * ReproductionService - Service for Heratio
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

/**
 * ReproductionService - Reproduction Request Management
 *
 * Handles reproduction requests, items, files, and cost calculation.
 * Note: No integrated payment processing - costs are for manual invoicing.
 *
 * Migrated from AtoM: ahgResearchPlugin/lib/Services/ReproductionService.php
 */
class ReproductionService
{
    // Default pricing (can be overridden via settings)
    private array $defaultPricing = [
        'photocopy' => ['base' => 2.00, 'per_page' => 0.50],
        'scan' => ['base' => 5.00, 'per_page' => 1.00],
        'photograph' => ['base' => 15.00, 'per_image' => 5.00],
        'digital_copy' => ['base' => 10.00, 'per_file' => 2.00],
        'transcription' => ['base' => 50.00, 'per_page' => 25.00],
        'certification' => ['base' => 25.00, 'per_document' => 10.00],
    ];

    // =========================================================================
    // REQUEST MANAGEMENT
    // =========================================================================

    /**
     * Create a new reproduction request.
     *
     * @param int $researcherId The researcher ID
     * @param array $data Request data
     * @return int The new request ID
     */
    public function createRequest(int $researcherId, array $data): int
    {
        $referenceNumber = $this->generateReferenceNumber();

        return DB::table('research_reproduction_request')->insertGetId([
            'researcher_id' => $researcherId,
            'reference_number' => $referenceNumber,
            'purpose' => $data['purpose'] ?? null,
            'intended_use' => $data['intended_use'] ?? 'personal',
            'publication_details' => $data['publication_details'] ?? null,
            'status' => 'draft',
            'currency' => $data['currency'] ?? 'ZAR',
            'delivery_method' => $data['delivery_method'] ?? 'email',
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_email' => $data['delivery_email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Generate a unique reference number.
     *
     * @return string Reference number (e.g., REP-2024-00001)
     */
    private function generateReferenceNumber(): string
    {
        $year = date('Y');
        $prefix = "REP-{$year}-";

        $lastRef = DB::table('research_reproduction_request')
            ->where('reference_number', 'like', $prefix . '%')
            ->orderBy('reference_number', 'desc')
            ->value('reference_number');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, -5);
            $newNum = $lastNum + 1;
        } else {
            $newNum = 1;
        }

        return $prefix . str_pad($newNum, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get a reproduction request by ID.
     *
     * @param int $requestId The request ID
     * @return object|null The request or null
     */
    public function getRequest(int $requestId): ?object
    {
        $request = DB::table('research_reproduction_request as r')
            ->leftJoin('research_researcher as res', 'r.researcher_id', '=', 'res.id')
            ->leftJoin('user as proc', 'r.processed_by', '=', 'proc.id')
            ->leftJoin('actor_i18n as proc_name', function ($join) {
                $join->on('proc.id', '=', 'proc_name.id')->where('proc_name.culture', '=', 'en');
            })
            ->where('r.id', $requestId)
            ->select(
                'r.*',
                'res.first_name',
                'res.last_name',
                'res.email as researcher_email',
                'res.institution',
                'proc_name.authorized_form_of_name as processed_by_name'
            )
            ->first();

        if ($request) {
            $request->items = $this->getItems($requestId);
            $request->status_history = $this->getStatusHistory($requestId, 'reproduction');
        }

        return $request;
    }

    /**
     * Get reproduction requests for a researcher.
     *
     * @param int $researcherId The researcher ID
     * @param array $filters Optional filters (status, date_from, date_to)
     * @return array List of requests
     */
    public function getRequests(int $researcherId, array $filters = []): array
    {
        $query = DB::table('research_reproduction_request')
            ->where('researcher_id', $researcherId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $requests = $query->orderBy('created_at', 'desc')->get()->toArray();

        foreach ($requests as &$request) {
            $request->item_count = DB::table('research_reproduction_item')
                ->where('request_id', $request->id)
                ->count();
        }

        return $requests;
    }

    /**
     * Get all reproduction requests (admin).
     *
     * @param array $filters Optional filters
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array List of requests
     */
    public function getAllRequests(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('research_reproduction_request as r')
            ->join('research_researcher as res', 'r.researcher_id', '=', 'res.id')
            ->select(
                'r.*',
                'res.first_name',
                'res.last_name',
                'res.email as researcher_email',
                'res.institution'
            );

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('r.reference_number', 'like', $search)
                    ->orWhere('res.first_name', 'like', $search)
                    ->orWhere('res.last_name', 'like', $search)
                    ->orWhere('res.email', 'like', $search);
            });
        }

        return $query->orderBy('r.created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }

    /**
     * Update a reproduction request.
     *
     * @param int $requestId The request ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateRequest(int $requestId, array $data): bool
    {
        $allowed = [
            'purpose', 'intended_use', 'publication_details', 'estimated_cost',
            'final_cost', 'currency', 'payment_reference', 'payment_date',
            'payment_method', 'invoice_number', 'invoice_date', 'delivery_method',
            'delivery_address', 'delivery_email', 'notes', 'admin_notes',
        ];

        $updateData = array_intersect_key($data, array_flip($allowed));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return DB::table('research_reproduction_request')
            ->where('id', $requestId)
            ->update($updateData) >= 0;
    }

    /**
     * Submit a draft request.
     *
     * @param int $requestId The request ID
     * @return array Result with success status
     */
    public function submitRequest(int $requestId): array
    {
        $request = DB::table('research_reproduction_request')
            ->where('id', $requestId)
            ->first();

        if (!$request) {
            return ['success' => false, 'error' => 'Request not found'];
        }

        if ($request->status !== 'draft') {
            return ['success' => false, 'error' => 'Request has already been submitted'];
        }

        // Check if there are items
        $itemCount = DB::table('research_reproduction_item')
            ->where('request_id', $requestId)
            ->count();

        if ($itemCount === 0) {
            return ['success' => false, 'error' => 'Cannot submit request with no items'];
        }

        // Calculate estimated cost
        $estimatedCost = $this->calculateCosts($requestId);

        $this->updateStatus($requestId, 'submitted', null, 'Request submitted by researcher');

        DB::table('research_reproduction_request')
            ->where('id', $requestId)
            ->update([
                'estimated_cost' => $estimatedCost,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return ['success' => true, 'estimated_cost' => $estimatedCost];
    }

    /**
     * Update request status.
     *
     * @param int $requestId The request ID
     * @param string $newStatus The new status
     * @param int|null $changedBy User ID who made the change
     * @param string|null $notes Optional notes
     * @return bool Success status
     */
    public function updateStatus(int $requestId, string $newStatus, ?int $changedBy = null, ?string $notes = null): bool
    {
        $request = DB::table('research_reproduction_request')
            ->where('id', $requestId)
            ->first();

        if (!$request) {
            return false;
        }

        $oldStatus = $request->status;

        // Log status change
        DB::table('research_request_status_history')->insert([
            'request_id' => $requestId,
            'request_type' => 'reproduction',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $changedBy,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $updateData = [
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($newStatus === 'completed') {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
        }

        if ($changedBy && in_array($newStatus, ['processing', 'in_production', 'completed'])) {
            $updateData['processed_by'] = $changedBy;
        }

        $updated = DB::table('research_reproduction_request')
            ->where('id', $requestId)
            ->update($updateData) > 0;

        // Emit workflow event if available
        if ($updated) {
            try {
                event('research.reproduction.status_changed', [
                    'object_id' => $requestId,
                    'object_type' => 'research_reproduction_request',
                    'performed_by' => $changedBy ?? 0,
                    'from_status' => $oldStatus,
                    'to_status' => $newStatus,
                    'comment' => $notes ?? "Status: {$oldStatus} -> {$newStatus}",
                ]);
            } catch (\Exception $e) {
                // Workflow plugin may not be installed
            }
        }

        return $updated;
    }

    /**
     * Get status history for a request.
     *
     * @param int $requestId The request ID
     * @param string $requestType The request type
     * @return array Status history
     */
    public function getStatusHistory(int $requestId, string $requestType = 'reproduction'): array
    {
        return DB::table('research_request_status_history as h')
            ->leftJoin('user as u', 'h.changed_by', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('u.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->where('h.request_id', $requestId)
            ->where('h.request_type', $requestType)
            ->select('h.*', 'ai.authorized_form_of_name as changed_by_name')
            ->orderBy('h.created_at', 'desc')
            ->get()
            ->toArray();
    }

    // =========================================================================
    // ITEMS
    // =========================================================================

    /**
     * Add an item to a reproduction request.
     *
     * @param int $requestId The request ID
     * @param array $data Item data
     * @return int The item ID
     */
    public function addItem(int $requestId, array $data): int
    {
        // Get object title
        $objectTitle = null;
        if (!empty($data['object_id'])) {
            $object = DB::table('information_object_i18n')
                ->where('id', $data['object_id'])
                ->where('culture', 'en')
                ->first();
            $objectTitle = $object->title ?? null;
        }

        return DB::table('research_reproduction_item')->insertGetId([
            'request_id' => $requestId,
            'object_id' => $data['object_id'],
            'digital_object_id' => $data['digital_object_id'] ?? null,
            'reproduction_type' => $data['reproduction_type'] ?? 'scan',
            'format' => $data['format'] ?? 'PDF',
            'resolution' => $data['resolution'] ?? null,
            'color_mode' => $data['color_mode'] ?? 'grayscale',
            'quantity' => $data['quantity'] ?? 1,
            'page_range' => $data['page_range'] ?? null,
            'special_instructions' => $data['special_instructions'] ?? null,
            'status' => 'pending',
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove an item from a request.
     *
     * @param int $itemId The item ID
     * @return bool Success status
     */
    public function removeItem(int $itemId): bool
    {
        // Delete associated files first
        $files = DB::table('research_reproduction_file')
            ->where('item_id', $itemId)
            ->get();

        foreach ($files as $file) {
            if (file_exists($file->file_path)) {
                unlink($file->file_path);
            }
        }

        DB::table('research_reproduction_file')->where('item_id', $itemId)->delete();

        return DB::table('research_reproduction_item')->where('id', $itemId)->delete() > 0;
    }

    /**
     * Get items for a request.
     *
     * @param int $requestId The request ID
     * @return array List of items
     */
    public function getItems(int $requestId): array
    {
        $items = DB::table('research_reproduction_item as i')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('i.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'i.object_id', '=', 'slug.object_id')
            ->where('i.request_id', $requestId)
            ->select('i.*', 'ioi.title as object_title', 'slug.slug')
            ->get()
            ->toArray();

        foreach ($items as &$item) {
            $item->files = DB::table('research_reproduction_file')
                ->where('item_id', $item->id)
                ->get()
                ->toArray();
        }

        return $items;
    }

    /**
     * Update an item.
     *
     * @param int $itemId The item ID
     * @param array $data Fields to update
     * @return bool Success status
     */
    public function updateItem(int $itemId, array $data): bool
    {
        $allowed = [
            'reproduction_type', 'format', 'resolution', 'color_mode',
            'quantity', 'page_range', 'special_instructions', 'unit_price',
            'total_price', 'status', 'notes',
        ];

        $updateData = array_intersect_key($data, array_flip($allowed));

        return DB::table('research_reproduction_item')
            ->where('id', $itemId)
            ->update($updateData) >= 0;
    }

    /**
     * Mark an item as completed.
     *
     * @param int $itemId The item ID
     * @return bool Success status
     */
    public function completeItem(int $itemId): bool
    {
        return DB::table('research_reproduction_item')
            ->where('id', $itemId)
            ->update([
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    // =========================================================================
    // FILES
    // =========================================================================

    /**
     * Upload a file for a reproduction item.
     *
     * @param int $itemId The item ID
     * @param array $fileData File information
     * @return int The file ID
     */
    public function uploadFile(int $itemId, array $fileData): int
    {
        $downloadToken = bin2hex(random_bytes(32));
        $downloadExpires = date('Y-m-d H:i:s', strtotime('+30 days'));

        return DB::table('research_reproduction_file')->insertGetId([
            'item_id' => $itemId,
            'file_name' => $fileData['file_name'],
            'file_path' => $fileData['file_path'],
            'file_size' => $fileData['file_size'] ?? null,
            'mime_type' => $fileData['mime_type'] ?? null,
            'checksum' => $fileData['checksum'] ?? null,
            'download_expires_at' => $downloadExpires,
            'download_token' => $downloadToken,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get files for an item.
     *
     * @param int $itemId The item ID
     * @return array List of files
     */
    public function getFiles(int $itemId): array
    {
        return DB::table('research_reproduction_file')
            ->where('item_id', $itemId)
            ->get()
            ->toArray();
    }

    /**
     * Get a file by download token.
     *
     * @param string $token The download token
     * @return object|null The file or null
     */
    public function getFileByToken(string $token): ?object
    {
        return DB::table('research_reproduction_file')
            ->where('download_token', $token)
            ->where(function ($q) {
                $q->whereNull('download_expires_at')
                    ->orWhere('download_expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->first();
    }

    /**
     * Record a file download.
     *
     * @param int $fileId The file ID
     * @return bool Success status
     */
    public function recordDownload(int $fileId): bool
    {
        return DB::table('research_reproduction_file')
            ->where('id', $fileId)
            ->update([
                'download_count' => DB::raw('download_count + 1'),
            ]) > 0;
    }

    /**
     * Delete a file.
     *
     * @param int $fileId The file ID
     * @return bool Success status
     */
    public function deleteFile(int $fileId): bool
    {
        $file = DB::table('research_reproduction_file')
            ->where('id', $fileId)
            ->first();

        if ($file && file_exists($file->file_path)) {
            unlink($file->file_path);
        }

        return DB::table('research_reproduction_file')
            ->where('id', $fileId)
            ->delete() > 0;
    }

    // =========================================================================
    // COST CALCULATION
    // =========================================================================

    /**
     * Calculate costs for a reproduction request.
     *
     * Note: This is for generating quotes/invoices manually.
     * No integrated payment processing.
     *
     * @param int $requestId The request ID
     * @return float Total estimated cost
     */
    public function calculateCosts(int $requestId): float
    {
        $items = $this->getItems($requestId);
        $pricing = $this->getPricing();
        $totalCost = 0.0;

        foreach ($items as $item) {
            $type = $item->reproduction_type;
            $typePricing = $pricing[$type] ?? $pricing['scan'];

            $baseCost = $typePricing['base'] ?? 0;
            $quantity = $item->quantity ?? 1;

            // Calculate per-unit cost
            $perUnit = 0;
            if (isset($typePricing['per_page'])) {
                $perUnit = $typePricing['per_page'];
            } elseif (isset($typePricing['per_image'])) {
                $perUnit = $typePricing['per_image'];
            } elseif (isset($typePricing['per_file'])) {
                $perUnit = $typePricing['per_file'];
            } elseif (isset($typePricing['per_document'])) {
                $perUnit = $typePricing['per_document'];
            }

            $itemCost = $baseCost + ($perUnit * $quantity);

            // Color premium
            if ($item->color_mode === 'color') {
                $itemCost *= 1.5;
            }

            // High resolution premium
            if ($item->resolution && preg_match('/(\d+)/', $item->resolution, $matches)) {
                $dpi = (int) $matches[1];
                if ($dpi >= 600) {
                    $itemCost *= 1.25;
                }
            }

            // Update item cost
            DB::table('research_reproduction_item')
                ->where('id', $item->id)
                ->update([
                    'unit_price' => $perUnit,
                    'total_price' => $itemCost,
                ]);

            $totalCost += $itemCost;
        }

        return round($totalCost, 2);
    }

    /**
     * Get pricing configuration.
     *
     * @return array Pricing by reproduction type
     */
    public function getPricing(): array
    {
        // Try to load from settings
        $customPricing = config('research.reproduction_pricing', null);

        if ($customPricing && is_array($customPricing)) {
            return array_merge($this->defaultPricing, $customPricing);
        }

        return $this->defaultPricing;
    }

    /**
     * Generate an invoice number.
     *
     * @param int $requestId The request ID
     * @return string Invoice number
     */
    public function generateInvoiceNumber(int $requestId): string
    {
        $year = date('Y');
        $month = date('m');

        return "INV-{$year}{$month}-" . str_pad($requestId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Record payment for a request.
     *
     * @param int $requestId The request ID
     * @param array $paymentData Payment details
     * @return bool Success status
     */
    public function recordPayment(int $requestId, array $paymentData): bool
    {
        $updated = DB::table('research_reproduction_request')
            ->where('id', $requestId)
            ->update([
                'payment_reference' => $paymentData['reference'] ?? null,
                'payment_date' => $paymentData['date'] ?? date('Y-m-d'),
                'payment_method' => $paymentData['method'] ?? null,
                'final_cost' => $paymentData['amount'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;

        if ($updated) {
            $this->updateStatus($requestId, 'in_production', null, 'Payment recorded');
        }

        return $updated;
    }

    // =========================================================================
    // STATISTICS
    // =========================================================================

    /**
     * Get reproduction request statistics.
     *
     * @param string|null $dateFrom Start date
     * @param string|null $dateTo End date
     * @return array Statistics
     */
    public function getStatistics(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $dateTo ?? date('Y-m-d');

        $query = DB::table('research_reproduction_request')
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $revenue = (clone $query)
            ->where('status', 'completed')
            ->sum('final_cost');

        $byType = DB::table('research_reproduction_item as i')
            ->join('research_reproduction_request as r', 'i.request_id', '=', 'r.id')
            ->whereBetween('r.created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->selectRaw('i.reproduction_type, COUNT(*) as count')
            ->groupBy('i.reproduction_type')
            ->pluck('count', 'reproduction_type')
            ->toArray();

        return [
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_requests' => array_sum($byStatus),
            'by_status' => $byStatus,
            'by_type' => $byType,
            'total_revenue' => $revenue,
            'pending_count' => ($byStatus['submitted'] ?? 0) + ($byStatus['processing'] ?? 0),
        ];
    }
}
