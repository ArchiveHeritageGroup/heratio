<?php

/**
 * OcrExportService - OCR text export in TXT, ALTO, hOCR, PAGE-XML formats.
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

namespace AhgIiifCollection\Services;

use Illuminate\Support\Facades\DB;

/**
 * Exports stored OCR data (iiif_ocr_text + iiif_ocr_block) for an
 * information object in four formats:
 *
 *   - exportTxt       Plain text, paginated with "--- page N ---" markers.
 *   - exportAlto      ALTO 4.x XML (Library of Congress).
 *   - exportHocr      hOCR HTML profile (ocr_page / ocr_carea / ocr_line / ocrx_word).
 *   - exportPageXml   PRImA PAGE-XML 2019-07-15.
 *
 * Each method returns the rendered document as a string, or throws
 * \RuntimeException when no OCR data exists for the requested information
 * object (caller should translate that to HTTP 404).
 *
 * The "object id" passed in is `information_object.id`. The OCR tables
 * key on `iiif_ocr_text.object_id` which is the same value (the
 * information-object id, NOT the polymorphic `object.id` — though in
 * AtoM's inheritance model they coincide).
 *
 * Phase 3 of #665. Does NOT trigger any OCR work; strictly exports
 * what's already on disk in the two tables.
 */
class OcrExportService
{
    private const HOCR_DOCTYPE = '<!DOCTYPE html>';

    /**
     * Concatenate full_text across all iiif_ocr_text rows for the IO,
     * separated by "--- page N ---" markers. Pages are ordered by the
     * iiif_ocr_text row id (ascending) so the natural ingest order is
     * preserved.
     *
     * @throws \RuntimeException when no OCR rows exist for $objectId
     */
    public function exportTxt(int $objectId): string
    {
        $rows = $this->fetchTextRows($objectId);
        if ($rows->isEmpty()) {
            throw new \RuntimeException("No OCR text for information object {$objectId}");
        }

        $parts = [];
        $page = 0;
        foreach ($rows as $row) {
            ++$page;
            $body = (string) ($row->full_text ?? '');
            $parts[] = "--- page {$page} ---";
            $parts[] = $body;
            $parts[] = '';
        }

        return implode("\n", $parts);
    }

    /**
     * Render ALTO 4.x XML. One <Page> per iiif_ocr_text row, one
     * <TextBlock> per distinct page_number within the row's blocks
     * (falls back to a single block when blocks have no page_number),
     * one <TextLine> per block whose block_type is 'line' or
     * 'paragraph'/'region' (treated as one TextLine), and one
     * <String> per word-level block.
     *
     * Word-level blocks set HPOS / VPOS / WIDTH / HEIGHT directly from
     * the iiif_ocr_block columns. CONTENT is the block's text. WC
     * (word confidence) is taken from iiif_ocr_block.confidence /
     * 100 when present.
     *
     * @throws \RuntimeException when no OCR rows exist for $objectId
     */
    public function exportAlto(int $objectId): string
    {
        $rows = $this->fetchTextRows($objectId);
        if ($rows->isEmpty()) {
            throw new \RuntimeException("No OCR text for information object {$objectId}");
        }

        $generated = gmdate('Y-m-d\TH:i:s\Z');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<alto xmlns="http://www.loc.gov/standards/alto/ns-v4#"'
              . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
              . ' xsi:schemaLocation="http://www.loc.gov/standards/alto/ns-v4# http://www.loc.gov/standards/alto/v4/alto-4-2.xsd">' . "\n";
        $xml .= "  <Description>\n";
        $xml .= "    <MeasurementUnit>pixel</MeasurementUnit>\n";
        $xml .= "    <sourceImageInformation>\n";
        $xml .= "      <fileName>heratio-io-{$objectId}</fileName>\n";
        $xml .= "    </sourceImageInformation>\n";
        $xml .= "    <OCRProcessing ID=\"OCR_1\">\n";
        $xml .= "      <ocrProcessingStep>\n";
        $xml .= "        <processingDateTime>{$generated}</processingDateTime>\n";
        $xml .= "        <processingSoftware>\n";
        $xml .= "          <softwareCreator>The Archive and Heritage Group (Pty) Ltd</softwareCreator>\n";
        $xml .= "          <softwareName>Heratio OCR Export</softwareName>\n";
        $xml .= "        </processingSoftware>\n";
        $xml .= "      </ocrProcessingStep>\n";
        $xml .= "    </OCRProcessing>\n";
        $xml .= "  </Description>\n";
        $xml .= "  <Layout>\n";

        $pageSeq = 0;
        foreach ($rows as $row) {
            ++$pageSeq;
            $blocks = $this->fetchBlockRows((int) $row->id);
            $extent = $this->computeExtent($blocks);
            $physId = "PAGE_{$row->id}";
            $xml .= "    <Page ID=\"{$physId}\" PHYSICAL_IMG_NR=\"{$pageSeq}\"";
            $xml .= " WIDTH=\"{$extent['width']}\" HEIGHT=\"{$extent['height']}\">\n";
            $xml .= "      <PrintSpace HPOS=\"0\" VPOS=\"0\""
                  . " WIDTH=\"{$extent['width']}\" HEIGHT=\"{$extent['height']}\">\n";

            // Group blocks by page_number then by line. For simple word-only
            // payloads (the common case from Tesseract's word output) we wrap
            // every word in a single TextBlock / TextLine.
            $byLine = $this->groupBlocksByLine($blocks);
            foreach ($byLine as $lineId => $lineBlocks) {
                $lineExtent = $this->computeExtent(collect($lineBlocks));
                $xml .= "        <TextBlock ID=\"BLOCK_{$row->id}_{$lineId}\""
                      . " HPOS=\"{$lineExtent['hpos']}\" VPOS=\"{$lineExtent['vpos']}\""
                      . " WIDTH=\"{$lineExtent['width']}\" HEIGHT=\"{$lineExtent['height']}\">\n";
                $xml .= "          <TextLine ID=\"LINE_{$row->id}_{$lineId}\""
                      . " HPOS=\"{$lineExtent['hpos']}\" VPOS=\"{$lineExtent['vpos']}\""
                      . " WIDTH=\"{$lineExtent['width']}\" HEIGHT=\"{$lineExtent['height']}\">\n";
                foreach ($lineBlocks as $b) {
                    $content = $this->e((string) ($b->text ?? ''));
                    $wcAttr = $this->confidenceAttr($b->confidence ?? null);
                    $xml .= "            <String ID=\"WORD_{$b->id}\""
                          . " HPOS=\"" . (int) $b->x . "\" VPOS=\"" . (int) $b->y . "\""
                          . " WIDTH=\"" . (int) $b->width . "\" HEIGHT=\"" . (int) $b->height . "\""
                          . " CONTENT=\"{$content}\"{$wcAttr}/>\n";
                }
                $xml .= "          </TextLine>\n";
                $xml .= "        </TextBlock>\n";
            }

            $xml .= "      </PrintSpace>\n";
            $xml .= "    </Page>\n";
        }

        $xml .= "  </Layout>\n";
        $xml .= "</alto>\n";

        return $xml;
    }

