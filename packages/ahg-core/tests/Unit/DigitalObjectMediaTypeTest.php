<?php

/**
 * DigitalObjectMediaTypeTest - the viewer's media type follows a clear
 * audio/, image/ or video/ MIME type even when the stored media_type_id says
 * otherwise (10 Nov 2026: audio stored as "Other" showed a file icon instead
 * of a player, and API v2 uploads stored images as Text and audio as Video);
 * the stored id still decides for everything else.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Unit;

use AhgCore\Services\DigitalObjectService as D;
use PHPUnit\Framework\TestCase;

class DigitalObjectMediaTypeTest extends TestCase
{
    private function obj(?int $type, string $mime, string $name = 'f'): object
    {
        return (object) ['media_type_id' => $type, 'mime_type' => $mime, 'name' => $name];
    }

    public function test_a_clear_mime_type_wins_over_a_wrong_stored_id(): void
    {
        $this->assertSame('audio', D::getMediaType($this->obj(D::MEDIA_OTHER, 'audio/wav')), 'audio stored as Other');
        $this->assertSame('audio', D::getMediaType($this->obj(D::MEDIA_VIDEO, 'audio/mpeg')), 'API v2 stored audio as Video');
        $this->assertSame('image', D::getMediaType($this->obj(D::MEDIA_TEXT, 'image/jpeg')), 'API v2 stored images as Text');
        $this->assertSame('video', D::getMediaType($this->obj(D::MEDIA_OTHER, 'video/mp4')), 'API v2 stored video as Other');
    }

    public function test_the_stored_id_still_decides_otherwise(): void
    {
        $this->assertSame('text', D::getMediaType($this->obj(D::MEDIA_TEXT, 'application/pdf')));
        $this->assertSame('other', D::getMediaType($this->obj(D::MEDIA_OTHER, 'application/zip')));
        $this->assertSame('audio', D::getMediaType($this->obj(null, '', 'clip.m4a')), 'extension fallback');
    }

    public function test_uploads_get_the_right_taxonomy_ids(): void
    {
        $this->assertSame(D::MEDIA_IMAGE, D::resolveMediaTypeId('image/tiff'));
        $this->assertSame(D::MEDIA_AUDIO, D::resolveMediaTypeId('audio/wav'));
        $this->assertSame(D::MEDIA_VIDEO, D::resolveMediaTypeId('video/mp4'));
    }
}
