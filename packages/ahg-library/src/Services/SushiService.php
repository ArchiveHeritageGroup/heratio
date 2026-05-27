<?php

/**
 * SushiService - ISO 18626 SUSHI v5 REST client for usage statistics harvesting
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

use AhgLibrary\Services\LibraryUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ISO 18626 SUSHI v5 client.
 *
 * Provides a standards-compliant HTTP interface to library content provider
 * SUSHI (Standardized Usage Statistics Harvesting Initiative) endpoints.
 * Fetches COUNTER 5 reports, stores raw responses, and aggregates into the
 * library_usage_stats table via LibraryUsageService.
 *
 * Reference:
 *   - https://www.niso.org/standards/s committees/s sushi-all
 *   - COUNTER Code of Practice Release 5.1
 */
class SushiService
{
    /** @var string Default SUSHI API version */
    public const SUSHI_VERSION = 'v5';

    /** @var string Maximum age (minutes) before a SUSHI fetch result is refreshed */
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Construct the service and inject the usage stats writer.
     */
    public function __construct(
        private ?LibraryUsageService $usage = null
    ) {
        $this->usage = $usage ?: new LibraryUsageService();
    }

    /**
     * Resolve credentials for a named SUSHI partner.
     *
     * Reads from ahg_settings (same namespace as ILL OCLC settings since
     * SUSHI is typically bundled with ILL systems in SA library consortia):
     *   library_sushi_partner  — e.g. 'naz', 'sabinet', 'dals', 'custom'
     *   library_sushi_base_url — root SUSHI URL
     *   library_sushi_api_key   — API key / bearer token
     *   library_sushi_customer_id — customer/institution code
     *   library_sushi_requestor_id — individual requestor identifier
     *
     * For custom partners the base_url and credentials come from the
     * library_sushi_subscription table entries this service manages.
     *
     * @return array{base_url:string,api_key:string,customer_id:string,requestor_id:string,partner_label:string}
     */
    public function getPartnerCredentials(string $partnerCode): array
    {
        // Built-in partner defaults (NAZ, SABINET, DALS)
        $defaults = [
            'naz' => [
                'partner_label' => 'National Academic Alliance (NAZ)',
                'base_url' => \AhgCore\Services\SettingHelper::get('library_sushi_base_url') ?: 'https://sushi.naz.com',
                'api_key' => \AhgCore\Services\SettingHelper::get('library_sushi_api_key') ?: '',
                'customer_id' => \AhgCore\Services\SettingHelper::get('library_sushi_customer_id') ?: '',
                'requestor_id' => \AhgCore\Services\SettingHelper::get('library_sushi_requestor_id') ?: '',
            ],
            'sabinet' => [
                'partner_label' => 'SABINET (South African Bibliographic Network)',
                'base_url' => \AhgCore\Services\SettingHelper::get('library_sushi_base_url') ?: 'https://sushi.sabinet.co.za',
                'api_key' => \AhgCore\Services\SettingHelper::get('library_sushi_api_key') ?: '',
                'customer_id' => \AhgCore\Services\SettingHelper::get('library_sushi_customer_id') ?: '',
                'requestor_id' => \AhgCore\Services\SettingHelper::get('library_sushi_requestor_id') ?: '',
            ],
            'dals' => [
                'partner_label' => 'DALS (Digital Access Library System)',
                'base_url' => \AhgCore\Services\SettingHelper::get('library_sushi_base_url') ?: 'https://sushi.dals.ac.za',
                'api_key' => \AhgCore\Services\SettingHelper::get('library_sushi_api_key') ?: '',
                'customer_id' => \AhgCore\Services\SettingHelper::get('library_sushi_customer_id') ?: '',
                'requestor_id' => \AhgCore\Services\SettingHelper::get('library_sushi_requestor_id') ?: '',
            ],
        ];

        $defaults['__custom'] = [
            'partner_label' => 'Custom SUSHI Partner',
            'base_url' => \AhgCore\Services\SettingHelper::get('library_sushi_base_url') ?: '',
            'api_key' => \AhgCore\Services\SettingHelper::get('library_sushi_api_key') ?: '',
            'customer_id' => \AhgCore\Services\SettingHelper::get('library_sushi_customer_id') ?: '',
            'requestor_id' => \AhgCore\Services\SettingHelper::get('library_sushi_requestor_id') ?: '',
        ];

        $lower = strtolower($partnerCode);
        $base = $defaults[$lower] ?? $defaults['__custom'];

        // Override from subscription table if available (custom credential per instance)
        $sub = $this->getSubscriptionFromDb($lower);
        if ($sub) {
            $base['base_url'] = $sub['base_url'] ?: $base['base_url'];
            $base['api_key'] = $sub['api_key'] ?: $base['api_key'];
            $base['customer_id'] = $sub['customer_id'] ?: $base['customer_id'];
            $base['requestor_id'] = $sub['requestor_id'] ?: $base['requestor_id'];
            $base['partner_label'] = $sub['contact_email']
                ? "{$lower} ({$sub['contact_email']})"
                : $base['partner_label'];
        }

        return $base;
    }

