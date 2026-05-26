<?php

/**
 * OffsiteReplicator - driver factory + GPG wrapper for off-site backups
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

use AhgCore\Services\AhgSettingsService;
use RuntimeException;

/**
 * Resolves the configured off-site driver and (optionally) wraps the
 * outbound payload in GPG symmetric AES256 encryption before handing it
 * to the driver. Operator sets the passphrase once via
 * `ahg_setting.backup_encryption_passphrase`; without it the upload
 * proceeds unencrypted with a clear warning recorded by the calling
 * command.
 *
 * Issue #671 Phase 3.
 */
class OffsiteReplicator
{
    public const DRIVERS = ['s3', 'rsync', 'localfs'];

    public function driver(?string $name = null): OffsiteDriverInterface
    {
        $name = $name ?: (string) config('backup.offsite.driver', 'localfs');
        $cfg = (array) config('backup.offsite.'.$name, []);

        switch ($name) {
            case 's3':
                return new S3OffsiteDriver($cfg);
            case 'rsync':
                return new RsyncOffsiteDriver($cfg);
            case 'localfs':
                return new LocalFsOffsiteDriver($cfg);
            default:
                throw new RuntimeException(
                    "OffsiteReplicator: unknown driver '{$name}'. Valid: ".implode(', ', self::DRIVERS)
                );
        }
    }

    /**
     * Return the passphrase that should be used for GPG symmetric
     * encryption, or null if none has been configured. Reads the
     * `backup_encryption_passphrase` ahg_setting (group=backup).
     */
    public function encryptionPassphrase(): ?string
    {
        $pass = AhgSettingsService::get('backup_encryption_passphrase');
        if (is_string($pass) && $pass !== '') {
            return $pass;
        }
        return null;
    }

    /**
     * GPG-encrypt $localPath with AES256 symmetric using the configured
     * passphrase. Returns the path to the encrypted temp file (caller is
     * responsible for unlinking after push). Returns null when no
     * passphrase is configured - the caller treats null as "push the
     * file as-is, unencrypted, and warn".
     *
     * Requires the `gpg` binary on the host. Heratio installs that
     * configure off-site replication MUST `apt install gnupg` or
     * equivalent; the absence of `gpg` is treated as an explicit
     * configuration error rather than silently downgrading.
     */
    public function encryptIfConfigured(string $localPath): ?string
    {
        $pass = $this->encryptionPassphrase();
        if ($pass === null) {
            return null;
        }

        if (!$this->gpgAvailable()) {
            throw new RuntimeException(
                'OffsiteReplicator: backup_encryption_passphrase is set but the `gpg` binary is not on PATH. '.
                'Install gnupg (apt install gnupg) or clear the passphrase to push unencrypted.'
            );
        }

        $passFile = tempnam(sys_get_temp_dir(), 'ahg-backup-pass-');
        if ($passFile === false) {
            throw new RuntimeException('OffsiteReplicator: cannot create temp passphrase file.');
        }
        // 0600 by default via tempnam; ensure it stays that way.
        chmod($passFile, 0600);
        file_put_contents($passFile, $pass);

        $outFile = $localPath.'.gpg';

        $cmd = sprintf(
            'gpg --batch --yes --symmetric --cipher-algo AES256 --passphrase-file %s -o %s %s 2>&1',
            escapeshellarg($passFile),
            escapeshellarg($outFile),
            escapeshellarg($localPath)
        );
        exec($cmd, $out, $rc);
        @unlink($passFile);

        if ($rc !== 0 || !is_file($outFile)) {
            throw new RuntimeException(
                'OffsiteReplicator: gpg encryption failed (rc='.$rc.'): '.implode("\n", $out)
            );
        }
        return $outFile;
    }

    private function gpgAvailable(): bool
    {
        $rc = 0;
        $out = [];
        @exec('command -v gpg', $out, $rc);
        return $rc === 0 && !empty($out);
    }
}
