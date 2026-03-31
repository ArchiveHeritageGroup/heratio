<?php

/**
 * PdfToolsController - Controller for Heratio
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



namespace AhgPdfTools\Controllers;

use AhgPdfTools\Services\PdfTextExtractService;
use AhgPdfTools\Services\TiffPdfMergeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * PDF Tools Controller.
 *
 * Dashboard for PDF text extraction and TIFF/PDF merging.
 * Ported from ahgTiffPdfMergePlugin.
 */
class PdfToolsController extends Controller
{
    public function __construct(
        private readonly PdfTextExtractService $textService,
        private readonly TiffPdfMergeService $mergeService
    ) {}

    /**
     * Dashboard: tool availability, statistics, quick actions.
     */
    public function index()
    {
        $pdftotextAvailable   = $this->textService->isPdftotextAvailable();
        $imageMagickAvailable = $this->mergeService->isImageMagickAvailable();
        $ghostscriptAvailable = $this->mergeService->isPdfASupported();

        $pdftotextVersion   = $this->textService->getPdftotextVersion();
        $imageMagickVersion = $this->mergeService->getImageMagickVersion();
        $ghostscriptVersion = $this->mergeService->getGhostscriptVersion();

        $pdfStats = $this->textService->getStatistics();

        $supportedFormats = $this->mergeService->getSupportedInputFormats();

        return view('ahg-pdf-tools::index', compact(
            'pdftotextAvailable',
            'imageMagickAvailable',
            'ghostscriptAvailable',
            'pdftotextVersion',
            'imageMagickVersion',
            'ghostscriptVersion',
            'pdfStats',
            'supportedFormats',
        ));
    }