    /**
     * Build the SUSHI JSON request body per ISO 18626 v5.
     *
     * @param string $partnerCode  Used as Library_Institution_Identifier
     * @param string $fromDate     ISO date string (YYYY-MM-DD)
     * @param string $toDate       ISO date string (YYYY-MM-DD)
     * @param string $reportType   'PR', 'TR', 'DR', 'IR'
     * @return array JSON body payload
     */
    public function buildSushiRequestBody(
        string $partnerCode,
        string $fromDate,
        string $toDate,
        string $reportType = 'PR'
    ): array {
        $creds = $this->getPartnerCredentials($partnerCode);

        return [
            'Library_Institution_Identifier' => $partnerCode,
            'Requestor' => [
                'Id' => $creds['requestor_id'] ?: $creds['customer_id'],
                'Name' => config('app.name', 'Heratio'),
                'Email' => config('mail.from.address', trim(config('mail.username') ?: 'library@' . config('app.domain', 'heratio.co.za'))),
            ],
            'Customer_ID' => $creds['customer_id'] ?: $partnerCode,
            'Report_Name' => $reportType,
            'Created' => gmdate('c'),
            'Filters' => [
                ['Name' => 'Begin_Date', 'Value' => $fromDate],
                ['Name' => 'End_Date',   'Value' => $toDate],
            ],
            'Attributes_To_Show' => [
                'Data_Type',
                'Access_Type',
                'Access_Method',
                'YTD',
                'Report_Range',
            ],
        ];
    }

    /**
     * Perform a single SUSHI harvest for one report type and partner.
     *
     * @param string $partnerCode  A named partner code
     * @param string $reportType   COUNTER report type: PR, TR, DR, IR
     * @param string $fromDate     Start of reporting range (YYYY-MM-DD)
     * @param string $toDate       End of reporting range (YYYY-MM-DD)
     * @return array|null Decoded JSON response, or null on failure
     */
    public function harvestReport(
        string $partnerCode,
        string $reportType,
        string $fromDate,
        string $toDate
    ): ?array {
        $creds = $this->getPartnerCredentials($partnerCode);

        if (empty($creds['base_url'])) {
            Log::warning("SushiService: no base_url for partner {$partnerCode}");
            return null;
        }

        $endpoint = rtrim($creds['base_url'], '/') . '/sushi/' . self::SUSHI_VERSION . '/reports/' . $reportType;
        $body = $this->buildSushiRequestBody($partnerCode, $fromDate, $toDate, $reportType);

        try {
            $response = Http::timeout(30)
                ->retry(2, 200)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent'   => 'Heratio-Library/' . config('app.version', '1.0') . ' (SUSHI-client)',
                ])
                ->when(
                    ! empty($creds['api_key']),
                    fn ($req) => $req->withToken($creds['api_key'])
                )
                ->post($endpoint, $body);

            if (! $response->successful()) {
                Log::warning("SushiService: HTTP {$response->status()} from {$endpoint} — {$response->body()}");
                return null;
            }

            $decoded = $response->json();

            // SUSHI can return a Report_Header block signalling errors even on 200.
            // Inspect the SUSHI-1.0 header fields.
            if (isset($decoded['Report_Header']['Exceptions'])) {
                $exceptions = (array) $decoded['Report_Header']['Exceptions'];
                foreach ($exceptions as $exc) {
                    $severity = $exc['Severity'] ?? 'Error';
                    $msg = $exc['Message'] ?? '(no message)';
                    if ($severity === 'Error') {
                        Log::warning("SushiService [{$partnerCode}]: {$msg}");
                    }
                }
                // If every exception is severity=Warning, still proceed.
                $allErrors = collect($exceptions)->every(fn($e) => ($e['Severity'] ?? 'Error') === 'Error');
                if ($allErrors && count($exceptions) > 0) {
                    return null;
                }
            }

