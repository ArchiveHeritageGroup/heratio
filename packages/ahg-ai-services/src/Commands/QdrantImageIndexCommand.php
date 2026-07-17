<?php

/**
 * QdrantImageIndexCommand - multimodal image-embedding index for visual search.
 *
 * #1272: builds the `archive_images` Qdrant collection from IMAGE digital_object
 * rows. For each image:
 *   1. Resolve the real file bytes from the multi-root storage layout
 *      (storage_path / uploads_path / AtoM mirror) - the same resolution logic
 *      AiController::getDigitalObjectPath() uses.
 *   2. base64-encode the bytes and POST them to the multimodal embedder behind
 *      the AHG AI gateway per the #1272 embed contract:
 *          POST {embed_url}/embed/image
 *          { "model": "nomic-embed-vision-v1.5", "image": "<base64-bytes>" }
 *          Authorization: Bearer <ahg_live key>
 *      Response: { "embedding": [768 floats] }.
 *   3. Upsert the 768-dim vector + payload into the configured Qdrant
 *      collection (768-dim, Cosine) - mirrors the upsert in QdrantIndexCommand.
 *
 * The gateway is the only sanctioned door (standing AHG rule): embeddings route
 * through ai.theahg.co.za, never a direct GPU node port. The gateway route (B)
 * and the GPU service (C) honouring this contract are built separately.
 *
 * Flags:
 *   --collection=        Qdrant collection (default ahg_discovery_image_collection / archive_images)
 *   --limit=0            Stop after N images (0 = unbounded)
 *   --batch=32           Qdrant upsert batch size
 *   --offset=0           Skip the first N image rows
 *   --recreate           Drop + recreate the collection at 768/Cosine
 *   --db-name=           Source database (defaults to current connection)
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Support\AiServicesSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QdrantImageIndexCommand extends Command
{
    /** Image extensions considered indexable (mirrors AiController::getDigitalObjectPath). */
    protected const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'];

    /** Default vector geometry for the nomic-embed-vision-v1.5 collection (#1272). */
    protected const VECTOR_DIM = 768;

    protected $signature = 'ahg:qdrant-image-index
        {--db-name=          : Source database to read from (defaults to current connection)}
        {--collection=       : Qdrant collection (default: ahg_discovery_image_collection / archive_images)}
        {--limit=0           : Stop after N images (0 = unbounded)}
        {--offset=0          : Skip the first N image rows}
        {--batch=32          : Upsert batch size}
        {--recreate          : Drop + recreate the collection at 768/Cosine}';

    protected $description = 'Build the Qdrant image-embedding index (nomic-embed-vision-v1.5, 768/Cosine) for visual search';

    public function handle(): int
    {
        $sourceDb   = (string) ($this->option('db-name') ?: DB::connection()->getDatabaseName());
        $collection = (string) ($this->option('collection') ?: $this->setting('ahg_discovery_image_collection', 'archive_images'));
        $limit      = (int) $this->option('limit');
        $offset     = (int) $this->option('offset');
        $batchSize  = max(1, (int) $this->option('batch'));

        $embedBase  = $this->embedBase();
        $embedModel = $this->setting('ahg_discovery_image_embed_model', 'nomic-embed-vision-v1.5');
        $embedKey   = $this->resolveApiKey();
        $qdrantUrl  = rtrim($this->setting('semantic_qdrant_url', 'http://localhost:6333'), '/');

        $this->info('Qdrant image index build (#1272)');
        $this->line("  source DB:   {$sourceDb}");
        $this->line("  collection:  {$collection}");
        $this->line("  embed:       {$embedBase}/embed/image ({$embedModel}) [gateway]");
        $this->line("  qdrant:      {$qdrantUrl}");
        $this->line('  vector:      ' . self::VECTOR_DIM . ' / Cosine');
        $this->line("  batch:       {$batchSize}");

        // Qdrant must be reachable before we waste embed calls.
        if ($this->httpJson('GET', $qdrantUrl . '/collections') === null) {
            $this->error('Qdrant unreachable: ' . $qdrantUrl);
            return self::FAILURE;
        }

        // Ensure collection exists at 768/Cosine (recreate if asked).
        $this->ensureCollection($qdrantUrl, $collection, self::VECTOR_DIM, (bool) $this->option('recreate'));
        if ($this->option('recreate')) {
            $this->line("  collection recreated (vector=" . self::VECTOR_DIM . ', distance=Cosine)');
        }

        $cn = DB::connection();

        // Master digital objects with a name + path. We do extension filtering in
        // PHP so the IMAGE_EXTS list stays the single source of truth.
        $base = $cn->table('digital_object as dobj')
            ->whereNull('dobj.parent_id')
            ->whereNotNull('dobj.path')
            ->whereNotNull('dobj.name')
            ->where('dobj.object_id', '!=', 1)
            ->orderByDesc('dobj.id');

        $totalRows = (int) (clone $base)->count();
        $this->line('  master DOs:  ' . number_format($totalRows));

        $bar = $this->output->createProgressBar($totalRows);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%');
        $bar->setMessage('starting');
        $bar->start();

        $batch     = [];
        $indexed   = 0;
        $skipped   = 0;
        $errors    = 0;
        $seen      = 0;        // image rows seen (post extension filter)
        $rowOffset = 0;
        $chunkSize = 500;

        // #1406 P3 - AI fence (#1388 Principle 5): never embed images belonging to
        // a community-restricted record into the vision/image vector store.
        $restrictedSet = array_flip(\AhgCore\Services\TermProtocolService::restrictedRecordIds());

        while (true) {
            $rows = (clone $base)
                ->offset($rowOffset)
                ->limit($chunkSize)
                ->get(['dobj.id', 'dobj.object_id', 'dobj.name', 'dobj.path', 'dobj.mime_type']);

            if ($rows->isEmpty()) {
                break;
            }
            $rowOffset += $rows->count();

            foreach ($rows as $row) {
                $bar->advance();

                // #1406 P3 - skip images of community-restricted records.
                if (isset($restrictedSet[(int) $row->object_id])) {
                    $skipped++;
                    continue;
                }

                $ext = strtolower(pathinfo((string) $row->name, PATHINFO_EXTENSION));
                if (! in_array($ext, self::IMAGE_EXTS, true)) {
                    continue; // not an image - silently pass over (not a skip)
                }

                // Honour --offset over the IMAGE stream, not the raw DO stream.
                if ($seen < $offset) {
                    $seen++;
                    continue;
                }

                // Stop once we have processed --limit images.
                if ($limit > 0 && ($seen - $offset) >= $limit) {
                    $rows = collect(); // force outer break
                    break;
                }
                $seen++;

                $bar->setMessage('obj=' . $row->object_id);

                $file = $this->resolveImagePath((string) $row->path, (string) $row->name);
                if ($file === null) {
                    $skipped++;
                    Log::debug("image-index skip (no file) object_id={$row->object_id} name={$row->name}");
                    continue;
                }

                $bytes = @file_get_contents($file);
                if ($bytes === false || $bytes === '') {
                    $skipped++;
                    Log::debug("image-index skip (unreadable) {$file}");
                    continue;
                }

                $vec = $this->embedImage($embedBase, $embedModel, $bytes, $embedKey);
                if ($vec === null) {
                    $errors++;
                    Log::debug("image-index embed failed object_id={$row->object_id}");
                    continue;
                }
                if (count($vec) !== self::VECTOR_DIM) {
                    $errors++;
                    Log::warning('image-index unexpected vector dim ' . count($vec) . " (want " . self::VECTOR_DIM . ") object_id={$row->object_id}");
                    continue;
                }

                $meta = $this->ioMeta($cn, (int) $row->object_id);

                $batch[] = [
                    'id'     => (int) $row->id, // digital_object.id is the stable point id
                    'vector' => $vec,
                    'payload' => [
                        'database'             => $sourceDb,
                        'information_object_id' => (int) $row->object_id,
                        'digital_object_id'    => (int) $row->id,
                        'object_id'            => (int) $row->object_id, // legacy payload key (existing consumers)
                        'title'                => $meta['title'] ?? null,
                        'slug'                 => $meta['slug'] ?? null,
                        // Canonical RiC entity IRI of the parent information object
                        // (governance pin / #1319) - the join key into the Fuseki
                        // graph for GraphRAG grounding (#1320). An image hit thus
                        // resolves to its record's authority node.
                        'entity_iri'           => ($meta['slug'] ?? null)
                            ? rtrim(config('ric.base_uri', 'https://ric.theahg.co.za/ric'), '/') . '/informationobject/' . $meta['slug']
                            : null,
                        'thumb_url'            => $meta['slug'] ? '/' . $meta['slug'] : null,
                        'mime_type'            => $row->mime_type ?: ('image/' . ($ext === 'jpg' ? 'jpeg' : $ext)),
                    ],
                ];

                if (count($batch) >= $batchSize) {
                    if ($this->upsert($qdrantUrl, $collection, $batch)) {
                        $indexed += count($batch);
                    } else {
                        $errors += count($batch);
                    }
                    $batch = [];
                }
            }

            if ($rows->isEmpty()) {
                break;
            }
            if ($limit > 0 && ($seen - $offset) >= $limit) {
                break;
            }
        }

        // Flush tail.
        if (! empty($batch)) {
            if ($this->upsert($qdrantUrl, $collection, $batch)) {
                $indexed += count($batch);
            } else {
                $errors += count($batch);
            }
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine();

        $this->info(sprintf(
            'Image index: indexed %s, skipped %s, errors %s (collection=%s)',
            number_format($indexed),
            number_format($skipped),
            number_format($errors),
            $collection
        ));

        return self::SUCCESS;
    }

    /* -------------------------------------------------------------------- */

    /**
     * Resolve image bytes via the multi-root storage layout.
     *
     * Mirrors AiController::getDigitalObjectPath() (#1272): the stored path
     * already begins with "/uploads/", so the NAS storage_path is the primary
     * base; uploads_path and the AtoM mirror are fallbacks for legacy/mirrored
     * data.
     */
    protected function resolveImagePath(string $path, string $name): ?string
    {
        $rel = ltrim($path, '/') . $name;
        $candidates = [
            rtrim((string) config('heratio.storage_path'), '/') . '/' . $rel,
            rtrim((string) config('heratio.uploads_path'), '/') . '/' . $rel,
            '/usr/share/nginx/archive/' . $rel,
            '/usr/share/nginx/archive/uploads/'
                . ltrim(str_replace('/uploads/', '', $path), '/') . $name,
        ];
        foreach ($candidates as $full) {
            if (is_file($full)) {
                return $full;
            }
        }

        return null;
    }

    /** Title + slug for the owning information_object (denormalised into the payload). */
    protected function ioMeta($cn, int $objectId): array
    {
        try {
            $row = $cn->table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', 'en');
                })
                ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
                ->where('io.id', '=', $objectId)
                ->select('ioi.title', 'slug.slug')
                ->first();

            return [
                'title' => $row->title ?? null,
                'slug'  => $row->slug ?? null,
            ];
        } catch (Throwable $e) {
            return ['title' => null, 'slug' => null];
        }
    }

    /**
     * Call the multimodal image-embedding endpoint per the #1272 contract:
     *   POST {base}/embed/image  { "model": ..., "image": "<base64>" }
     *   -> { "embedding": [768 floats] }
     *
     * @return array<int, float>|null
     */
    protected function embedImage(string $base, string $model, string $bytes, ?string $key): ?array
    {
        $payload = json_encode([
            'model' => $model,
            'image' => base64_encode($bytes),
        ]);

        $resp = $this->httpRaw('POST', rtrim($base, '/') . '/embed/image', $payload, 60000, $key);
        if ($resp === null) {
            return null;
        }
        $decoded = json_decode($resp, true);
        if (! is_array($decoded) || empty($decoded['embedding']) || ! is_array($decoded['embedding'])) {
            return null;
        }

        return array_map('floatval', $decoded['embedding']);
    }

    protected function ensureCollection(string $url, string $collection, int $vectorSize, bool $recreate): void
    {
        $exists = $this->httpJson('GET', $url . '/collections/' . urlencode($collection)) !== null;
        if ($exists && $recreate) {
            $this->httpJson('DELETE', $url . '/collections/' . urlencode($collection));
            $exists = false;
        }
        if (! $exists) {
            $this->httpJson('PUT', $url . '/collections/' . urlencode($collection), [
                'vectors' => ['size' => $vectorSize, 'distance' => 'Cosine'],
            ]);
        }
    }

    protected function upsert(string $url, string $collection, array $batch): bool
    {
        $resp = $this->httpJson(
            'PUT',
            $url . '/collections/' . urlencode($collection) . '/points?wait=true',
            ['points' => $batch]
        );
        if ($resp === null) {
            return false;
        }

        return ($resp['status'] ?? '') === 'ok' || ! empty($resp['result']);
    }

    /**
     * Resolve the image-embed base URL (#1272).
     *
     * Default + canonical is the AHG gateway base. An ahg_discovery_image_embed_url
     * override is honoured only if it is NOT a raw GPU node URL (standing rule:
     * never wire an app direct to a node port). The test harness overrides this
     * setting to point at a local stub, which is allowed because the stub is a
     * localhost CPU service used for the round-trip proof only.
     */
    protected function embedBase(): string
    {
        $override = (string) $this->setting('ahg_discovery_image_embed_url', '');
        if ($override !== '') {
            return rtrim($override, '/');
        }

        return rtrim((AiServicesSettings::apiUrl() ?: 'https://ai.theahg.co.za/ai/v1'), '/');
    }

    /**
     * Resolve the gateway API key the same way ImageSearchStrategy / NER / HTR do.
     */
    protected function resolveApiKey(): ?string
    {
        try {
            $key = DB::table('ahg_ner_settings')
                ->where('setting_key', 'api_key')
                ->value('setting_value');
            if ($key !== null && $key !== '') {
                return (string) $key;
            }

            $key = DB::table('ahg_ai_settings')
                ->where('feature', 'general')
                ->where('setting_key', 'api_key')
                ->value('setting_value');
            if ($key !== null && $key !== '') {
                return (string) $key;
            }
        } catch (Throwable $e) {
            // settings tables absent - fall through.
        }

        return AiServicesSettings::apiKey();
    }

    /** JSON request returning a decoded array (or null on non-2xx / transport error). */
    protected function httpJson(string $method, string $url, ?array $body = null, int $timeoutMs = 30000, ?string $key = null): ?array
    {
        $raw = $this->httpRaw($method, $url, $body === null ? null : json_encode($body), $timeoutMs, $key);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Low-level request returning the raw response body (or null on non-2xx / transport error). */
    protected function httpRaw(string $method, string $url, ?string $body = null, int $timeoutMs = 30000, ?string $key = null): ?string
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($key !== null && $key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT_MS        => max(500, $timeoutMs),
            CURLOPT_CONNECTTIMEOUT_MS => max(500, min(3000, $timeoutMs)),
            CURLOPT_HTTPHEADER        => $headers,
            CURLOPT_CUSTOMREQUEST     => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code < 200 || $code >= 300) {
            if ($err) {
                Log::debug("image-index http {$method} {$url}: {$err}");
            }

            return null;
        }

        return (string) $resp;
    }

    protected function setting(string $key, string $default): string
    {
        try {
            $row = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            if ($row !== null && $row !== '') {
                return (string) $row;
            }
        } catch (Throwable $e) {
            // setting table may not exist
        }

        return $default;
    }
}
