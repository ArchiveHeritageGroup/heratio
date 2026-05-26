<?php

/**
 * IiifAvCanvasEmissionTest - Unit tests for A/V canvas emission in
 * IiifCollectionService::buildAvCanvasV3 (issue #695).
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

namespace AhgIiifCollection\Tests\Unit;

use AhgIiifCollection\Services\IiifCollectionService;
use Tests\TestCase;

/**
 * Validates that audio + video canvases emitted in Presentation API 3.0
 * manifests carry the spec-required fields: duration, painting body type
 * (Sound / Video), MediaFragmentSelector service block, and (for video)
 * width / height on both canvas + body.
 *
 * buildAvCanvasV3 is private; we reach it through reflection so we can
 * structurally validate the canvas shape without standing up an
 * information_object + digital_object + slug fixture in the DB. The
 * digital_object_property lookup is best-effort and returns no rows when
 * the seeded id is absent - so the test exercises the default-duration /
 * default-dimensions fallbacks that ship with the service.
 *
 * Conceptually mirrors the assertions the IIIF Presentation Validator
 * (https://presentation-validator.iiif.io/) runs:
 *   - temporal Canvas requires `duration` > 0
 *   - spatial Canvas requires `width` + `height` > 0
 *   - painting body's `type` matches the media kind
 *   - MediaFragmentSelector must declare conformsTo media-frags
 */
class IiifAvCanvasEmissionTest extends TestCase
{
    private function invokeBuildAv(string $type, object $digitalObject): array
    {
        $svc = new IiifCollectionService();
        $ref = new \ReflectionClass($svc);
        $m = $ref->getMethod('buildAvCanvasV3');
        $m->setAccessible(true);
        return $m->invoke(
            $svc,
            'https://heratio.example/iiif-manifest/sample',
            'https://heratio.example',
            1,
            $digitalObject,
            $type
        );
    }

    public function test_audio_canvas_emits_sound_painting_body_with_duration(): void
    {
        $do = (object) [
            'id' => 0, // No digital_object_property rows seeded - default duration.
            'name' => 'interview.mp3',
            'path' => 'uploads/audio/',
            'mime_type' => 'audio/mpeg',
            'byte_size' => 12345,
        ];

        $canvas = $this->invokeBuildAv('Sound', $do);

        $this->assertSame('Canvas', $canvas['type']);
        $this->assertArrayHasKey('duration', $canvas);
        $this->assertGreaterThan(0.0, $canvas['duration']);

        // Audio canvases must NOT carry width / height.
        $this->assertArrayNotHasKey('width', $canvas);
        $this->assertArrayNotHasKey('height', $canvas);

        $annotation = $canvas['items'][0]['items'][0];
        $this->assertSame('painting', $annotation['motivation']);
        $body = $annotation['body'];
        $this->assertSame('Sound', $body['type']);
        $this->assertSame('audio/mpeg', $body['format']);
        $this->assertArrayHasKey('duration', $body);
        $this->assertGreaterThan(0.0, $body['duration']);

        // MediaFragmentSelector service block - lets viewers request
        // #t=ss,ee ranges per the W3C Media Fragments URI spec.
        $this->assertArrayHasKey('service', $body);
        $this->assertSame('MediaFragmentSelector', $body['service'][0]['type']);
        $this->assertSame('http://www.w3.org/TR/media-frags/', $body['service'][0]['conformsTo']);
    }

    public function test_video_canvas_carries_width_height_and_video_painting_body(): void
    {
        $do = (object) [
            'id' => 0,
            'name' => 'lecture.mp4',
            'path' => 'uploads/video/',
            'mime_type' => 'video/mp4',
            'byte_size' => 999999,
        ];

        $canvas = $this->invokeBuildAv('Video', $do);

        $this->assertSame('Canvas', $canvas['type']);
        $this->assertGreaterThan(0, $canvas['width']);
        $this->assertGreaterThan(0, $canvas['height']);
        $this->assertGreaterThan(0.0, $canvas['duration']);

        $body = $canvas['items'][0]['items'][0]['body'];
        $this->assertSame('Video', $body['type']);
        $this->assertSame('video/mp4', $body['format']);
        $this->assertGreaterThan(0, $body['width']);
        $this->assertGreaterThan(0, $body['height']);
        $this->assertGreaterThan(0.0, $body['duration']);
    }

    public function test_video_canvas_target_matches_canvas_id(): void
    {
        $do = (object) [
            'id' => 0,
            'name' => 'clip.webm',
            'path' => 'uploads/video/',
            'mime_type' => 'video/webm',
            'byte_size' => 1,
        ];
        $canvas = $this->invokeBuildAv('Video', $do);
        $canvasId = $canvas['id'];
        $annotation = $canvas['items'][0]['items'][0];
        $this->assertSame($canvasId, $annotation['target']);
    }

    public function test_audio_canvas_label_falls_back_to_filename(): void
    {
        $do = (object) [
            'id' => 0,
            'name' => 'oral-history.wav',
            'path' => 'uploads/',
            'mime_type' => 'audio/wav',
            'byte_size' => 1,
        ];
        $canvas = $this->invokeBuildAv('Sound', $do);
        $this->assertSame('oral-history.wav', $canvas['label']['en'][0]);
    }

    public function test_audio_and_video_mime_detection_in_collection_service(): void
    {
        $svc = new IiifCollectionService();
        $ref = new \ReflectionClass($svc);

        $isAudio = $ref->getMethod('isAudioMime');
        $isAudio->setAccessible(true);
        $isVideo = $ref->getMethod('isVideoMime');
        $isVideo->setAccessible(true);

        $this->assertTrue($isAudio->invoke($svc, 'audio/mpeg', 'a.mp3'));
        $this->assertTrue($isAudio->invoke($svc, '', 'a.flac'));
        $this->assertTrue($isAudio->invoke($svc, '', 'a.opus'));
        $this->assertFalse($isAudio->invoke($svc, 'image/jpeg', 'a.jpg'));

        $this->assertTrue($isVideo->invoke($svc, 'video/mp4', 'b.mp4'));
        $this->assertTrue($isVideo->invoke($svc, '', 'b.webm'));
        $this->assertTrue($isVideo->invoke($svc, '', 'b.mkv'));
        $this->assertFalse($isVideo->invoke($svc, 'audio/wav', 'b.wav'));
    }
}
