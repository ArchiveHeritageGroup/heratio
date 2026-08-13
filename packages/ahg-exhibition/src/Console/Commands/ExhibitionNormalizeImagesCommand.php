<?php

/**
 * Make browser-renderable copies for objects hung in an exhibition space (#1139).
 *
 * The walkthrough can only draw jpg/png/gif/webp/bmp. An object whose only image
 * is a TIFF or RAW master falls through to a bare pedestal placeholder - it is
 * not invisible, but it is not the artwork either, and nothing on the page says
 * why. This command finds those objects and has an access copy made.
 *
 * It deliberately converts NOTHING itself. ahg-preservation already owns image
 * normalization: the `preservation_normalization_rule` registry ships an
 * image/tiff -> JPEG rule with purpose `access`, and NormalizationService writes
 * the result as a usage-141 reference derivative, which is precisely the usage
 * ExhibitionSpaceService::bestImageUrl ranks first. Adding a second converter
 * here would duplicate the tool handling, the timeouts, the PREMIS events and the
 * idempotency rules for no gain.
 *
 * The whole-repository equivalent already exists as
 * `ahg:normalize-existing --purpose=access --mime=image/tiff`. This command is
 * the curator-scoped version: fix the gallery I am building, not everything.
 *
 * ahg-preservation is a SOFT dependency - guarded by class_exists so this
 * no-ops with an explanation on an install that does not carry the package.
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

namespace AhgExhibition\Console\Commands;

use AhgExhibition\Services\ExhibitionSpaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExhibitionNormalizeImagesCommand extends Command
{
    protected $signature = 'ahg:exhibition-normalize-images
        {--space= : Limit to one exhibition space (slug or id). Default: every space}
        {--sync : Convert inline instead of queueing}
        {--dry-run : List what would be converted and change nothing}';

    protected $description = 'Make browser-renderable access copies for objects placed in exhibition spaces whose only image is a TIFF/RAW master (#1139).';

    public function handle(ExhibitionSpaceService $spaces): int
    {
        if (! class_exists(\AhgPreservation\Services\NormalizationService::class)
            || ! Schema::hasTable('preservation_normalization_rule')) {
            $this->warn('ahg-preservation is not installed here - nothing to do.');
            $this->line('  That package owns image normalization; without it there is no converter to call.');

            return self::SUCCESS;
        }
        if (! Schema::hasTable('ahg_exhibition_placement')) {
            $this->warn('No exhibition placements table - nothing to do.');

            return self::SUCCESS;
        }

        $q = DB::table('ahg_exhibition_placement as p')->select('p.information_object_id', 'p.exhibition_space_id');

        if ($space = $this->option('space')) {
            $row = DB::table('ahg_exhibition_space')
                ->when(ctype_digit((string) $space), fn ($w) => $w->where('id', (int) $space), fn ($w) => $w->where('slug', $space))
                ->first(['id', 'name']);
            if (! $row) {
                $this->error("No exhibition space matching '{$space}'.");

                return self::FAILURE;
            }
            $q->where('p.exhibition_space_id', $row->id);
            $this->info("Space: {$row->name}");
        }

        $ioIds = $q->distinct()->pluck('information_object_id')->map(fn ($v) => (int) $v)->filter()->unique()->all();
        if (empty($ioIds)) {
            $this->info('No placements found.');

            return self::SUCCESS;
        }

        $this->info(count($ioIds).' placed object(s) to check...');

        $service = app(\AhgPreservation\Services\NormalizationService::class);
        $sync = (bool) $this->option('sync');
        $dry = (bool) $this->option('dry-run');
        $needed = 0;
        $handled = 0;
        $failed = 0;
        $unconvertible = 0;

        foreach ($ioIds as $ioId) {
            // "Can the walkthrough show this at all?" - NOT "does it have a JPEG".
            // A GLB renders as a 3D model and a splat as a splat; neither needs a
            // flat image, and counting them as needing one told operators that 8
            // perfectly good objects were broken. Only an object the walkthrough
            // would fall back to a bare pedestal for actually needs a derivative.
            $media = $spaces->getObjectMedia($ioId);
            if (! empty($media['image_url']) || ! empty($media['model_url'])
                || ! empty($media['splat_url']) || ! empty($media['doc_url'])) {
                continue;
            }

            // Masters only (usage 140). A derivative that is itself unrenderable is
            // not a conversion source.
            $masters = DB::table('digital_object')
                ->where('object_id', $ioId)
                ->where('usage_id', 140)
                ->whereNotNull('mime_type')
                ->get(['id', 'mime_type', 'name']);

            foreach ($masters as $m) {
                $needed++;
                // Ask the rule registry, rather than hardcoding a format list here -
                // an operator who adds a RAW or JP2 rule gets it for free.
                $hasRule = DB::table('preservation_normalization_rule')
                    ->where('purpose', 'access')
                    ->where('source_mime', $m->mime_type)
                    ->when(Schema::hasColumn('preservation_normalization_rule', 'is_active'),
                        fn ($w) => $w->where('is_active', 1))
                    ->exists();

                if (! $hasRule) {
                    $unconvertible++;
                    $this->line("  <fg=yellow>no access rule</> io {$ioId} - {$m->mime_type} ({$m->name})");

                    continue;
                }

                if ($dry) {
                    $this->line("  would convert  io {$ioId} - {$m->mime_type} ({$m->name})");
                    $handled++;

                    continue;
                }

                if ($sync) {
                    // Remember where the conversion log ended, so a failure reports
                    // THIS attempt's error. Some failures (a foreign encryption
                    // envelope, a missing file) return before any row is written,
                    // and reading "the latest row" would then replay a stale error
                    // from a previous run as though it had just happened.
                    $lastLogId = (int) DB::table('preservation_format_conversion')
                        ->where('digital_object_id', $m->id)->max('id');

                    $res = $service->normalizeDigitalObject((int) $m->id, 'access');
                    if ($res) {
                        $this->line("  <fg=green>converted</> io {$ioId} -> {$res['target_format']}");
                        $handled++;
                    } else {
                        // Count the failure separately: reporting an attempt as a
                        // success would say "converted 3" for three objects that
                        // did not convert, which is worse than saying nothing.
                        $failed++;
                        $why = DB::table('preservation_format_conversion')
                            ->where('digital_object_id', $m->id)
                            ->where('id', '>', $lastLogId)
                            ->orderByDesc('id')
                            ->value('error_message');
                        $this->line("  <fg=red>failed</>    io {$ioId} - ".($why
                            ? substr(strtok((string) $why, "\n"), 0, 110)
                            : 'bailed before conversion (unreadable, undecryptable or already done) - see the log'));
                    }
                } else {
                    \AhgPreservation\Jobs\NormalizeDigitalObjectJob::dispatch((int) $m->id, 'access');
                    $this->line("  queued     io {$ioId} - {$m->mime_type}");
                    $handled++;
                }
            }
        }

        if ($needed === 0) {
            $this->info('Every placed object already renders - image, 3D model, splat or PDF.');

            return self::SUCCESS;
        }

        $verb = $dry ? 'would handle' : ($sync ? 'converted' : 'queued');
        $this->info("{$needed} master(s) without a renderable copy; {$verb} {$handled}.");
        if ($failed > 0) {
            $this->warn("{$failed} failed to convert - see preservation_format_conversion.error_message for each.");
        }
        if ($unconvertible > 0) {
            $this->warn("{$unconvertible} had no matching access rule - add one to preservation_normalization_rule to cover them.");
        }
        if (! $sync && ! $dry) {
            $this->line('Queued - make sure a queue worker is running, or re-run with --sync.');
        }

        return self::SUCCESS;
    }
}
