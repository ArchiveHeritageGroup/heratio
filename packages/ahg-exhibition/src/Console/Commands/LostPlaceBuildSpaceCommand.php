<?php

/**
 * LostPlaceBuildSpaceCommand - #1323 "Lost Places" POC: turn a gathered lost
 * place into a WALKABLE reconstruction.
 *
 * Takes the lost-place record built by `ahg:lost-place-demo` (one record holding
 * the public-domain evidence images), splits each image into its own catalogue
 * item so it can be hung as a framed object, creates a gallery exhibition space,
 * places the items around the room, and links the lost-place record to the space
 * as its reconstruction (`ahg_lost_place_reconstruction`). The result appears in
 * the public Reconstructions gallery and is walkable in the 3D twin - with a
 * guided tour auto-built from the placements (see walkthrough.blade.php).
 *
 * Idempotent + reversible (`--remove`).
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
use AhgExhibition\Services\ReconstructionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LostPlaceBuildSpaceCommand extends Command
{
    protected $signature = 'ahg:lost-place-build-space
                            {--place=Crystal Palace : the lost place (must already be ingested via ahg:lost-place-demo)}
                            {--remove : tear down the reconstruction space + per-image records}';

    protected $description = 'Lost Places POC (#1323): build a walkable reconstruction space from a gathered lost place.';

    private const PLACE_TAXONOMY_ID = 42;

    public function handle(ExhibitionSpaceService $spaces, ReconstructionService $recon): int
    {
        $place = trim((string) $this->option('place'));
        $recordTitle = "The {$place} (demo - Lost Places #1323)";
        $spaceName = "The {$place} (reconstruction)";

        $parentId = (int) (DB::table('information_object as io')
            ->join('information_object_i18n as i', function ($j) {
                $j->on('io.id', '=', 'i.id')->on('i.culture', '=', 'io.source_culture');
            })->where('i.title', $recordTitle)->value('io.id') ?? 0);
        if (! $parentId) {
            $this->error("No lost-place record found for \"{$place}\". Run: php artisan ahg:lost-place-demo --place=\"{$place}\" first.");

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            return $this->remove($place, $spaceName, $parentId);
        }

        $termId = (int) (DB::table('term')
            ->join('term_i18n', function ($j) {
                $j->on('term.id', '=', 'term_i18n.id')->on('term_i18n.culture', '=', 'term.source_culture');
            })->where('term.taxonomy_id', self::PLACE_TAXONOMY_ID)->where('term_i18n.name', $place)->value('term.id') ?? 0);

        // The evidence images on the lost-place record (master images only).
        $images = DB::table('digital_object')
            ->where('object_id', $parentId)->whereNull('parent_id')
            ->where('mime_type', 'like', 'image/%')
            ->select('name', 'path', 'mime_type')->get();
        if ($images->isEmpty()) {
            $this->error('The lost-place record has no images to place.');

            return self::FAILURE;
        }

        // 1. Split each image into its own catalogue item (so it can be hung).
        $childIds = [];
        DB::transaction(function () use ($images, $place, $termId, &$childIds) {
            $n = 0;
            foreach ($images as $img) {
                $n++;
                $title = "{$place} - evidence {$n}";
                $childIds[] = $this->ensureChildRecord($title, $place, $termId, $img);
            }
        });
        $this->line('Per-image records: '.count($childIds));

        // 2. Create (or reuse) the gallery space.
        $spaceId = (int) (DB::table('ahg_exhibition_space')->where('name', $spaceName)->value('id') ?? 0);
        if (! $spaceId) {
            $spaceId = $spaces->create([
                'name' => $spaceName,
                'space_type' => 'gallery',
                'notes' => "Virtual reconstruction of {$place} (Lost Places POC #1323).",
                'room_w' => 16, 'room_d' => 10, 'room_h' => 4.5,
            ]);
        }
        $this->line("Gallery space: {$spaceId} ({$spaceName})");

        // 3. Place each item around the room (evenly along the long walls).
        $placed = 0;
        $existing = DB::table('ahg_exhibition_placement')->where('exhibition_space_id', $spaceId)
            ->pluck('information_object_id')->flip();
        $count = max(1, count($childIds));
        foreach (array_values($childIds) as $i => $cid) {
            if (isset($existing[$cid])) {
                continue;
            }
            $posX = ($i + 0.5) / $count;             // spread across the width
            $posY = ($i % 2 === 0) ? 0.12 : 0.88;    // alternate front/back wall
            $spaces->createPlacementAt($spaceId, (int) $cid, $posX, $posY, 1.0);
            $placed++;
        }
        $this->line("Placed: {$placed} object(s)");

        // 4. Link the lost-place record to the space as its reconstruction.
        $already = DB::table('ahg_lost_place_reconstruction')
            ->where('information_object_id', $parentId)->where('exhibition_space_id', $spaceId)->exists();
        if (! $already) {
            $recon->link($parentId, $spaceId, "Reconstructed from {$images->count()} public-domain evidence images (#1323).");
        }

        $slug = (string) DB::table('ahg_exhibition_space')->where('id', $spaceId)->value('slug');
        $this->info("\nWalkable reconstruction ready.");
        $this->line('  Public gallery : /reconstructions');
        $this->line("  Walkthrough    : /exhibition-space/{$slug}/walkthrough");
        $this->line('  A "Full tour" auto-builds from the placements; the guided-tour launcher now shows on desktop too.');

        return self::SUCCESS;
    }

    private function ensureChildRecord(string $title, string $place, int $termId, object $img): int
    {
        $existing = DB::table('information_object as io')
            ->join('information_object_i18n as i', function ($j) {
                $j->on('io.id', '=', 'i.id')->on('i.culture', '=', 'io.source_culture');
            })->where('i.title', $title)->value('io.id');
        if ($existing) {
            return (int) $existing;
        }

        $id = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);

        // digital surrogate reuses the already-downloaded file (same path/name).
        $doId = (int) DB::table('object')->insertGetId([
            'class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('digital_object')->insert([
            'id' => $doId, 'object_id' => $id, 'name' => $img->name, 'path' => $img->path,
            'mime_type' => $img->mime_type, 'parent_id' => null,
        ]);

        // link to the place access point so the gather counts it
        if ($termId) {
            $rid = (int) DB::table('object')->insertGetId([
                'class_name' => 'QubitObjectTermRelation', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('object_term_relation')->insert(['id' => $rid, 'object_id' => $id, 'term_id' => $termId]);
        }

        return $id;
    }

    private function remove(string $place, string $spaceName, int $parentId): int
    {
        $spaceId = (int) (DB::table('ahg_exhibition_space')->where('name', $spaceName)->value('id') ?? 0);
        if ($spaceId) {
            DB::table('ahg_lost_place_reconstruction')->where('exhibition_space_id', $spaceId)->delete();
            DB::table('ahg_exhibition_placement')->where('exhibition_space_id', $spaceId)->delete();
            DB::table('ahg_exhibition_space')->where('id', $spaceId)->delete();
        }
        // per-image child records (title prefix "{place} - evidence")
        $kids = DB::table('information_object_i18n')->where('title', 'like', $place.' - evidence %')->pluck('id')->all();
        foreach ($kids as $cid) {
            $doIds = DB::table('digital_object')->where('object_id', $cid)->pluck('id')->all();
            $relIds = DB::table('object_term_relation')->where('object_id', $cid)->pluck('id')->all();
            DB::table('digital_object')->where('object_id', $cid)->delete();
            DB::table('object_term_relation')->where('object_id', $cid)->delete();
            DB::table('information_object_i18n')->where('id', $cid)->delete();
            DB::table('information_object')->where('id', $cid)->delete();
            DB::table('object')->whereIn('id', array_merge($doIds, $relIds, [$cid]))->delete();
        }
        $this->info("Removed reconstruction space + ".count($kids)." per-image record(s) for \"{$place}\".");

        return self::SUCCESS;
    }
}
