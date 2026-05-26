<?php

/**
 * OffsiteDriverInterface - off-site backup replication driver contract
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

namespace AhgBackup\Services;

/**
 * Off-site replication driver contract. Concrete implementations live
 * alongside this interface (S3OffsiteDriver, RsyncOffsiteDriver,
 * LocalFsOffsiteDriver). The `backup:replicate` command picks one based on
 * `config('backup.offsite.driver')` and never talks to a concrete class
 * directly. Add a new transport by writing a fourth class against this
 * interface and registering it in the factory in
 * `AhgBackupServiceProvider::registerOffsiteDriver()`.
 *
 * Issue #671 Phase 3.
 */
interface OffsiteDriverInterface
{
    /**
     * Upload a local file to the off-site destination.
     *
     * @param string $localPath absolute path to the file on this host
     * @return array{remote_path: string, size_bytes: int, sha256: string}
     *
     * @throws \RuntimeException on any failure - callers must catch
     */
    public function push(string $localPath): array;

    /**
     * Re-verify that the remote object exists and its SHA-256 still
     * matches the value we recorded on push. Implementations may use a
     * server-side hash where the protocol supports it (S3 SHA256
     * checksum), or pull-and-rehash for transports that don't.
     */
    public function verify(string $remotePath, string $expectedSha256): bool;

    /**
     * Download a remote object back to local disk. Used by the future
     * granular-restore work in #671 Phase 4; included on the interface
     * now so every driver carries the same surface.
     */
    public function pull(string $remotePath, string $localPath): bool;

    /**
     * List remote objects, optionally filtered to those modified at or
     * after `$since`. Returns a map keyed by remote path so callers can
     * dedupe against the replication ledger.
     *
     * @return array<string,array{size: int, last_modified: string, sha256: ?string}>
     */
    public function list(?\DateTimeInterface $since = null): array;

    /**
     * Short driver name used in the `ahg_backup_replication.driver`
     * column and in user-facing log lines. One of: s3, rsync, localfs.
     */
    public function name(): string;
}
