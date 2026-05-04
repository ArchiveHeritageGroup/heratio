<?php

/**
 * NerService - Service for Heratio
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



namespace AhgAiServices\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NER (Named Entity Recognition) Service
 *
 * Extracts named entities from text using either the AHG AI API
 * or the LLM service as fallback. Creates access points (term
 * entries in object_term_relation) for extracted entities.
 *
 * Ported from ahgAIPlugin ahgNerService.php
 */
class NerService
{
    // AtoM taxonomy/term type IDs (from class table inheritance)
    private const CORPORATE_BODY_ID       = 131;
    private const PERSON_ID               = 132;
    private const NAME_ACCESS_POINT_ID    = 161;
    private const TAXONOMY_PLACE_ID       = 42;
    private const TAXONOMY_SUBJECT_ID     = 35;
    private const TAXONOMY_NAME_ID        = 3;

    private LlmService $llmService;
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;

    /**
     * Cached AI API health response (model name + version) so we can stamp every
     * recorded inference without hitting /health on every call. Refreshed
     * lazily by resolveModelIdentity().
     *
     * @var array{model:string,version:string}|null
     */
    private ?array $modelIdentity = null;

    public function __construct(LlmService $llmService)
    {
        $this->llmService = $llmService;
        $this->apiUrl     = $this->loadSetting('api_url', 'http://192.168.0.112:5004/ai/v1');
        $this->apiKey     = $this->loadSetting('api_key', '');
        $this->timeout    = (int) $this->loadSetting('api_timeout', '60');
    }

    /**
     * Extract named entities from text.
     *
     * Uses the AHG AI API if available, falls back to LLM-based extraction.
     *
     * @return array ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => []]
     */
    public function extract(string $text): array
    {
        $default = ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => []];

        // Gate: check if NER is enabled in AI Services settings
        if ($this->loadSetting('ner_enabled', '1') !== '1') {
            return $default;
        }

        if (empty(trim($text))) {
            return $default;
        }

        // Try the dedicated AI API first
        $apiResult = $this->extractViaApi($text);

        if ($apiResult !== null) {
            return $this->normalizeApiResult($apiResult);
        }

