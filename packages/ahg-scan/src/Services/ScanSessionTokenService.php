<?php

/**
 * ScanSessionTokenService — Heratio ahg-scan
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lifecycle for scan_session_token rows. A token is bound one-to-one to an
 * ingest_session (session_kind='scan_api'), optionally to an API key + user.
 * Tokens expire 24h after creation by default; callers can override.
 */
class ScanSessionTokenService
{
    public function create(array $defaults, ?int $apiKeyId, ?int $userId, int $ttlHours = 24): array
    {
        $ingestSessionId = DB::table('ingest_session')->insertGetId([
            'user_id' => $userId ?? 1,
            'title' => ($defaults['title'] ?? 'Scan API session') . ' ' . date('Y-m-d H:i'),
            'entity_type' => 'description',
            'sector' => $defaults['sector'] ?? 'archive',
            'standard' => $defaults['standard'] ?? 'isadg',
            'session_kind' => 'scan_api',
            'auto_commit' => (int) ($defaults['auto_commit'] ?? 0),
            'source_ref' => null, // populated below
            'repository_id' => $defaults['repository_id'] ?? null,
            'parent_id' => $defaults['parent_id'] ?? null,
            'status' => 'configure',
            'output_create_records' => 1,
            'derivative_thumbnails' => (int) ($defaults['derivative_thumbnails'] ?? 1),
            'derivative_reference' => (int) ($defaults['derivative_reference'] ?? 1),
            'process_virus_scan' => (int) ($defaults['process_virus_scan'] ?? 1),
            'process_ocr' => (int) ($defaults['process_ocr'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $token = $this->generateToken();
        DB::table('scan_session_token')->insert([
            'token' => $token,
            'ingest_session_id' => $ingestSessionId,
            'api_key_id' => $apiKeyId,
            'user_id' => $userId,
            'status' => 'open',
            'expires_at' => now()->addHours($ttlHours),
            'created_at' => now(),
        ]);

        // Link the token back as source_ref so watchers/admin UI can find it.
        DB::table('ingest_session')->where('id', $ingestSessionId)
            ->update(['source_ref' => 'scan-api:' . $token]);

        return ['token' => $token, 'ingest_session_id' => $ingestSessionId];
    }

    public function find(string $token): ?object
    {
        $row = DB::table('scan_session_token as t')
            ->leftJoin('ingest_session as s', 't.ingest_session_id', '=', 's.id')
            ->where('t.token', $token)
            ->select('t.*', 's.parent_id', 's.repository_id', 's.sector', 's.standard', 's.auto_commit', 's.status as session_status')
            ->first();
        if (!$row) { return null; }
        if ($row->status === 'open' && $row->expires_at && strtotime($row->expires_at) < time()) {
            DB::table('scan_session_token')->where('token', $token)->update(['status' => 'expired']);
            $row->status = 'expired';
        }
        return $row;
    }

    public function commit(string $token): void
    {
        DB::table('scan_session_token')->where('token', $token)->update([
            'status' => 'committed',
            'committed_at' => now(),
        ]);
    }

    public function abandon(string $token): void
    {
        DB::table('scan_session_token')->where('token', $token)->update([
            'status' => 'abandoned',
        ]);
    }

    protected function generateToken(): string
    {
        // 48-char url-safe token
        return rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '=');
    }
}
