<?php

/**
 * ImageEmbeddingService - heratio#1272. Turns images and short texts into
 * vectors in one shared image/text space, through the AHG AI gateway only,
 * and keeps the Qdrant image collection and its ledger (ahg_image_embedding)
 * in step.
 *
 * Gateway contract: docs/technical/image-embedding-gateway-contract.md
 *   POST {gateway}/embed/image  {"model": "...", "image": "<base64>"} -> {"embedding": [..]}
 *   POST {gateway}/embed/text   {"model": "...", "text": "..."}      -> {"embedding": [..]}
 *   or, for the nomic pair, text through {gateway}/ollama/api/embeddings.
 *
 * One Qdrant point per master digital object (point id = digital_object.id),
 * payload carries information_object_id. Search maps hits back to records and
 * applies the disclosure gates there (ImageSearchStrategy).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDiscovery\Services;

use AhgAiServices\Support\AiServicesSettings;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\EncryptionService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ImageEmbeddingService
{
    public const STATUS_EMBEDDED = 'embedded';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    /** Returned by indexDigitalObject() when the gateway or Qdrant is down: nothing recorded, try again later. */
    public const UNAVAILABLE = 'unavailable';

    public const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'];

    /** A 1x1 PNG - the probe that tells "gateway up" from "this image is bad". */
    private const PROBE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** True after a call failed because the gateway (or no upstream node) could not serve it. */
    public bool $unavailable = false;

    public ?string $lastError = null;

    /** @var array<string, int> collection => vector size already confirmed this process */
    private array $ensured = [];

    /** @var array<int, true>|null */
    private ?array $communityRestricted = null;

    /* ------------------------------------------------------------------ */
    /*  Embedding through the gateway                                      */
    /* ------------------------------------------------------------------ */

    /** @return array<int, float>|null */
    public function embedImageBytes(string $bytes, int $timeoutSeconds = 60): ?array
    {
        return $this->callGateway('/embed/image', [
            'model' => $this->imageModel(),
            'image' => base64_encode($bytes),
        ], $timeoutSeconds);
    }

    /**
     * Embed a search text into the image space. The nomic pair uses the
     * gateway's Ollama route; an empty text-model setting means the image
     * model has its own text tower behind {gateway}/embed/text.
     *
     * @return array<int, float>|null
     */
    public function embedText(string $text, int $timeoutSeconds = 10): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $textModel = $this->rawSetting('ahg_discovery_image_text_model') ?? 'nomic-embed-text';
        if ($textModel === '') {
            return $this->callGateway('/embed/text', ['model' => $this->imageModel(), 'text' => $text], $timeoutSeconds);
        }
        $prefix = $this->rawSetting('ahg_discovery_image_text_prefix') ?? 'search_query: ';

        return $this->callGateway('/ollama/api/embeddings', ['model' => $textModel, 'prompt' => $prefix.$text], $timeoutSeconds);
    }

    /** Is the image embedder answering at all? One tiny call. */
    public function probe(): bool
    {
        return $this->embedImageBytes(base64_decode(self::PROBE_PNG), 30) !== null;
    }

    /** @return array<int, float>|null */
    protected function callGateway(string $path, array $body, int $timeoutSeconds): ?array
    {
        $this->unavailable = false;
        $this->lastError = null;
        $key = AiServicesSettings::gatewayKey();
        try {
            $req = Http::acceptJson()->timeout(max(1, $timeoutSeconds))->connectTimeout(5);
            if ($key) {
                $req = $req->withToken($key);
            }
            $resp = $req->post($this->gatewayBase().$path, $body);
        } catch (ConnectionException $e) {
            return $this->fail(true, 'gateway unreachable');
        } catch (Throwable $e) {
            return $this->fail(true, 'gateway error');
        }

        if (! $resp->successful()) {
            // 5xx, 404, 408 or 429 = no upstream can serve the route yet, or it
            // is busy; 401/403 = the key. Any other 4xx is about this input.
            $down = $resp->serverError() || in_array($resp->status(), [401, 403, 404, 408, 429], true);

            return $this->fail($down, 'gateway HTTP '.$resp->status());
        }
        $vec = $resp->json('embedding');
        if (! is_array($vec) || $vec === [] || ! is_numeric($vec[0] ?? null)) {
            return $this->fail(false, 'no embedding in the gateway reply');
        }

        return array_map('floatval', array_values($vec));
    }

    private function fail(bool $unavailable, string $error): ?array
    {
        $this->unavailable = $unavailable;
        $this->lastError = $error;
        Log::debug('[image-embed] '.$error);

        return null;
    }

    /* ------------------------------------------------------------------ */
    /*  Indexing                                                           */
    /* ------------------------------------------------------------------ */

    /** Master image digital objects of real records. */
    public function candidates(): Builder
    {
        return DB::table('digital_object as d')
            ->whereNull('d.parent_id')
            ->whereNotNull('d.name')
            ->where('d.object_id', '>', 1)
            ->where(function ($q) {
                $q->where('d.mime_type', 'like', 'image/%');
                foreach (self::IMAGE_EXTS as $ext) {
                    $q->orWhere('d.name', 'like', '%.'.$ext);
                }
            });
    }

    /**
     * Images never embedded with the current model, changed since (checksum),
     * or failed fewer than $maxAttempts times.
     */
    public function pending(int $maxAttempts = 3): Builder
    {
        $model = $this->imageModel();

        return $this->candidates()
            ->leftJoin('ahg_image_embedding as e', 'e.digital_object_id', '=', 'd.id')
            ->where(function ($q) use ($model, $maxAttempts) {
                $q->whereNull('e.digital_object_id')
                    ->orWhere('e.model', '!=', $model)
                    ->orWhere(function ($c) {
                        $c->whereNotNull('d.checksum')->whereNotNull('e.checksum')->whereColumn('e.checksum', '!=', 'd.checksum');
                    })
                    ->orWhere(function ($f) use ($maxAttempts) {
                        $f->where('e.status', self::STATUS_FAILED)->where('e.attempts', '<', $maxAttempts);
                    });
            });
    }

    /**
     * Embed one master image and upsert its point. Returns a STATUS_* value,
     * or UNAVAILABLE when the gateway or Qdrant could not take it (nothing is
     * recorded, so the next sweep tries again).
     */
    public function indexDigitalObject(int $digitalObjectId): string
    {
        $do = DB::table('digital_object')->where('id', $digitalObjectId)->whereNull('parent_id')
            ->first(['id', 'object_id', 'name', 'path', 'mime_type', 'checksum']);
        if (! $do || (int) $do->object_id <= 1) {
            return self::STATUS_SKIPPED;
        }
        $ioId = (int) $do->object_id;

        // #1406 / #1388 Principle 5: community-protocol records stay out of the vector store.
        if ($this->isCommunityRestricted($ioId)) {
            $this->deletePoints([$digitalObjectId]);

            return $this->record($do, self::STATUS_SKIPPED, 'community protocol');
        }

        $bytes = $this->readImage($do);
        if ($bytes === null) {
            return $this->record($do, self::STATUS_SKIPPED, 'no readable image file within the size limit');
        }

        $vec = $this->embedImageBytes($bytes);
        if ($vec === null) {
            return $this->unavailable ? self::UNAVAILABLE : $this->record($do, self::STATUS_FAILED, $this->lastError);
        }

        try {
            $this->ensureCollection(count($vec));
            $this->upsert($do, $vec);
        } catch (RuntimeException $e) {
            Log::warning('[image-embed] '.$e->getMessage());
            $this->lastError = $e->getMessage();

            return self::UNAVAILABLE;
        }

        return $this->record($do, self::STATUS_EMBEDDED, null, count($vec));
    }

    private function record(object $do, string $status, ?string $error, ?int $dims = null): string
    {
        $prev = DB::table('ahg_image_embedding')->where('digital_object_id', $do->id)->first(['attempts', 'model']);
        $attempts = $status === self::STATUS_FAILED
            ? (($prev && $prev->model === $this->imageModel()) ? (int) $prev->attempts + 1 : 1)
            : 0;
        DB::table('ahg_image_embedding')->updateOrInsert(['digital_object_id' => $do->id], [
            'information_object_id' => (int) $do->object_id,
            'model' => $this->imageModel(),
            'dimensions' => $dims,
            'status' => $status,
            'attempts' => min(255, $attempts),
            'error' => $error ? mb_substr($error, 0, 255) : null,
            'checksum' => $do->checksum ?: null,
            'embedded_at' => $status === self::STATUS_EMBEDDED ? now() : null,
            'updated_at' => now(),
        ]);

        return $status;
    }

    /**
     * The reference copy when there is one (smaller, always a web format),
     * else the master. Decrypts files encrypted at rest.
     */
    protected function readImage(object $master): ?string
    {
        $maxBytes = max(1, (int) ($this->rawSetting('ahg_discovery_image_max_mb') ?: 25)) * 1024 * 1024;
        $ref = DB::table('digital_object')->where('parent_id', $master->id)
            ->where('usage_id', DigitalObjectService::USAGE_REFERENCE)->first(['id', 'object_id', 'name', 'path']);
        $enc = new EncryptionService;

        foreach (array_filter([$ref, $master]) as $row) {
            if (! isset($row->object_id)) {
                $row->object_id = $master->object_id;
            }
            $file = DigitalObjectService::resolveDiskPath($row);
            if ($file === null || @filesize($file) > $maxBytes) {
                continue;
            }
            $bytes = $enc->isFileEncrypted($file) ? $enc->streamFileDecrypted($file) : @file_get_contents($file);
            if (is_string($bytes) && $bytes !== '') {
                return $bytes;
            }
        }

        return null;
    }

    protected function isCommunityRestricted(int $ioId): bool
    {
        if ($this->communityRestricted === null) {
            try {
                $this->communityRestricted = array_fill_keys(\AhgCore\Services\TermProtocolService::restrictedRecordIds(), true);
            } catch (Throwable $e) {
                $this->communityRestricted = [];
            }
        }

        return isset($this->communityRestricted[$ioId]);
    }

    /* ------------------------------------------------------------------ */
    /*  Qdrant                                                             */
    /* ------------------------------------------------------------------ */

    public function collection(): string
    {
        return $this->rawSetting('ahg_discovery_image_collection') ?: 'archive_images';
    }

    /** Create the collection at this size, or refuse when it exists at another size. */
    public function ensureCollection(int $size): void
    {
        $c = $this->collection();
        if (($this->ensured[$c] ?? null) === $size) {
            return;
        }
        $resp = $this->qdrant('get', '/collections/'.rawurlencode($c));
        if ($resp->successful()) {
            $have = $resp->json('result.config.params.vectors.size');
            if ($have !== null && (int) $have !== $size) {
                throw new RuntimeException("Qdrant collection {$c} holds {$have}-dimension vectors but the embedder returns {$size}; rebuild it with ahg:image-embed --recreate");
            }
        } elseif ($resp->status() === 404) {
            $made = $this->qdrant('put', '/collections/'.rawurlencode($c), ['vectors' => ['size' => $size, 'distance' => 'Cosine']]);
            if (! $made->successful()) {
                throw new RuntimeException("Could not create Qdrant collection {$c} (HTTP {$made->status()})");
            }
        } else {
            throw new RuntimeException("Qdrant unavailable (HTTP {$resp->status()})");
        }
        $this->ensured[$c] = $size;
    }

    /** Drop the collection and forget every ledger row, ready for a full rebuild. */
    public function recreate(): void
    {
        $c = $this->collection();
        $resp = $this->qdrant('delete', '/collections/'.rawurlencode($c));
        if (! $resp->successful() && $resp->status() !== 404) {
            throw new RuntimeException("Could not drop Qdrant collection {$c} (HTTP {$resp->status()})");
        }
        unset($this->ensured[$c]);
        DB::table('ahg_image_embedding')->delete();
    }

    /** @param int[] $digitalObjectIds */
    public function deletePoints(array $digitalObjectIds): void
    {
        if ($digitalObjectIds === []) {
            return;
        }
        try {
            $this->qdrant('post', '/collections/'.rawurlencode($this->collection()).'/points/delete?wait=true',
                ['points' => array_values(array_map('intval', $digitalObjectIds))]);
        } catch (RuntimeException $e) {
            // Qdrant down: the point stays until the next rebuild; search still gates it.
        }
    }

    protected function upsert(object $do, array $vec): void
    {
        $meta = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 'io.id')->where('i.culture', '=', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.id', (int) $do->object_id)
            ->first(['i.title', 'slug.slug']);
        $slug = $meta->slug ?? null;

        $resp = $this->qdrant('put', '/collections/'.rawurlencode($this->collection()).'/points?wait=true', ['points' => [[
            'id' => (int) $do->id,
            'vector' => $vec,
            'payload' => [
                'database' => DB::connection()->getDatabaseName(),
                'information_object_id' => (int) $do->object_id,
                'digital_object_id' => (int) $do->id,
                'object_id' => (int) $do->object_id, // older consumers read this key
                'title' => $meta->title ?? null,
                'slug' => $slug,
                'entity_iri' => $slug ? rtrim((string) config('ric.base_uri', 'https://ric.theahg.co.za/ric'), '/').'/informationobject/'.$slug : null,
                'mime_type' => $do->mime_type,
                'model' => $this->imageModel(),
            ],
        ]]]);
        if (! $resp->successful()) {
            throw new RuntimeException("Qdrant upsert failed (HTTP {$resp->status()})");
        }
    }

    protected function qdrant(string $method, string $path, ?array $body = null): Response
    {
        $url = rtrim($this->rawSetting('semantic_qdrant_url') ?: 'http://localhost:6333', '/').$path;
        try {
            $req = Http::acceptJson()->timeout(30)->connectTimeout(3);

            return $body === null ? $req->{$method}($url) : $req->{$method}($url, $body);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Qdrant unreachable');
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Settings                                                           */
    /* ------------------------------------------------------------------ */

    public function imageModel(): string
    {
        return $this->rawSetting('ahg_discovery_image_embed_model') ?: 'nomic-embed-vision-v1.5';
    }

    /**
     * The gateway base. An ahg_discovery_image_embed_url override is honoured
     * only when it is not a raw GPU node address, so a stale row can never
     * wire Heratio straight to a node port.
     */
    public function gatewayBase(): string
    {
        $override = (string) $this->rawSetting('ahg_discovery_image_embed_url');
        if ($override !== '' && ! self::looksLikeNode($override)) {
            return rtrim($override, '/');
        }

        return rtrim(AiServicesSettings::apiUrl() ?: 'https://ai.theahg.co.za/ai/v1', '/');
    }

    public static function looksLikeNode(string $url): bool
    {
        return (bool) preg_match('~:(11434|5004|5006|8011)\b|://(?:127\.0\.0\.1|localhost|192\.168\.|10\.|172\.(?:1[6-9]|2\d|3[01])\.)~i', $url);
    }

    /** The stored value ('' included), or null when the row is missing. */
    protected function rawSetting(string $key): ?string
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');

            return $v === null ? null : (string) $v;
        } catch (Throwable $e) {
            return null;
        }
    }
}
