<?php

/**
 * FtpUploadController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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

        if ($configured) {
            $listResult = $svc->listFiles();
            $files = $listResult['success'] ? $listResult['files'] : [];
            $listError = $listResult['success'] ? null : ($listResult['message'] ?? 'Connection failed');
        }

        return view('ahg-ftp-upload::index', [
            'title' => 'FTP Upload',
            'configured' => $configured,
            'remotePath' => $remotePath,
            'diskPath' => $diskPath,
            'files' => $files,
            'listError' => $listError,
            'protocol' => $settings['ftp_protocol'] ?? 'sftp',
            'chunkSize' => self::CHUNK_SIZE,
        ]);
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
        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'POST required']);
        }

        $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $request->input('uploadId', ''));
        $chunkIndex = (int) $request->input('chunkIndex', -1);
        $totalChunks = (int) $request->input('totalChunks', 0);
        $fileName = $request->input('fileName', '');
        $fileSize = (int) $request->input('fileSize', 0);

        if (empty($uploadId) || $chunkIndex < 0 || $totalChunks < 1 || empty($fileName)) {
            return response()->json(['success' => false, 'message' => 'Missing chunk metadata']);
        }

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json(['success' => false, 'message' => 'Chunk upload error']);
        }

        // Create chunk directory for this upload
        $uploadDir = self::CHUNK_DIR . '/' . $uploadId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Save chunk to disk
        $chunkPath = $uploadDir . '/chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT);
        $request->file('file')->move($uploadDir, 'chunk_' . str_pad($chunkIndex, 6, '0', STR_PAD_LEFT));

        // Save metadata on first chunk
        $metaPath = $uploadDir . '/meta.json';
        if ($chunkIndex === 0) {
            file_put_contents($metaPath, json_encode([
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'totalChunks' => $totalChunks,
                'created' => time(),
            ]));
        }

        // Count received chunks
        $receivedChunks = count(glob($uploadDir . '/chunk_*'));

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
        return $this->assembleAndUpload($uploadId, $uploadDir, $fileName, $totalChunks);
    }

    /**
     * Legacy single-file upload (kept for small files / backwards compat).
     */
    public function upload(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'POST required']);
        }

        if (!$request->hasFile('file') || !$request->file('file')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file uploaded or upload error']);
        }

        $tmpPath = $request->file('file')->getRealPath();
        $originalName = $request->file('file')->getClientOriginalName();

        $svc = FtpService::fromSettings();
        $result = $svc->upload($tmpPath, $originalName);

        return response()->json($result);
    }

    /**
     * AJAX: list remote files.
     */
    public function listFiles()
    {
        $svc = FtpService::fromSettings();

        return response()->json($svc->listFiles());
    }

    /**
     * AJAX: delete a remote file.
     */
    public function deleteFile(Request $request)
    {
        if (!$request->isMethod('post')) {
            return response()->json(['success' => false, 'message' => 'POST required']);
        }

        $data = $request->json()->all();
        $filename = $data['filename'] ?? $request->input('filename', '');

        if (empty($filename)) {
            return response()->json(['success' => false, 'message' => 'No filename specified']);
        }

        $svc = FtpService::fromSettings();

        return response()->json($svc->deleteFile($filename));
    }

    /**
     * Reassemble chunks into a single file and upload via FTP/SFTP.
     */
    protected function assembleAndUpload(string $uploadId, string $uploadDir, string $fileName, int $totalChunks)
    {
        $assembledPath = self::CHUNK_DIR . '/' . $uploadId . '_assembled';

        try {
            $out = fopen($assembledPath, 'wb');
            if (!$out) {
                $this->cleanupChunks($uploadDir, $assembledPath);

                return response()->json(['success' => false, 'message' => 'Failed to create assembled file']);
            }

            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkPath = $uploadDir . '/chunk_' . str_pad($i, 6, '0', STR_PAD_LEFT);
                if (!file_exists($chunkPath)) {
                    fclose($out);
                    $this->cleanupChunks($uploadDir, $assembledPath);

                    return response()->json(['success' => false, 'message' => 'Missing chunk ' . $i]);
                }

                $in = fopen($chunkPath, 'rb');
                if (!$in) {
                    fclose($out);
                    $this->cleanupChunks($uploadDir, $assembledPath);

                    return response()->json(['success' => false, 'message' => 'Cannot read chunk ' . $i]);
                }

                stream_copy_to_stream($in, $out);
                fclose($in);
            }

            fclose($out);

            // Upload assembled file via FTP/SFTP
            $svc = FtpService::fromSettings();
            $result = $svc->upload($assembledPath, $fileName);

            // Cleanup
            $this->cleanupChunks($uploadDir, $assembledPath);

            return response()->json($result);
        } catch (\Exception $e) {
            $this->cleanupChunks($uploadDir, $assembledPath);

            return response()->json(['success' => false, 'message' => 'Assembly failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Clean up chunk directory and assembled file.
     */
    protected function cleanupChunks(string $uploadDir, string $assembledPath = ''): void
    {
        if (is_dir($uploadDir)) {
            $files = glob($uploadDir . '/*');
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
