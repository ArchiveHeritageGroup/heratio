<?php

/**
 * ahg:media-derivatives - make sure every audio and video master has a copy
 * the browser can play (MP4 H.264 480p / MP3). Masters whose only "reference"
 * is a placeholder icon, or that have none, are queued for the ffmpeg job
 * (GenerateMediaDerivativesJob), or converted straight away with --now.
 *
 * Runs nightly (01:30) with --missing, so older files and any conversion that
 * failed are caught without anyone noticing a video that will not play.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace AhgCore\Commands;

use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\MediaDerivativeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MediaDerivativesCommand extends Command
{
    protected $signature = 'ahg:media-derivatives
        {--missing : Only masters with no playable audio/video copy (the default)}
        {--limit=500 : At most this many masters per run}
        {--now : Convert straight away instead of queueing the job}
        {--dry-run : List them, change nothing}';

    protected $description = 'Give every audio/video master a browser-playable MP4/MP3 copy';

    public function handle(): int
    {
        $masters = self::missing((int) $this->option('limit'));
        $this->info($masters->count().' audio/video master(s) without a playable copy');
        foreach ($masters as $m) {
            if ($this->option('dry-run')) {
                $this->line("  {$m->id} {$m->mime_type} {$m->name}");

                continue;
            }
            if ($this->option('now')) {
                $made = MediaDerivativeService::generateForMaster((int) $m->id);
                $this->line("  {$m->id} {$m->name}: {$made} made");
            } else {
                \AhgCore\Jobs\GenerateMediaDerivativesJob::dispatch((int) $m->id);
            }
        }
        if (! $this->option('dry-run') && ! $this->option('now') && $masters->count()) {
            $this->info('Queued for the worker.');
        }

        return self::SUCCESS;
    }

    /** Audio/video masters with no reference copy that is itself audio/video. */
    public static function missing(int $limit = 500)
    {
        return DB::table('digital_object as m')
            ->whereNull('m.parent_id')
            // By the file's MIME type, not the media-type label: some 3D model parts
            // (.mtl, .obj) carry a video media type but are not media at all.
            ->where(fn ($q) => $q->where('m.mime_type', 'like', 'video/%')->orWhere('m.mime_type', 'like', 'audio/%')
                ->orWhere('m.mime_type', 'application/mxf'))
            ->whereNotExists(fn ($q) => $q->from('digital_object as d')->whereColumn('d.parent_id', 'm.id')
                ->where('d.usage_id', DigitalObjectService::USAGE_REFERENCE)
                ->where(fn ($w) => $w->where('d.mime_type', 'like', 'video/%')->orWhere('d.mime_type', 'like', 'audio/%')))
            ->orderBy('m.id')->limit(max(1, $limit))
            ->get(['m.id', 'm.name', 'm.mime_type']);
    }
}
