<?php

/**
 * ScanApiController — /api/v2/scan/* endpoints
 *
 * Mode B entry point: scanner applications (VueScan, NAPS2, custom scripts)
 * upload files directly via HTTP and Heratio runs the ingest pipeline.
 *
 * All routes require api.auth:scan:write (or logged-in admin session).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Controllers\Api;

use AhgScan\Jobs\ProcessScanFile;
use AhgScan\Services\ScanSessionTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ScanApiController extends Controller
{
    public function __construct(protected ScanSessionTokenService $tokens) {}

    /**
     * GET /api/v2/scan/destinations?q=...&parent=...
     *
     * Autocomplete / browse parents. Returns information objects (minus root)
     * matching the query, limited to 50 rows.
     */
    public function destinations(Request $request): JsonResponse
    {
        $q = (string) $request->get('q', '');
        $parent = (int) $request->get('parent', 0);

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'io.id')
            ->where('io.id', '>', 1)
            ->select('io.id', 'io.identifier', 'io.parent_id', 'i18n.title', 'sl.slug');

        if ($parent > 0) { $query->where('io.parent_id', $parent); }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('io.identifier', 'like', $like)
                  ->orWhere('i18n.title', 'like', $like)
                  ->orWhere('sl.slug', 'like', $like);
            });
        }

        $rows = $query->orderBy('i18n.title')->limit(50)->get();

        return response()->json([
            'success' => true,
            'count' => $rows->count(),
            'data' => $rows->map(fn($r) => [
                'id' => $r->id,
                'parent_id' => $r->parent_id,
                'identifier' => $r->identifier,
                'title' => $r->title,
                'slug' => $r->slug,
            ]),
        ]);
    }

    /**
     * POST /api/v2/scan/sessions
     *
     * Body (JSON): {parent_id, sector, standard, repository_id, auto_commit, ...}
     * Creates an ingest_session (kind=scan_api) + scan_session_token.
     */
    public function createSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => 'nullable|integer|min:1',
            'repository_id' => 'nullable|integer|min:1',
            'sector' => 'nullable|in:archive,library,gallery,museum',
            'standard' => 'nullable|string|max:32',
            'auto_commit' => 'nullable|boolean',
            'title' => 'nullable|string|max:255',
        ]);

        $apiKeyId = $request->attributes->get('api_key_id');
        $userId = $request->attributes->get('api_user_id');

        $result = $this->tokens->create($data, $apiKeyId, $userId);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $result['token'],
                'ingest_session_id' => $result['ingest_session_id'],
                'expires_in_hours' => 24,
                'upload_url' => url('/api/v2/scan/sessions/' . $result['token'] . '/files'),
                'commit_url' => url('/api/v2/scan/sessions/' . $result['token'] . '/commit'),
            ],
        ], 201);
    }

    /**
     * GET /api/v2/scan/sessions/{token}
     *
     * Status + summary of files in this session.
     */
    public function showSession(string $token): JsonResponse
    {
        $session = $this->tokens->find($token);
        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $files = DB::table('ingest_file')
            ->where('session_id', $session->ingest_session_id)
            ->select('id', 'original_name', 'status', 'stage', 'resolved_io_id', 'resolved_do_id', 'error_message', 'created_at', 'completed_at')
            ->orderBy('id')
            ->get();

        $counts = $files->groupBy('status')->map->count();

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $session->token,
                'status' => $session->status,
                'expires_at' => $session->expires_at,
                'ingest_session_id' => $session->ingest_session_id,
                'parent_id' => $session->parent_id,
                'sector' => $session->sector,
                'standard' => $session->standard,
                'auto_commit' => (bool) $session->auto_commit,
                'counts' => $counts,
                'files' => $files,
            ],
        ]);
    }

    /**
     * POST /api/v2/scan/sessions/{token}/files
     *
     * Multipart form-data:
     *   file:     the scan file (required)
     *   sidecar:  optional heratioScan XML (takes precedence over form fields)
     *   metadata: optional JSON blob with identifier/title/etc. for this file
     *
     * Stages the file into {heratio.scan.staging_path}/{token}/<safe-name>
     * and creates an ingest_file row. If the session's auto_commit is set,
     * the processing job is dispatched immediately.
     */
    public function uploadFile(Request $request, string $token): JsonResponse
    {
        $session = $this->tokens->find($token);
        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Session not found'], 404);
        }
        if ($session->status !== 'open') {
            return response()->json(['success' => false, 'error' => 'Session is ' . $session->status], 400);
        }

        $request->validate([
            'file' => 'required|file',
            'sidecar' => 'nullable|file|mimetypes:application/xml,text/xml',
            'metadata' => 'nullable|string',
        ]);

        $maxMb = (int) config('ahg_settings.scan_max_upload_mb', 2048);
        $maxBytes = $maxMb * 1024 * 1024;
        $file = $request->file('file');
        $sidecarUpload = $request->file('sidecar');

        if ($file->getSize() > $maxBytes) {
            return response()->json([
                'success' => false,
                'error' => "File exceeds max upload size ({$maxMb} MB)",
            ], 413);
        }

        $stagingRoot = rtrim(config('heratio.scan.staging_path'), '/') . '/' . $token;
        if (!is_dir($stagingRoot) && !@mkdir($stagingRoot, 0775, true) && !is_dir($stagingRoot)) {
            return response()->json(['success' => false, 'error' => 'Cannot create staging dir'], 500);
        }
        // Ensure the ClamAV daemon (runs as a different user) can read staged
        // files. 0755 on the dir + 0644 on each file is enough without opening
        // anything write-worthy to the world.
        @chmod($stagingRoot, 0755);

        // Use the uploaded file's realPath + copy() rather than Symfony's ->move().
        // ->move() internally calls a mime-type guesser after starting the rename,
        // which trips over some PHP-FPM configurations that clean up the temp file
        // mid-request. copy() is a single syscall and never re-resolves the source.
        $originalName = $file->getClientOriginalName() ?: 'upload.bin';
        $safeName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $originalName);
        $storedPath = $stagingRoot . '/' . $safeName;
        $seq = 1;
        while (file_exists($storedPath)) {
            $storedPath = $stagingRoot . '/' . $seq++ . '_' . $safeName;
        }
        if (!@copy($file->getRealPath(), $storedPath)) {
            return response()->json(['success' => false, 'error' => 'Failed to stage uploaded file'], 500);
        }
        @chmod($storedPath, 0644);

        $sidecarPath = null;
        if ($sidecarUpload) {
            $sidecarName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $sidecarUpload->getClientOriginalName() ?: 'sidecar.xml');
            $sidecarPath = $stagingRoot . '/' . $sidecarName;
            if (!@copy($sidecarUpload->getRealPath(), $sidecarPath)) {
                @unlink($storedPath);
                return response()->json(['success' => false, 'error' => 'Failed to stage sidecar'], 500);
            }
            @chmod($sidecarPath, 0644);
        }

        // Optional inline metadata → treat as lightweight sidecar replacement:
        // write it to a transient JSON file so stageResolveDestination can
        // merge it. For v1 we use it only to seed ingest_file fields.
        $inlineMeta = [];
        if ($request->filled('metadata')) {
            $decoded = json_decode((string) $request->input('metadata'), true);
            if (is_array($decoded)) { $inlineMeta = $decoded; }
        }

        $hash = @hash_file('sha256', $storedPath) ?: null;

        $fileId = DB::table('ingest_file')->insertGetId([
            'session_id' => $session->ingest_session_id,
            'file_type' => 'digital_object',
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'file_size' => filesize($storedPath) ?: null,
            'mime_type' => $file->getMimeType() ?: null,
            'status' => 'pending',
            'source_hash' => $hash,
            'attempts' => 0,
            'sidecar_path' => $sidecarPath,
            'sidecar_json' => $inlineMeta ? json_encode($inlineMeta) : null,
            'created_at' => now(),
        ]);

        $dispatched = false;
        if ($session->auto_commit) {
            ProcessScanFile::dispatch($fileId, null);
            $dispatched = true;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ingest_file_id' => $fileId,
                'auto_dispatched' => $dispatched,
                'status_url' => url('/api/v2/scan/sessions/' . $token),
            ],
        ], 201);
    }

    /**
     * POST /api/v2/scan/sessions/{token}/commit
     *
     * Kick processing for every pending file in the session. Marks the
     * token committed; the underlying ingest_session stays open until the
     * scheduled cleanup sweeps expired tokens.
     */
    public function commit(string $token): JsonResponse
    {
        $session = $this->tokens->find($token);
        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Session not found'], 404);
        }
        if ($session->status !== 'open') {
            return response()->json(['success' => false, 'error' => 'Session is ' . $session->status], 400);
        }

        $pending = DB::table('ingest_file')
            ->where('session_id', $session->ingest_session_id)
            ->whereIn('status', ['pending', 'failed'])
            ->pluck('id');

        foreach ($pending as $id) {
            ProcessScanFile::dispatch((int) $id, null);
        }

        $this->tokens->commit($token);

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'dispatched' => $pending->count(),
                'status_url' => url('/api/v2/scan/sessions/' . $token),
            ],
        ]);
    }

    /**
     * DELETE /api/v2/scan/sessions/{token}
     *
     * Mark session abandoned and delete any staged files that haven't been
     * ingested yet. Already-created IOs are left untouched.
     */
    public function abandon(string $token): JsonResponse
    {
        $session = $this->tokens->find($token);
        if (!$session) {
            return response()->json(['success' => false, 'error' => 'Not found'], 404);
        }

        $pending = DB::table('ingest_file')
            ->where('session_id', $session->ingest_session_id)
            ->whereIn('status', ['pending', 'failed'])
            ->get();

        foreach ($pending as $f) {
            if ($f->stored_path && is_file($f->stored_path)) {
                @unlink($f->stored_path);
            }
            if ($f->sidecar_path && is_file($f->sidecar_path)) {
                @unlink($f->sidecar_path);
            }
            DB::table('ingest_file')->where('id', $f->id)->update([
                'status' => 'quarantined',
                'error_message' => 'Session abandoned',
                'completed_at' => now(),
            ]);
        }

        $this->tokens->abandon($token);

        $stagingRoot = rtrim(config('heratio.scan.staging_path'), '/') . '/' . $token;
        if (is_dir($stagingRoot) && count(@scandir($stagingRoot) ?: []) <= 2) {
            @rmdir($stagingRoot);
        }

        return response()->json(['success' => true, 'data' => ['token' => $token, 'status' => 'abandoned']]);
    }
}
