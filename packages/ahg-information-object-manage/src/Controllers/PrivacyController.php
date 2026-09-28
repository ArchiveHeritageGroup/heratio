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

use AhgCore\Support\AuditLog;
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

        // Which of the record's images is being redacted (heratio#1503).
        // ?do= picks one; without it, the default master, as before.
        $renderer = app(RedactionRenderService::class);
        $masters = $renderer->mastersFor((int) $io->id);
        $digitalObject = $this->redactionTarget($io, request()->query('do'));

        // Existing redactions on THIS image only
        $existingRedactions = $digitalObject
            ? $renderer->regionsQuery((int) $io->id, (int) $digitalObject->id, false)
                ->orderBy('page_number')->orderBy('created_at')->get()
            : collect();

        // Parse coordinates from JSON and build flat array for JS. Includes
        // the `normalized` flag so the editor's loader can scale 0-1 fractions
        // back into canvas pixels at the current zoom level.
        $redactionRegions = $existingRedactions->map(function ($r) {
            $rect = \AhgInformationObjectManage\Services\PrivacyService::redactionRect($r->coordinates);
            return [
                'id'         => $r->id,
                'left'       => $rect['left'],
                'top'        => $rect['top'],
                'width'      => $rect['width'],
                'height'     => $rect['height'],
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
            'masters'            => $masters,
            'existingRedactions' => $redactionRegions,
            'documentUrl'        => $documentUrl,
            'documentType'       => $documentType,
            'totalPages'         => $totalPages,
        ]);
    }

    /**
     * The image a redaction request is about: the ?do= one when it belongs to
     * the record, the default master when none is given. A ?do= naming some
     * other record's file is a 404 - it must never select a file to redact or
     * to serve by id alone.
     */
    private function redactionTarget(object $io, $doParam): ?object
    {
        $renderer = app(RedactionRenderService::class);
        if ($doParam === null || $doParam === '') {
            return $renderer->defaultMaster((int) $io->id);
        }
        if (!ctype_digit((string) $doParam)) {
            abort(404);
        }

        return $renderer->masterFor((int) $io->id, (int) $doParam) ?? abort(404);
    }

    /**
     * POST /privacy/redaction/{slug}/save?do={id} - persist the regions drawn
     * on one image. The client sends the FULL list for that image (no
     * per-region ids), so it is a replace-all of that image's regions only;
     * the record's other images keep theirs. Returns JSON for the AJAX caller.
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

        // The image these regions are drawn on - the same resolver the editor
        // used, so a save lands on the file that was on screen.
        $target = $this->redactionTarget($io, $request->query('do'));
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'This record has no digital object to redact'], 422);
        }
        $digitalObjectId = (int) $target->id;

        // Snapshot the existing region set before the replace-all so the
        // audit row carries a real before/after diff (region writes don't go
        // through any of the wrapped service::update paths).
        $beforeRegions = $this->snapshotRedactions((int) $io->id);

        try {
            \DB::transaction(function () use ($io, $digitalObjectId, $regions) {
                // Replace-all for this image: drop its existing regions (and any
                // unbound legacy rows, when it is the default master), then
                // insert the new set bound to it. Matches the client payload,
                // which has no ids.
                app(RedactionRenderService::class)
                    ->regionsQuery((int) $io->id, $digitalObjectId, false)
                    ->delete();

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
                        // Auto-promote on save - there is no editor/admin
                        // approval workflow; the user who saves *is* the admin
                        // and the rectangle takes effect for non-admin viewers
                        // immediately. Was 'pending' for a workflow that
                        // never existed in this implementation.
                        'status'            => 'applied',
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

        // Audit-trail: visual-redaction edits don't go through any of the
        // service::update paths wrapped in v1.52.23, and the replace-all
        // shape means there's no per-region create/move/delete - one save
        // call is one audit event. Capture the before/after region sets so
        // /admin/acl/audit-log shows what the user changed.
        $afterRegions = $this->snapshotRedactions((int) $io->id);
        try {
            AuditLog::captureMutation((int) $io->id, 'information_object', 'redaction_regions_save', [
                'before_count' => count($beforeRegions),
                'after_count'  => count($afterRegions),
                'before'       => $beforeRegions,
                'after'        => $afterRegions,
            ]);
        } catch (\Throwable $e) { /* audit failure must not break the save */ }

        return response()->json([
            'success' => true,
            'count'   => count($regions),
            'message' => count($regions) . ' redaction region' . (count($regions) === 1 ? '' : 's') . ' saved.',
        ]);
    }

    /**
     * Snapshot the visual-redaction set for an IO in a stable, audit-friendly
     * shape. Strips per-row id / timestamps so before/after diffs aren't
     * dominated by churn the user can't see.
     */
    private function snapshotRedactions(int $objectId): array
    {
        return \DB::table('privacy_visual_redaction')
            ->where('object_id', $objectId)
            ->orderBy('page_number')
            ->orderBy('id')
            ->get(['page_number', 'region_type', 'coordinates', 'normalized', 'source', 'label', 'color', 'status'])
            ->map(fn ($r) => [
                'page'       => (int) $r->page_number,
                'type'       => (string) $r->region_type,
                'coords'     => \AhgInformationObjectManage\Services\PrivacyService::redactionRect($r->coordinates),
                'normalized' => (int) $r->normalized,
                'source'     => (string) $r->source,
                'label'      => $r->label,
                'color'      => (string) $r->color,
                'status'     => (string) $r->status,
            ])
            ->all();
    }

    /**
     * Stream the redacted master file to non-admin viewers. Admins are
     * redirected to the original. On cache miss, renders synchronously
     * via RedactionRenderService.
     *
     * GET /privacy/redacted-asset/{slug}/{do?} - {do} picks one of the
     * record's images (heratio#1503); without it, the default master.
     */
    public function redactedAsset(string $slug, ?string $do = null)
    {
        $io = $this->getIO($slug);
        if (!$io) abort(404);

        $isAdmin = auth()->check() && auth()->user()
            && (method_exists(auth()->user(), 'isAdministrator')
                ? auth()->user()->isAdministrator()
                : (bool) (auth()->user()->is_admin ?? false));

        // Same resolver as the renderer, so the two agree on which file this
        // is. An id that is not one of this record's images is a 404.
        $renderer = app(RedactionRenderService::class);
        $master = $this->redactionTarget($io, $do);
        if (!$master) abort(404);

        // Admins bypass the redactor - return the original file.
        if ($isAdmin) {
            return $this->streamOriginal($master);
        }

        $redactedPath = $renderer->render((int) $io->id, (int) $master->id);
        if (!$redactedPath || !file_exists($redactedPath)) {
            // render() returns null for BOTH "no regions on file" (safe to
            // serve the original) AND "regions exist but rendering failed"
            // (must NOT serve the original - that leaks the very content we
            // redact). Distinguish them so we fail CLOSED on a render failure
            // instead of silently leaking, and log accurately.
            $hasRegions = \Schema::hasTable('privacy_visual_redaction')
                && $renderer->regionsQuery((int) $io->id, (int) $master->id)->exists();
            if ($hasRegions) {
                \Log::error('[redaction] render FAILED for an IO with regions on file - refusing to serve the original (fail-closed). Check the redaction-cache dir is www-data-writable.', ['io_id' => $io->id]);
                abort(503, 'This record has redactions that could not be applied right now. Please try again later or contact the institution.');
            }
            \Log::info('[redaction] no regions on file; serving original', ['io_id' => $io->id]);
            return $this->streamOriginal($master);
        }

        // Read the type off the DERIVATIVE, never off the master. A redacted
        // TIFF is transcoded to JPEG so a browser can display it, and sending
        // the master's image/tiff for that file makes the browser refuse it -
        // which is how Mirador ended up showing nothing for image masters.
        $redactedName = pathinfo((string) $master->name, PATHINFO_FILENAME)
            . '.' . pathinfo($redactedPath, PATHINFO_EXTENSION);

        return response()->file($redactedPath, [
            'Content-Type'        => RedactionRenderService::mimeForPath($redactedPath),
            'Content-Disposition' => 'inline; filename="' . $redactedName . '"',
            'X-Heratio-Redacted'  => '1',
        ]);
    }

    private function streamOriginal(object $master)
    {
        // Same resolver as RedactionRenderService - the web-facing path field
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
