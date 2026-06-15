<?php

/**
 * LibraryUsageController - COUNTER 5 / SUSHI usage statistics HTTP layer.
 *
 * Wraps LibraryUsageService and SushiService to expose:
 *   GET  /library-manage/usage              — usage dashboard / PR report
 *   GET  /library-manage/usage/tr           — title-level report (TR)
 *   GET  /library-manage/usage/dr           — database report (DR)
 *   GET  /library-manage/usage/harvest      — trigger SUSHI harvest for all partners
 *   GET  /library-manage/usage/subscriptions       — manage SUSHI partners
 *   POST /library-manage/usage/subscriptions       — add/update a SUSHI partner
 *   GET  /library-manage/usage/subscriptions/{id}/test — test SUSHI endpoint connectivity
 *   GET  /library-manage/usage/export/{type}       — download TSV for PR/TR/DR
 *
 * Copyright (C) 2026 Johan Pieterse
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryUsageService;
use AhgLibrary\Services\SushiService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class LibraryUsageController extends Controller
{
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private LibraryUsageService $usage,
        private SushiService $sushi
    ) {}

    // ── Dashboard / platform report (PR) ──────────────────────────────────

    /**
     * Platform Usage Report (PR) — default view.
     * Shows aggregated metrics across all reporting periods.
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $fromDate = $this->parseDate($request->query('from', now()->subMonths(6)->toDateString()));
        $toDate   = $this->parseDate($request->query('to', now()->toDateString()));
        $period   = $request->query('period', 'monthly');

        $stats = $this->usage->getStats($period, $fromDate, $toDate);
        $subscriptions = $this->usage->getActiveSubscriptions();

        return view('ahg-library::usage.index', [
            'stats' => $stats,
            'subscriptions' => $subscriptions,
            'fromDate' => $fromDate,
            'toDate'   => $toDate,
            'period'   => $period,
            'reportType' => 'PR',
        ]);
    }

    /**
     * Title Usage Report (TR) — per-title breakdown.
     */
    public function titleReport(Request $request): \Illuminate\View\View
    {
        $fromDate = $this->parseDate($request->query('from', now()->subMonths(6)->toDateString()));
        $toDate   = $this->parseDate($request->query('to', now()->toDateString()));

        $report = $this->usage->buildCounterReport('TR', $fromDate, $toDate);

        // The tr view renders a per-title pivot ($rows with ->total/->html/->pdf)
        // plus the date range as $start/$end. Pivot the COUNTER Report_Items by
        // title; on an install with no usage data this is an empty collection
        // (the view shows its empty-state rather than 500ing).
        $rows = collect($report['Report_Items'] ?? [])
            ->groupBy(fn ($i) => $i['Item_Name'] ?? '')
            ->map(function ($metrics, $title) {
                $by = collect($metrics)->keyBy('Metric_Type');
                return [
                    'title' => $title,
                    'total' => (int) ($by->get('Total_Item_Requests')['Count'] ?? collect($metrics)->sum('Count')),
                    'html'  => (int) ($by->get('Total_Item_Requests_HTML')['Count'] ?? 0),
                    'pdf'   => (int) ($by->get('Total_Item_Requests_PDF')['Count'] ?? 0),
                ];
            })
            ->values();

        return view('ahg-library::usage.tr', [
            'report'   => $report,
            'rows'     => $rows,
            'start'    => $fromDate,
            'end'      => $toDate,
            'fromDate' => $fromDate,
            'toDate'   => $toDate,
        ]);
    }

    /**
     * Database Usage Report (DR) — per-database breakdown.
     */
    public function databaseReport(Request $request): \Illuminate\View\View
    {
        $fromDate = $this->parseDate($request->query('from', now()->subMonths(6)->toDateString()));
        $toDate   = $this->parseDate($request->query('to', now()->toDateString()));

        $report = $this->usage->buildCounterReport('DR', $fromDate, $toDate);

        return view('ahg-library::usage.dr', [
            'report'   => $report,
            'fromDate' => $fromDate,
            'toDate'   => $toDate,
        ]);
    }

    // ── SUSHI harvest ─────────────────────────────────────────────────────

    /**
     * Trigger a SUSHI harvest for all active partner subscriptions.
     */
    public function harvest(Request $request): RedirectResponse
    {
        $fromDate = $this->parseDate($request->query('from', now()->subMonth()->toDateString()));
        $toDate   = $this->parseDate($request->query('to', now()->toDateString()));

        try {
            // Ensure tables exist first
            $this->usage->ensureTables();

            $totalRecords = $this->usage->harvestFromAllPartners($fromDate, $toDate, ['PR', 'TR']);

            return redirect()->route('library.usage')
                ->with('success', "SUSHI harvest complete. {$totalRecords} usage record(s) collected.");
        } catch (Throwable $e) {
            Log::error('LibraryUsageController harvest error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Harvest failed: ' . $e->getMessage());
        }
    }

    // ── SUSHI partner subscriptions ───────────────────────────────────────

    /**
     * List active SUSHI subscriptions.
     * Also handles optional deletion via query string (e.g. from blade action link).
     */
    public function subscriptions(Request $request): \Illuminate\View\View
    {
        $this->usage->ensureTables();

        // Handle optional deletion — triggered by ?delete_id=N in the URL
        if ($id = $request->query('delete_id')) {
            $this->subscriptionsDelete((int) $id);
        }

        $subscriptions = $this->usage->getActiveSubscriptions();

        return view('ahg-library::usage.subscriptions', [
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Add or update a SUSHI partner subscription.
     */
    public function subscriptionsStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'partner_code'  => 'required|string|max:50|regex:/^[a-z0-9_]+$/',
            'contact_email' => 'required|email|max:255',
            'base_url'      => 'required|url|max:500',
            'api_key'       => 'nullable|string|max:500',
            'report_types'  => 'nullable|array',
            'report_types.*'=> 'string|in:PR,TR,DR,IR',
        ]);

        try {
            $this->usage->ensureTables();
            $reportTypes = $validated['report_types'] ?? ['PR', 'TR'];
            $apiKey = $validated['api_key'] ?? '';

            $this->usage->subscribePartner(
                $validated['partner_code'],
                $validated['contact_email'],
                $apiKey,
                $validated['base_url'],
                $reportTypes
            );

            return redirect()->route('library.usage-subscriptions')
                ->with('success', "Partner '{$validated['partner_code']}' saved.");
        } catch (Throwable $e) {
            Log::error('LibraryUsageController subscriptionsStore error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to save partner: ' . $e->getMessage());
        }
    }

    /**
     * Delete a SUSHI partner subscription by ID.
     */
    private function subscriptionsDelete(int $id): RedirectResponse
    {
        try {
            DB::table('library_sushi_subscription')->where('id', $id)->delete();
            return redirect()->route('library.usage-subscriptions')
                ->with('success', 'Partner removed.');
        } catch (Throwable $e) {
            Log::error('LibraryUsageController subscriptionsDelete error: ' . $e->getMessage());
            return redirect()->route('library.usage-subscriptions')
                ->with('error', 'Failed to remove partner: ' . $e->getMessage());
        }
    }

    /**
     * Test connectivity to a SUSHI partner endpoint.
     * Returns JSON — called via AJAX from the subscriptions blade.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $partnerCode = $request->query('partner_code', '');

        if ($partnerCode === '') {
            return response()->json(['ok' => false, 'error' => 'No partner code provided.']);
        }

        try {
            $result = $this->sushi->testConnection($partnerCode);
            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'partner_code' => $partnerCode,
                'partner_label' => $partnerCode,
                'services' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ── TSV / CSV export ──────────────────────────────────────────────────

    /**
     * Download a COUNTER report export. Format chosen via ?format=tsv|csv|xlsx
     * (default tsv). XLSX uses PhpSpreadsheet; csv is comma-delimited.
     */
    public function export(Request $request, string $type = 'PR'): Response
    {
        $fromDate = $this->parseDate($request->query('from', now()->subMonths(6)->toDateString()));
        $toDate   = $this->parseDate($request->query('to', now()->toDateString()));

        $allowed = ['PR', 'TR', 'TR_J1', 'TR_J3', 'DR', 'IR'];
        if (!in_array($type, $allowed, true)) {
            $type = 'PR';
        }

        $format = strtolower((string) $request->query('format', 'tsv'));
        if (!in_array($format, ['tsv', 'csv', 'xlsx'], true)) {
            $format = 'tsv';
        }

        $base = "counter-{$type}-{$fromDate}_to_{$toDate}";

        if ($format === 'xlsx') {
            try {
                $binary = $this->usage->getReportXlsx($type, $fromDate, $toDate);
                return response($binary, 200, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Content-Disposition' => "attachment; filename=\"{$base}.xlsx\"",
                ]);
            } catch (Throwable $e) {
                // PhpSpreadsheet missing or write failure - fall back to CSV so
                // the operator still gets a usable download.
                Log::warning('LibraryUsageController XLSX export fell back to CSV: ' . $e->getMessage());
                $format = 'csv';
            }
        }

        if ($format === 'csv') {
            $rows = $this->usage->getReportRows($type, $fromDate, $toDate);
            $handle = fopen('php://temp', 'r+');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            $csv = (string) stream_get_contents($handle);
            fclose($handle);
            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$base}.csv\"",
            ]);
        }

        $tsv = $this->usage->getReportCsv($type, $fromDate, $toDate);
        return response($tsv, 200, [
            'Content-Type' => 'text/tab-separated-values; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$base}.tsv\"",
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function parseDate(?string $date): string
    {
        if (!$date) {
            return now()->toDateString();
        }
        // Accept any ISO-like date and normalise to YYYY-MM-DD
        $parsed = date_create($date);
        return $parsed ? $parsed->format(self::DATE_FORMAT) : now()->toDateString();
    }
}