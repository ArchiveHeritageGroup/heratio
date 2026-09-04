<?php

/**
 * RedactionDerivativeFormatTest - a redacted derivative has to be something a
 * browser will actually display.
 *
 * The redaction path cannot go through Cantaloupe: Cantaloupe tiles the
 * ORIGINAL file, so leaving the IIIF image service in a redacted record's
 * manifest lets a viewer deep-zoom straight past the redaction. Dropping the
 * service also drops Cantaloupe's transcode, which is what made this matter.
 *
 * Until v1.154.719 the derivative kept the master's extension and was served
 * under the master's mime type, so a redacted TIFF was written as TIFF and sent
 * as image/tiff. No browser renders that, and Mirador showed nothing at all -
 * fail-safe, but only by accident, and archival TIFF is precisely what the
 * deep-zoom viewers exist for. The PDF path was always fine, which is why it
 * went unnoticed: the only redacted record on dev is a PDF.
 *
 * Pure statics, no DB and no container, so this runs in CI.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgInformationObjectManage\Tests\Unit;

use AhgInformationObjectManage\Services\RedactionRenderService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RedactionDerivativeFormatTest extends TestCase
{
    public static function unrenderableMasters(): array
    {
        return [
            'tiff' => ['archival-scan.tiff'],
            'tif' => ['archival-scan.tif'],
            'uppercase' => ['ARCHIVAL-SCAN.TIF'],
            'jp2' => ['plate-004.jp2'],
            'jpx' => ['plate-004.jpx'],
            'no extension' => ['masterfile'],
        ];
    }

    #[DataProvider('unrenderableMasters')]
    public function test_a_master_a_browser_cannot_render_is_transcoded_to_jpeg(string $name): void
    {
        $this->assertSame('jpg', RedactionRenderService::derivativeExtension($name, 'image'));
    }

    public static function renderableMasters(): array
    {
        return [
            'jpg' => ['photo.jpg', 'jpg'],
            'jpeg' => ['photo.jpeg', 'jpeg'],
            'png' => ['diagram.png', 'png'],
            'gif' => ['anim.gif', 'gif'],
            'webp' => ['modern.webp', 'webp'],
        ];
    }

    #[DataProvider('renderableMasters')]
    public function test_a_renderable_master_keeps_its_format(string $name, string $expected): void
    {
        $this->assertSame($expected, RedactionRenderService::derivativeExtension($name, 'image'));
    }

    /** PDFs render natively and carry their page structure; never transcode one. */
    public function test_a_pdf_stays_a_pdf(): void
    {
        $this->assertSame('pdf', RedactionRenderService::derivativeExtension('deed-of-gift.pdf', 'pdf'));
        $this->assertSame('pdf', RedactionRenderService::derivativeExtension('scan.tiff', 'pdf'));
    }

    /**
     * The header must describe the derivative, not the master. Sending the
     * master's image/tiff for a transcoded JPEG makes the browser refuse it,
     * which is the whole defect.
     */
    public function test_the_content_type_follows_the_derivative(): void
    {
        $this->assertSame('image/jpeg', RedactionRenderService::mimeForPath('/cache/1/abc.jpg'));
        $this->assertSame('image/png', RedactionRenderService::mimeForPath('/cache/1/abc.png'));
        $this->assertSame('application/pdf', RedactionRenderService::mimeForPath('/cache/1/abc.pdf'));
    }

    public function test_an_unknown_extension_does_not_claim_a_type_it_cannot_prove(): void
    {
        $this->assertSame('application/octet-stream', RedactionRenderService::mimeForPath('/cache/1/abc.tiff'));
        $this->assertSame('application/octet-stream', RedactionRenderService::mimeForPath('/cache/1/abc'));
    }

    /**
     * The two have to agree: whatever extension the derivative is written with
     * is the extension the content type is then read back off. The Python
     * redactor also picks its output format from that same extension, so a
     * disagreement here would produce a file whose contents, name and header
     * were three different formats.
     */
    public function test_every_extension_written_has_a_matching_content_type(): void
    {
        foreach (['scan.tiff', 'photo.jpg', 'diagram.png', 'anim.gif', 'modern.webp'] as $master) {
            $ext = RedactionRenderService::derivativeExtension($master, 'image');

            $this->assertNotSame(
                'application/octet-stream',
                RedactionRenderService::mimeForPath('x.'.$ext),
                "derivativeExtension() writes .{$ext} but mimeForPath() cannot name its type"
            );
        }
    }
}