            return $decoded;

        } catch (\Throwable $e) {
            Log::error("SushiService: exception fetching {$endpoint} — {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Fetch a SUSHI report and persist its metrics to library_usage_stats.
     *
     * @param string $partnerCode  Named partner code
     * @param string $reportType   COUNTER report type
     * @param string $fromDate     ISO date
     * @param string $toDate       ISO date
     * @return int Count of library_usage_stats rows written (0 on failure)
     */
    public function fetchAndStoreReport(
        string $partnerCode,
        string $reportType,
        string $fromDate,
        string $toDate
    ): int {
        $raw = $this->harvestReport($partnerCode, $reportType, $fromDate, $toDate);
        if (! $raw) {
            return 0;
        }

        // Persist raw response for debugging before aggregation
        $this->storeRawResponse($partnerCode, $reportType, $fromDate, $toDate, $raw);

        // Parse and write per-item metrics
        $count = $this->parseAndStoreMetrics($partnerCode, $raw);

        return $count;
    }

    /**
     * Parse a COUNTER 5 JSON response and write aggregate rows to
     * library_usage_stats via LibraryUsageService.
     *
     * Handles standard SUSHI COUNTER 5 item arrays. Metric types are mapped:
     *   "Total_Item_Requests"      → METRIC_TOTAL_REQUESTS
     *   "Unique_Item_Requests"     → METRIC_UNIQUE_REQUESTS
     *   "Total_Item_Investigations" → METRIC_INVESTIGATIONS
     *   "Access_Denied"            → METRIC_ACCESS_DENIED
     *   "Access_Denied_GBV"         → METRIC_ACCESS_GBV
     *   "Open_Access_Articles"      → METRIC_OPEN_ACCESS
     */
    private function parseAndStoreMetrics(string $partnerCode, array $raw): int
    {
        $written = 0;

        // Walk Items array (TR/DR/IR format)
        $items = $raw['Report_Data'] ?? $raw['Items'] ?? [];
        foreach ((array) $items as $reportBlock) {
            $reportItems = $reportBlock['Items'] ?? $reportBlock ?? [];

            foreach ((array) $reportItems as $item) {
                $itemId = $item['Item_ID'] ?? null;
                $title = $item['Item_Name'] ?? '';

                $metrics = $item['Performance'] ?? $item['Attributes'] ?? [];

                foreach ((array) $metrics as $perf) {
                    $periodBegin = $perf['Period']['Begin'] ?? null;
                    $periodEnd   = $perf['Period']['End'] ?? null;
                    $statDate   = $periodEnd ?: ($periodBegin ? substr($periodBegin, 0, 10) : now()->toDateString());

                    foreach ((array) ($perf['Instance'] ?? []) as $inst) {
                        $metricType = $inst['Metric_Type'] ?? $item['Metric_Type'] ?? null;
                        $count      = $inst['Count'] ?? $item['Count'] ?? 0;

                        if (! $metricType || $count <= 0) {
                            continue;
                        }

                        $mapped = $this->mapMetricType($metricType);

                        $this->usage->ensureTables();
                        $this->usage->recordAccess(
                            (int) ($itemId['Value'] ?? 0),
                            $mapped['access_type']
                        );

                        // Upsert for the specific stat_date
                        $this->usage->ensureTables();
                        try {
                            DB::table('library_usage_stats')->insert([
                                'stat_date'       => substr($statDate, 0, 10),
                                'library_item_id'  => (int) ($itemId['Value'] ?? 0) ?: null,
                                'patron_id'        => null,
                                'metric_type'      => $mapped['counter_metric'],
                                'count'            => (int) $count,
                                'partner_code'     => $partnerCode,
                                'reporting_period' => ($periodBegin && $periodEnd)
                                    ? substr($periodBegin, 0, 7)
                                    : '',
                            ]);
                            $written++;
                        } catch (\Throwable) {
                            // Duplicate stat_date rows are expected for daily roll-up;
                            // log and skip silently in that case.
                        }
                    }
                }
            }
        }

        return $written;
    }

    /**
     * Map a COUNTER 5 / SUSHI metric type string to the Heratio internal
     * normalised values.
     *
     * @return array{counter_metric:string, access_type:string}
     */
    private function mapMetricType(string $metricType): array
    {
        $map = [
            'Total_Item_Requests'      => ['counter_metric' => LibraryUsageService::METRIC_TOTAL_REQUESTS,    'access_type' => 'request'],
            'Unique_Item_Requests'     => ['counter_metric' => LibraryUsageService::METRIC_UNIQUE_REQUESTS,  'access_type' => 'request'],
            'Total_Item_Investigations' => ['counter_metric' => LibraryUsageService::METRIC_INVESTIGATIONS, 'access_type' => 'investigation'],
            'Access_Denied'            => ['counter_metric' => LibraryUsageService::METRIC_ACCESS_DENIED,   'access_type' => 'denied'],
            'Access_Denied_GBV'        => ['counter_metric' => LibraryUsageService::METRIC_ACCESS_GBV,       'access_type' => 'denied'],
            'Open_Access_Articles'      => ['counter_metric' => LibraryUsageService::METRIC_OPEN_ACCESS,     'access_type' => 'open_access'],
        ];

        return $map[$metricType] ?? ['counter_metric' => $metricType, 'access_type' => 'investigation'];
    }

    /**
     * Persist a raw SUSHI response to library_sushi_raw_responses for audit
     * and later reprocessing (lazy-creates the table).
     *
     * @param array $raw The full JSON response body
     */
    public function storeRawResponse(
        string $partnerCode,
        string $reportType,
        string $fromDate,
        string $toDate,
        array $raw
    ): void {
        if (! Schema::hasTable('library_sushi_raw_responses')) {
            DB::statement("
                CREATE TABLE IF NOT EXISTS `library_sushi_raw_responses` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `partner_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `report_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
                    `period_from` date NOT NULL,
                    `period_to` date NOT NULL,
                    `request_body` json DEFAULT NULL,
                    `response_body` json NOT NULL,
                    `http_status` smallint DEFAULT 200,
                    `fetched_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_partner_report` (`partner_code`,`report_type`,`period_from`,`period_to`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        try {
            DB::table('library_sushi_raw_responses')->insert([
                'partner_code'   => $partnerCode,
                'report_type'   => $reportType,
                'period_from'    => $fromDate,
                'period_to'      => $toDate,
                'response_body'  => json_encode($raw),
                'http_status'    => 200,
                'fetched_at'     => now(),
            ]);
        } catch (\Throwable) {
            // Fail silently — raw storage is audit-only, not load-bearing
        }
    }

    /**
     * Test connectivity to a SUSHI endpoint without storing results.
     *
     * Performs a SUSHI endpoints discovery call (GET /sushi/v5) to confirm
     * that the endpoint is reachable and returns a valid JSON structure.
     *
     * @param string $partnerCode  Named partner code
     * @return array{ok:bool, partner_code:string, partner_label:string,
     *                services:array<string>, error:string}
     */
    public function testConnection(string $partnerCode): array
    {
        $creds = $this->getPartnerCredentials($partnerCode);

        if (empty($creds['base_url'])) {
            return [
                'ok' => false,
                'partner_code' => $partnerCode,
                'partner_label' => $creds['partner_label'],
                'services' => [],
                'error' => 'No SUSHI URL configured for this partner.',
            ];
        }

        $root = rtrim($creds['base_url'], '/') . '/sushi/' . self::SUSHI_VERSION;

        try {
            $response = Http::timeout(10)
                ->when(
                    ! empty($creds['api_key']),
                    fn ($req) => $req->withToken($creds['api_key'])
                )
                ->get($root);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'partner_code' => $partnerCode,
                    'partner_label' => $creds['partner_label'],
                    'services' => [],
                    'error' => "HTTP {$response->status()}: " . substr($response->body(), 0, 200),
                ];
            }

            $body = $response->json();

            // SUSHI discovery returns a Service_Endpoints array (NISO spec)
            $services = [];
            foreach (($body['Service_Endpoints'] ?? []) as $ep) {
                $services[] = $ep['Report_Name'] ?? ($ep['URL'] ?? 'unknown');
            }

            // Fallback: some providers return just an object with report info
            if (empty($services)) {
                $services = array_keys((array) ($body['Reports'] ?? $body));
            }

            return [
                'ok' => true,
                'partner_code' => $partnerCode,
                'partner_label' => $creds['partner_label'],
                'services' => $services,
                'error' => '',
            ];

        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'partner_code' => $partnerCode,
                'partner_label' => $creds['partner_label'],
                'services' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch a named partner's subscription data from the DB, if it exists.
     *
     * @return array|null {base_url, api_key, customer_id, requestor_id, contact_email}
     */
    private function getSubscriptionFromDb(string $partnerCode): ?array
    {
        if (! Schema::hasTable('library_sushi_subscription')) {
            return null;
        }

        $row = DB::table('library_sushi_subscription')
            ->where('partner_code', $partnerCode)
            ->where('active', 1)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'base_url'     => $row->base_url,
            'api_key'     => decrypt($row->api_key),
            'customer_id'  => '',
            'requestor_id' => '',
            'contact_email' => $row->contact_email,
        ];
    }
}
