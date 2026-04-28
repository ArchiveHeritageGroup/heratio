<?php

/**
 * QdrantIndexCommand — rebuilds the Qdrant vector index from the
 * information_object catalogue.
 *
 * For each row:
 *   1. Build a text representation: title + scope_and_content (+ creator names).
 *   2. Send the text to the embedding service (Ollama by default) for a vector.
 *   3. Upsert the vector + payload (slug, title, parent_id, has_scope, etc.)
 *      into the configured Qdrant collection.
 *
 * Configurable via flags or ahg_settings:
 *   --db-name=atom              source database to read from
 *   --collection=anc_records    Qdrant collection to write to
 *   --reset                     recreate the collection (drops + recreates with the
 *                               vector size announced by the embedding model)
 *   --offset / --limit          pagination for resumable runs
 *   --batch=64                  upsert batch size (Qdrant /points)
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class QdrantIndexCommand extends Command
{
    protected $signature = 'ahg:qdrant-index
        {--db-name=                 : Source database to read from (defaults to current connection)}
        {--collection=anc_records   : Qdrant collection name}
        {--reset                    : Drop + recreate the collection}
        {--offset=0                 : Skip the first N rows}
        {--limit=0                  : Stop after N rows (0 = unbounded)}
        {--batch=64                 : Upsert batch size}
        {--dry-run                  : Embed + count, but do not write to Qdrant}';

    protected $description = 'Rebuild Qdrant vector index from information_object rows';

    public function handle(): int
    {
        $sourceDb     = (string) ($this->option('db-name') ?: DB::connection()->getDatabaseName());
        $collection   = (string) $this->option('collection');
        $offset       = (int) $this->option('offset');
        $limit        = (int) $this->option('limit');
        $batchSize    = max(1, (int) $this->option('batch'));
        $dryRun       = (bool) $this->option('dry-run');

        $embeddingUrl   = $this->setting('semantic_embedding_url',   'http://192.168.0.78:11434');
        $embeddingModel = $this->setting('semantic_embedding_model', 'all-minilm');
        $qdrantUrl      = $this->setting('semantic_qdrant_url',      'http://localhost:6333');
        $cultureDefault = 'en';

        $this->info("Qdrant index rebuild");
        $this->line("  source DB:     {$sourceDb}");
        $this->line("  collection:    {$collection}");
        $this->line("  embedding:     {$embeddingUrl} ({$embeddingModel})");
        $this->line("  qdrant:        {$qdrantUrl}");
        $this->line("  batch / dry?:  {$batchSize} / " . ($dryRun ? 'YES' : 'no'));

        // Pre-flight — confirm both backends are reachable.
        if (! $this->ping($embeddingUrl . '/api/version', 2000)) {
            $this->error('Embedding service unreachable: ' . $embeddingUrl);
            return self::FAILURE;
        }
        if (! $this->ping($qdrantUrl . '/collections', 2000)) {
            $this->error('Qdrant unreachable: ' . $qdrantUrl);
            return self::FAILURE;
        }

        // Determine vector size by embedding a probe string.
        $probe = $this->embed($embeddingUrl, $embeddingModel, 'probe');
        if ($probe === null) {
            $this->error('Probe embedding failed — check the embedding model is pulled.');
            return self::FAILURE;
        }
        $vectorSize = count($probe);
        $this->line("  vector size:   {$vectorSize}");

        // Reset collection if requested.
        if ($this->option('reset') && ! $dryRun) {
            $this->ensureCollection($qdrantUrl, $collection, $vectorSize, true);
            $this->line("  collection reset (vector_size={$vectorSize}, distance=Cosine)");
        } else {
            $this->ensureCollection($qdrantUrl, $collection, $vectorSize, false);
        }

        // Walk rows.
        $cn   = DB::connection();
        $totalRows = (int) $cn->table('information_object')
            ->where('id', '!=', 1)
            ->count();
        $this->line("  total IOs:     " . number_format($totalRows));

        $cap = $limit > 0 ? min($limit, max(0, $totalRows - $offset)) : ($totalRows - $offset);
        if ($cap <= 0) {
            $this->info('Nothing to do (offset/limit produces empty range).');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($cap);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%');
        $bar->setMessage('starting');
        $bar->start();

        $batch = [];
        $indexed = 0;
        $skipped = 0;
        $errors  = 0;
        $rowOffset = $offset;
        $remaining = $cap;

        $chunkSize = 500;
        while ($remaining > 0) {
            $take = min($chunkSize, $remaining);
            $rows = $cn->table('information_object as io')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($cultureDefault) {
                    $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $cultureDefault);
                })
                ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
                ->where('io.id', '!=', 1)
                ->orderBy('io.id')
                ->offset($rowOffset)
                ->limit($take)
                ->select(
                    'io.id', 'io.parent_id',
                    'ioi.title', 'ioi.scope_and_content',
                    'slug.slug'
                )
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $bar->setMessage('id=' . $row->id);

                $text = $this->buildText($row);
                if ($text === '') {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $vec = $this->embed($embeddingUrl, $embeddingModel, $text);
                if ($vec === null) {
                    $errors++;
                    $bar->advance();
                    continue;
                }

                $batch[] = [
                    'id'      => (int) $row->id,
                    'vector'  => $vec,
                    'payload' => [
                        'database'      => $sourceDb,
                        'title'         => $row->title,
                        'slug'          => $row->slug,
                        'parent_id'     => $row->parent_id ? (int) $row->parent_id : null,
                        'has_scope'     => ! empty($row->scope_and_content),
                        'has_transcript'=> false,
                    ],
                ];

                if (! $dryRun && count($batch) >= $batchSize) {
                    if ($this->upsert($qdrantUrl, $collection, $batch)) {
                        $indexed += count($batch);
                    } else {
                        $errors += count($batch);
                    }
                    $batch = [];
                }
                $bar->advance();
            }

            $rowOffset += $rows->count();
            $remaining -= $rows->count();
        }

        // Flush.
        if (! $dryRun && ! empty($batch)) {
            if ($this->upsert($qdrantUrl, $collection, $batch)) {
                $indexed += count($batch);
            } else {
                $errors += count($batch);
            }
        } elseif ($dryRun) {
            $indexed += count($batch);
            $batch = [];
        }

        $bar->setMessage('done');
        $bar->finish();
        $this->newLine();

        $this->info(sprintf('Indexed: %s, skipped: %s, errors: %s', number_format($indexed), number_format($skipped), number_format($errors)));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function buildText(object $row): string
    {
        $parts = [];
        if (! empty($row->title)) {
            $parts[] = (string) $row->title;
        }
        if (! empty($row->scope_and_content)) {
            // Strip simple HTML/PHP tags and collapse whitespace; cap to ~3000 chars.
            $clean = preg_replace('/\s+/', ' ', strip_tags((string) $row->scope_and_content));
            $parts[] = mb_substr($clean, 0, 3000);
        }
        return trim(implode("\n\n", $parts));
    }

    protected function ensureCollection(string $url, string $collection, int $vectorSize, bool $reset): void
    {
        $exists = $this->httpJson('GET', $url . '/collections/' . urlencode($collection)) !== null;
        if ($exists && $reset) {
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
        $resp = $this->httpJson('PUT',
            $url . '/collections/' . urlencode($collection) . '/points?wait=true',
            ['points' => $batch]
        );
        if ($resp === null) {
            return false;
        }
        return ($resp['status'] ?? '') === 'ok' || ! empty($resp['result']);
    }

    protected function embed(string $baseUrl, string $model, string $text): ?array
    {
        $resp = $this->httpJson('POST', rtrim($baseUrl, '/') . '/api/embeddings', [
            'model'  => $model,
            'prompt' => $text,
        ], 30000);
        if (! is_array($resp) || empty($resp['embedding']) || ! is_array($resp['embedding'])) {
            return null;
        }
        return array_map('floatval', $resp['embedding']);
    }

    protected function ping(string $url, int $timeoutMs): bool
    {
        return $this->httpJson('GET', $url, null, $timeoutMs) !== null;
    }

    protected function httpJson(string $method, string $url, ?array $body = null, int $timeoutMs = 30000): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => max(500, $timeoutMs),
            CURLOPT_CONNECTTIMEOUT_MS => max(500, min(3000, $timeoutMs)),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false || $code < 200 || $code >= 300) {
            if ($err) {
                Log::debug("Qdrant index http {$method} {$url}: {$err}");
            }
            return null;
        }
        $decoded = json_decode((string) $resp, true);
        return is_array($decoded) ? $decoded : null;
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
