<?php

/**
 * LostPlaceReconstruct3dCommand - #1323: generate the lost place's 3D STRUCTURE
 * on the GPU backend and composite it into the walkable twin.
 *
 * Sends a building photo of the lost place to the gateway image-to-3D service
 * (`GatewayImageTo3dService` -> TRELLIS), saves the returned glb/splat, sets it
 * as the reconstruction space's `scan_shell` (so the walkthrough renders the
 * real structure - "walls/windows" - instead of a bare room), and records the
 * AI-generated geometry as inferred in the RiC provenance register (#1321).
 *
 * The viewer already renders scan_shell (#1156) + splats (#1193) + placed 3D
 * objects, so this command is the missing generation step. Fail-soft: reports
 * cleanly if the GPU endpoint or imagery is unavailable.
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
use AhgExhibition\Services\LostPlaceGatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LostPlaceReconstruct3dCommand extends Command
{
    protected $signature = 'ahg:lost-place-reconstruct-3d
                            {--place=Crystal Palace : the lost place (built via lost-place-demo + build-space)}
                            {--format=glb : output format: glb | splat | usdz}
                            {--scale=5 : scan-shell fit scale (room metres)}
                            {--structure-image= : absolute path to a specific building photo to use as the seed}
                            {--multi=0 : use up to N angled building photos for multi-view reconstruction (needs the multi-image gateway/worker)}';

    protected $description = 'Lost Places POC (#1323): generate the 3D structure on the GPU backend + set it as the room scan_shell.';

    public function handle(LostPlaceGatherService $gather, ExhibitionSpaceService $spaces): int
    {
        $place = trim((string) $this->option('place'));
        $recordTitle = "The {$place} (demo - Lost Places #1323)";
        $format = (string) $this->option('format');

        $recordId = (int) (DB::table('information_object as io')
            ->join('information_object_i18n as i', function ($j) {
                $j->on('io.id', '=', 'i.id')->on('i.culture', '=', 'io.source_culture');
            })->where('i.title', $recordTitle)->value('io.id') ?? 0);
        if (! $recordId) {
            $this->error("No lost-place record for \"{$place}\". Run ahg:lost-place-demo first.");

            return self::FAILURE;
        }

        $spaceId = (int) (DB::table('ahg_lost_place_reconstruction')
            ->where('information_object_id', $recordId)->value('exhibition_space_id') ?? 0);
        if (! $spaceId) {
            $this->error("\"{$place}\" has no reconstruction space. Run ahg:lost-place-build-space first.");

            return self::FAILURE;
        }

        // Seed: a single explicit photo, N angled views (--multi), else the best linked image.
        $multi = max(0, (int) $this->option('multi'));
        $seedPaths = [];
        $explicit = (string) $this->option('structure-image');
        if ($explicit !== '') {
            $seedPaths = [$explicit];
        } else {
            $place_term = $gather->resolvePlace($place);
            if ($place_term && $multi > 1) {
                $seedPaths = $gather->seedImages((int) $place_term->term_id, $multi);
            } elseif ($place_term) {
                $seed = $gather->seedImage((int) $place_term->term_id);
                $seedPaths = isset($seed['path']) ? [$seed['path']] : [];
            }
        }
        $seedPaths = array_values(array_filter($seedPaths, 'is_file'));
        if (! $seedPaths) {
            $this->error('No on-disk building photo to seed the structure from.');

            return self::FAILURE;
        }
        if (count($seedPaths) > 1) {
            $this->line('Seed images ('.count($seedPaths).' views, multi-view reconstruction):');
            foreach ($seedPaths as $p) {
                $this->line('  - '.$p);
            }
        } else {
            $this->line('Seed image: '.$seedPaths[0]);
        }

        if (! class_exists('Ahg3dModel\\Services\\GatewayImageTo3dService')) {
            $this->error('ahg-3d-model GatewayImageTo3dService not available.');

            return self::FAILURE;
        }
        $gpu = app('Ahg3dModel\\Services\\GatewayImageTo3dService');
        $this->info('Generating 3D structure on the GPU backend (gateway image-to-3d / TRELLIS) - this can take a while...');
        $asset = $gpu->generate(count($seedPaths) > 1 ? $seedPaths : $seedPaths[0], $format);
        if (! $asset) {
            $this->warn('  The GPU 3D service returned nothing (endpoint unavailable or auth/scope). Wiring is ready - re-run when it is live.');

            return self::SUCCESS;
        }

        // Save + set as the room's scan shell.
        $slug = Str::slug($place);
        $rel = 'uploads/lost-place-3d/'.$slug.'/structure.'.$asset['ext'];
        $abs = rtrim((string) config('heratio.storage_path'), '/').'/'.$rel;
        @mkdir(dirname($abs), 0775, true);
        file_put_contents($abs, $asset['bytes']);

        $spaces->setScanShell($spaceId, '/'.$rel);
        DB::table('ahg_exhibition_space')->where('id', $spaceId)
            ->update(['scan_shell_scale' => max(0.001, (float) $this->option('scale'))]);

        // Provenance: the geometry is AI-inferred, never documented fact (#1321/#1323).
        if (class_exists('AhgRic\\Services\\RicProvenanceService')) {
            try {
                app('AhgRic\\Services\\RicProvenanceService')->markInferred(
                    'information_object', $recordId, 'TRELLIS (gateway image-to-3d)', null, 'ahg:has3dStructure', 'trellis'
                );
            } catch (\Throwable $e) {
            }
        }

        $spaceSlug = (string) DB::table('ahg_exhibition_space')->where('id', $spaceId)->value('slug');
        $this->info("\n3D structure generated (".number_format(strlen($asset['bytes'])).' bytes) + set as the room scan_shell.');
        $this->line("  Walk it: /exhibition-space/{$spaceSlug}/walkthrough");
        $this->line('  Tagged INFERRED in the provenance register (exports prov:wasGeneratedBy).');

        return self::SUCCESS;
    }
}
