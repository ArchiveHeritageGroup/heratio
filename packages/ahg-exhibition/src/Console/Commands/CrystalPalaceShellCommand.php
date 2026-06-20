<?php

/**
 * CrystalPalaceShellCommand - #1323: generate the parametric Crystal Palace
 * shell and set it as the reconstruction room's walkable scan_shell.
 *
 * Where ahg:lost-place-reconstruct-3d sends photos to the GPU (TRELLIS) and gets
 * back an exterior OBJECT, this builds the building as real walkable geometry -
 * nave, aisles, iron colonnade, glazed barrel vaults and the crossing transept -
 * procedurally (ParametricCrystalPalaceService -> GlbBuilder), then drops it in
 * as the room's scan_shell and turns the space open-air so you see sky through
 * the glass (the palace stood in a park). Tagged INFERRED in the RiC provenance
 * register (#1321): a parametric reconstruction, not documented fact.
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
use AhgExhibition\Services\ParametricCrystalPalaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CrystalPalaceShellCommand extends Command
{
    protected $signature = 'ahg:crystal-palace-shell
                            {--place=Crystal Palace : the lost place (built via lost-place-demo + build-space)}
                            {--outdoor=1 : set the space open-air (sky + park) so the glazing reads correctly}';

    protected $description = 'Lost Places POC (#1323): build the parametric Crystal Palace shell + set it as the walkable room scan_shell.';

    public function handle(ExhibitionSpaceService $spaces, ParametricCrystalPalaceService $palace): int
    {
        $place = trim((string) $this->option('place'));
        $recordTitle = "The {$place} (demo - Lost Places #1323)";

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

        $this->info('Generating the parametric Crystal Palace shell (nave + transept + glazed vaults)...');
        $shell = $palace->generate();

        $slug = Str::slug($place);
        $rel = 'uploads/lost-place-3d/'.$slug.'/shell.glb';
        $abs = rtrim((string) config('heratio.storage_path'), '/').'/'.$rel;
        @mkdir(dirname($abs), 0775, true);
        file_put_contents($abs, $shell['bytes']);

        // Drop it in as the walkable shell at true metres (scale 1); size the room to
        // its footprint and make it open-air so the glazing reads against sky, not walls.
        $spaces->setScanShell($spaceId, '/'.$rel);
        DB::table('ahg_exhibition_space')->where('id', $spaceId)->update([
            'scan_shell_scale' => 1.0,
            'room_w' => $shell['length'],
            'room_d' => $shell['width'],
            'room_h' => 22,
            'is_outdoor' => (int) $this->option('outdoor') === 1 ? 1 : 0,
        ]);

        // Provenance: parametric reconstruction is inferred, not documented fact.
        if (class_exists('AhgRic\\Services\\RicProvenanceService')) {
            try {
                app('AhgRic\\Services\\RicProvenanceService')->markInferred(
                    'information_object', $recordId, 'Parametric reconstruction (Heratio ParametricCrystalPalaceService)', null, 'ahg:has3dStructure', 'parametric'
                );
            } catch (\Throwable $e) {
            }
        }

        $spaceSlug = (string) DB::table('ahg_exhibition_space')->where('id', $spaceId)->value('slug');
        $this->info("\nParametric shell generated (".number_format(strlen($shell['bytes'])).' bytes) + set as the room scan_shell.');
        $this->line("  Walk it: /exhibition-space/{$spaceSlug}/walkthrough");
        $this->line('  Footprint '.$shell['length'].'m x '.$shell['width'].'m, open-air; tagged INFERRED (parametric) in the provenance register.');

        return self::SUCCESS;
    }
}
