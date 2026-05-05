<?php

/**
 * PrivacyController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\AiNerService;
use AhgInformationObjectManage\Services\PrivacyService;
use AhgInformationObjectManage\Services\RedactionRenderService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgPrivacyPlugin/
 */
class PrivacyController extends Controller
{
    protected PrivacyService $privacyService;
    protected AiNerService $nerService;

    public function __construct(PrivacyService $privacyService, AiNerService $nerService)
    {
        $this->privacyService = $privacyService;
        $this->nerService = $nerService;
    }

    /**
     * Scan an IO for PII.
     * Shows potential PII found by NER extraction as a scan result.
     */
    /**
     * POST handler for the "Save Scan Results" button on the privacy scan
     * page. Recomputes the same NER-derived PII scan + persists the summary
     * + entities into audit_log for compliance review (no dedicated
     * privacy_pii_scan table exists yet; audit_log is the canonical
     * cross-module event store).
     */
    public function saveScan(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) abort(404);

        // Recompute the same scan shape the GET handler renders so the
        // saved snapshot matches what the user just looked at.
        $allEntities = $this->nerService->getEntitiesForObject($id);
        $piiRiskMap = [
            'SA_ID' => 'high', 'PASSPORT' => 'high', 'BANK' => 'high',
            'TAX' => 'high', 'MEDICAL' => 'high', 'BIOMETRIC' => 'high',
            'EMAIL' => 'medium', 'PHONE' => 'medium', 'DOB' => 'medium',
            'ADDRESS' => 'low', 'NAME' => 'low', 'IP_ADDRESS' => 'low', 'PERSON' => 'low',
        ];
        $piiEntities = [];
        foreach ($allEntities as $entity) {
            $risk = $piiRiskMap[$entity->entity_type] ?? null;
            if ($risk !== null) {
                $piiEntities[] = [
                    'type'       => $entity->entity_type,
                    'value'      => $entity->entity_value,
                    'confidence' => (float) $entity->confidence,
                    'risk'       => $risk,
                ];
            }
        }
        $highCount = count(array_filter($piiEntities, fn($e) => $e['risk'] === 'high'));
        $medCount  = count(array_filter($piiEntities, fn($e) => $e['risk'] === 'medium'));
        $lowCount  = count(array_filter($piiEntities, fn($e) => $e['risk'] === 'low'));
        $riskScore = min(100, ($highCount * 30) + ($medCount * 15) + ($lowCount * 5));

        $snapshot = [
            'risk_score'     => $riskScore,
            'high'           => $highCount,
            'medium'         => $medCount,
            'low'            => $lowCount,
            'entity_count'   => count($piiEntities),
            'fields_scanned' => ['title', 'scope_and_content', 'archival_history'],
            'entities'       => $piiEntities,
            'scanned_at'     => now()->toIso8601ZuluString(),
        ];

        try {
            \DB::table('audit_log')->insert([
                'table_name' => 'privacy_pii_scan',
                'record_id'  => $id,
                'action'     => 'create',
                'new_record' => json_encode($snapshot),
                'user_id'    => auth()->id(),
                'username'   => auth()->user()->username ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 500),
                'module'     => 'privacy',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('saveScan: audit_log insert failed: ' . $e->getMessage());
        }

        $entityCount = count($piiEntities);
        return redirect()
            ->route('io.privacy.scan', ['id' => $id])
            ->with('success', "Scan results saved (risk score {$riskScore}, {$entityCount} PII entities recorded in audit_log).");
    }

    public function scan(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        // Get NER entities for this object that are PII-related
        $allEntities = $this->nerService->getEntitiesForObject($id);

        // Map NER entity types to PII risk categories
        $piiRiskMap = [
            'SA_ID'      => 'high',
            'PASSPORT'   => 'high',
            'BANK'       => 'high',
            'TAX'        => 'high',
            'MEDICAL'    => 'high',
            'BIOMETRIC'  => 'high',
            'EMAIL'      => 'medium',
            'PHONE'      => 'medium',
            'DOB'        => 'medium',
            'ADDRESS'    => 'low',
            'NAME'       => 'low',
            'IP_ADDRESS' => 'low',
            'PERSON'     => 'low',
        ];

        // Build scan result from NER entities
        $piiEntities = [];
        $fieldsScanned = ['title', 'scope_and_content', 'archival_history'];

        foreach ($allEntities as $entity) {
            $risk = $piiRiskMap[$entity->entity_type] ?? null;
            if ($risk !== null) {
                $piiEntities[] = (object) [
                    'type'       => $entity->entity_type,
                    'value'      => $entity->entity_value,
                    'confidence' => (float) $entity->confidence,
                    'risk'       => $risk,
                    'source'     => 'NER extraction',
                ];
            }
        }

        // Calculate risk score
        $riskScore = 0;
        if (!empty($piiEntities)) {
            $highCount = count(array_filter($piiEntities, fn($e) => $e->risk === 'high'));
            $medCount = count(array_filter($piiEntities, fn($e) => $e->risk === 'medium'));
            $lowCount = count(array_filter($piiEntities, fn($e) => $e->risk === 'low'));
            $riskScore = min(100, ($highCount * 30) + ($medCount * 15) + ($lowCount * 5));
        }

        $scanResult = (object) [
            'entities'       => $piiEntities,
            'risk_score'     => $riskScore,
            'fields_scanned' => $fieldsScanned,
        ];

        return view('ahg-io-manage::privacy.scan', [
            'io'         => $io,
            'scanResult' => $scanResult,
        ]);
    }

