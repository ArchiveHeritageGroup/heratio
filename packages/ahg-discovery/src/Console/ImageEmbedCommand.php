<?php

/**
 * ahg:image-embed - heratio#1272. Embeds master images for image search,
 * through the AHG AI gateway, into the Qdrant image collection.
 *
 * Default run = the pending sweep: only images never embedded with the
 * current model, changed since, or failed fewer than --max-attempts times.
 * Scheduled every 10 minutes in GPU hours, it is how a newly ingested image
 * (upload, ingest wizard, scanner, API) becomes searchable, whatever path
 * created it.
 *
 *   php artisan ahg:image-embed                 pending sweep
 *   php artisan ahg:image-embed --limit=50      at most 50 images
 *   php artisan ahg:image-embed --io=123        one record's images, embedded again
 *   php artisan ahg:image-embed --recreate      drop the collection and rebuild everything
 *
 * A single probe call goes first; when the gateway cannot embed images
 * (no node serving the model, GPUs off for the night) nothing is touched
 * and the run ends quietly, so the next sweep simply picks up.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgDiscovery\Console;

use AhgDiscovery\Services\ImageEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImageEmbedCommand extends Command
{
    protected $signature = 'ahg:image-embed
        {--limit=0 : Stop after N images (0 = no limit)}
        {--io= : Embed the images of this one record again}
        {--max-attempts=3 : Give up on an image after this many failed embeds}
        {--recreate : Drop the Qdrant collection and the ledger, then embed every image}';

    protected $description = 'Embed images through the AI gateway for image-to-image and text-to-image search';

    public function handle(ImageEmbeddingService $svc): int
    {
        $this->line('Image embedding: '.$svc->gatewayBase().'/embed/image ('.$svc->imageModel().') -> Qdrant '.$svc->collection());

        if (! $svc->probe()) {
            $this->warn('The gateway image embedder is not available ('.($svc->lastError ?: 'no reply').'). Nothing done; the next run will try again.');

            return self::SUCCESS;
        }

        if ($this->option('recreate')) {
            try {
                $svc->recreate();
            } catch (RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->line('Collection and ledger cleared.');
        }

        $limit = max(0, (int) $this->option('limit'));
        $io = (int) $this->option('io');
        $query = $io > 0
            ? $svc->candidates()->where('d.object_id', $io)
            : $svc->pending(max(1, (int) $this->option('max-attempts')));

        $counts = [ImageEmbeddingService::STATUS_EMBEDDED => 0, ImageEmbeddingService::STATUS_SKIPPED => 0, ImageEmbeddingService::STATUS_FAILED => 0];
        $done = 0;
        $lastId = 0;
        while (true) {
            $ids = (clone $query)->where('d.id', '>', $lastId)->orderBy('d.id')->limit(200)->pluck('d.id');
            if ($ids->isEmpty()) {
                break;
            }
            foreach ($ids as $id) {
                $lastId = (int) $id;
                $status = $svc->indexDigitalObject($lastId);
                if ($status === ImageEmbeddingService::UNAVAILABLE) {
                    $this->warn("Stopped at digital object {$lastId}: ".($svc->lastError ?: 'gateway or Qdrant unavailable').'.');
                    break 2;
                }
                $counts[$status]++;
                if ($limit > 0 && ++$done >= $limit) {
                    break 2;
                }
            }
        }

        $this->info(sprintf('Embedded %d, skipped %d, failed %d. Ledger: %d images embedded.',
            $counts['embedded'], $counts['skipped'], $counts['failed'],
            DB::table('ahg_image_embedding')->where('status', ImageEmbeddingService::STATUS_EMBEDDED)->count()));

        return self::SUCCESS;
    }
}
