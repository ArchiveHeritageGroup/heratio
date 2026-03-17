<?php

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