    /**
     * Merge form (GET) and merge execution (POST).
     */
    public function merge(Request $request)
    {
        if ($request->isMethod('GET')) {
            $pageSizes    = $this->mergeService->getSupportedPageSizes();
            $pdfaVersions = $this->mergeService->getSupportedPdfAVersions();
            $formats      = $this->mergeService->getSupportedInputFormats();
            $imageMagickAvailable = $this->mergeService->isImageMagickAvailable();
            $ghostscriptAvailable = $this->mergeService->isPdfASupported();

            return view('ahg-pdf-tools::merge', compact(
                'pageSizes',
                'pdfaVersions',
                'formats',
                'imageMagickAvailable',
                'ghostscriptAvailable',
            ));
        }

        // POST: validate and merge
        $request->validate([
            'files'       => 'required|array|min:1',
            'files.*'     => 'required|file|max:102400', // 100 MB per file
            'quality'     => 'nullable|integer|min:0|max:100',
            'dpi'         => 'nullable|integer|min:72|max:600',
            'page_size'   => 'nullable|string|in:' . implode(',', $this->mergeService->getSupportedPageSizes()),
            'orientation' => 'nullable|string|in:portrait,landscape',
            'pdfa'        => 'nullable|boolean',
            'pdfa_version'=> 'nullable|string|in:' . implode(',', $this->mergeService->getSupportedPdfAVersions()),
        ]);

        $uploadedFiles = $request->file('files');
        $tmpInputPaths = [];

        try {
            // Move uploaded files to temp
            foreach ($uploadedFiles as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                $allowed = array_merge($this->mergeService->getSupportedInputFormats(), ['pdf']);

                if (!in_array($ext, $allowed)) {
                    return redirect()->route('pdf-tools.merge')
                        ->with('error', "Unsupported file format: {$ext}");
                }

                $tmpPath = sys_get_temp_dir() . '/heratio_merge_in_' . uniqid() . '.' . $ext;
                $file->move(dirname($tmpPath), basename($tmpPath));
                $tmpInputPaths[] = $tmpPath;
            }

            $outputFilename = 'merged_' . date('Ymd_His') . '.pdf';
            $outputPath = sys_get_temp_dir() . '/' . $outputFilename;

            $options = [
                'quality'     => (int) ($request->input('quality', 90)),
                'dpi'         => (int) ($request->input('dpi', 150)),
                'pageSize'    => $request->input('page_size', 'a4'),
                'orientation' => $request->input('orientation', 'portrait'),
                'pdfa'        => (bool) $request->input('pdfa', false),
                'pdfaVersion' => $request->input('pdfa_version', '2b'),
            ];

            $this->mergeService->merge($tmpInputPaths, $outputPath, $options);

            if (!file_exists($outputPath)) {
                return redirect()->route('pdf-tools.merge')
                    ->with('error', 'Merge completed but output file was not created.');
            }

            // Stream download and clean up
            return response()->download($outputPath, $outputFilename, [
                'Content-Type' => 'application/pdf',
            ])->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            return redirect()->route('pdf-tools.merge')
                ->with('error', 'Merge failed: ' . $e->getMessage());
        } finally {
            // Clean up input temp files
            foreach ($tmpInputPaths as $tmp) {
                if (file_exists($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }

    /**
     * Extract text from an uploaded or existing PDF.
     */
    public function extractText(Request $request)
    {
        $extractedText = null;
        $filename = null;

        // Mode 1: extract from uploaded file
        if ($request->hasFile('pdf_file')) {
            $request->validate([
                'pdf_file' => 'required|file|mimes:pdf|max:102400',
            ]);

            $file = $request->file('pdf_file');
            $filename = $file->getClientOriginalName();
            $tmpPath = sys_get_temp_dir() . '/heratio_extract_' . uniqid() . '.pdf';
            $file->move(dirname($tmpPath), basename($tmpPath));

            try {
                $extractedText = $this->textService->extractText($tmpPath);
            } catch (\Throwable $e) {
                return redirect()->route('pdf-tools.index')
                    ->with('error', 'Text extraction failed: ' . $e->getMessage());
            } finally {
                if (file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        // Mode 2: extract from existing digital object by ID
        if ($request->filled('digital_object_id')) {
            $doId = (int) $request->input('digital_object_id');
            $digitalObject = DB::table('digital_object')->where('id', $doId)->first();

            if (!$digitalObject) {
                return redirect()->route('pdf-tools.index')
                    ->with('error', "Digital object {$doId} not found.");
            }

            $uploadsPath = config('heratio.uploads_path', '/mnt/nas/heratio/archive');
            $filePath = rtrim($uploadsPath, '/') . '/' . ltrim($digitalObject->path, '/');

            if (!file_exists($filePath)) {
                $filePath = public_path($digitalObject->path);
            }

            $filename = $digitalObject->name ?: basename($digitalObject->path);

            try {
                $extractedText = $this->textService->extractText($filePath);

                // Store in property table for future use
                if ($extractedText !== null) {
                    $this->textService->storeExtractedText($doId, $extractedText, app()->getLocale());
                }
            } catch (\Throwable $e) {
                return redirect()->route('pdf-tools.index')
                    ->with('error', 'Text extraction failed: ' . $e->getMessage());
            }
        }

        if ($extractedText === null && $filename !== null) {
            return redirect()->route('pdf-tools.index')
                ->with('error', 'No text could be extracted from the PDF. It may contain only images (scanned document).');
        }

        if ($extractedText === null) {
            return redirect()->route('pdf-tools.index')
                ->with('error', 'No PDF file provided.');
        }

        return view('ahg-pdf-tools::extract-result', compact('extractedText', 'filename'));
    }

    /**
     * Batch extract text from PDFs.
     */
    public function batchExtractText()
    {
        if (!$this->textService->isPdftotextAvailable()) {
            return redirect()->route('pdf-tools.index')
                ->with('error', 'pdftotext is not installed.');
        }

        try {
            $count = $this->textService->batchExtract([], 50);

            $stats = $this->textService->getStatistics();

            $msg = "Extracted text from {$count} PDFs.";
            if ($stats['remaining_count'] > 0) {
                $msg .= " {$stats['remaining_count']} remaining - run again to continue.";
            } else {
                $msg .= " All PDFs processed.";
            }

            return redirect()->route('pdf-tools.index')
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('pdf-tools.index')
                ->with('error', 'Batch extraction failed: ' . $e->getMessage());
        }
    }
}
