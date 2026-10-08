<?php

/**
 * RebuildTextLayerTest - ahg:pdf-rebuild-text-layer end to end (heratio#1523).
 *
 * Needs a Python with ocrmypdf: set HERATIO_OCRMYPDF_PYTHON to it, for example
 * HERATIO_OCRMYPDF_PYTHON=/home/johanpiet/.venvs/pdftools/bin/python. Skipped
 * otherwise (CI has no ocrmypdf).
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

namespace Tests\Feature;

use Tests\TestCase;

class RebuildTextLayerTest extends TestCase
{
    public function test_a_position_less_text_becomes_the_searchable_layer(): void
    {
        $python = getenv('HERATIO_OCRMYPDF_PYTHON');
        if (! $python || ! class_exists(\Imagick::class) || trim((string) shell_exec('command -v qpdf tesseract pdftoppm gs pdftotext | wc -l')) !== '5') {
            $this->markTestSkipped('needs HERATIO_OCRMYPDF_PYTHON, imagick, qpdf, tesseract, pdftoppm, gs and pdftotext');
        }
        $dir = sys_get_temp_dir().'/rtl-'.uniqid();
        mkdir("{$dir}/texts", 0777, true);
        $img = new \Imagick;
        $img->newImage(1275, 1650, 'white');
        $draw = new \ImagickDraw;
        $draw->setFontSize(40);
        $img->annotateImage($draw, 100, 200, 0, 'SECAFORTH HIGHNANDERS');   // what the scan prints, roughly
        $img->setImageFormat('pdf');
        $img->setImageResolution(150, 150);
        $img->writeImage("{$dir}/in.pdf");
        file_put_contents("{$dir}/texts/0001.txt", "SEAFORTH HIGHLANDERS\n");   // the better reading

        $this->artisan('ahg:pdf-rebuild-text-layer', [
            'pdf' => "{$dir}/in.pdf", '--out' => "{$dir}/out", '--text-dir' => "{$dir}/texts",
            '--dpi' => 150, '--python' => $python, '--no-lossy' => true,
        ])->assertExitCode(0);

        $text = (string) shell_exec('pdftotext '.escapeshellarg("{$dir}/out/PDF/in.pdf").' -');
        $this->assertStringContainsString('SEAFORTH HIGHLANDERS', $text);
        $this->assertFileExists("{$dir}/out/PDFA/in - PDFA.pdf");
        shell_exec('rm -rf '.escapeshellarg($dir));
    }
}