    /**
     * Visual redaction tool for digital objects.
     */
    public function redaction(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Get the master digital object (try 140=master first, then any)
        $digitalObject = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->where('usage_id', 140)
            ->first();
        if (!$digitalObject) {
            $digitalObject = DB::table('digital_object')
                ->where('object_id', $io->id)
                ->orderBy('usage_id')
                ->first();
        }

        // Get existing redactions for this object
        $existingRedactions = $this->privacyService->getRedactions($io->id);

        // Parse coordinates from JSON and build flat array for JS. Includes
        // the `normalized` flag so the editor's loader can scale 0-1 fractions
        // back into canvas pixels at the current zoom level.
        $redactionRegions = $existingRedactions->map(function ($r) {
            $coords = is_string($r->coordinates) ? json_decode($r->coordinates, true) : (array) $r->coordinates;
            return [
                'id'         => $r->id,
                'left'       => $coords['left'] ?? $coords['x'] ?? 0,
                'top'        => $coords['top'] ?? $coords['y'] ?? 0,
                'width'      => $coords['width'] ?? $coords['w'] ?? 100,
                'height'     => $coords['height'] ?? $coords['h'] ?? 50,
                'normalized' => (int) ($r->normalized ?? 0),
                'page'       => $r->page_number,
                'label'      => $r->label,
                'color'      => $r->color,
                'status'     => $r->status,
            ];
        })->values()->toArray();

        // Determine document type and URL
        $documentUrl = null;
        $documentType = null;
        $totalPages = 1;

        if ($digitalObject) {
            $path = $digitalObject->path ?? null;
            $name = $digitalObject->name ?? null;

            if ($path && $name) {
                // External URL
                if (str_starts_with($path, 'http')) {
                    $documentUrl = $path;
                } else {
                    $documentUrl = rtrim($path, '/') . '/' . $name;
                }
            }

            $mimeType = $digitalObject->mime_type ?? '';
            if (str_contains($mimeType, 'pdf')) {
                $documentType = 'pdf';
            } elseif (str_starts_with($mimeType, 'image/')) {
                $documentType = 'image';
            } elseif (str_starts_with($mimeType, 'model/') || str_contains($mimeType, 'gltf') || str_contains($mimeType, 'obj')) {
                $documentType = '3d';
            } else {
                $documentType = 'unsupported';
            }
        }

        return view('ahg-io-manage::privacy.redaction', [
            'io'                 => $io,
            'digitalObject'      => $digitalObject,
            'existingRedactions' => $redactionRegions,
            'documentUrl'        => $documentUrl,
            'documentType'       => $documentType,
            'totalPages'         => $totalPages,
        ]);
    }

