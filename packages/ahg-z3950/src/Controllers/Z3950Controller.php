<?php

/**
 * Z3950Controller — Z39.50 client and server for Heratio
 *
 * Provides a web UI for the Z39.50 protocol client. Allows operators to:
 * - Manage target profiles (add/edit/delete remote Z39.50 servers)
 * - Search remote targets using bib-1 attribute set
 * - Browse result sets and import records into the Heratio catalogue
 * - View connection logs and query statistics
 *
 * Z39.50 requires the `yaz` PECL extension. The client uses yaz_search()
 * to query remote targets and yaz_record() to retrieve MARC records.
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

namespace AhgZ3950\Controllers;

use AhgZ3950\Services\Z3950Service;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class Z3950Controller extends Controller
{
    protected Z3950Service $service;

    public function __construct(Z3950Service $service)
    {
        $this->service = $service;
    }

    /**
     * Dashboard — system overview and quick links.
     */
    public function index(): Response
    {
        $stats = [
            'total_targets'   => DB::connection('heratio')
                ->table('z3950_targets')
                ->count(),
            'total_searches'  => DB::connection('heratio')
                ->table('z3950_query_log')
                ->count(),
            'total_imports'   => DB::connection('heratio')
                ->table('z3950_import_log')
                ->count(),
            'yaz_available'   => extension_loaded('yaz'),
        ];

        return response()->view('ahg-z3950::index', [
            'stats'   => $stats,
            'targets' => DB::connection('heratio')->table('z3950_targets')->limit(5)->get(),
        ]);
    }

    /**
     * Search form — select target and build query.
     */
    public function search(): Response
    {
        $targets = DB::connection('heratio')
            ->table('z3950_targets')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return response()->view('ahg-z3950::search', [
            'targets' => $targets,
        ]);
    }

    /**
     * Run a Z39.50 search against a remote target.
     */
    public function searchRun(Request $request): Response
    {
        $validated = $request->validate([
            'target_id'  => 'required|integer',
            'query'      => 'required|string|max:1000',
            'syntax'     => 'nullable|string|in:USmarc,SUTRS,XML',
            'element_set' => 'nullable|string|in:F,B,S',
            'max_records' => 'nullable|integer|min:1|max:1000',
        ]);

        $target = DB::connection('heratio')
            ->table('z3950_targets')
            ->find($validated['target_id']);

        if (! $target) {
            return redirect()->back()->with('error', 'Target not found.');
        }

        if (! extension_loaded('yaz')) {
            Log::error('[Z39.50] yaz extension not loaded');
            return redirect()->back()
                ->with('error', 'The yaz extension is not installed on this server.');
        }

        $syntax     = $validated['syntax'] ?? $target->syntax ?? 'USmarc';
        $elementSet = $validated['element_set'] ?? $target->element_set ?? 'F';
        $maxRecords = $validated['max_records'] ?? config('ahg-z3950.client.max_records', 100);

        $start  = microtime(true);
        $result = $this->service->search(
            $target->host,
            (int) $target->port,
            $target->database,
            $validated['query'],
            $syntax,
            $elementSet,
            $maxRecords
        );
        $elapsed = round((microtime(true) - $start) * 1000);

        DB::connection('heratio')->table('z3950_query_log')->insert([
            'target_id'    => $target->id,
            'query'        => $validated['query'],
            'syntax'       => $syntax,
            'result_count' => $result['count'] ?? 0,
            'elapsed_ms'   => $elapsed,
            'error'        => $result['error'] ?? null,
            'created_at'  => now(),
        ]);

        if (! empty($result['error'])) {
            Log::warning('[Z39.50] Search error', [
                'target' => $target->host,
                'error'  => $result['error'],
            ]);
            return redirect()->back()
                ->withInput()
                ->with('error', "Search failed: {$result['error']}");
        }

        $resultSet = bin2hex(random_bytes(16));
        session()->put("z3950_rs_{$resultSet}", [
            'target_id'   => $target->id,
            'records'     => $result['records'] ?? [],
            'syntax'      => $syntax,
            'element_set' => $elementSet,
        ]);

        return redirect()->route('z3950.result', ['resultSet' => $resultSet]);
    }

    /**
     * Browse a result set retrieved from a prior search.
     * Pre-parses MARC records so the view can display field data without
     * calling the service directly.
     */
    public function result(string $resultSet): Response
    {
        $data = session()->get("z3950_rs_{$resultSet}");

        if (! $data) {
            return redirect()->route('z3950.search')
                ->with('error', 'Result set expired or not found. Run a new search.');
        }

        $parsed = [];
        foreach ($data['records'] as $idx => $record) {
            $parsed[$idx] = $this->service->parseMarcRecord($record);
        }

        return response()->view('ahg-z3950::result', [
            'resultSet'  => $resultSet,
            'targetId'   => $data['target_id'],
            'records'    => $data['records'],
            'parsed'    => $parsed,
            'syntax'    => $data['syntax'],
            'elementSet' => $data['element_set'],
        ]);
    }

    /**
     * Import a single MARC record from a result set into the Heratio catalogue.
     */
    public function import(string $resultSet, int $recordNumber): Response
    {
        $data = session()->get("z3950_rs_{$resultSet}");

        if (! $data || ! isset($data['records'][$recordNumber])) {
            return redirect()->back()->with('error', 'Record not found in result set.');
        }

        $marcRecord = $data['records'][$recordNumber];
        $stats = $this->service->importMarc($marcRecord, $data['syntax']);

        DB::connection('heratio')->table('z3950_import_log')->insert([
            'target_id'        => $data['target_id'],
            'result_set'       => $resultSet,
            'record_number'    => $recordNumber,
            'marc_content'     => $marcRecord,
            'works_created'    => $stats['works'] ?? 0,
            'instances_created' => $stats['instances'] ?? 0,
            'created_at'       => now(),
        ]);

        return redirect()->back()->with('success', sprintf(
            'Imported: %d work(s), %d instance(s).',
            $stats['works'] ?? 0,
            $stats['instances'] ?? 0
        ));
    }

    /**
     * Batch import all records from a result set.
     */
    public function importBatch(Request $request): Response
    {
        $validated = $request->validate([
            'result_set'     => 'required|string',
            'record_numbers' => 'nullable|string', // "all" or comma-separated integers
        ]);

        $data = session()->get("z3950_rs_{$validated['result_set']}");

        if (! $data) {
            return redirect()->back()->with('error', 'Result set expired or not found.');
        }

        $numbers = $validated['record_numbers'] === 'all'
            ? array_keys($data['records'])
            : array_map('intval', array_filter(explode(',', $validated['record_numbers'] ?? '')));

        $totalWorks = 0;
        $totalInstances = 0;
        $imported = 0;
        $errors = [];

        foreach ($numbers as $n) {
            if (! isset($data['records'][$n])) {
                continue;
            }
            try {
                $stats = $this->service->importMarc($data['records'][$n], $data['syntax']);
                $totalWorks     += $stats['works'] ?? 0;
                $totalInstances += $stats['instances'] ?? 0;
                $imported++;

                DB::connection('heratio')->table('z3950_import_log')->insert([
                    'target_id'        => $data['target_id'],
                    'result_set'      => $validated['result_set'],
                    'record_number'   => $n,
                    'marc_content'    => $data['records'][$n],
                    'works_created'   => $stats['works'] ?? 0,
                    'instances_created' => $stats['instances'] ?? 0,
                    'created_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                $errors[] = "Record {$n}: {$e->getMessage()}";
                Log::error("[Z39.50] Import error record {$n}: {$e->getMessage()}");
            }
        }

        $msg = "Batch imported {$imported} record(s).";
        if ($errors) {
            $msg .= ' Errors: ' . implode('; ', $errors);
        }

        return redirect()->route('z3950.search')
            ->with(empty($errors) ? 'success' : 'warning', $msg);
    }

    /**
     * Admin dashboard — target list and query/import stats.
     */
    public function admin(): Response
    {
        $targets = DB::connection('heratio')
            ->table('z3950_targets')
            ->orderBy('name')
            ->get();

        $recentQueries = DB::connection('heratio')
            ->table('z3950_query_log')
            ->join('z3950_targets', 'z3950_query_log.target_id', '=', 'z3950_targets.id')
            ->select('z3950_query_log.*', 'z3950_targets.name as target_name')
            ->orderBy('z3950_query_log.created_at', 'desc')
            ->limit(20)
            ->get();

        $recentImports = DB::connection('heratio')
            ->table('z3950_import_log')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->view('ahg-z3950::admin', [
            'targets'       => $targets,
            'recentQueries' => $recentQueries,
            'recentImports' => $recentImports,
        ]);
    }

    /**
     * Show target creation form.
     */
    public function createTarget(): Response
    {
        return response()->view('ahg-z3950::target-form', [
            'target' => null,
            'action' => 'create',
        ]);
    }

    /**
     * Persist a new target profile.
     */
    public function storeTarget(Request $request): Response
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer|min:1|max:65535',
            'database'    => 'required|string|max:255',
            'syntax'      => 'nullable|string|in:USmarc,MARC21,SUTRS,XML',
            'element_set' => 'nullable|string|in:F,B,S',
            'active'      => 'nullable|boolean',
        ]);

        DB::connection('heratio')->table('z3950_targets')->insert([
            'name'        => $validated['name'],
            'host'        => $validated['host'],
            'port'        => (int) $validated['port'],
            'database'    => $validated['database'],
            'syntax'      => $validated['syntax'] ?? 'USmarc',
            'element_set' => $validated['element_set'] ?? 'F',
            'active'      => ! empty($validated['active']),
            'created_at' => now(),
        ]);

        return redirect()->route('z3950.admin')
            ->with('success', "Target '{$validated['name']}' added.");
    }

    /**
     * Delete a target profile.
     */
    public function deleteTarget(int $id): Response
    {
        $target = DB::connection('heratio')
            ->table('z3950_targets')
            ->find($id);

        if (! $target) {
            return redirect()->back()->with('error', 'Target not found.');
        }

        DB::connection('heratio')
            ->table('z3950_targets')
            ->where('id', $id)
            ->delete();

        return redirect()->route('z3950.admin')
            ->with('success', "Target '{$target->name}' removed.");
    }
}