    /**
     * Render an hOCR HTML document. Follows the hOCR 1.2 profile:
     *   - <div class="ocr_page" id="page_N" title="image …; bbox 0 0 W H">
     *   - <div class="ocr_carea" id="block_…" title="bbox x y x+w y+h">
     *   - <span class="ocr_line" id="line_…" title="bbox …">
     *   - <span class="ocrx_word" id="word_…" title="bbox x y x+w y+h; x_wconf C">
     *
     * @throws \RuntimeException when no OCR rows exist for $objectId
     */
    public function exportHocr(int $objectId): string
    {
        $rows = $this->fetchTextRows($objectId);
        if ($rows->isEmpty()) {
            throw new \RuntimeException("No OCR text for information object {$objectId}");
        }

        $html = self::HOCR_DOCTYPE . "\n";
        $html .= '<html xmlns="http://www.w3.org/1999/xhtml">' . "\n";
        $html .= "<head>\n";
        $html .= '  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />' . "\n";
        $html .= '  <meta name="ocr-system" content="Heratio OCR Export" />' . "\n";
        $html .= '  <meta name="ocr-capabilities" content="ocr_page ocr_carea ocr_line ocrx_word" />' . "\n";
        $html .= "  <title>OCR for information object {$objectId}</title>\n";
        $html .= "</head>\n";
        $html .= "<body>\n";

        $pageSeq = 0;
        foreach ($rows as $row) {
            ++$pageSeq;
            $blocks = $this->fetchBlockRows((int) $row->id);
            $extent = $this->computeExtent($blocks);
            $pageTitle = "image \"heratio-io-{$objectId}-p{$pageSeq}\"; bbox 0 0 {$extent['width']} {$extent['height']}; ppageno " . ($pageSeq - 1);
            $html .= "  <div class=\"ocr_page\" id=\"page_{$pageSeq}\" title=\"" . $this->e($pageTitle) . "\">\n";

            $byLine = $this->groupBlocksByLine($blocks);
            foreach ($byLine as $lineId => $lineBlocks) {
                $lineExtent = $this->computeExtent(collect($lineBlocks));
                $careaBbox = "bbox {$lineExtent['hpos']} {$lineExtent['vpos']} " . ($lineExtent['hpos'] + $lineExtent['width']) . ' ' . ($lineExtent['vpos'] + $lineExtent['height']);
                $html .= "    <div class=\"ocr_carea\" id=\"block_{$row->id}_{$lineId}\" title=\"{$careaBbox}\">\n";
                $html .= "      <span class=\"ocr_line\" id=\"line_{$row->id}_{$lineId}\" title=\"{$careaBbox}\">";
                $wordSep = '';
                foreach ($lineBlocks as $b) {
                    $x2 = (int) $b->x + (int) $b->width;
                    $y2 = (int) $b->y + (int) $b->height;
                    $title = "bbox " . (int) $b->x . " " . (int) $b->y . " {$x2} {$y2}";
                    if ($b->confidence !== null && $b->confidence !== '') {
                        $title .= '; x_wconf ' . (int) round((float) $b->confidence);
                    }
                    $html .= $wordSep . '<span class="ocrx_word" id="word_' . (int) $b->id . '" title="' . $this->e($title) . '">' . $this->e((string) ($b->text ?? '')) . '</span>';
                    $wordSep = ' ';
                }
                $html .= "</span>\n";
                $html .= "    </div>\n";
            }

            $html .= "  </div>\n";
        }

        $html .= "</body>\n";
        $html .= "</html>\n";

        return $html;
    }