    /**
     * POST /privacy/redaction/{slug}/save — persist the regions drawn by
     * the user. The client sends the FULL list (no per-region ids), so we
     * treat it as a replace-all: delete the IO's existing redactions, then
     * insert the new set. Returns JSON for the AJAX caller.
     */
    public function saveRedactions(\Illuminate\Http\Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        $payload = $request->json()->all();
        $regions = $payload['regions'] ?? $request->input('regions', []);
        if (!is_array($regions)) $regions = [];

        // Resolve the digital_object id (master if available) so the
        // redactions are stored against the correct file.
        $digitalObjectId = \DB::table('digital_object')
            ->where('object_id', $io->id)
            ->where('usage_id', 140)
            ->value('id');
        if (!$digitalObjectId) {
            $digitalObjectId = \DB::table('digital_object')
                ->where('object_id', $io->id)
                ->orderBy('usage_id')
                ->value('id');
        }

        try {
            \DB::transaction(function () use ($io, $digitalObjectId, $regions) {
                // Replace-all: drop existing redactions for this IO, then insert
                // the new set. Matches the client payload which has no ids.
                \DB::table('privacy_visual_redaction')->where('object_id', $io->id)->delete();

                foreach ($regions as $r) {
                    if (!is_array($r)) continue;
                    $this->privacyService->saveRedaction([
                        'object_id'         => $io->id,
                        'digital_object_id' => $digitalObjectId,
                        'page_number'       => (int) ($r['page'] ?? 1),
                        'region_type'       => 'rectangle',
                        'coordinates'       => [
                            'left'   => (float) ($r['left']   ?? 0),
                            'top'    => (float) ($r['top']    ?? 0),
                            'width'  => (float) ($r['width']  ?? 0),
                            'height' => (float) ($r['height'] ?? 0),
                        ],
                        // Honour the editor's normalisation flag. Editor JS
                        // now sends coords as 0-1 fractions of the canvas
                        // (normalized=1) so renderer can multiply by the
                        // file's native dimensions independent of zoom.
                        'normalized'        => (int) ($r['normalized'] ?? 0) === 1 ? 1 : 0,
                        'source'            => 'manual',
                        'status'            => 'pending',
                        'created_by'        => auth()->id(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('saveRedactions failed: ' . $e->getMessage(), ['io_id' => $io->id]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save: ' . $e->getMessage(),
            ], 500);
        }

        // Bust the cached redacted file so the next non-admin viewer
        // re-renders against the updated region set.
        try {
            app(RedactionRenderService::class)->invalidate((int) $io->id);
        } catch (\Throwable $e) { /* not fatal */ }

        return response()->json([
            'success' => true,
            'count'   => count($regions),
            'message' => count($regions) . ' redaction region' . (count($regions) === 1 ? '' : 's') . ' saved.',
        ]);
    }

    /**
     * Stream the redacted master file to non-admin viewers. Admins are
     * redirected to the original. On cache miss, renders synchronously
     * via RedactionRenderService.
     *
     * GET /privacy/redacted-asset/{slug}
     */
    public function redactedAsset(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        $isAdmin = auth()->check() && auth()->user()
            && (method_exists(auth()->user(), 'isAdministrator')
                ? auth()->user()->isAdministrator()
                : (bool) (auth()->user()->is_admin ?? false));

        $master = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->whereNull('parent_id')
            ->first();
        if (!$master) abort(404);

        // Admins bypass the redactor — return the original file.
        if ($isAdmin) {
            return $this->streamOriginal($master);
        }

        $renderer = app(RedactionRenderService::class);
        $redactedPath = $renderer->render((int) $io->id);
        if (!$redactedPath || !file_exists($redactedPath)) {
            // No redactions OR render failed — fall through to original.
            // Logging this so unexpected failures surface in the audit log.
            if ($redactedPath === null) {
                \Log::info('[redaction] no regions; serving original', ['io_id' => $io->id]);
            }
            return $this->streamOriginal($master);
        }

        return response()->file($redactedPath, [
            'Content-Type'        => $master->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($master->name) . '"',
            'X-Heratio-Redacted'  => '1',
        ]);
    }

    private function streamOriginal(object $master)
    {
        // Same resolver as RedactionRenderService — the web-facing path field
        // starts with /uploads/r/ which has to be stripped before joining
        // with config('heratio.uploads_path').
        $uploads = rtrim(config('heratio.uploads_path', '/mnt/nas/heratio/archive'), '/');
        $rawPath = ltrim((string) $master->path, '/');
        $stripped = $rawPath;
        foreach (['uploads/r/', 'uploads/'] as $prefix) {
            if (str_starts_with($stripped, $prefix)) {
                $stripped = substr($stripped, strlen($prefix));
                break;
            }
        }
        $candidates = [
            $uploads . '/' . $stripped . $master->name,
            $uploads . '/' . $rawPath . $master->name,
            rtrim((string) $master->path, '/') . '/' . $master->name,
            $uploads . '/' . $master->name,
        ];
        foreach ($candidates as $c) {
            if ($c && file_exists($c)) {
                return response()->file($c, [
                    'Content-Type'        => $master->mime_type ?: 'application/octet-stream',
                    'Content-Disposition' => 'inline; filename="' . basename($master->name) . '"',
                ]);
            }
        }
        abort(404);
    }

    /**
     * Privacy dashboard with DSAR, breach, and processing activity stats.
     */
    public function dashboard()
    {
        $stats = $this->privacyService->getDashboardStats();

        return view('ahg-io-manage::privacy.dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * Fetch an IO by slug with i18n data.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select(
                'io.id',
                'i18n.title',
                'i18n.scope_and_content',
                's.slug'
            )
            ->first();
    }

    /**
     * Fetch an IO by ID with i18n data.
     */
    private function getIOById(int $id): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $id)
            ->select(
                'io.id',
                'i18n.title',
                'i18n.scope_and_content',
                'i18n.archival_history',
                's.slug'
            )
            ->first();
    }
}
