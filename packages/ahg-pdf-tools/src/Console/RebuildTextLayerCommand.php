<?php

/**
 * ahg:pdf-rebuild-text-layer - replace a PDF's invisible text layer with
 * better OCR text that has no word positions (heratio#1523).
 *
 * The vision engine (heratio#1522) reads pages far better than Tesseract but
 * returns plain text. This rebuilds the PDF's searchable layer from it:
 *
 *   1. render each page (pdftoppm) and run Tesseract for word boxes (TSV)
 *      and a fallback text-only page
 *   2. take each page's text from --text-dir/NNNN.txt, or read it now with the
 *      vision engine
 *   3. align the words to Tesseract's boxes (bin/rebuild-text-layer.py, which
 *      needs ocrmypdf in --python's environment)
 *   4. overlay the text pages on the original (qpdf), then write PDF, PDF/A-2b
 *      (ocrmypdf --skip-text) and a lossy copy (Ghostscript)
 *
 * Traps found on the Brenthurst build and handled here: linearisation of
 * multi-GB files leaves an invalid table, so --fast-web-view 999999; qpdf
 * --check decodes every image (20+ minutes at 600 ppi), so structure is
 * checked with --show-npages and --show-xref; the page count of the text
 * layer must equal the original's.
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

namespace AhgPdfTools\Console;

use AhgAiServices\Services\OcrService;
use AhgAiServices\Services\VisionOcrService;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class RebuildTextLayerCommand extends Command
{
    protected $signature = 'ahg:pdf-rebuild-text-layer
        {pdf : The PDF whose text layer is rebuilt}
        {--out= : Output folder (default: next to the PDF, "<name> rebuilt")}
        {--text-dir= : Folder of NNNN.txt page texts (default: read each page with the vision engine)}
        {--dpi=300 : Render resolution for OCR and box alignment}
        {--lang=eng : Tesseract language(s) for the word boxes}
        {--python=python3 : Python with ocrmypdf installed (runs the aligner)}
        {--no-pdfa : Skip the PDF/A-2b copy}
        {--no-lossy : Skip the lossy copy}';

    protected $description = 'Rebuild a PDF\'s searchable text layer from position-less OCR text (vision transcripts), and write PDF, PDF/A-2b and lossy copies';

    public function handle(): int
    {
        $pdf = (string) $this->argument('pdf');
        if (! is_file($pdf)) {
            $this->error("No such PDF: {$pdf}");

            return self::FAILURE;
        }
        $name = pathinfo($pdf, PATHINFO_FILENAME);
        $out = rtrim((string) ($this->option('out') ?: dirname($pdf).'/'.$name.' rebuilt'), '/');
        $work = $out.'/work';
        foreach (["{$work}/pages", "{$work}/ocr", "{$work}/text", "{$work}/layer"] as $d) {
            @mkdir($d, 0775, true);
        }
        $dpi = (int) $this->option('dpi');
        $python = (string) $this->option('python');

        $pages = (int) trim($this->sh(['qpdf', '--show-npages', $pdf]));
        $this->info("{$pages} page(s); rendering at {$dpi} dpi");
        $this->sh(['pdftoppm', '-r', (string) $dpi, '-png', $pdf, "{$work}/pages/p"], 7200);
        $images = glob("{$work}/pages/p-*.png") ?: [];
        natsort($images);
        $images = array_values($images);
        if (count($images) !== $pages) {
            $this->error('Rendered '.count($images)." page images for {$pages} pages.");

            return self::FAILURE;
        }

        $vision = $this->option('text-dir') ? null : app(VisionOcrService::class);
        $ocr = app(OcrService::class);
        $bar = $this->output->createProgressBar($pages);
        foreach ($images as $i => $img) {
            $n = sprintf('%04d', $i + 1);
            // Word boxes plus Tesseract's own text-only page, the fallback layer.
            $this->sh(['tesseract', $img, "{$work}/ocr/{$n}", '-l', (string) $this->option('lang'),
                '-c', 'textonly_pdf=1', 'tsv', 'pdf'], 1800);
            if ($vision) {
                $words = $ocr->parseTsv((string) @file_get_contents("{$work}/ocr/{$n}.tsv"))['words'] ?? [];
                $read = $vision->read($img, $words);
                file_put_contents("{$work}/text/{$n}.txt", $read['success'] ? $read['text'] : '');
            } elseif (is_file($src = rtrim((string) $this->option('text-dir'), '/')."/{$n}.txt")) {
                copy($src, "{$work}/text/{$n}.txt");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $aligner = dirname(__DIR__, 2).'/bin/rebuild-text-layer.py';
        $this->line(trim($this->sh([$python, $aligner, '--tsv', "{$work}/ocr", '--text', "{$work}/text",
            '--out', "{$work}/layer", '--dpi', (string) $dpi], 7200, true)));

        $layerPages = glob("{$work}/layer/*.pdf") ?: [];
        sort($layerPages);
        if (count($layerPages) !== $pages) {
            $this->error('The text layer has '.count($layerPages)." pages, the PDF {$pages}.");

            return self::FAILURE;
        }
        $this->sh(array_merge(['qpdf', '--empty', '--pages'], $layerPages, ['--', "{$work}/text.pdf"]), 3600);
        $this->sh(['qpdf', $pdf, '--overlay', "{$work}/text.pdf", '--', "{$work}/overlaid.pdf"], 3600);

        @mkdir("{$out}/PDF", 0775, true);
        $final = "{$out}/PDF/{$name}.pdf";
        $ocrmypdf = [$python, '-m', 'ocrmypdf', '--skip-text', '--optimize', '0', '--fast-web-view', '999999'];
        $this->sh(array_merge($ocrmypdf, ['--output-type', 'pdf', "{$work}/overlaid.pdf", $final]), 7200, true);
        $outputs = [$final];
        if (! $this->option('no-pdfa')) {
            @mkdir("{$out}/PDFA", 0775, true);
            $this->sh(array_merge($ocrmypdf, ['--output-type', 'pdfa-2', $final, $outputs[] = "{$out}/PDFA/{$name} - PDFA.pdf"]), 7200, true);
        }
        if (! $this->option('no-lossy')) {
            @mkdir("{$out}/PDF Lossy", 0775, true);
            $this->sh(['gs', '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER', '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.7',
                '-dDownsampleColorImages=true', '-dColorImageResolution=300', '-dColorImageDownsampleType=/Bicubic',
                '-dAutoFilterColorImages=false', '-dColorImageFilter=/DCTEncode', '-dJPEGQ=70', '-dPassThroughJPEGImages=false',
                '-sOutputFile='.($outputs[] = "{$out}/PDF Lossy/{$name} - lossy.pdf"), $final], 7200);
        }

        foreach ($outputs as $f) {
            $n = (int) trim($this->sh(['qpdf', '--show-npages', $f]));
            $this->sh(['qpdf', '--show-xref', $f]);   // structure check; --check would decode every image
            $this->line(sprintf('%d pages  %s  %s', $n, $this->size($f), $f));
            if ($n !== $pages) {
                $this->error("{$f} has {$n} pages, expected {$pages}.");

                return self::FAILURE;
            }
        }
        $this->info('Done. Check that title pages and spines are searchable: that is where a text layer is most often thin.');

        return self::SUCCESS;
    }

    /** Run a command, return stdout; throws on failure. With $stderr, stderr is returned too. */
    private function sh(array $cmd, int $timeout = 600, bool $stderr = false): string
    {
        $p = new Process($cmd, null, null, null, $timeout);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new \RuntimeException(implode(' ', array_map('escapeshellarg', $cmd))." failed:\n".$p->getErrorOutput());
        }

        return $p->getOutput().($stderr ? $p->getErrorOutput() : '');
    }

    private function size(string $f): string
    {
        $b = (float) @filesize($f);
        foreach (['B', 'KB', 'MB', 'GB'] as $u) {
            if ($b < 1024 || $u === 'GB') {
                return round($b, 1).' '.$u;
            }
            $b /= 1024;
        }

        return '';
    }
}
