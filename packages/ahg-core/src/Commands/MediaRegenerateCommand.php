<?php

/**
 * ahg:media-regenerate - make audio/video playback copies again from scratch:
 * the reference MP4/MP3, the poster or waveform and the HLS renditions
 * (encrypted with fresh keys when media_hls_encrypt is on, watermarked when
 * the video watermark applies). Old HLS segments, playlists and keys are
 * removed and no duplicate derivative rows are left. The master is never
 * written, and files the shared catalogue uses are refused.
 * See MediaRegenerateService.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Commands;

use AhgCore\Jobs\RegenerateMediaJob;
use AhgCore\Services\MediaRegenerateService;
use Illuminate\Console\Command;

class MediaRegenerateCommand extends Command
{
    protected $signature = 'ahg:media-regenerate
        {--id=* : master digital object ids}
        {--record=* : information object ids}
        {--all-video : every video master}
        {--dry-run : say what would be done, change nothing}
        {--queue : one queued job per master instead of running here}';

    protected $description = 'Make audio/video playback copies again (watermark, encrypted HLS) without duplicates';

    public function handle(): int
    {
        $ids = MediaRegenerateService::targets((array) $this->option('id'), (array) $this->option('record'), (bool) $this->option('all-video'));
        if ($ids === []) {
            $this->warn('No audio/video masters selected (use --id, --record or --all-video).');

            return self::SUCCESS;
        }
        $this->info(count($ids).' audio/video master(s) selected');

        if ($this->option('queue') && ! $this->option('dry-run')) {
            foreach ($ids as $id) {
                RegenerateMediaJob::dispatch($id);
            }
            $this->info(count($ids).' master(s) queued for the worker.');

            return self::SUCCESS;
        }

        $tally = [];
        foreach ($ids as $id) {
            $r = MediaRegenerateService::regenerate($id, (bool) $this->option('dry-run'));
            $tally[$r['status']] = ($tally[$r['status']] ?? 0) + 1;
            $this->line(sprintf('  %d %s: %s', $r['id'], $r['status'], $r['detail']));
        }
        ksort($tally);
        $this->info('Summary: '.implode(', ', array_map(fn ($k, $v) => "$v $k", array_keys($tally), $tally)));

        return isset($tally['failed']) ? self::FAILURE : self::SUCCESS;
    }
}
