<?php

/**
 * MediaDerivativesQueueTest - audio/video masters get the ffmpeg job queued
 * (not a placeholder icon), and the nightly sweep finds masters whose only
 * reference is an icon. 11 Nov 2026: a WMV on heratio.org never played because
 * its "reference" was never a video.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU Affero General Public License v3.0 or later.
 */

namespace Tests\Feature;

use AhgCore\Commands\MediaDerivativesCommand;
use AhgCore\Jobs\GenerateMediaDerivativesJob;
use AhgCore\Services\DigitalObjectService as DOS;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MediaDerivativesQueueTest extends TestCase
{
    use DatabaseTransactions;

    public function test_audio_and_video_queue_the_job_images_and_pdfs_do_not(): void
    {
        Queue::fake();
        $this->assertTrue(DOS::queueMediaDerivatives(1001, null, 'video/x-ms-wmv'));
        $this->assertTrue(DOS::queueMediaDerivatives(1002, DOS::MEDIA_AUDIO, 'audio/aiff'));
        $this->assertFalse(DOS::queueMediaDerivatives(1003, null, 'image/jpeg'));
        $this->assertFalse(DOS::queueMediaDerivatives(1004, null, 'application/pdf'));
        Queue::assertPushed(GenerateMediaDerivativesJob::class, 2);
        Queue::assertPushed(GenerateMediaDerivativesJob::class, fn ($j) => $j->masterId === 1001);
    }

    public function test_sweep_finds_a_video_whose_reference_is_only_an_icon(): void
    {
        $iconOnly = $this->master('zz_icon.wmv', 'video/x-ms-wmv');
        $this->derivative($iconOnly, 'reference_zz_icon_wmv.png', 'image/png');
        $playable = $this->master('zz_ok.mov', 'video/quicktime');
        $this->derivative($playable, 'reference_zz_ok.mp4', 'video/mp4');

        $ids = MediaDerivativesCommand::missing(100000)->pluck('id')->all();
        $this->assertContains($iconOnly, $ids);
        $this->assertNotContains($playable, $ids);
    }

    private function master(string $name, string $mime): int
    {
        $io = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => $name, 'path' => '/uploads/r/'.$io.'/',
            'mime_type' => $mime, 'media_type_id' => DOS::MEDIA_VIDEO, 'byte_size' => 1]);

        return $id;
    }

    private function derivative(int $master, string $name, string $mime): void
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => DB::table('digital_object')->where('id', $master)->value('object_id'), 'parent_id' => $master,
            'usage_id' => DOS::USAGE_REFERENCE, 'name' => $name, 'path' => '/uploads/r/x/', 'mime_type' => $mime, 'media_type_id' => DOS::MEDIA_VIDEO, 'byte_size' => 1]);
    }
}
