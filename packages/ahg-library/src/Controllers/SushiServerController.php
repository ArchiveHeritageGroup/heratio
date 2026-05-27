<?php

/**
 * SushiServerController - SUSHI 5.0 REST server endpoint.
 *
 * Exposes the Heratio library's COUNTER R5 reports to consortium consumers
 * via the NISO Z39.93 SUSHI protocol. Issue heratio#766.
 *
 * Routes (mounted under /api/sushi/r5):
 *   GET  /status
 *   GET  /reports
 *   GET  /reports/{report_id}
 *   GET  /members
 *
 * Authentication: customer_id + requestor_id + api_key (query string OR header).
 * Configured per-consumer in library_sushi_consumer table; for the open
 * version of the catalogue we accept the "anonymous" consumer.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SushiServerController extends Controller
{
    public function __construct(private LibraryUsageService $usage)
    {
    }

    /**
     * GET /api/sushi/r5/status - service health + alerts.
     */
    public function status(): JsonResponse
    {
        return response()->json([[
            'Description' => 'Heratio SUSHI 5.0 service',
            'Service_Active' => true,
            'Registry_URL' => url('/api/sushi/r5'),
            'Note' => 'Operated by ' . config('app.name'),
            'Alerts' => [],
        ]]);
    }

    /**
     * GET /api/sushi/r5/members - institutions served by this endpoint.
     */
    public function members(Request $request): JsonResponse
    {
        if (!$this->authorise($request)) {
            return $this->sushiException(2010, 'Insufficient Information to Process Request');
        }
        return response()->json([[
            'Customer_ID' => 'heratio-self',
            'Requestor_ID' => $request->query('requestor_id', 'anon'),
            'Name' => (string) (config('library.counter.institution_name') ?: config('app.name')),
            'Notes' => '',
            'Institution_ID' => [],
        ]]);
    }

    /**
     * GET /api/sushi/r5/reports - list of supported report IDs.
     */
    public function reports(Request $request): JsonResponse
    {
        if (!$this->authorise($request)) {
            return $this->sushiException(2010, 'Insufficient Information to Process Request');
        }
        return response()->json([
            ['Report_ID' => 'PR',    'Report_Name' => 'Platform Usage',  'Release' => '5', 'Path' => '/reports/pr'],
            ['Report_ID' => 'TR',    'Report_Name' => 'Title Usage',     'Release' => '5', 'Path' => '/reports/tr'],
            ['Report_ID' => 'TR_J1', 'Report_Name' => 'Journal Requests (Excluding OA_Gold)', 'Release' => '5', 'Path' => '/reports/tr_j1'],
            ['Report_ID' => 'TR_J3', 'Report_Name' => 'Journal Usage by Access Type', 'Release' => '5', 'Path' => '/reports/tr_j3'],
            ['Report_ID' => 'DR',    'Report_Name' => 'Database Usage',  'Release' => '5', 'Path' => '/reports/dr'],
            ['Report_ID' => 'IR',    'Report_Name' => 'Item Usage',      'Release' => '5', 'Path' => '/reports/ir'],
        ]);
    }

    /**
     * GET /api/sushi/r5/reports/{report_id}?begin_date=&end_date=
     */
    public function report(Request $request, string $reportId): JsonResponse
    {
        if (!$this->authorise($request)) {
            return $this->sushiException(2010, 'Insufficient Information to Process Request');
        }

        $reportId = strtoupper($reportId);
        $valid = ['PR', 'TR', 'TR_J1', 'TR_J3', 'DR', 'IR'];
        if (!in_array($reportId, $valid, true)) {
            return $this->sushiException(3000, 'Report Not Supported: ' . $reportId);
        }

        $begin = $this->normaliseDate($request->query('begin_date'), '-1 month');
        $end   = $this->normaliseDate($request->query('end_date'), 'today');

        $report = $this->usage->buildCounterReport($reportId, $begin, $end);

        // SUSHI 5.0 envelope: Report_Header + Report_Items
        $items = $report['Report_Items'] ?? [];
        $header = $report;
        unset($header['Report_Items']);

        return response()->json([
            'Report_Header' => $header,
            'Report_Items'  => $items,
        ]);
    }

    /**
     * Light auth: read api_key + customer_id from query string or header,
     * lookup in library_sushi_consumer if present. Anonymous accepted unless
     * library.sushi.require_auth = true.
     */
    private function authorise(Request $request): bool
    {
        if (!config('library.sushi.require_auth', false)) {
            return true;
        }
        $apiKey = $request->query('api_key') ?? $request->header('Authorization');
        $customerId = $request->query('customer_id');
        $requestorId = $request->query('requestor_id');

        if (!$apiKey || !$customerId || !$requestorId) return false;

        if (!Schema::hasTable('library_sushi_consumer')) {
            // Default-allow if the registry table isn't installed yet.
            return true;
        }

        return DB::table('library_sushi_consumer')
            ->where('customer_id', $customerId)
            ->where('requestor_id', $requestorId)
            ->where('api_key_hash', hash('sha256', $apiKey))
            ->where('active', 1)
            ->exists();
    }

    private function sushiException(int $code, string $message): JsonResponse
    {
        return response()->json([
            'Code' => $code,
            'Severity' => 'Error',
            'Message' => $message,
            'Created' => now()->toIso8601String(),
        ], 401);
    }

    private function normaliseDate(?string $raw, string $fallback): string
    {
        try {
            if ($raw) return date('Y-m-d', strtotime($raw));
            return date('Y-m-d', strtotime($fallback));
        } catch (\Throwable) {
            return date('Y-m-d');
        }
    }
}
