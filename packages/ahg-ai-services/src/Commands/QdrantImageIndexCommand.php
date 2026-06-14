<?php

/**
 * QdrantImageIndexCommand - CLIP image-embedding index for visual search.
 *
 * NEEDS-DECISION (#1268): there is NO CLIP / image-embedding service in
 * Heratio and NO AHG gateway route for image embeddings. The working
 * QdrantIndexCommand handles TEXT embeddings (all-minilm via the gateway's
 * /ollama passthrough); there is no equivalent vision pathway, and standing up
 * one would require a CLIP model behind the gateway plus a gateway route.
 *
 * The standing AHG gateway rule forbids an application from calling a GPU node
 * port directly, so this command must NOT be hand-wired to a node. Rather than
 * fake a successful index (the original stub printed "Done." and returned 0),
 * it now fails loudly with a clear TODO and a NON-ZERO exit.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class QdrantImageIndexCommand extends Command
{
    protected $signature = 'ahg:qdrant-image-index {--db-name=archive} {--db-user=root} {--db-password=} {--collection=} {--atom-root=} {--reset} {--offset=0} {--limit=0}';
    protected $description = 'CLIP image embeddings index';

    public function handle(): int
    {
        $this->error('ahg:qdrant-image-index is NOT implemented (NEEDS-DECISION, #1268).');
        $this->warn('Blocked on: a CLIP / image-embedding model exposed via an AHG gateway route.');
        $this->line('For TEXT semantic indexing use ahg:qdrant-index (already gateway-routed).');
        $this->line('No image index was built.');

        // TODO(#1268): implement once the gateway exposes a CLIP image-embedding
        // route. The vectors must be obtained through ai.theahg.co.za/ai/v1/...
        // - never a direct GPU node port.
        return self::FAILURE;
    }
}