        // Fall back to LLM-based extraction
        return $this->llmService->extractEntities($text);
    }

    /**
     * Extract entities from a PDF file via the AI API.
     */
    public function extractFromPdf(string $filePath): array
    {
        $default = ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => []];

        if (!file_exists($filePath)) {
            return $default;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-API-Key' => $this->apiKey])
                ->attach('file', file_get_contents($filePath), basename($filePath))
                ->post($this->apiUrl . '/ner/extract-pdf');

            if ($response->successful()) {
                $body = $response->json();
                if (!empty($body['success'])) {
                    return $this->normalizeApiResult($body);
                }
            }
        } catch (\Exception $e) {
            Log::warning('NerService::extractFromPdf failed: ' . $e->getMessage());
        }

        return $default;
    }

    /**
     * Extract named entities AND record the inference.
     *
     * Issue #61 / ADR-0002: this is the canonical entry point for any caller
     * that has a target information_object id. Logs one inference per call
     * (input = text, output = entities json) tagged with model + version
     * via getApiHealth(), confidence = NULL (the current API does not expose
     * per-entity scores; once the upstream API returns scores, we will
     * record per-entity instead).
     *
     * Returns the same shape as extract() so callers swap one for the other.
     */
    public function extractAndRecord(string $text, int $informationObjectId, ?int $userId = null): array
    {
        $t0 = microtime(true);
        $entities = $this->extract($text);
        $elapsedMs = (int) round((microtime(true) - $t0) * 1000);

        try {
            $svc = app(\AhgProvenanceAi\Services\InferenceService::class);
            [$inHash, $inExc]   = \AhgProvenanceAi\DTO\InferenceRecord::hashAndExcerpt($text);
            $outputJson         = json_encode($entities, JSON_UNESCAPED_UNICODE);
            [$outHash, $outExc] = \AhgProvenanceAi\DTO\InferenceRecord::hashAndExcerpt((string) $outputJson);
            [$model, $version]  = $this->resolveModelIdentity();

            $svc->record(new \AhgProvenanceAi\DTO\InferenceRecord(
                serviceName:      'NER',
                modelName:        $model,
                modelVersion:     $version,
                inputHash:        $inHash,
                outputHash:       $outHash,
                targetEntityType: 'information_object',
                targetEntityId:   $informationObjectId,
                targetField:      'access_points',
                confidence:       null,
                standard:         'ICIP-name-access-points',
                endpoint:         $this->apiUrl . '/ner/extract',
                inputExcerpt:     $inExc,
                outputExcerpt:    $outExc,
                elapsedMs:        $elapsedMs,
                userId:           $userId,
            ));
        } catch (\Throwable $e) {
            // Defence in depth: provenance failure must never break the
            // user-visible NER flow. Log + continue with the entities.
            Log::warning('NerService::extractAndRecord provenance write failed: ' . $e->getMessage());
        }

        return $entities;
    }

    /**
     * Resolve the upstream model name + version, cached for the life of this
     * service instance. Falls back to ('unknown', 'unknown') if /health does
     * not respond or the response shape is unexpected.
     *
     * @return array{0:string,1:string} [model, version]
     */
    protected function resolveModelIdentity(): array
    {
        if ($this->modelIdentity !== null) {
            return [$this->modelIdentity['model'], $this->modelIdentity['version']];
        }

        $model   = 'unknown';
        $version = 'unknown';
        try {
            $health = $this->getApiHealth();
            // The local NER adapter exposes {"model":"qwen3:8b", "model_loaded":true, ...}
            // Future spaCy-based deployments may expose {"model":"en_core_web_sm","version":"3.8.0",...}
            if (is_array($health)) {
                if (!empty($health['model'])) {
                    $model = (string) $health['model'];
                }
                if (!empty($health['version'])) {
                    $version = (string) $health['version'];
                } elseif (!empty($health['model_version'])) {
                    $version = (string) $health['model_version'];
                }
            }
        } catch (\Throwable $e) {
            // Health probe must never break the inference path.
        }

        $this->modelIdentity = ['model' => $model, 'version' => $version];
        return [$model, $version];
    }

    /**
     * Create access points (term/actor relations) for extracted entities on an information object.
     *
     * @param int   $informationObjectId The target information object
     * @param array $entities            ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => []]
     * @return int  Count of created access points
     */
    public function createAccessPoints(int $informationObjectId, array $entities): int
    {
        $count  = 0;
        $culture = 'en';

        // Store entities to ahg_ner_entity for review workflow
        foreach ($entities as $type => $values) {
            $entityType = $this->mapEntityType($type);

            foreach ($values as $value) {
                $value = trim($value);
                if (empty($value)) {
                    continue;
                }

                // Check for duplicate
                $exists = DB::table('ahg_ner_entity')
                    ->where('object_id', $informationObjectId)
                    ->where('entity_type', $entityType)
                    ->where('entity_value', $value)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('ahg_ner_entity')->insert([
                    'object_id'    => $informationObjectId,
                    'entity_type'  => $entityType,
                    'entity_value' => $value,
                    'confidence'   => 1.0000,
                    'status'       => 'pending',
                    'created_at'   => now(),
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * Get pending NER entities for an information object.
     */
    public function getPendingEntities(int $objectId): array
    {
        return DB::table('ahg_ner_entity')
            ->where('object_id', $objectId)
            ->where('status', 'pending')
            ->orderBy('entity_type')
            ->orderBy('entity_value')
            ->get()
            ->toArray();
    }

    /**
     * Get all pending entities grouped by object.
     */
    public function getPendingObjects(int $limit = 50, string $culture = 'en'): array
    {
        return DB::table('ahg_ner_entity')
            ->join('information_object', 'ahg_ner_entity.object_id', '=', 'information_object.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('information_object_i18n', function ($join) use ($culture) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->where('ahg_ner_entity.status', 'pending')
            ->select(
                'information_object.id',
                'slug.slug',
                'information_object_i18n.title',
                DB::raw('COUNT(*) as pending_count')
            )
            ->groupBy('information_object.id', 'slug.slug', 'information_object_i18n.title')
            ->orderBy('pending_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Update an entity status (link, approve, reject, dismiss).
     */
    public function updateEntityStatus(int $entityId, string $action, ?int $targetId = null, ?int $userId = null): bool
    {
        $entity = DB::table('ahg_ner_entity')->where('id', $entityId)->first();

        if (!$entity) {
            return false;
        }

        $update = [
            'status'      => $action === 'link' ? 'linked' : $action,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ];

        if ($targetId) {
            $update['linked_actor_id'] = $targetId;
        }

        DB::table('ahg_ner_entity')
            ->where('id', $entityId)
            ->update($update);

        // If linking, create the appropriate access point relation
        if ($action === 'link' && $targetId) {
            $this->createRelation($entity, $targetId);
        }

        return true;
    }

    /**
     * Get NER statistics.
     */
    public function getStats(): array
    {
        return (array) DB::table('ahg_ner_entity')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'linked' THEN 1 ELSE 0 END) as linked,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                COUNT(DISTINCT object_id) as objects_count
            ")
            ->first();
    }

    /**
     * Check if the AI API is available.
     */
    public function isApiAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->apiUrl . '/health');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get API health details.
     */
    public function getApiHealth(): array
    {
        try {
            $response = Http::timeout(5)->get($this->apiUrl . '/health');

            if ($response->successful()) {
                return $response->json() ?? ['status' => 'ok'];
            }

            return ['status' => 'error', 'error' => 'HTTP ' . $response->status()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * Extract entities via the dedicated AI API.
     */
    private function extractViaApi(string $text): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $this->apiKey,
                ])
                ->post($this->apiUrl . '/ner/extract', [
                    'text'  => $text,
                    'clean' => true,
                ]);

            if ($response->successful()) {
                $body = $response->json();
                if (!empty($body['success'])) {
                    return $body;
                }
            }
        } catch (\Exception $e) {
            Log::info('NerService::extractViaApi not available, falling back to LLM: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Normalize API result to standard format.
     */
    private function normalizeApiResult(array $apiResult): array
    {
        $entities = $apiResult['entities'] ?? [];

        return [
            'persons'       => $entities['PERSON'] ?? $entities['persons'] ?? [],
            'organizations' => $entities['ORG'] ?? $entities['organizations'] ?? [],
            'places'        => $entities['GPE'] ?? $entities['places'] ?? [],
            'dates'         => $entities['DATE'] ?? $entities['dates'] ?? [],
        ];
    }

    /**
     * Map friendly entity type names to NER entity type codes.
     */
    private function mapEntityType(string $type): string
    {
        $map = [
            'persons'       => 'PERSON',
            'organizations' => 'ORG',
            'places'        => 'GPE',
            'dates'         => 'DATE',
            'PERSON'        => 'PERSON',
            'ORG'           => 'ORG',
            'GPE'           => 'GPE',
            'DATE'          => 'DATE',
        ];

        return $map[$type] ?? strtoupper($type);
    }

    /**
     * Create an access point relation between an entity and an information object.
     */
    private function createRelation(object $entity, int $targetId): void
    {
        $objectId = $entity->object_id;

        if ($entity->entity_type === 'PERSON' || $entity->entity_type === 'ORG') {
            // Link actor to object via event (name access point)
            $this->linkActorToObject($objectId, $targetId);
        } elseif ($entity->entity_type === 'GPE') {
            // Link place term to object
            $this->linkPlaceToObject($objectId, $targetId);
        }
    }

    /**
     * Link an actor as a name access point to an information object.
     */
    private function linkActorToObject(int $objectId, int $actorId): void
    {
        // Check if relation already exists
        $exists = DB::table('relation')
            ->where('subject_id', $objectId)
            ->where('object_id', $actorId)
            ->where('type_id', self::NAME_ACCESS_POINT_ID)
            ->exists();

        if ($exists) {
            return;
        }

        // Insert into object table first (class table inheritance)
        $id = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('relation')->insert([
            'id'         => $id,
            'subject_id' => $objectId,
            'object_id'  => $actorId,
            'type_id'    => self::NAME_ACCESS_POINT_ID,
        ]);
    }

    /**
     * Link a place term to an information object via object_term_relation.
     */
    private function linkPlaceToObject(int $objectId, int $termId): void
    {
        $exists = DB::table('object_term_relation')
            ->where('object_id', $objectId)
            ->where('term_id', $termId)
            ->exists();

        if ($exists) {
            return;
        }

        // Insert into object table first
        $id = DB::table('object')->insertGetId([
            'class_name' => 'QubitObjectTermRelation',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('object_term_relation')->insert([
            'id'        => $id,
            'object_id' => $objectId,
            'term_id'   => $termId,
        ]);
    }

    /**
     * Load a setting from ahg_ai_settings (general feature).
     */
    private function loadSetting(string $key, string $default): string
    {
        try {
            // Primary: ahg_ner_settings (user-facing AI Services settings page)
            $value = DB::table('ahg_ner_settings')
                ->where('setting_key', $key)
                ->value('setting_value');
            if ($value !== null && $value !== '') {
                return $value;
            }

            // Fallback: ahg_ai_settings (legacy AtoM migration)
            $value = DB::table('ahg_ai_settings')
                ->where('feature', 'general')
                ->where('setting_key', $key)
                ->value('setting_value');
            if ($value !== null && $value !== '') {
                return $value;
            }
        } catch (\Exception $e) {
            // DB not available during boot
        }

        return $default;
    }
}
