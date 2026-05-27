<?php

/**
 * EmbeddedMetadataContextService - Service for Heratio
 *
 * Copyright (C) 2026 Plain Sailing Information Systems
 * Author: Johan Pieterse <johan@plainsailingisystems.co.za>
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

declare(strict_types=1);

namespace AhgAiServices\Services;

use AhgAiServices\DTO\AiContextHints;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Embedded-metadata context hints for AI services.
 *
 * Given a digital_object id, this service reads the embedded EXIF / IPTC /
 * XMP fields that ahg-metadata-extraction has previously persisted to the
 * `property` table (scope='metadata_extraction') and surfaces them as an
 * AiContextHints DTO. The DTO can then be appended as a system-prompt
 * prefix by every AI service - NER, HTR, Donut, raw LLM - so the model
 * sees, for example, "this photograph was taken on 1969-07-20 at
 * 28.0473,-26.2041" and stops hallucinating that the photo is from 1942.
 *
 * Issue #750. Cached per-request: hints for a given digital_object id are
 * computed at most once per Laravel request, regardless of how many AI
 * services consume them.
 *
 * Privacy gate: GPS hints are dropped when `ahg_pii_finding_embedded`
 * (#751) has flagged the source coordinate. When the #751 table is absent
 * (still in flight), a warning is logged and GPS proceeds ungated - the
 * defence-in-depth principle is "fail open with audit trail" rather than
 * "fail closed and silently drop the feature".
 *
 * Audit hook: the consumer is expected to log an `inference_context_used`
 * event to the inference receipt chain (issue #693 / ahg-ai-compliance)
 * via the helper logContextEvent() below, which is callable from each AI
 * service after a successful inference.
 */
final class EmbeddedMetadataContextService
{
    /**
     * Per-request cache. Key: digital_object id. Value: hydrated hints.
     * Cleared on Laravel boot (instance is rebuilt every request).
     *
     * @var array<int,AiContextHints>
     */
    private array $cache = [];

    /**
     * Surface the hints for a digital_object. Returns the empty DTO when:
     * - $digitalObjectId is null or non-positive
     * - the property / digital_object tables are missing (CI bootstrap)
     * - no metadata_extraction rows exist for the object
     * - every candidate field is empty after filtering
     *
     * The empty DTO is itself cached so repeated calls within a request
     * stay free.
     */
    public function forDigitalObject(?int $digitalObjectId): AiContextHints
    {
        if ($digitalObjectId === null || $digitalObjectId <= 0) {
            return AiContextHints::empty();
        }

        if (array_key_exists($digitalObjectId, $this->cache)) {
            return $this->cache[$digitalObjectId];
        }

        return $this->cache[$digitalObjectId] = $this->build($digitalObjectId);
    }

    /**
     * Build the hint set for an information_object by resolving its
     * digital objects (master copy preferred) and surfacing the first
     * non-empty hint set we find. Convenience for callers that hold an
     * IO id but not a DO id (NER, the raw-LLM suggestion pipeline).
     */
    public function forInformationObject(?int $informationObjectId): AiContextHints
    {
        if ($informationObjectId === null || $informationObjectId <= 0) {
            return AiContextHints::empty();
        }

        try {
            if (!Schema::hasTable('digital_object')) {
                return AiContextHints::empty();
            }

            // Master copy first, then any other DO attached to this IO.
            // usage_id 1 == master in AtoM's Qubit class-table inheritance.
            $rows = DB::table('digital_object')
                ->where('information_object_id', $informationObjectId)
                ->orderByRaw('CASE WHEN usage_id = 1 THEN 0 ELSE 1 END')
                ->orderBy('id')
                ->limit(5)
                ->pluck('id');

            foreach ($rows as $doId) {
                $hints = $this->forDigitalObject((int) $doId);
                if (!$hints->isEmpty()) {
                    return $hints;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] EmbeddedMetadataContextService::forInformationObject failed: ' . $e->getMessage());
        }

        return AiContextHints::empty();
    }

    /**
     * Clear the per-request cache. Tests + long-running queue workers may
     * want to invalidate between iterations; the typical web request never
     * needs to call this.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Audit hook: log "the AI saw these hints" to the inference receipt
     * chain. Called from each AI service after a successful inference so
     * operators can audit, per-call, exactly what context the model was
     * given. Fails soft - a receipt-chain failure must never abort the
     * inference flow.
     */
    public function logContextEvent(string $service, int $digitalObjectId, AiContextHints $hints): void
    {
        if ($hints->isEmpty()) {
            return;
        }

        if (!class_exists(\AhgAiCompliance\Services\InferenceLogger::class)) {
            return;
        }

        try {
            $payload = json_encode([
                'digital_object_id' => $digitalObjectId,
                'hints' => $hints->toArray(),
            ], JSON_UNESCAPED_UNICODE) ?: '{}';

            app(\AhgAiCompliance\Services\InferenceLogger::class)->log(
                'inference_context_used',
                $service,
                null,
                'digital_object:' . $digitalObjectId,
                $payload,
                ['event' => 'inference_context_used'],
            );
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] EmbeddedMetadataContextService::logContextEvent failed: ' . $e->getMessage());
        }
    }

    /**
     * Internal: assemble hints for one digital_object id.
     */
    private function build(int $digitalObjectId): AiContextHints
    {
        try {
            if (!Schema::hasTable('property')) {
                return AiContextHints::empty();
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] EmbeddedMetadataContextService schema probe failed: ' . $e->getMessage());
            return AiContextHints::empty();
        }

        $props = $this->fetchProperties($digitalObjectId);
        if ($props === []) {
            return AiContextHints::empty();
        }

        $suppressed = [];

        $dateHint = $this->resolveDateHint($props);

        [$placeHint, $gpsReason] = $this->resolvePlaceHint($props, $digitalObjectId);
        if ($gpsReason !== null) {
            $suppressed[] = $gpsReason;
        }

        $creatorHint  = $this->resolveCreatorHint($props);
        $subjectHints = $this->resolveSubjectHints($props);

        return new AiContextHints(
            dateHint:          $dateHint,
            placeHint:         $placeHint,
            creatorHint:       $creatorHint,
            subjectHints:      $subjectHints,
            suppressedReasons: $suppressed,
        );
    }

