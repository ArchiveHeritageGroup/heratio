<?php

/**
 * SeedGalleryDemoCommand — seeds the 22 demo AI-generated gallery PNGs
 * shipped under docs/.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgGallery\Console\Commands;

use AhgCore\Constants\TermId;
use AhgCore\Services\DigitalObjectService;
use AhgGallery\Services\GalleryService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SeedGalleryDemoCommand extends Command
{
    protected $signature = 'gallery:seed-demo
        {--source= : Override source dir (default: docs/)}
        {--force : Re-seed even if identifier already exists}
        {--dry-run : Print what would be inserted without writing}';

    protected $description = 'Seed the 22 AI-generated demo Gallery items from docs/Gallery*.png';

    /**
     * Item map. Title/description merged from docs/gallery descriptions and PNG bottom captions.
     * Items 19 and 20 had no caption text on the PNG; titles are curatorial placeholders.
     */
    private function items(): array
    {
        return [
            [
                'n' => 1, 'file' => 'Gallery Item 1.png',
                'title' => 'Fragments of a Silent City',
                'style' => 'Contemporary abstract expressionism',
                'materials' => 'Acrylic, mixed media on canvas',
                'palette' => 'Teal, black, cream, grey, gold',
                'mood' => 'Bold, elegant, atmospheric, urban',
                'description' => 'This piece has a dramatic, architectural abstract style. The composition is built from vertical blocks of deep teal, charcoal black, warm cream, grey and metallic gold. The surface looks textured, almost like layered plaster or heavy acrylic paint, with visible scraping, dripping and palette-knife marks. The mood is urban, moody and sophisticated. The gold accents give it a sense of refinement, while the dark vertical forms suggest city structures, weathered walls, or an abstract skyline.',
            ],
            [
                'n' => 2, 'file' => 'Gallery Item 2.png',
                'title' => 'Erosion of Light',
                'style' => 'Fluid abstract / organic abstraction',
                'materials' => 'Acrylic, mixed media on canvas',
                'palette' => 'Rust, peach, cream, slate blue, charcoal, gold',
                'mood' => 'Warm, flowing, balanced, natural, refined',
                'description' => 'Softer and more fluid than its sister piece. Flowing organic shapes in peach, rust orange, cream, grey-blue, charcoal and subtle gold move across the canvas like ink, smoke, marble or geological layers. The texture gives it a tactile, polished fine-art feel. The burnt orange centre creates a focal point, while the blue-grey and cream areas balance it with coolness and space.',
            ],
            [
                'n' => 3, 'file' => 'Gallery Item 3.png',
                'title' => 'Measured Earth',
                'style' => 'Geometric organic abstraction / modern mixed media',
                'materials' => 'Acrylic, mixed media on canvas',
                'palette' => 'Olive, terracotta, cream, beige, charcoal, gold',
                'mood' => 'Grounded, elegant, warm, balanced, architectural',
                'description' => 'A structured modern abstract combining soft geometry with organic curves. Large overlapping blocks and rounded forms in olive green, stone beige, cream, terracotta, charcoal and muted gold. The surface has a weathered, tactile texture with visible cracks, brushed marks and distressed areas. The gold accents add refined luxury without making the work overly decorative.',
            ],
            [
                'n' => 4, 'file' => 'Gallery Item 4.png',
                'title' => 'Mineral Current',
                'style' => 'Fluid abstract / resin-inspired marble abstraction',
                'materials' => 'Acrylic, mixed media on canvas',
                'palette' => 'Indigo, emerald, grey, white, charcoal, copper-gold',
                'mood' => 'Dynamic, luxurious, flowing, atmospheric, powerful',
                'description' => 'Sweeping movement across the canvas in deep indigo, navy blue, emerald green, smoky grey, pearl white and copper-gold highlights. Shapes flow into one another like ink, resin, marble, water currents or a storm seen from above. Metallic lines and speckled gold details create a sense of energy and luxury.',
            ],
            [
                'n' => 5, 'file' => 'Gallery 5.png',
                'title' => 'Silent Dialogue',
                'style' => 'Contemporary Abstract',
                'materials' => 'Acrylic, charcoal & mixed media on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Cream, beige, charcoal, black',
                'mood' => 'Calm, contemplative, contrasted',
                'description' => 'This piece explores the tension between presence and absence. Muted tones and organic forms create a sense of calm, while bold black shapes and gestural lines introduce contrast and energy. It invites the viewer to pause and find their own meaning within the space between the elements.',
            ],
            [
                'n' => 6, 'file' => 'Gallery 6.png',
                'title' => 'Beyond the Horizon',
                'style' => 'Contemporary Abstract',
                'materials' => 'Acrylic & gold leaf on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Deep blue, soft neutrals, gold',
                'mood' => 'Expansive, optimistic, contemplative',
                'description' => 'A reflection on vast landscapes and infinite possibilities. Deep blues meet soft neutrals, with gold accents symbolising light breaking through. The layered texture and movement evoke a sense of depth, freedom and quiet optimism.',
            ],
            [
                'n' => 7, 'file' => 'Gallery 7.png',
                'title' => 'Silent Dialogue (II)',
                'style' => 'Contemporary Abstract',
                'materials' => 'Acrylic, charcoal & mixed media on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Cream, beige, charcoal, black',
                'mood' => 'Calm, contemplative, contrasted',
                'description' => 'An alternate render of Silent Dialogue, exploring the same tension between presence and absence. Muted tones and organic forms balance against bold black shapes and gestural lines.',
            ],
            [
                'n' => 8, 'file' => 'Gallery 8.png',
                'title' => 'Beyond the Horizon (II)',
                'style' => 'Contemporary Abstract',
                'materials' => 'Acrylic & gold leaf on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Deep blue, soft neutrals, gold',
                'mood' => 'Expansive, optimistic, contemplative',
                'description' => 'An alternate render of Beyond the Horizon. Deep blues meet soft neutrals with gold accents standing in for light breaking through cloud and sea.',
            ],
            [
                'n' => 9, 'file' => 'Gallery Item 9.png',
                'title' => 'The Lady of the Arcade',
                'style' => 'Renaissance-inspired portraiture',
                'materials' => 'Oil on canvas',
                'palette' => 'Deep red, navy blue, gold, stone beige, soft green',
                'mood' => 'Noble, calm, symbolic, refined, contemplative',
                'description' => 'A formal Renaissance-inspired portrait of a noblewoman standing beneath a stone arcade. Rich deep red and dark blue garments with gold detailing suggest status, refinement and learning. She holds white lilies and a book — purity and inner reflection — against an open arch revealing a calm landscape with distant buildings, hills and sky.',
            ],
            [
                'n' => 10, 'file' => 'Gallery Item 10.png',
                'title' => "The Humanist's Study",
                'style' => 'Renaissance-inspired narrative portrait',
                'materials' => 'Oil on canvas',
                'palette' => 'Burgundy, dark brown, gold, cream, olive green, sky blue',
                'mood' => 'Intellectual, warm, classical, reflective, scholarly',
                'description' => 'A Renaissance-inspired scholar or artist seated in a study. Books, architectural drawings, a lute, an hourglass, a celestial instrument and classical statues visible through the window create the feeling of a learned humanist environment where art, science, music and philosophy connect. The figure wears a dark red robe and appears thoughtful, as if interrupted while working.',
            ],
            [
                'n' => 11, 'file' => 'Gallery Item 11.png',
                'title' => 'Lady of the Rose Garden',
                'style' => 'Renaissance-inspired portraiture',
                'materials' => 'Oil on panel',
                'dimensions' => '70 x 90 cm',
                'palette' => 'Deep red, gold, stone, soft green',
                'mood' => 'Noble, contemplative, harmonious',
                'description' => 'A portrait inspired by Renaissance ideals of beauty, virtue and learning. The roses symbolise grace and inner nobility, while the book reflects wisdom and contemplation. The open landscape beyond the arch represents harmony between humanity and the natural world.',
            ],
            [
                'n' => 12, 'file' => 'Gallery Item 12.png',
                'title' => "The Architect's Vision",
                'style' => 'Renaissance-inspired narrative portrait',
                'materials' => 'Oil on canvas',
                'dimensions' => '90 x 110 cm',
                'palette' => 'Warm earth, parchment, sky blue',
                'mood' => 'Inventive, scholarly, aspirational',
                'description' => 'A celebration of the Renaissance spirit of invention and knowledge. The architect studies ancient forms, guided by geometry, proportion and imagination. Symbols of time, art and science surround him, reminding us of the pursuit of excellence and the legacy we build for the future.',
            ],
            [
                'n' => 13, 'file' => 'Gallery Item 13.png',
                'title' => 'Neon Bloom',
                'style' => 'Trendy contemporary art',
                'materials' => 'Mixed media on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Pink, coral, lilac, cream',
                'mood' => 'Playful, expressive, uplifting',
                'description' => 'A celebration of colour, rhythm and modern femininity. Soft tones and vibrant accents collide in a layered composition that feels playful, expressive and current. The work balances warmth and energy, creating a statement piece with a stylish, uplifting presence.',
            ],
            [
                'n' => 14, 'file' => 'Gallery Item 14.png',
                'title' => 'Digital Tide',
                'style' => 'Trendy contemporary art',
                'materials' => 'Acrylic & gold drizzle on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Cool blue, emerald, gold',
                'mood' => 'Immersive, sophisticated, modern',
                'description' => 'An exploration of flow, depth and contemporary mood. Cool blues and emerald tones move through the canvas like a shifting current, while gold details introduce light and refinement. The piece feels immersive, sophisticated and perfectly suited to a modern interior.',
            ],
            [
                'n' => 15, 'file' => 'Gallery Item 15.png',
                'title' => 'Mist Over the Ridge',
                'style' => 'Nature / Landscape',
                'materials' => 'Oil & mixed media on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Soft gold, mist white, blue-green',
                'mood' => 'Calm, restorative, expansive',
                'description' => 'A meditation on stillness, distance and the first light of morning. Layered hills recede into veils of mist, while soft gold and cool blue-green tones create a calm, restorative atmosphere. The piece invites the viewer to pause and breathe within the quiet vastness of the natural world.',
            ],
            [
                'n' => 16, 'file' => 'Gallery Item 16.png',
                'title' => 'River of Quiet Light',
                'style' => 'Nature / Landscape',
                'materials' => 'Acrylic & texture on canvas',
                'dimensions' => '120 x 150 cm',
                'palette' => 'Amber, olive, dusk blue',
                'mood' => 'Peaceful, reflective, lyrical',
                'description' => 'An imagined landscape shaped by water, memory and evening light. A winding river gathers reflections of amber sky and deepening shadow, moving gently through reeds and open land. The work feels peaceful yet alive, celebrating the beauty of motion, colour and silence in nature.',
            ],
            [
                'n' => 17, 'file' => 'Gallery Item 17.png',
                'title' => 'First Light Over the Valley',
                'style' => 'Romantic landscape / textured nature painting',
                'materials' => 'Oil on canvas',
                'palette' => 'Blue, teal, soft gold, peach, forest green',
                'mood' => 'Calm, expansive, hopeful, restorative',
                'description' => 'A majestic mountain sunrise viewed from a high ridge. The foreground is filled with wildflowers, grasses and dark evergreen trees, while layers of blue-green mountains recede into the distance. Mist sits low in the valleys, creating a soft, atmospheric depth. Warm peach and gold tones in the sky contrast with the cooler blues of the mountains.',
            ],
            [
                'n' => 18, 'file' => 'Gallery Item 18.png',
                'title' => 'Golden Hour on the Riverbend',
                'style' => 'Impressionistic landscape / expressive nature painting',
                'materials' => 'Oil on canvas',
                'palette' => 'Orange, coral, gold, dusky blue, olive green',
                'mood' => 'Warm, peaceful, nostalgic, contemplative',
                'description' => 'A golden sunset over a winding marsh river. The river leads the eye through the scene, reflecting the orange and pink sky across the water. Tall reeds, grasses and distant trees frame the composition, suggesting the end of a long day where nature becomes still but full of colour.',
            ],
            [
                'n' => 19, 'file' => 'Gallery Item 19.png',
                'title' => 'Sentinel of the Mist',
                'style' => 'Wildlife / Romantic naturalism',
                'materials' => 'Oil & gold leaf on canvas (digital)',
                'palette' => 'Slate blue, mist grey, gold, deep umber',
                'mood' => 'Regal, watchful, mythic',
                'description' => 'A noble red stag stands atop a rocky outcrop above a misty pine valley at dawn. Gold-leaf accents catch on tree branches and stone, lending the scene a regal, almost mythic quality. The composition speaks to wilderness, watchfulness and the dignity of the wild.',
            ],
            [
                'n' => 20, 'file' => 'Gallery Item 20.png',
                'title' => 'Egret at Golden Hour',
                'style' => 'Wildlife / Decorative naturalism',
                'materials' => 'Acrylic & gold leaf on canvas (digital)',
                'palette' => 'Coral, peach, teal, ivory, gold',
                'mood' => 'Serene, lyrical, luminous',
                'description' => 'A great white egret stands among reeds at the edge of a glowing wetland as the sun sets. Warm coral and orange tones reflect across the water while gold-leaf detailing highlights the rushes and feathers. The piece captures stillness, grace and the soft drama of dusk.',
            ],
            [
                'n' => 21, 'file' => 'Gallery Item 21.png',
                'title' => 'Guardian of the Savanna',
                'style' => 'Wildlife portraiture',
                'materials' => 'Acrylic & gold leaf on canvas',
                'dimensions' => '100 x 120 cm',
                'palette' => 'Earth, ochre, gold',
                'mood' => 'Noble, timeless, powerful',
                'description' => 'This portrait captures the quiet strength and nobility of the lion, symbol of courage, leadership and protection. Earthy tones and gold leaf accents evoke the raw beauty of the African wilderness and the timeless presence of its king.',
            ],
            [
                'n' => 22, 'file' => 'Gallery Item 22.png',
                'title' => 'Bonds of the Wild',
                'style' => 'Wildlife narrative',
                'materials' => 'Oil on canvas',
                'dimensions' => '100 x 120 cm',
                'palette' => 'Sunset orange, sienna, dusk blue',
                'mood' => 'Tender, reflective, vital',
                'description' => 'A tender moment between mother and calf, a celebration of love, guidance and generational wisdom. Bold brushwork and vibrant colours express the spirit of togetherness that sustains life in the wild.',
            ],
            [
                'n' => 25, 'file' => 'Gallery Item 25.png',
                'title' => 'King of the Silent Plains',
                'style' => 'Wildlife sculpture',
                'materials' => 'Bronze',
                'dimensions' => '38 x 28 x 60 cm',
                'edition' => 'Limited Edition of 20',
                'palette' => 'Patinated bronze, deep umber',
                'mood' => 'Noble, quiet, authoritative',
                'description' => 'A symbol of courage, leadership and quiet authority. Every textured detail speaks of strength earned, not given. This piece captures the spirit of a guardian who watches, protects and inspires.',
            ],
            [
                'n' => 26, 'file' => 'Gallery Item 26.png',
                'title' => 'Earthkeeper',
                'style' => 'Wildlife sculpture',
                'materials' => 'Stone and brass',
                'dimensions' => '50 x 20 x 35 cm',
                'edition' => 'Edition of 15',
                'palette' => 'Stone grey, warm brass',
                'mood' => 'Resilient, grounded, protective',
                'description' => 'A tribute to resilience and ancient wisdom. The rhinoceros, carved in solid stone with brass accents, reminds us of our duty to protect the earth and all who journey upon it.',
            ],
            [
                'n' => 27, 'file' => 'Gallery Item 27.png',
                'title' => 'Sky Sentinel',
                'style' => 'Mythic / fantasy sculpture',
                'materials' => 'Bronze & patinated metal',
                'dimensions' => '52 x 38 x 70 cm',
                'edition' => 'Limited Edition of 18',
                'palette' => 'Patinated bronze, deep umber, gold',
                'mood' => 'Mythic, watchful, soaring',
                'description' => 'Poised between earth and wind, Sky Sentinel embodies the eternal mythicality of ancient skies. Its form captures the moment before flight — muscles coiled, wings unfolding, vision set beyond horizon and time. A tribute to the unseen guardians of deep time, it speaks to freedom, instinct, and the vast silence that shaped a world long before our own. A symbol of courage, perspective, and the enduring spirit of the wild.',
            ],
            [
                'n' => 23, 'file' => 'Gallery Item 23.png',
                'title' => 'Shadow Stalker',
                'style' => 'Wildlife sculpture',
                'materials' => 'Bronze',
                'dimensions' => '48 x 28 x 52 cm',
                'edition' => 'Limited Edition of 25',
                'palette' => 'Patinated bronze, deep umber',
                'mood' => 'Focused, watchful, powerful',
                'description' => 'A celebration of power, focus and silent movement. This leopard embodies grace under pressure, a reminder that true strength lies in patience, awareness and purpose.',
            ],
            [
                'n' => 24, 'file' => 'Gallery Item 24.png',
                'title' => 'Unbreakable Bond',
                'style' => 'Wildlife narrative sculpture',
                'materials' => 'Stone resin',
                'dimensions' => '45 x 30 x 22 cm',
                'edition' => 'Edition of 30',
                'palette' => 'Stone grey, warm umber',
                'mood' => 'Tender, protective, generational',
                'description' => 'This piece honours the deep connection between mother and child. With strength, guidance and unconditional love, the bond between them becomes a safe place where life begins and wisdom is passed on.',
            ],
            [
                'n' => 28, 'file' => 'Gallery Item 28.png',
                'title' => 'Dawn Glider',
                'style' => 'Mythic / fantasy sculpture',
                'materials' => 'Stone resin & brass',
                'dimensions' => '48 x 34 x 66 cm',
                'edition' => 'Edition of 20',
                'palette' => 'Stone white, warm brass',
                'mood' => 'Soaring, ancient, lyrical',
                'description' => 'Dawn Glider is a celebration of motion and the quiet mastery of the air. Its sweeping form and wind-shaped plinth evoke effortless lift and ancient skies. Crafted in stone resin with hand-finished brass details, it honours the resilience and lightness of creatures long vanished, inviting us to imagine the world as it once soared.',
            ],
            [
                'n' => 29, 'file' => 'Gallery Item 29.png',
                'title' => 'Tidal Ascent',
                'style' => 'Figurative sculpture',
                'materials' => 'Bronze with blue-green patina',
                'dimensions' => '28 x 24 x 41 cm (small) / 75 x 36 x 104 cm (large)',
                'palette' => 'Bronze, blue-green patina',
                'mood' => 'Aspirational, renewing, luminous',
                'description' => 'Tidal Ascent captures the instant of breaking through — where effort meets liberation. The swimmer rises with power and grace, propelled by inner strength and the rhythm of the tide. A celebration of aspiration, renewal, and the pull toward light.',
            ],
            [
                'n' => 30, 'file' => 'Gallery Item 30.png',
                'title' => 'Understream Figure',
                'style' => 'Figurative sculpture',
                'materials' => 'Stone resin with brass accents',
                'dimensions' => '30 x 27 x 25 cm (small) / 76 x 36 x 31 cm (large)',
                'palette' => 'Stone white, warm brass',
                'mood' => 'Fluid, weightless, focused',
                'description' => 'Understream Figure embodies the quiet power of flow and focus beneath the surface. In a state of weightless harmony, the swimmer becomes one with the current — guided by intuition, meeting purpose, ascent unstoppable.',
            ],
        ];
    }

    public function handle(GalleryService $service): int
    {
        $sourceDir = $this->option('source') ?: base_path('docs');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (!is_dir($sourceDir)) {
            $this->error("Source directory not found: {$sourceDir}");
            return 1;
        }

        $items = $this->items();
        $created = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($items as $item) {
            $identifier = sprintf('GALLERY-DEMO-%02d', $item['n']);
            $sourcePath = $sourceDir . '/' . $item['file'];

            if (!file_exists($sourcePath)) {
                $this->warn("[{$identifier}] Source file missing: {$sourcePath} — skipping");
                $errors++;
                continue;
            }

            $existingId = DB::table('information_object')
                ->where('identifier', $identifier)
                ->value('id');

            if ($existingId && !$force) {
                $this->line("[{$identifier}] Already seeded (IO #{$existingId}) — skipping");
                $skipped++;
                continue;
            }

            $scope = $item['description']
                . "\n\n— Style: {$item['style']}"
                . (isset($item['palette']) ? "\n— Palette: {$item['palette']}" : '')
                . (isset($item['mood']) ? "\n— Feeling: {$item['mood']}" : '');

            $payload = [
                'identifier' => $identifier,
                'title' => $item['title'],
                'scope_and_content' => $scope,
                'extent_and_medium' => trim(
                    ($item['materials'] ?? '')
                    . (isset($item['dimensions']) ? ', ' . $item['dimensions'] : '')
                    . (isset($item['edition']) ? ', ' . $item['edition'] : ''),
                    ', '
                ),
                'source_standard' => 'CCO',
                // CCO / museum_metadata
                'work_type' => 'Painting (digital reproduction)',
                'classification' => 'Visual art',
                'creator_identity' => 'AI-generated',
                'creator_role' => 'Generative model',
                'style' => $item['style'],
                'materials' => $item['materials'] ?? null,
                'dimensions' => $item['dimensions'] ?? null,
                'rights_type' => 'AI-generated content — non-authoritative',
                'cataloger_name' => 'gallery:seed-demo',
                'cataloging_date' => now()->format('Y-m-d'),
            ];

            if ($dryRun) {
                $this->info("[{$identifier}] DRY RUN — would create '{$item['title']}' from {$item['file']}");
                continue;
            }

            try {
                if ($existingId && $force) {
                    $this->warn("[{$identifier}] --force set; deleting existing IO #{$existingId} first");
                    $oldSlug = DB::table('slug')->where('object_id', $existingId)->value('slug');
                    if ($oldSlug) {
                        $service->delete($oldSlug);
                    }
                }

                $slug = $service->create($payload);
                $newId = DB::table('slug')->where('slug', $slug)->value('object_id');

                if (!$newId) {
                    $this->error("[{$identifier}] Created but slug lookup failed");
                    $errors++;
                    continue;
                }

                // Copy PNG to a temp location and wrap in UploadedFile (test mode = no real HTTP upload).
                $tmpPath = tempnam(sys_get_temp_dir(), 'galseed_') . '.png';
                copy($sourcePath, $tmpPath);
                $upload = new UploadedFile(
                    $tmpPath,
                    $item['file'],
                    'image/png',
                    null,
                    true // test mode
                );

                DigitalObjectService::upload((int) $newId, $upload);

                // Publish (default is draft)
                DB::table('status')
                    ->where('object_id', $newId)
                    ->where('type_id', TermId::STATUS_TYPE_PUBLICATION)
                    ->update(['status_id' => TermId::PUBLICATION_STATUS_PUBLISHED]);

                $this->info("[{$identifier}] Created IO #{$newId} '{$item['title']}' (slug: {$slug})");
                $created++;
            } catch (\Throwable $e) {
                $this->error("[{$identifier}] Failed: " . $e->getMessage());
                $errors++;
            }
        }

        $this->newLine();
        $this->info("=== Gallery seed summary ===");
        $this->line("Created: {$created}");
        $this->line("Skipped: {$skipped}");
        $this->line("Errors:  {$errors}");

        return $errors === 0 ? 0 : 1;
    }
}
