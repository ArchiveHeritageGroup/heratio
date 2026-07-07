<?php

/**
 * DipIngestService - orchestrate ingest of an Archivematica DIP into Heratio.
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

namespace AhgArchivematica\Services;

use AhgArchivematica\Services\Mets\MetsParser;
use AhgIngest\Services\IngestService;
use AhgPreservation\Services\PreservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Direction 1 (Archivematica -> Heratio) orchestrator.
 *
 * Given a DIP - either pulled from the Storage Service by UUID, or handed to us
 * as an uploaded tarball / already-extracted directory - this service:
 *
 *   1. downloads (if needed) and unpacks the DIP into a work dir under Heratio
 *      storage config;
 *   2. finds + parses METS.xml (MetsParser);
 *   3. matches it to an information_object (DipMatcher);
 *   4. for each access file, calls IngestService::ingestFile() to create a
 *      digital_object under the matched description (reusing the canonical
 *      ingest path - no reinvention);
 *   5. maps PREMIS fixity/format into ahg-preservation;
 *   6. upserts an am_link row (dip_uuid -> object_id, status = 'linked').
 *
 * Idempotent: if an am_link row already exists for the DIP UUID with
 * status = 'linked', the DIP is skipped.
 */
class DipIngestService
{
    /** Marker title used to find-or-create the long-lived ingest_session. */
    private const SESSION_TITLE = 'Archivematica DIP ingest';

    public function __construct(
        private ArchivematicaSsClient $ss,
        private MetsParser $mets,
        private DipMatcher $matcher,
        private IngestService $ingest,
        private PreservationService $preservation,
    ) {
    }

    /**
     * Pull a DIP from the Storage Service by UUID, then ingest it.
     *
     * @return array<string,mixed> summary
     */
    public function ingestFromSs(string $dipUuid): array
    {
        // Idempotency: bail before we download anything expensive.
        if ($this->alreadyLinked($dipUuid)) {
            return $this->summary($dipUuid, null, 0, 'skipped', 'already linked');
        }

        $workDir = $this->workDir($dipUuid);
        $downloadPath = $workDir . '/download.package';
        $this->ss->downloadPackage($dipUuid, $downloadPath);

        $extractDir = $workDir . '/extracted';
        $this->unpack($downloadPath, $extractDir);
        @unlink($downloadPath);

        return $this->ingestFromPath($extractDir, $dipUuid);
    }

    /**
     * Ingest a DIP from an uploaded tarball (the push endpoint path).
     *
     * @return array<string,mixed> summary
     */
    public function ingestUploadedTarball(string $tarballPath, ?string $dipUuid = null): array
    {
        if (! is_file($tarballPath)) {
            throw new RuntimeException("DIP tarball not found: {$tarballPath}");
        }
        if ($dipUuid !== null && $this->alreadyLinked($dipUuid)) {
            return $this->summary($dipUuid, null, 0, 'skipped', 'already linked');
        }

        $workDir = $this->workDir($dipUuid ?? ('upload-' . substr(sha1($tarballPath . microtime()), 0, 12)));
        $extractDir = $workDir . '/extracted';
        $this->unpack($tarballPath, $extractDir);

        return $this->ingestFromPath($extractDir, $dipUuid);
    }

    /**
     * Core pipeline: parse METS in $extractedDir, match, ingest access files,
     * map PREMIS, write am_link.
     *
     * @return array<string,mixed> summary
     */
    public function ingestFromPath(string $extractedDir, ?string $dipUuid = null): array
    {
        if (! is_dir($extractedDir)) {
            throw new RuntimeException("DIP directory not found: {$extractedDir}");
        }

        $metsPath = $this->findMets($extractedDir);
        if ($metsPath === null) {
            throw new RuntimeException("No METS.xml found under: {$extractedDir}");
        }

        $parsed = $this->mets->parseFile($metsPath);

        // Fall back to the AIP UUID from METS when the caller has no DIP UUID.
        $effectiveUuid = $dipUuid ?: ($parsed['objid'] ?? null);

        if ($effectiveUuid !== null && $this->alreadyLinked($effectiveUuid)) {
            return $this->summary($effectiveUuid, null, 0, 'skipped', 'already linked');
        }

        $objectId = $this->matcher->match($parsed);
        if ($objectId === null) {
            $this->recordLink($effectiveUuid, null, 'unmatched');

            return $this->summary($effectiveUuid, null, 0, 'unmatched',
                'no information_object matched the DIP');
        }

        $sessionId = $this->ensureSession();
        $io = DB::table('information_object')->where('id', $objectId)->first();

        $ingested = 0;
        foreach (($parsed['access_files'] ?? []) as $access) {
            $filePath = $this->resolveAccessFilePath($extractedDir, (string) ($access['href'] ?? ''));
            if ($filePath === null) {
                Log::warning('[ahg-archivematica] access file not found in DIP', [
                    'dip'  => $effectiveUuid,
                    'href' => $access['href'] ?? null,
                ]);
                continue;
            }

            $originalName = basename($filePath);
            $meta = $this->buildIngestMeta($io, $objectId);

            try {
                $result = $this->ingest->ingestFile($sessionId, $filePath, $meta, $originalName);
                $ingested++;
                $this->mapPremisToPreservation(
                    (int) $result['do_id'],
                    (int) $result['io_id'],
                    $access,
                    $parsed['premis'] ?? [],
                    (string) $effectiveUuid,
                );
            } catch (\Throwable $e) {
                Log::error('[ahg-archivematica] ingestFile failed for DIP access file', [
                    'dip'  => $effectiveUuid,
                    'file' => $originalName,
                    'err'  => $e->getMessage(),
                ]);
            }
        }

        $this->recordLink($effectiveUuid, $objectId, 'linked');

        return $this->summary($effectiveUuid, $objectId, $ingested, 'linked');
    }