    /**
     * Fetch every metadata_extraction property for a digital_object as a
     * flat key -> value map. The keys are dot/colon-flattened EXIF/IPTC/XMP
     * paths produced by MetadataExtractionService::flattenMetadata().
     *
     * @return array<string,string>
     */
    private function fetchProperties(int $digitalObjectId): array
    {
        try {
            $rows = DB::table('property')
                ->leftJoin('property_i18n', 'property.id', '=', 'property_i18n.id')
                ->where('property.object_id', $digitalObjectId)
                ->where('property.scope', 'metadata_extraction')
                ->select('property.name', 'property_i18n.value')
                ->get();

            $out = [];
            foreach ($rows as $row) {
                $name  = (string) ($row->name ?? '');
                $value = trim((string) ($row->value ?? ''));
                if ($name !== '' && $value !== '') {
                    $out[$name] = $value;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] EmbeddedMetadataContextService::fetchProperties failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Pick the best available date hint. Priority: EXIF DateTimeOriginal,
     * then EXIF DateTime, then IPTC date_created, then XMP date_time_original.
     * Returns the raw string - the LLM is free to normalise it.
     */
    private function resolveDateHint(array $props): ?string
    {
        foreach ([
            'exif:EXIF:DateTimeOriginal',
            'exif:IFD0:DateTime',
            'exif:DateTimeOriginal',
            'exif:DateTime',
            'iptc:date_created',
            'xmp:date_time_original',
            'xmp:create_date',
        ] as $key) {
            if (!empty($props[$key])) {
                return $props[$key];
            }
        }
        return null;
    }

    /**
     * Pick the best available place hint. Prefer reverse-geocoded place
     * name when a geocoder writes one back to gps:place; fall through to
     * "lat,lon" decimal from gps:decimal; fall through to lat/lon split.
     *
     * GPS is gated through the #751 PII finding table. Anything flagged
     * there is dropped entirely and a suppressedReason is surfaced for
     * the receipt event.
     *
     * @return array{0:?string,1:?string} [hint, suppressedReason]
     */
    private function resolvePlaceHint(array $props, int $digitalObjectId): array
    {
        if (!empty($props['gps:place'])) {
            return [$props['gps:place'], null];
        }

        $latLon = null;
        if (!empty($props['gps:decimal'])) {
            $latLon = $props['gps:decimal'];
        } elseif (!empty($props['gps:latitude']) && !empty($props['gps:longitude'])) {
            $latLon = $props['gps:latitude'] . ',' . $props['gps:longitude'];
        }

        if ($latLon === null) {
            return [null, null];
        }

        $gateReason = $this->checkGpsPrivacyGate($digitalObjectId);
        if ($gateReason !== null) {
            return [null, $gateReason];
        }

        return [$latLon, null];
    }

    /**
     * Apply the issue #751 privacy gate. Returns a non-null suppression
     * reason when the digital_object has an open GPS PII finding; returns
     * null when the gate clears or the gate table is missing (defence:
     * fail open, log a warning so the operator notices).
     */
    private function checkGpsPrivacyGate(int $digitalObjectId): ?string
    {
        try {
            if (!Schema::hasTable('ahg_pii_finding_embedded')) {
                Log::warning('[ahg-ai] embedded-metadata GPS gate: ahg_pii_finding_embedded missing, proceeding without privacy gate (issue #751 not yet shipped)');
                return null;
            }

            $finding = DB::table('ahg_pii_finding_embedded')
                ->where('digital_object_id', $digitalObjectId)
                ->where('pii_type', 'gps_coordinate')
                ->whereIn('resolution_status', ['pending', 'escalated'])
                ->orderByDesc('id')
                ->first();

            if ($finding === null) {
                return null;
            }

            return 'GPS suppressed by PII finding #' . $finding->id;
        } catch (\Throwable $e) {
            Log::warning('[ahg-ai] embedded-metadata GPS gate probe failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pick the best creator hint. Priority: IPTC By-line, XMP dc:creator,
     * EXIF Artist.
     */
    private function resolveCreatorHint(array $props): ?string
    {
        foreach ([
            'iptc:byline',
            'iptc:by_line',
            'xmp:creator',
            'exif:IFD0:Artist',
            'exif:Artist',
        ] as $key) {
            if (!empty($props[$key])) {
                return $props[$key];
            }
        }
        return null;
    }

    /**
     * Subject hints: IPTC Keywords or XMP dc:subject, deduplicated,
     * preserving original order. JSON arrays (the flattened form for
     * sequential lists per MetadataExtractionService) are decoded.
     *
     * @return list<string>
     */
    private function resolveSubjectHints(array $props): array
    {
        $raw = $props['iptc:keywords']
            ?? $props['xmp:keywords']
            ?? $props['xmp:subject']
            ?? null;

        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = null;
        if ($raw !== '' && ($raw[0] === '[' || $raw[0] === '{')) {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }

        if ($decoded === null) {
            // Treat as comma- or semicolon-separated.
            $decoded = preg_split('/[,;]\s*/', $raw) ?: [];
        }

        $out = [];
        foreach ($decoded as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }
            $key = mb_strtolower($term);
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = $term;
        }

        return array_values($out);
    }
}