    /**
     * Render PRImA PAGE-XML (2019-07-15 schema). One <Page> per
     * iiif_ocr_text row, one <TextRegion> per line group, one
     * <TextLine> per line, one <Word> per word-level block.
     *
     * Coords use the four-corner polygon form
     *   points="x1,y1 x2,y1 x2,y2 x1,y2"
     * built from each block's x / y / width / height columns.
     *
     * @throws \RuntimeException when no OCR rows exist for $objectId
     */
    public function exportPageXml(int $objectId): string
    {
        $rows = $this->fetchTextRows($objectId);
        if ($rows->isEmpty()) {
            throw new \RuntimeException("No OCR text for information object {$objectId}");
        }

        $generated = gmdate('Y-m-d\TH:i:s');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<PcGts xmlns="http://schema.primaresearch.org/PAGE/gts/pagecontent/2019-07-15"'
              . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
              . ' xsi:schemaLocation="http://schema.primaresearch.org/PAGE/gts/pagecontent/2019-07-15 http://schema.primaresearch.org/PAGE/gts/pagecontent/2019-07-15/pagecontent.xsd">' . "\n";
        $xml .= "  <Metadata>\n";
        $xml .= "    <Creator>Heratio OCR Export</Creator>\n";
        $xml .= "    <Created>{$generated}</Created>\n";
        $xml .= "    <LastChange>{$generated}</LastChange>\n";
        $xml .= "  </Metadata>\n";

        $pageSeq = 0;
        foreach ($rows as $row) {
            ++$pageSeq;
            $blocks = $this->fetchBlockRows((int) $row->id);
            $extent = $this->computeExtent($blocks);
            $xml .= "  <Page imageFilename=\"heratio-io-{$objectId}-p{$pageSeq}\""
                  . " imageWidth=\"{$extent['width']}\" imageHeight=\"{$extent['height']}\">\n";

            $byLine = $this->groupBlocksByLine($blocks);
            foreach ($byLine as $lineId => $lineBlocks) {
                $lineExtent = $this->computeExtent(collect($lineBlocks));
                $regionId = "region_{$row->id}_{$lineId}";
                $xml .= "    <TextRegion id=\"{$regionId}\" type=\"paragraph\">\n";
                $xml .= "      <Coords points=\"" . $this->pointsFromBbox(
                    $lineExtent['hpos'],
                    $lineExtent['vpos'],
                    $lineExtent['width'],
                    $lineExtent['height']
                ) . "\"/>\n";
                $xml .= "      <TextLine id=\"line_{$row->id}_{$lineId}\">\n";
                $xml .= "        <Coords points=\"" . $this->pointsFromBbox(
                    $lineExtent['hpos'],
                    $lineExtent['vpos'],
                    $lineExtent['width'],
                    $lineExtent['height']
                ) . "\"/>\n";

                $lineText = '';
                $wordSep = '';
                foreach ($lineBlocks as $b) {
                    $xml .= "        <Word id=\"word_" . (int) $b->id . "\">\n";
                    $xml .= "          <Coords points=\"" . $this->pointsFromBbox(
                        (int) $b->x,
                        (int) $b->y,
                        (int) $b->width,
                        (int) $b->height
                    ) . "\"/>\n";
                    $xml .= "          <TextEquiv";
                    if ($b->confidence !== null && $b->confidence !== '') {
                        $conf = sprintf('%.4f', max(0.0, min(1.0, ((float) $b->confidence) / 100.0)));
                        $xml .= " conf=\"{$conf}\"";
                    }
                    $xml .= "><Unicode>" . $this->e((string) ($b->text ?? '')) . "</Unicode></TextEquiv>\n";
                    $xml .= "        </Word>\n";
                    $lineText .= $wordSep . (string) ($b->text ?? '');
                    $wordSep = ' ';
                }
                $xml .= "        <TextEquiv><Unicode>" . $this->e($lineText) . "</Unicode></TextEquiv>\n";
                $xml .= "      </TextLine>\n";
                $xml .= "    </TextRegion>\n";
            }

            $xml .= "  </Page>\n";
        }

        $xml .= "</PcGts>\n";

        return $xml;
    }

