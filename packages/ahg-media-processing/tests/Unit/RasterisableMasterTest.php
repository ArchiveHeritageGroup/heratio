<?php

/**
 * RasterisableMasterTest - only ask ImageMagick for a thumbnail of something
 * it could plausibly rasterise.
 *
 * regenerateDerivatives() shelled out to `convert` for every master with a row,
 * whatever it was, and logged two ERRORs per failure - one for the thumbnail,
 * one for the reference. On heratio.org that was 24 files and 164 error lines
 * in a single day, and it is the likeliest reason ahg:cron-run exited non-zero
 * 69 times: the scheduler reports only that the command failed, and the cause
 * sits in a log nobody reads because it is full of these.
 *
 * A .csv has no thumbnail. That is not a failure, it is a fact about CSVs, and
 * recording it as an error is how an error log stops being read.
 *
 * The 24 real files split 20 / 3 / 1: twenty the wrong type, three foreign
 * ciphertext, and exactly one genuine failure - a TIFF that crashes
 * ImageMagick's allocator outright. That one still errors, which is correct.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems. AGPL-3.0-or-later.
 */

namespace AhgMediaProcessing\Tests\Unit;

use AhgMediaProcessing\Services\DerivativeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RasterisableMasterTest extends TestCase
{
    /** Names taken from the files that actually failed on heratio.org. */
    public static function nonRasterisableMasters(): array
    {
        return [
            'plain text' => ['master_readme.txt', 'text/plain'],
            'csv' => ['master_survey_responses.csv', 'text/csv'],
            'transcript' => ['master_interview_01.txt', null],
            'measurements' => ['master_climate_measurements.csv', null],
            'audio' => ['Heratio.m4a', 'audio/mp4'],
            'video by mime' => ['clip.mov', 'video/quicktime'],
            'json' => ['manifest.json', 'application/json'],
            'no extension' => ['masterfile', null],
        ];
    }

    #[DataProvider('nonRasterisableMasters')]
    public function test_a_master_with_no_raster_form_is_not_sent_to_imagemagick(string $name, ?string $mime): void
    {
        $this->assertFalse(DerivativeService::isRasterisable($name, $mime));
    }

    public static function rasterisableMasters(): array
    {
        return [
            'jpeg' => ['engelbrecht-coat-of-arms.jpg'],
            'tiff' => ['marble_statue_ultra_high_res.tiff'],
            'tif' => ['Ceremonies_Performed.tif'],
            'png' => ['diagram.png'],
            'jp2' => ['plate-004.jp2'],
            'pdf' => ['deed-of-gift.pdf'],
            'camera raw' => ['DSC0001.nef'],
            'uppercase' => ['SCAN.TIFF'],
        ];
    }

    #[DataProvider('rasterisableMasters')]
    public function test_a_real_image_master_is_still_attempted(string $name): void
    {
        $this->assertTrue(DerivativeService::isRasterisable($name, null));
    }

    /**
     * webp stays in: ImageMagick here reports `WEBP rw+ (libwebp 1.3.2)`, so it
     * can read them. The one that failed on heratio.org was foreign ciphertext
     * wearing a .webp name, not an unsupported format - checked rather than
     * assumed, because dropping webp on that evidence would have silently
     * stopped generating thumbnails for every genuine webp in the estate.
     */
    public function test_webp_is_not_excluded_on_the_strength_of_one_bad_file(): void
    {
        $this->assertTrue(DerivativeService::isRasterisable('Mobrey_crest.webp', 'image/webp'));
    }

    /**
     * The mime check has to win. A file named .jpg whose stored mime says audio
     * is not an image, and trusting the extension alone would send it to
     * ImageMagick anyway.
     */
    public function test_a_contradicting_mime_type_beats_the_extension(): void
    {
        $this->assertFalse(DerivativeService::isRasterisable('recording.jpg', 'audio/mpeg'));
        $this->assertFalse(DerivativeService::isRasterisable('page.png', 'text/plain'));
    }

    public function test_a_missing_name_is_not_rasterisable(): void
    {
        $this->assertFalse(DerivativeService::isRasterisable(null, null));
        $this->assertFalse(DerivativeService::isRasterisable('', 'image/jpeg'));
    }
}
