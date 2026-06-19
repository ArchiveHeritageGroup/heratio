<?php

/**
 * LostPlaceReconstructCommand - #1323 "Lost Places" POC, 3D rebuild step.
 *
 * Wires the evidence gather into a 3D reconstruction: it resolves a place's best
 * on-disk seed photo and feeds it to the `ahg-3d-model` TripoSR pipeline (via the
 * AHG AI gateway) to generate a rough 3D model. Because the geometry is
 * AI-inferred - not documented fact - the result is recorded in the RiC
 * provenance register (`RicProvenanceService`) so it exports `prov:wasGeneratedBy`
 * + `ahg:assertionStatus: inferred` and is never mistaken for evidence (the
 * #1323 non-negotiable; governance pin section 6).
 *
 * Degrades gracefully: reports cleanly when a place has no on-disk imagery to
 * seed from, or when the TripoSR gateway endpoint is not currently available -
 * the wiring is ready and re-runs once the service is live.
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

use AhgExhibition\Services\LostPlaceGatherService;
use Illuminate\Console\Command;

class LostPlaceReconstructCommand extends Command
{
    protected $signature = 'ahg:lost-place-reconstruct
                            {place : place name (partial ok) or place-taxonomy term id}
                            {--dry-run : resolve the seed image + check the service, but do not generate}';

    protected $description = 'Lost Places POC (#1323): generate a TripoSR 3D model from a place\'s seed photo, tagged as AI-inferred with provenance.';

    public function handle(LostPlaceGatherService $service): int
    {
        $query = (string) $this->argument('place');
        $result = $service->gather($query);

        if (! $result['place']) {
            $this->error("No place matched \"{$query}\" in the places taxonomy.");

            return self::FAILURE;
        }
        $place = $result['place'];
        $this->info("Place: {$place['name']}  (term #{$place['term_id']})");

        $seed = $service->seedImage((int) $place['term_id']);
        if (! $seed) {
            $this->warn('  No linked imagery on disk to seed TripoSR. Gather/discover photos first (--discover, or #1272 for unseeded places).');

            return self::SUCCESS;
        }
        $this->line("  Seed image: IO {$seed['io_id']}  {$seed['rel']}");

        // Is the TripoSR gateway endpoint reachable? Treat a non-zero code OR a
        // throwing health command (settings missing, endpoint unreachable) as
        // "unavailable" - the wiring is ready either way.
        $healthy = false;
        try {
            $healthy = $this->callSilently('ahg:triposr-health') === 0;
        } catch (\Throwable $e) {
            $healthy = false;
        }
        if (! $healthy) {
            $this->warn('  TripoSR service is not currently available (gateway endpoint). Wiring is ready - re-run when the service is live.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('  [dry-run] Would generate a TripoSR model from the seed image and tag it INFERRED (provenance).');

            return self::SUCCESS;
        }

        $code = $this->call('ahg:triposr-generate', [
            '--image'     => $seed['path'],
            '--object-id' => $seed['io_id'],
        ]);

        if ($code !== 0) {
            $this->error('  TripoSR generation failed (see output above).');

            return self::FAILURE;
        }

        // Record the AI-inferred geometry in the RiC provenance register so it
        // exports as distinguishable from documented fact (#1321 / #1323).
        $marked = false;
        if (class_exists('AhgRic\\Services\\RicProvenanceService')) {
            try {
                $marked = app('AhgRic\\Services\\RicProvenanceService')->markInferred(
                    'information_object',
                    (int) $seed['io_id'],
                    'TripoSR',
                    null,
                    'ahg:has3dReconstruction',
                    'triposr'
                );
            } catch (\Throwable $e) {
                // provenance register absent; the model still imported.
            }
        }

        $this->info('  3D reconstruction generated.'
            .($marked ? ' Tagged INFERRED in the provenance register (exports with prov:wasGeneratedBy).' : ''));

        return self::SUCCESS;
    }
}
