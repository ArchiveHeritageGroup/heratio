<?php

/**
 * RedactionPerImageScopeTest - heratio#1503.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Services\AttachedDigitalObjectService;
use AhgInformationObjectManage\Services\RedactionRenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A record with more than one image is redacted per image. What must hold:
 * a region covers the image it is bound to and no other; an unbound legacy
 * region still covers every primary master (the safe direction) but never an
 * attached object; and a digital object id that is not one of the record's
 * images is not a redaction target.
 */
class RedactionPerImageScopeTest extends TestCase
{
    use DatabaseTransactions;

    private RedactionRenderService $redactor;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('privacy_visual_redaction') || ! AttachedDigitalObjectService::available()) {
            $this->markTestSkipped('redaction or attached-object tables not installed.');
        }

        $this->redactor = new RedactionRenderService;
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId([
            'class_name' => $class, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** digital_object.id carries the id of its own `object` row (class table inheritance). */
    private function master(?int $ioId, string $name): int
    {
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert([
            'id' => $id, 'object_id' => $ioId, 'parent_id' => null, 'usage_id' => 140,
            'name' => $name, 'path' => '/uploads/r/test/', 'mime_type' => 'image/jpeg',
        ]);

        return $id;
    }

    private function attach(int $ioId, int $doId): void
    {
        DB::table(AttachedDigitalObjectService::TABLE)->insert([
            'information_object_id' => $ioId, 'digital_object_id' => $doId,
            'sort_order' => 1, 'is_primary' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function region(int $ioId, ?int $doId): void
    {
        DB::table('privacy_visual_redaction')->insert([
            'object_id' => $ioId, 'digital_object_id' => $doId,
            'coordinates' => json_encode(['left' => 0.1, 'top' => 0.1, 'width' => 0.2, 'height' => 0.2]),
            'status' => 'applied',
        ]);
    }

    public function test_bound_region_covers_only_its_own_image(): void
    {
        $io = $this->objectRow('QubitInformationObject');
        $primary = $this->master($io, 'primary.jpg');
        $extra = $this->master(null, 'extra.jpg');
        $this->attach($io, $extra);

        $this->region($io, $extra);

        $this->assertSame(1, $this->redactor->regionsQuery($io, $extra)->count());
        $this->assertSame(0, $this->redactor->regionsQuery($io, $primary)->count());
        $this->assertSame([$extra], $this->redactor->redactedMasterIds($io));
    }

    public function test_legacy_unbound_region_covers_every_primary_but_no_attachment(): void
    {
        $io = $this->objectRow('QubitInformationObject');
        $first = $this->master($io, 'first.jpg');
        $second = $this->master($io, 'second.jpg');
        $extra = $this->master(null, 'extra.jpg');
        $this->attach($io, $extra);

        $this->region($io, null);

        $this->assertSame(1, $this->redactor->regionsQuery($io, $first)->count());
        $this->assertSame(1, $this->redactor->regionsQuery($io, $second)->count());
        $this->assertSame(0, $this->redactor->regionsQuery($io, $extra)->count());
    }

    public function test_replace_all_for_one_image_leaves_the_others_alone(): void
    {
        $io = $this->objectRow('QubitInformationObject');
        $primary = $this->master($io, 'primary.jpg');
        $extra = $this->master(null, 'extra.jpg');
        $this->attach($io, $extra);
        $this->region($io, $primary);
        $this->region($io, $extra);

        // What saveRedactions() does for the attached image.
        $this->redactor->regionsQuery($io, $extra, false)->delete();

        $this->assertSame(1, $this->redactor->regionsQuery($io, $primary)->count());
        $this->assertSame(0, $this->redactor->regionsQuery($io, $extra)->count());
    }

    public function test_another_records_image_is_not_a_target(): void
    {
        $io = $this->objectRow('QubitInformationObject');
        $other = $this->objectRow('QubitInformationObject');
        $mine = $this->master($io, 'mine.jpg');
        $theirs = $this->master($other, 'theirs.jpg');

        $this->assertSame($mine, (int) $this->redactor->masterFor($io, $mine)->id);
        $this->assertNull($this->redactor->masterFor($io, $theirs));
        // A page showing a foreign or unknown row falls back to the record's own master.
        $this->assertSame($mine, (int) $this->redactor->masterOrDefault($io, $theirs)->id);
    }
}
