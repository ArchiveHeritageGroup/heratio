<?php

/**
 * PrivacyController - Controller for Heratio
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



namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\AiNerService;
use AhgInformationObjectManage\Services\PrivacyService;
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

        // Parse coordinates from JSON and build flat array for JS
        $redactionRegions = $existingRedactions->map(function ($r) {
            $coords = is_string($r->coordinates) ? json_decode($r->coordinates, true) : (array) $r->coordinates;
            return [
                'id'     => $r->id,
                'left'   => $coords['left'] ?? $coords['x'] ?? 0,
                'top'    => $coords['top'] ?? $coords['y'] ?? 0,
                'width'  => $coords['width'] ?? $coords['w'] ?? 100,
                'height' => $coords['height'] ?? $coords['h'] ?? 50,
                'page'   => $r->page_number,
                'label'  => $r->label,
                'color'  => $r->color,
                'status' => $r->status,
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
