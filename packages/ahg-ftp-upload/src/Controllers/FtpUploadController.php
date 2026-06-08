<?php

/**
 * FtpUploadController - Controller for Heratio
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

namespace AhgFtpUpload\Controllers;

use AhgFtpUpload\Services\FtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FtpUploadController extends Controller
{
    /** Chunk size must match JS: 10 MB */
    const CHUNK_SIZE = 10 * 1024 * 1024;

    /** Temp directory for chunk assembly */
    const CHUNK_DIR = '/tmp/ahg_ftp_chunks';

    /**
     * Main page: upload zone + file listing.
     * Menu path: ftpUpload/index
     */
    public function index()
    {
        $svc = FtpService::fromSettings();
        $configured = $svc->isConfigured();
        $remotePath = $svc->getRemotePath();
        $files = [];
        $listError = null;

        // Load settings for display
        $settings = [];
        try {
            $rows = DB::table('ahg_settings')
                ->where('setting_group', 'ftp')
                ->get(['setting_key', 'setting_value']);
            foreach ($rows as $row) {
                $settings[$row->setting_key] = $row->setting_value;
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        $diskPath = $settings['ftp_disk_path'] ?? $remotePath;
        $folders = [];

        if ($configured) {
            $listResult = $svc->listFiles();
            $files = $listResult['success'] ? ($listResult['files'] ?? []) : [];
            $folders = $listResult['success'] ? ($listResult['folders'] ?? []) : [];
            $listError = $listResult['success'] ? null : ($listResult['message'] ?? 'Connection failed');
        }

        return view('ahg-ftp-upload::index', [
            'title' => 'FTP Upload',
            'configured' => $configured,
            'remotePath' => $remotePath,
            'diskPath' => $diskPath,
            'files' => $files,
            'folders' => $folders,
            'listError' => $listError,
            'protocol' => $settings['ftp_protocol'] ?? 'sftp',
            'chunkSize' => self::CHUNK_SIZE,
        ]);
    }

    /**
     * Combine an uploaded folder into a PDF/A (background). Mirrors the AtoM
     * "Combine a folder into PDF/A" button: runs the memory-safe ahg:pdf-combine
     * command on the folder so the web request returns immediately.
     */
    public function combineFolder(Request $request)
    {
        $svc = FtpService::fromSettings();
        $base = rtrim((string) $svc->getRemotePath(), '/');

        $sub = trim((string) $request->input('folder', ''), '/');
        $sub = str_replace('..', '', $sub);
        $folder = $sub !== '' ? $base.'/'.$sub : $base;
        if ($folder === '' || ! is_dir($folder)) {
            return response()->json(['success' => false, 'error' => 'Folder not found']);
        }

        $rec = trim((string) $request->input('record', ''));
        $objId = null;
        if ($rec !== '') {
            $objId = ctype_digit($rec)
                ? (int) $rec
                : (int) \Illuminate\Support\Facades\DB::table('slug')->where('slug', $rec)->value('object_id');
            if (! $objId) {
                return response()->json(['success' => false, 'error' => 'Record not found: '.$rec]);
            }
        }

        // No slug: write the PDF (uniquely named, derived from the folder) into a
        // "_combined" holding area so it can be linked to a record by name later.
        $outOpt = '';
        if (! $objId) {
            $name = $this->sanitizeCombineName($sub !== '' ? basename($sub) : 'combined');
            $combinedDir = $base.'/_combined';
            if (! is_dir($combinedDir)) {
                @mkdir($combinedDir, 0775, true);
            }
            $outPath = $combinedDir.'/'.$name.'_'.substr(md5(uniqid('', true)), 0, 6).'.pdf';
            $outOpt = '--out='.escapeshellarg($outPath);
        }

        // Run the combine in the background (no queue worker required).
        $cmd = sprintf(
            'cd %s && %s artisan ahg:pdf-combine %s %s %s --clear-source >> %s 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($folder),
            $objId ? '--id='.$objId : '',
            $outOpt,
            escapeshellarg(storage_path('logs/pdf-combine.log'))
        );
        @exec($cmd);

        return response()->json([
            'success' => true,
            'message' => 'Combine started in the background. The PDF/A will be created'
                .($objId ? ' and attached to the record.' : ' (link it to a record below once it is ready).'),
        ]);
    }

    /** Sanitise + truncate a combine output base name (long slugs/folders). */
    private function sanitizeCombineName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $name);
        $name = trim(preg_replace('/_+/', '_', $name), '_');
        if (strlen($name) > 80) {
            $name = rtrim(substr($name, 0, 80), '_');
        }

        return $name !== '' ? $name : 'combined';
    }

    /**
     * List combined PDFs in the "_combined" holding area that have not yet been
     * linked to a record - the no-slug "link by name" picker.
     */
    public function readyToLink(Request $request)
    {
        $svc = FtpService::fromSettings();
        $base = rtrim((string) $svc->getRemotePath(), '/');
        $dir = $base.'/_combined';
        $items = [];
        foreach (glob($dir.'/*.pdf') ?: [] as $p) {
            if (! is_file($p)) {
                continue;
            }
            $items[] = [
                'file' => basename($p),
                'name' => basename($p),
                'size_mb' => round((@filesize($p) ?: 0) / 1048576, 2),
            ];
        }

        return response()->json(['success' => true, 'items' => $items]);
    }

    /**
     * Attach an already-combined PDF (from "_combined") to a record chosen by
     * slug - the after-the-fact "link by name" path for no-slug combines.
     */
    public function attachExisting(Request $request)
    {
        $svc = FtpService::fromSettings();
        $base = rtrim((string) $svc->getRemotePath(), '/');

        $file = basename((string) $request->input('file', ''));
        $rec = trim((string) $request->input('slug', ''));
        if ($file === '' || $rec === '') {
            return response()->json(['success' => false, 'error' => 'Missing file or record slug']);
        }

        $path = $base.'/_combined/'.$file;
        if (! is_file($path)) {
            return response()->json(['success' => false, 'error' => 'Combined PDF not found (may already be linked)']);
        }

        $objId = ctype_digit($rec)
            ? (int) $rec
            : (int) DB::table('slug')->where('slug', $rec)->value('object_id');
        if (! $objId) {
            return response()->json(['success' => false, 'error' => 'Record not found: '.$rec]);
        }

        try {
            // upload() moves the file into the uploads tree, so it leaves the
            // holding area automatically once linked.
            $uploaded = new \Illuminate\Http\UploadedFile($path, $file, 'application/pdf', null, true);
            $doId = \AhgCore\Services\DigitalObjectService::upload($objId, $uploaded);
            \AhgCore\Services\DigitalObjectService::generateDerivativesForMaster($doId);

            try {
                \Illuminate\Support\Facades\Artisan::call('ahg:optimize-pdfs', [
                    '--commit' => true, '--id' => $objId, '--min-mb' => 0,
                ]);
            } catch (\Throwable $we) {
                // Web derivative is best-effort.
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => 'Attach failed: '.$e->getMessage()]);
        }

        return response()->json(['success' => true, 'digital_object_id' => $doId]);
    }

    /**
     * Delete all files in the upload folder (clear all).
     */
    public function clearAll(Request $request)
    {
        $svc = FtpService::fromSettings();

        return response()->json($svc->clearAll((string) $request->input('folder', '')));
    }

    /**
     * Handle chunked file upload (AJAX).
     *
     * Each request sends one chunk with metadata:
     *   - file: the chunk blob
     *   - uploadId: unique ID for this upload session (generated client-side)
     *   - chunkIndex: 0-based chunk number
     *   - totalChunks: total number of chunks
     *   - fileName: original filename
     *   - fileSize: total file size in bytes
     *
     * When the last chunk arrives, the file is reassembled and uploaded via FTP/SFTP.
     */
    public function uploadChunk(Request $request)
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'POST required']);
        }

        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('uploadId', ''));
        $chunkIndex = (int) $request->input('chunkIndex', -1);
        $totalChunks = (int) $request->input('totalChunks', 0);
        $fileName = $request->input('fileName', '');
        $fileSize = (int) $request->input('fileSize', 0);
        // Folder-upload: client sends the file's path relative to the dropped
        // folder (e.g. 'session-2026/scans/page-001.tiff'). Empty or absent
        // for plain root-level file uploads, which keep their original behaviour.
        $relativePath = (string) $request->input('relativePath', '');

        if (empty($uploadId) || $chunkIndex < 0 || $totalChunks < 1 || empty($fileName)) {
            return response()->json(['success' => false, 'message' => 'Missing chunk metadata']);
        }

        if (! $request->hasFile('file') || ! $request->file('file')->isValid()) {
            return response()->json(['success' => false, 'message' => 'Chunk upload error']);
        }

        // Create chunk directory for this upload
        $uploadDir = self::CHUNK_DIR.'/'.$uploadId;
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Save chunk to disk
        $chunkPath = $uploadDir.'/chunk_'.str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);
        $request->file('file')->move($uploadDir, 'chunk_'.str_pad($chunkIndex, 6, '0', STR_PAD_LEFT));

        // Save metadata on first chunk
        $metaPath = $uploadDir.'/meta.json';
        if ($chunkIndex === 0) {
            file_put_contents($metaPath, json_encode([
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'totalChunks' => $totalChunks,
                'created' => time(),
            ]));
        }

        // Count received chunks
        $receivedChunks = count(glob($uploadDir.'/chunk_*'));

        // Not all chunks received yet — acknowledge this chunk
        if ($receivedChunks < $totalChunks) {
            return response()->json([
                'success' => true,
                'complete' => false,
                'received' => $receivedChunks,
                'total' => $totalChunks,
            ]);
        }

        // All chunks received — reassemble and upload
        return $this->assembleAndUpload($uploadId, $uploadDir, $fileName, $totalChunks, $relativePath);
    }

    /**
     * Legacy single-file upload (kept for small files / backwards compat).
     */
    public function upload(Request $request)
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'POST required']);
        }

        if (! $request->hasFile('file') || ! $request->file('file')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file uploaded or upload error']);
        }

        $tmpPath = $request->file('file')->getRealPath();
        $originalName = $request->file('file')->getClientOriginalName();

        $svc = FtpService::fromSettings();
        $result = $svc->upload($tmpPath, $originalName);

        return response()->json($result);
    }

    /**
     * AJAX: list remote files in a given subdirectory of the FTP root.
     *
     * `?dir=` (or absent) lists the configured remote root.
     * `?dir=foo/bar` lists files inside that subfolder. Sanitisation is
     * delegated to FtpService::sanitizeRelativePath (.. / hidden / NUL
     * / quotes / heredoc-expansion chars all rejected).
     */
    public function listFiles(Request $request)
    {
        $svc = FtpService::fromSettings();
        $dir = (string) $request->input('dir', '');

        return response()->json($svc->listFiles($dir));
    }

    /**
     * AJAX: delete a remote file (optionally inside a subdirectory).
     *
     * Accepts `dir` alongside `filename` so the listing's per-row delete
     * button works regardless of how deep the user has navigated.
     */
    public function deleteFile(Request $request)
    {
        if (! $request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'POST required']);
        }

        $data = $request->json()->all() ?: [];
        $filename = $data['filename'] ?? $request->input('filename', '');
        $dir = $data['dir'] ?? $request->input('dir', '');

        if (empty($filename)) {
            return response()->json(['success' => false, 'message' => 'No filename specified']);
        }

        $svc = FtpService::fromSettings();

        return response()->json($svc->deleteFile($filename, (string) $dir));
    }

    /**
     * Reassemble chunks into a single file and upload via FTP/SFTP.
     *
     * $relativePath, when present, is the client-side relative path of the
     * file inside the dropped folder (e.g. 'session-2026/scans/page-001.tiff').
     * The basename becomes the remote filename, the dirname becomes the
     * relative folder which FtpService::upload mkdir-p's on demand. Empty
     * means a flat root-level upload (legacy behaviour).
     */
    protected function assembleAndUpload(string $uploadId, string $uploadDir, string $fileName, int $totalChunks, string $relativePath = '')
    {
        $assembledPath = self::CHUNK_DIR.'/'.$uploadId.'_assembled';

        try {
            $out = fopen($assembledPath, 'wb');
            if (! $out) {
                $this->cleanupChunks($uploadDir, $assembledPath);

                return response()->json(['success' => false, 'message' => 'Failed to create assembled file']);
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $uploadDir.'/chunk_'.str_pad($i, 6, '0', STR_PAD_LEFT);
                if (! file_exists($chunkPath)) {
                    fclose($out);
                    $this->cleanupChunks($uploadDir, $assembledPath);

                    return response()->json(['success' => false, 'message' => 'Missing chunk '.$i]);
                }

                $in = fopen($chunkPath, 'rb');
                if (! $in) {
                    fclose($out);
                    $this->cleanupChunks($uploadDir, $assembledPath);

                    return response()->json(['success' => false, 'message' => 'Cannot read chunk '.$i]);
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }

            fclose($out);

            // Split the relative path into dir + filename. Filename always wins
            // over fileName from the form (defence in depth — the form field
            // could be inconsistent with the actual relative-path basename).
            $remoteFilename = $fileName;
            $relativeDir = '';
            if ($relativePath !== '') {
                $relativeFilename = basename($relativePath);
                if ($relativeFilename !== '') {
                    $remoteFilename = $relativeFilename;
                }
                $dir = trim((string) dirname($relativePath), '/.');
                $relativeDir = $dir;
            }

            // Upload assembled file via FTP/SFTP
            $svc = FtpService::fromSettings();
            $result = $svc->upload($assembledPath, $remoteFilename, $relativeDir);

            // Cleanup
            $this->cleanupChunks($uploadDir, $assembledPath);

            return response()->json($result);
        } catch (\Exception $e) {
            $this->cleanupChunks($uploadDir, $assembledPath);

            return response()->json(['success' => false, 'message' => 'Assembly failed: '.$e->getMessage()]);
        }
    }

    /**
     * Clean up chunk directory and assembled file.
     */
    protected function cleanupChunks(string $uploadDir, string $assembledPath = ''): void
    {
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir.'/*');
            if ($files) {
                foreach ($files as $f) {
                    @unlink($f);
                }
            }
            @rmdir($uploadDir);
        }

        if ($assembledPath && file_exists($assembledPath)) {
            @unlink($assembledPath);
        }
    }
}
