<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Bulk import endpoint. Accepts CSV or JSON via multipart/form-data and
 * dispatches each row to the appropriate RicEntityService creator. Returns
 * a per-row report — the import never short-circuits on a single failure.
 *
 *   POST /api/ric/v1/import?type=records&format=csv       multipart file=@records.csv
 *   POST /api/ric/v1/import?type=places                   application/json  [ {...}, {...} ]
 *
 * Dry-run:
 *   POST /api/ric/v1/import?type=places&dry_run=1          (validate + report, no writes)
 *
 * Max rows per request defaults to 10_000; bump via OPENRIC_IMPORT_MAX_ROWS env.
 * Requires the `write` scope.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use AhgRic\Services\RicEntityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    private const DEFAULT_MAX_ROWS = 10000;

    /** type → method on RicEntityService and expected label field */
    private const TYPE_MAP = [
        'places'         => ['createPlace',         'name'],
        'rules'          => ['createRule',          'title'],
        'activities'     => ['createActivity',      'name'],
        'instantiations' => ['createInstantiation', 'title'],
        'agents'         => ['createAgent',         'name'],
        'records'        => ['createRecord',        'title'],
        'repositories'   => ['createRepository',    'name'],
        'functions'      => ['createFunction',      'name'],
    ];

    private RicEntityService $svc;

    public function __construct()
    {
        $this->svc = new RicEntityService();
    }

    public function import(Request $request): JsonResponse
    {
        $type = strtolower((string) $request->query('type', ''));
        if (!isset(self::TYPE_MAP[$type])) {
            return response()->json([
                'error' => 'invalid_type',
                'message' => 'Query param "type" must be one of: ' . implode(', ', array_keys(self::TYPE_MAP)),
            ], 400);
        }

        $maxRows = (int) env('OPENRIC_IMPORT_MAX_ROWS', self::DEFAULT_MAX_ROWS);
        $dryRun  = filter_var($request->query('dry_run', '0'), FILTER_VALIDATE_BOOLEAN);

        // Resolve rows from whatever shape was submitted.
        [$rows, $error] = $this->extractRows($request);
        if ($error) {
            return response()->json($error, 400);
        }
        if (count($rows) > $maxRows) {
            return response()->json([
                'error' => 'too_many_rows',
                'message' => "Import limited to {$maxRows} rows per request; received " . count($rows) . ".",
            ], 413);
        }
        if (count($rows) === 0) {
            return response()->json(['error' => 'empty_payload', 'message' => 'No rows found.'], 400);
        }

        [$method, $labelField] = self::TYPE_MAP[$type];

        $started = microtime(true);
        $created = [];
        $errors  = [];

        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                $errors[] = ['row' => $idx + 1, 'error' => 'not_an_object', 'data' => $row];
                continue;
            }
            try {
                if ($dryRun) {
                    // Dry-run: only validate required fields. Do not write.
                    $this->dryValidate($type, $row);
                    $created[] = ['row' => $idx + 1, 'would_create' => true, 'label' => $row[$labelField] ?? null];
                } else {
                    $id = $this->svc->{$method}($row);
                    $slug = DB::table('slug')->where('object_id', $id)->value('slug');
                    $created[] = [
                        'row'   => $idx + 1,
                        'id'    => $id,
                        'slug'  => $slug,
                        'label' => $row[$labelField] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = [
                    'row'   => $idx + 1,
                    'error' => $e->getMessage(),
                    'label' => $row[$labelField] ?? null,
                ];
                Log::warning("[openric:import] row {$idx} failed: " . $e->getMessage());
            }
        }

        $elapsed = (int) ((microtime(true) - $started) * 1000);
        return response()->json([
            'type'        => $type,
            'dry_run'     => $dryRun,
            'total'       => count($rows),
            'succeeded'   => count($created),
            'failed'      => count($errors),
            'duration_ms' => $elapsed,
            'created'     => $created,
            'errors'      => $errors,
        ], $errors && !$created ? 422 : 201);
    }

    /**
     * Read rows from either a file upload (CSV / JSON) or a JSON body.
     * Returns [rows, null] on success; [null, errorObject] on failure.
     */
    private function extractRows(Request $request): array
    {
        $format = strtolower((string) $request->query('format', ''));

        // Priority 1: multipart file upload.
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            if (!$file->isValid()) {
                return [null, ['error' => 'invalid_file', 'message' => $file->getErrorMessage()]];
            }
            $contents = file_get_contents($file->getRealPath());
            $detected = $format ?: $this->sniffFormat($file->getClientOriginalExtension(), $contents);
            if ($detected === 'csv')  return [$this->parseCsv($contents), null];
            if ($detected === 'json') return $this->parseJson($contents);
            return [null, ['error' => 'unknown_format', 'message' => 'Pass ?format=csv|json or use a file with a .csv / .json extension.']];
        }

        // Priority 2: raw JSON body.
        $json = $request->json();
        if ($json && (is_array($json->all()) && !empty($json->all()))) {
            $raw = $json->all();
            // Accept either a top-level array or {"rows": [...]}.
            if (isset($raw['rows']) && is_array($raw['rows'])) return [$raw['rows'], null];
            if (array_is_list($raw)) return [$raw, null];
            return [null, ['error' => 'bad_json_shape', 'message' => 'JSON body must be an array of objects or {"rows":[...]}.']];
        }

        return [null, ['error' => 'no_payload', 'message' => 'Attach a file via multipart (field "file") or POST a JSON array.']];
    }

    private function sniffFormat(string $ext, string $contents): string
    {
        $ext = strtolower($ext);
        if (in_array($ext, ['csv', 'tsv'])) return 'csv';
        if ($ext === 'json') return 'json';
        $trim = ltrim($contents);
        if (str_starts_with($trim, '[') || str_starts_with($trim, '{')) return 'json';
        return 'csv';
    }

    /**
     * Parse CSV with auto-detected delimiter (comma or tab). First line is
     * the header. Empty string cells become PHP null so the service's
     * `?? null` fallbacks kick in.
     */
    private function parseCsv(string $contents): array
    {
        // Normalise line endings
        $contents = str_replace(["\r\n", "\r"], "\n", $contents);
        $delim = substr_count($contents, "\t") > substr_count($contents, ',') ? "\t" : ',';

        $lines = preg_split('/\n/', $contents);
        $headerLine = null;
        foreach ($lines as $i => $l) {
            if (trim($l) !== '') { $headerLine = $i; break; }
        }
        if ($headerLine === null) return [];
        $headers = array_map('trim', str_getcsv($lines[$headerLine], $delim));

        $rows = [];
        for ($i = $headerLine + 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '') continue;
            $cols = str_getcsv($lines[$i], $delim);
            $row = [];
            foreach ($headers as $idx => $h) {
                $v = $cols[$idx] ?? '';
                $row[$h] = $v === '' ? null : $v;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private function parseJson(string $contents): array
    {
        $parsed = json_decode($contents, true);
        if (!is_array($parsed)) {
            return [null, ['error' => 'invalid_json', 'message' => 'JSON did not decode to an array.']];
        }
        if (isset($parsed['rows']) && is_array($parsed['rows'])) return [$parsed['rows'], null];
        if (array_is_list($parsed)) return [$parsed, null];
        return [null, ['error' => 'bad_json_shape', 'message' => 'Expected an array of objects or {"rows":[...]}.']];
    }

    /**
     * Quick field presence check — the actual createXxx methods do their
     * own validation and will throw on missing required fields even if we
     * let it through. This is just so dry-run mode gives useful feedback.
     */
    private function dryValidate(string $type, array $row): void
    {
        $required = match ($type) {
            'places', 'activities', 'agents', 'repositories', 'functions' => ['name'],
            'rules', 'records', 'instantiations' => ['title'],
            default => [],
        };
        foreach ($required as $field) {
            if (empty($row[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }
}
