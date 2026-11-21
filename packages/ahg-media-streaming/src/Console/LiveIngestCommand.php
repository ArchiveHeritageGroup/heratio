<?php

/**
 * ahg:live-ingest {event} - the live stream worker (heratio#1743).
 *
 * Listens with ffmpeg as an RTMP server on the configured port (or --port) for
 * the event's stream key, writes live HLS and a full recording, marks the event
 * live on the first frames and ended when the source stops, then stores the
 * recording as the record's digital object. Blocks for the whole event;
 * Ctrl-C or SIGTERM ends the stream cleanly and still archives it.
 *
 * --archive: only store a recording left by a worker that died while live.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Console;

use AhgMediaStreaming\Services\LiveStreamService;
use Illuminate\Console\Command;

class LiveIngestCommand extends Command
{
    protected $signature = 'ahg:live-ingest {event : Live event id} {--port= : RTMP port (default: the live_ingest_port setting)} {--archive : Only add a leftover recording to the record}';

    protected $description = 'Run the RTMP live ingest for a live event: live HLS, recording, then archive to the record';

    public function handle(LiveStreamService $service): int
    {
        $id = (int) $this->argument('event');
        $say = fn (string $m) => $this->line($m);
        try {
            if ($this->option('archive')) {
                $service->archive($id, $say);

                return self::SUCCESS;
            }
            $port = $this->option('port') !== null ? (int) $this->option('port') : null;
            if ($port !== null && ($port < 1 || $port > 65535)) {
                $this->error('--port must be 1 to 65535.');

                return self::FAILURE;
            }
            $status = $service->run($id, $port, $say);
            $this->info("Live event #$id: $status.");

            return $status === LiveStreamService::FAILED ? self::FAILURE : self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