    // ------------------------------------------------------------------
    // Idempotency + am_link
    // ------------------------------------------------------------------

    private function alreadyLinked(string $uuid): bool
    {
        if ($uuid === '' || ! Schema::hasTable('am_link')) {
            return false;
        }

        return DB::table('am_link')
            ->where('dip_uuid', $uuid)
            ->where('status', 'linked')
            ->exists();
    }

    /**
     * Upsert an am_link row keyed on dip_uuid (which carries a UNIQUE index).
     */
    private function recordLink(?string $dipUuid, ?int $objectId, string $status): void
    {
        if (! Schema::hasTable('am_link')) {
            return;
        }
        $now = now();

        if ($dipUuid === null || $dipUuid === '') {
            // No stable key - insert a bare row so the link is still recorded.
            DB::table('am_link')->insert([
                'object_id'  => $objectId,
                'status'     => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $existing = DB::table('am_link')->where('dip_uuid', $dipUuid)->first();
        if ($existing) {
            DB::table('am_link')->where('id', $existing->id)->update([
                'object_id'  => $objectId ?? $existing->object_id,
                'status'     => $status,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('am_link')->insert([
                'object_id'  => $objectId,
                'dip_uuid'   => $dipUuid,
                'status'     => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Ingest wiring
    // ------------------------------------------------------------------

    /**
     * Find-or-create the long-lived ingest_session that DIP ingests run under.
     * Reuses IngestService::createSession() so every NOT NULL column is filled
     * with sane defaults; keyed by a marker title so re-runs share one session.
     */
    private function ensureSession(): int
    {
        $existing = DB::table('ingest_session')
            ->where('title', self::SESSION_TITLE)
            ->orderBy('id')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        // user_id 0 = system/background actor (ingest_session has no FK on it).
        $sessionId = $this->ingest->createSession(0, [
            'title'       => self::SESSION_TITLE,
            'entity_type' => 'description',
        ]);

        // Tag it as a non-wizard session where the schema supports it so the
        // Ingest wizard UI doesn't surface it as an interactive batch.
        try {
            if (Schema::hasColumn('ingest_session', 'session_kind')) {
                DB::table('ingest_session')->where('id', $sessionId)
                    ->update(['session_kind' => 'archivematica_dip']);
            }
        } catch (\Throwable $e) {
            // best-effort only.
        }

        return (int) $sessionId;
    }

    /**
     * Build the ingestFile() meta so the access file attaches to the *matched*
     * information_object rather than a new record.
     *
     * When the matched IO has an identifier we resolve straight to it via
     * (parent_id, identifier) - ingestFile then attaches the digital_object to
     * the existing description. When it has no identifier we nest the file
     * under the matched record (parent_id = matched id) as a safe, non-
     * destructive fallback.
     *
     * @param object|null $io the matched information_object row
     *
     * @return array<string,mixed>
     */
    private function buildIngestMeta(?object $io, int $objectId): array
    {
        $meta = [
            'repository_id' => $io->repository_id ?? null,
            'culture'       => 'en',
            'merge'         => 'add-sequence',
        ];

        if ($io && ! empty($io->identifier)) {
            $meta['parent_id']  = (int) ($io->parent_id ?? 1);
            $meta['identifier'] = $io->identifier;
        } else {
            // No identifier to resolve on - land the derivative under the
            // matched record itself.
            $meta['parent_id'] = $objectId;
        }

        return $meta;
    }

    // ------------------------------------------------------------------
    // PREMIS -> ahg-preservation
    // ------------------------------------------------------------------

    /**
     * Record the DIP's fixity/format for a freshly-created digital object and
     * log a PREMIS ingestion event. Best-effort: a failure here never fails
     * the ingest itself.
     *
     * @param array<string,mixed>       $access one access_files entry
     * @param array<int,array<string,mixed>> $premis all parsed PREMIS objects
     */
    private function mapPremisToPreservation(
        int $doId,
        int $ioId,
        array $access,
        array $premis,
        string $dipUuid
    ): void {
        try {
            $match = $this->matchPremisForAccess($access, $premis);

            // Prefer PREMIS fixity, fall back to the fileSec CHECKSUM attribute.
            $algorithm = $match['message_digest_algorithm'] ?? $access['checksum_type'] ?? null;
            $digest    = $match['message_digest'] ?? $access['checksum'] ?? null;

            if ($digest && $algorithm && Schema::hasTable('preservation_checksum')) {
                $now = now()->format('Y-m-d H:i:s');
                DB::table('preservation_checksum')->insert([
                    'digital_object_id'   => $doId,
                    'algorithm'           => strtolower((string) $algorithm),
                    'checksum_value'      => (string) $digest,
                    'file_size'           => $match['size'] ?? $access['size'] ?? null,
                    'generated_at'        => $now,
                    'verification_status' => 'pending',
                    'created_at'          => $now,
                ]);
            }

            // Format -> preservation_format + preservation_object_format.
            if (! empty($match['puid']) || ! empty($match['format_name'])) {
                $this->recordFormat($doId, $match);
            }

            $detail = sprintf(
                'Ingested from Archivematica DIP %s (access file %s%s)',
                $dipUuid !== '' ? $dipUuid : 'unknown',
                $access['href'] ?? '',
                ! empty($match['puid']) ? '; PUID ' . $match['puid'] : ''
            );
            $this->preservation->logEvent($doId, $ioId, 'ingestion', $detail, 'success');
        } catch (\Throwable $e) {
            Log::warning('[ahg-archivematica] PREMIS -> preservation mapping failed: ' . $e->getMessage(), [
                'do_id' => $doId,
                'io_id' => $ioId,
            ]);
        }
    }

    /**
     * Best-effort upsert of a format into the preservation registry tables.
     *
     * @param array<string,mixed> $premis
     */
    private function recordFormat(int $doId, array $premis): void
    {
        if (! Schema::hasTable('preservation_object_format')) {
            return;
        }

        $formatId = null;
        if (Schema::hasTable('preservation_format') && ! empty($premis['puid'])) {
            $formatId = DB::table('preservation_format')
                ->where('puid', $premis['puid'])
                ->value('id');
        }

        DB::table('preservation_object_format')->insert(array_filter([
            'digital_object_id'        => $doId,
            'format_id'                => $formatId,
            'detected_format_name'     => $premis['format_name'] ?? null,
            'detected_puid'            => $premis['puid'] ?? null,
            'created_at'               => now()->format('Y-m-d H:i:s'),
        ], static fn ($v) => $v !== null));
    }

    /**
     * Find the PREMIS object entry that describes a given access file, matching
     * on originalName basename or ADMID.
     *
     * @param array<string,mixed>            $access
     * @param array<int,array<string,mixed>> $premis
     *
     * @return array<string,mixed>
     */
    private function matchPremisForAccess(array $access, array $premis): array
    {
        $accessBase = $access['href'] ? basename((string) $access['href']) : null;
        $admid = $access['admid'] ?? null;

        foreach ($premis as $p) {
            if ($admid && ! empty($p['admid']) && $p['admid'] === $admid) {
                return $p;
            }
            if ($accessBase && ! empty($p['original_name'])
                && basename((string) $p['original_name']) === $accessBase) {
                return $p;
            }
        }

        // Single-object DIPs: fall back to the only PREMIS object present.
        return count($premis) === 1 ? $premis[0] : [];
    }

    // ------------------------------------------------------------------
    // Filesystem: work dir, unpack, locate METS + access files
    // ------------------------------------------------------------------

    private function workDir(string $key): string
    {
        $base = (string) config('archivematica.am_work_path', '');
        if ($base === '') {
            $base = rtrim((string) config('heratio.storage_path'), '/') . '/archivematica-work';
        }
        $dir = rtrim($base, '/') . '/' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create DIP work dir: {$dir}");
        }

        return $dir;
    }

    /**
     * Unpack a DIP archive into $destDir. Supports zip, tar(.gz/.bz2) and 7z
     * (via the system 7z binary); an already-extracted directory is copied
     * through untouched.
     */
    private function unpack(string $archivePath, string $destDir): void
    {
        if (! is_dir($destDir) && ! @mkdir($destDir, 0775, true) && ! is_dir($destDir)) {
            throw new RuntimeException("Cannot create extract dir: {$destDir}");
        }

        if (is_dir($archivePath)) {
            // Already a directory - point extraction at it in place.
            $this->recursiveCopy($archivePath, $destDir);

            return;
        }

        $lower = strtolower($archivePath);

        if (str_ends_with($lower, '.zip')) {
            $this->unpackZip($archivePath, $destDir);

            return;
        }

        if (str_ends_with($lower, '.tar')
            || str_ends_with($lower, '.tar.gz') || str_ends_with($lower, '.tgz')
            || str_ends_with($lower, '.tar.bz2')) {
            $this->unpackTar($archivePath, $destDir);

            return;
        }

        if (str_ends_with($lower, '.7z')) {
            $this->unpack7z($archivePath, $destDir);

            return;
        }

        // Unknown extension: probe the magic bytes. Archivematica DIP downloads
        // are commonly .7z; try that, then tar, then zip.
        $magic = (string) @file_get_contents($archivePath, false, null, 0, 6);
        if (str_starts_with($magic, "7z\xBC\xAF\x27\x1C")) {
            $this->unpack7z($archivePath, $destDir);

            return;
        }
        if (str_starts_with($magic, "PK\x03\x04")) {
            $this->unpackZip($archivePath, $destDir);

            return;
        }
        $this->unpackTar($archivePath, $destDir);
    }

    private function unpackZip(string $archivePath, string $destDir): void
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is not installed.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Cannot open DIP zip: {$archivePath}");
        }
        $zip->extractTo($destDir);
        $zip->close();
    }

    private function unpackTar(string $archivePath, string $destDir): void
    {
        if (! class_exists(\PharData::class)) {
            throw new RuntimeException('PHP Phar extension is not available for tar extraction.');
        }
        try {
            $phar = new \PharData($archivePath);
            $phar->extractTo($destDir, null, true);
        } catch (\Throwable $e) {
            throw new RuntimeException("Cannot extract DIP tar {$archivePath}: " . $e->getMessage(), 0, $e);
        }
    }

    private function unpack7z(string $archivePath, string $destDir): void
    {
        $bin = trim((string) @shell_exec('command -v 7z 2>/dev/null'))
            ?: trim((string) @shell_exec('command -v 7za 2>/dev/null'));
        if ($bin === '') {
            throw new RuntimeException(
                'DIP is a .7z archive but no 7z binary is available to extract it.'
            );
        }
        $cmd = escapeshellarg($bin) . ' x -y '
            . escapeshellarg($archivePath) . ' -o' . escapeshellarg($destDir) . ' 2>&1';
        $out = (string) @shell_exec($cmd);
        // 7z leaves nothing extracted on failure; verify.
        $hasEntries = false;
        foreach (new \DirectoryIterator($destDir) as $e) {
            if (! $e->isDot()) {
                $hasEntries = true;
                break;
            }
        }
        if (! $hasEntries) {
            throw new RuntimeException("7z extraction produced no output: {$out}");
        }
    }

    /**
     * Locate the METS document within an extracted DIP. Archivematica names it
     * METS.{uuid}.xml at the DIP root; we also accept a bare METS.xml anywhere.
     */
    private function findMets(string $dir): ?string
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $fallback = null;
        foreach ($it as $f) {
            if (! $f->isFile()) {
                continue;
            }
            $name = $f->getFilename();
            if (preg_match('/^METS\..*\.xml$/i', $name)) {
                return $f->getPathname();
            }
            if (strcasecmp($name, 'METS.xml') === 0) {
                $fallback = $f->getPathname();
            }
        }

        return $fallback;
    }

    /**
     * Resolve an FLocat href (relative to the DIP root) to a real file on disk.
     * Tries the direct join first, then falls back to a recursive basename
     * search (DIP roots are often nested under a top-level folder).
     */
    private function resolveAccessFilePath(string $extractedDir, string $href): ?string
    {
        if ($href === '') {
            return null;
        }

        $direct = rtrim($extractedDir, '/') . '/' . ltrim($href, '/');
        if (is_file($direct)) {
            return $direct;
        }

        $base = basename($href);
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractedDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getFilename() === $base) {
                return $f->getPathname();
            }
        }

        return null;
    }

    private function recursiveCopy(string $src, string $dst): void
    {
        if (! is_dir($dst) && ! @mkdir($dst, 0775, true) && ! is_dir($dst)) {
            throw new RuntimeException("Cannot create dir: {$dst}");
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $target = $dst . '/' . $it->getSubPathName();
            if ($item->isDir()) {
                if (! is_dir($target)) {
                    @mkdir($target, 0775, true);
                }
            } else {
                @copy($item->getPathname(), $target);
            }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function summary(?string $dipUuid, ?int $objectId, int $ingested, string $status, ?string $note = null): array
    {
        return array_filter([
            'dip_uuid'       => $dipUuid,
            'object_id'      => $objectId,
            'files_ingested' => $ingested,
            'status'         => $status,
            'note'           => $note,
        ], static fn ($v) => $v !== null);
    }
}