    /**
     * Fetch iiif_ocr_text rows for the given information_object.id,
     * ordered by ascending row id (ingest order).
     */
    private function fetchTextRows(int $objectId)
    {
        return DB::table('iiif_ocr_text')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->select('id', 'digital_object_id', 'object_id', 'full_text', 'format', 'language', 'confidence')
            ->get();
    }

    /**
     * Fetch iiif_ocr_block rows for the given iiif_ocr_text.id, ordered
     * by page_number then block_order then id (stable reading order).
     */
    private function fetchBlockRows(int $ocrId)
    {
        return DB::table('iiif_ocr_block')
            ->where('ocr_id', $ocrId)
            ->orderBy('page_number')
            ->orderBy('block_order')
            ->orderBy('id')
            ->select('id', 'ocr_id', 'page_number', 'block_type', 'text', 'x', 'y', 'width', 'height', 'confidence', 'block_order')
            ->get();
    }

    /**
     * Compute the bounding box covering a collection of blocks. Returns
     * an associative array with hpos/vpos/width/height keys. Falls back
     * to a 1000x1000 canvas when the collection is empty so that the
     * exported envelope still validates.
     *
     * @param iterable $blocks each item must expose x / y / width / height
     */
    private function computeExtent(iterable $blocks): array
    {
        $minX = null;
        $minY = null;
        $maxX = null;
        $maxY = null;
        foreach ($blocks as $b) {
            $x = (int) $b->x;
            $y = (int) $b->y;
            $w = (int) $b->width;
            $h = (int) $b->height;
            $minX = $minX === null ? $x : min($minX, $x);
            $minY = $minY === null ? $y : min($minY, $y);
            $maxX = $maxX === null ? ($x + $w) : max($maxX, $x + $w);
            $maxY = $maxY === null ? ($y + $h) : max($maxY, $y + $h);
        }
        if ($minX === null) {
            return ['hpos' => 0, 'vpos' => 0, 'width' => 1000, 'height' => 1000];
        }
        return [
            'hpos' => $minX,
            'vpos' => $minY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

    /**
     * Group blocks into lines for envelope structure. The schema only
     * stores word-level (or block-level) bounding boxes, so we cluster
     * by page_number + the integer-quantised y position. Blocks within
     * 12 pixels vertically end up on the same line.
     *
     * Returns array<string,array<object>> keyed by a stable "lineId" so
     * the rendered XML/HTML ids are reproducible.
     */
    private function groupBlocksByLine(iterable $blocks): array
    {
        $lines = [];
        foreach ($blocks as $b) {
            $page = (int) ($b->page_number ?? 1);
            $bucket = (int) floor(((int) $b->y) / 12);
            $key = "p{$page}_y{$bucket}";
            if (!isset($lines[$key])) {
                $lines[$key] = [];
            }
            $lines[$key][] = $b;
        }
        // sort lines top-to-bottom, words left-to-right
        uksort($lines, function ($a, $b) use ($lines) {
            $ya = isset($lines[$a][0]) ? (int) $lines[$a][0]->y : 0;
            $yb = isset($lines[$b][0]) ? (int) $lines[$b][0]->y : 0;
            return $ya <=> $yb;
        });
        foreach ($lines as &$line) {
            usort($line, fn ($p, $q) => ((int) $p->x) <=> ((int) $q->x));
        }
        unset($line);
        return $lines;
    }

    /**
     * Build a four-corner ALTO/PAGE points list from a bounding box.
     */
    private function pointsFromBbox(int $x, int $y, int $w, int $h): string
    {
        $x2 = $x + $w;
        $y2 = $y + $h;
        return "{$x},{$y} {$x2},{$y} {$x2},{$y2} {$x},{$y2}";
    }

    /**
     * Format an ALTO WC attribute fragment from a 0-100 confidence
     * value. Returns an empty string when confidence is missing.
     */
    private function confidenceAttr($confidence): string
    {
        if ($confidence === null || $confidence === '') {
            return '';
        }
        $wc = max(0.0, min(1.0, ((float) $confidence) / 100.0));
        return ' WC="' . sprintf('%.4f', $wc) . '"';
    }

    /**
     * XML-escape a string. Same helper signature as ExportController::e().
     */
    private function e(string $value = null): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
