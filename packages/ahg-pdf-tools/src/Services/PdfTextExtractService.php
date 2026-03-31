<?php

/**
 * PdfTextExtractService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PDF Text Extraction Service.
 *
 * Uses pdftotext (poppler-utils) to extract text content from PDF files.
 * Extracted text can be stored in the AtoM property table for search indexing.
 *
 * Ported from ahgTiffPdfMergePlugin.
 */
class PdfTextExtractService
{
    /**
     * Extract text from a PDF file.
     *
     * Uses: pdftotext -enc UTF-8 input.pdf -
     *
     * @return string|null Extracted text or null on failure
     */
    public function extractText(string $pdfPath): ?string
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF file not found: {$pdfPath}");
        }

        if (!$this->isPdftotextAvailable()) {
            throw new \RuntimeException('pdftotext is not installed. Install with: sudo apt install poppler-utils');
        }

        $command = sprintf(
            'pdftotext -enc UTF-8 %s - 2>&1',
            escapeshellarg($pdfPath)
        );

        $output = [];
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            Log::warning("PdfTextExtract: pdftotext failed for {$pdfPath} (code {$returnCode})");
            return null;
        }

        $text = implode("\n", $output);
        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    /**
     * Extract text from a specific page range.
     *
     * @param int $firstPage First page number (1-based)
     * @param int $lastPage  Last page number
     */
    public function extractTextPages(string $pdfPath, int $firstPage, int $lastPage): ?string
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF file not found: {$pdfPath}");
        }

        if (!$this->isPdftotextAvailable()) {
            throw new \RuntimeException('pdftotext is not installed.');
        }

        $command = sprintf(
            'pdftotext -enc UTF-8 -f %d -l %d %s - 2>&1',
            $firstPage,
            $lastPage,
            escapeshellarg($pdfPath)
        );

        $output = [];
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            return null;
        }

        $text = trim(implode("\n", $output));
        return $text !== '' ? $text : null;
    }

    /**
     * Check if pdftotext command is available.
     */
    public function isPdftotextAvailable(): bool
    {
        $result = shell_exec('which pdftotext 2>/dev/null');
        return !empty(trim($result ?? ''));
    }

    /**
     * Get the version of pdftotext.
     */
    public function getPdftotextVersion(): ?string
    {
        if (!$this->isPdftotextAvailable()) {
            return null;
        }

        $output = [];
        exec('pdftotext -v 2>&1', $output);

        foreach ($output as $line) {
            if (preg_match('/pdftotext version (\S+)/', $line, $m)) {
                return $m[1];
            }
            // Some versions print: "pdftotext version X.XX.X"
            if (preg_match('/(\d+\.\d+\.\d+)/', $line, $m)) {
                return $m[1];
            }
        }

        return $output[0] ?? null;
    }

    /**
     * Batch extract text for multiple PDF digital objects.
     *
     * Finds digital objects with mime_type 'application/pdf' that do not
     * yet have extracted text in the property table, extracts the text,
     * and stores it.
     *
     * @param array $digitalObjectIds Specific IDs to process, or empty for auto-detection
     * @return int Count of successfully extracted
     */
    public function batchExtract(array $digitalObjectIds = [], int $limit = 50): int
    {
        if (!$this->isPdftotextAvailable()) {
            throw new \RuntimeException('pdftotext is not installed.');
        }

        $uploadsPath = config('heratio.uploads_path', '/mnt/nas/heratio/archive');

        $query = DB::table('digital_object')
            ->where('mime_type', 'application/pdf')
            ->whereNotNull('path');

        if (!empty($digitalObjectIds)) {
            $query->whereIn('id', $digitalObjectIds);
        } else {
            // Only those without existing extracted text
            $query->whereNotIn('id', function ($sub) {
                $sub->select('object_id')
                    ->from('property')
                    ->where('scope', 'pdf_text_extraction')
                    ->distinct();
            });
        }

        $digitalObjects = $query->limit($limit)->get();
        $successCount = 0;

        foreach ($digitalObjects as $obj) {
            $filePath = rtrim($uploadsPath, '/') . '/' . ltrim($obj->path, '/');

            if (!file_exists($filePath)) {
                $filePath = public_path($obj->path);
            }

            if (!file_exists($filePath)) {
                Log::warning("PdfTextExtract batch: file not found for DO {$obj->id}: {$obj->path}");
                continue;
            }

            try {
                $text = $this->extractText($filePath);

                if ($text !== null) {
                    $this->storeExtractedText($obj->id, $text);
                    $successCount++;
                }
            } catch (\Throwable $e) {
                Log::warning("PdfTextExtract batch: failed for DO {$obj->id}: {$e->getMessage()}");
            }
        }

        return $successCount;
    }

    /**
     * Get extracted text for a digital object.
     */
    public function getExtractedText(int $digitalObjectId): ?string
    {
        $property = DB::table('property')
            ->where('object_id', $digitalObjectId)
            ->where('scope', 'pdf_text_extraction')
            ->where('name', 'extracted_text')
            ->first();

        if (!$property) {
            return null;
        }

        $i18n = DB::table('property_i18n')
            ->where('id', $property->id)
            ->first();

        return $i18n->value ?? null;
    }

    /**
     * Store extracted text in the property table.
     */
    public function storeExtractedText(int $digitalObjectId, string $text, string $culture = 'en'): void
    {
        // Delete any existing extracted text
        $existing = DB::table('property')
            ->where('object_id', $digitalObjectId)
            ->where('scope', 'pdf_text_extraction')
            ->where('name', 'extracted_text')
            ->get();

        foreach ($existing as $prop) {
            DB::table('property_i18n')->where('id', $prop->id)->delete();
            DB::table('property')->where('id', $prop->id)->delete();
            DB::table('object')->where('id', $prop->id)->delete();
        }

        // Create object entry
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitProperty',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create property
        DB::table('property')->insert([
            'id'             => $objectId,
            'object_id'      => $digitalObjectId,
            'name'           => 'extracted_text',
            'scope'          => 'pdf_text_extraction',
            'source_culture' => $culture,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Create i18n entry with the text
        DB::table('property_i18n')->insert([
            'id'      => $objectId,
            'culture' => $culture,
            'value'   => $text,
        ]);
    }

    /**
     * Get statistics for the PDF text extraction dashboard.
     */
    public function getStatistics(): array
    {
        $totalPdfs = DB::table('digital_object')
            ->where('mime_type', 'application/pdf')
            ->whereNotNull('path')
            ->count();

        $extractedCount = DB::table('property')
            ->where('scope', 'pdf_text_extraction')
            ->where('name', 'extracted_text')
            ->distinct('object_id')
            ->count('object_id');

        return [
            'total_pdfs'      => $totalPdfs,
            'extracted_count'  => $extractedCount,
            'remaining_count'  => $totalPdfs - $extractedCount,
        ];
    }
}
