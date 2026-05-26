<?php

/**
 * LocalFsOffsiteDriver - local-filesystem off-site driver (TEST ONLY)
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

use RuntimeException;

/**
 * Local filesystem off-site driver. Copies backup files into a
 * separate local directory. NOT a real off-site solution - it provides
 * zero protection against host loss, fire, or ransomware on the same
 * box. Intended for:
 *
 *   - CI / dev smoke tests of the replication pipeline,
 *   - the documented "this is a sandbox install" path,
 *   - operator dry-runs before pointing at S3/rsync for real.
 *
 * Operators should expect the `backup:replicate` command to print a
 * loud warning when this driver is selected in production.
 *
 * Issue #671 Phase 3.
 */
class LocalFsOffsiteDriver implements OffsiteDriverInterface
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        if (empty($config['path'])) {
            throw new RuntimeException('LocalFsOffsiteDriver: backup.offsite.localfs.path is required.');
        }
        if (!is_dir($config['path'])) {
            // Idempotent: the operator likely just set the config key
            // for the first time. Try to create the directory; if we
            // can't, bail loudly so the failure is obvious before the
            // first push.
            if (!@mkdir($config['path'], 0750, true) && !is_dir($config['path'])) {
                throw new RuntimeException(
                    'LocalFsOffsiteDriver: target path does not exist and could not be created: '.$config['path']
                );
            }
        }
    }

    public function name(): string
    {
        return 'localfs';
    }

    public function push(string $localPath): array
    {
        if (!is_file($localPath)) {
            throw new RuntimeException("LocalFsOffsiteDriver: local file not found: {$localPath}");
        }

        $size = (int) filesize($localPath);
        $sha = hash_file('sha256', $localPath);
        if ($sha === false) {
            throw new RuntimeException("LocalFsOffsiteDriver: failed to hash {$localPath}");
        }

        $remote = rtrim((string) $this->config['path'], '/').'/'.basename($localPath);
        if (!@copy($localPath, $remote)) {
            $err = error_get_last();
            throw new RuntimeException(
                'LocalFsOffsiteDriver: copy failed: '.($err['message'] ?? 'unknown error')
            );
        }

        // Sidecar SHA-256 file alongside the archive so verify() is cheap.
        @file_put_contents($remote.'.sha256', $sha."  ".basename($localPath)."\n");

        return [
            'remote_path' => $remote,
            'size_bytes'  => $size,
            'sha256'      => $sha,
        ];
    }

    public function verify(string $remotePath, string $expectedSha256): bool
    {
        if (!is_file($remotePath)) {
            return false;
        }
        $actual = hash_file('sha256', $remotePath);
        return $actual !== false && hash_equals($expectedSha256, $actual);
    }

    public function pull(string $remotePath, string $localPath): bool
    {
        if (!is_file($remotePath)) {
            return false;
        }
        return @copy($remotePath, $localPath);
    }

    public function list(?\DateTimeInterface $since = null): array
    {
        $path = rtrim((string) $this->config['path'], '/');
        if (!is_dir($path)) {
            return [];
        }
        $out = [];
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            if (str_ends_with($name, '.sha256')) {
                continue;
            }
            $full = $path.'/'.$name;
            if (!is_file($full)) {
                continue;
            }
            $mtime = filemtime($full) ?: 0;
            if ($since !== null && $mtime < $since->getTimestamp()) {
                continue;
            }
            // Read sidecar SHA if present, otherwise hash on demand for
            // honesty in the list view.
            $sha = null;
            $sidecar = $full.'.sha256';
            if (is_file($sidecar)) {
                $line = (string) file_get_contents($sidecar);
                $parts = preg_split('/\s+/', trim($line), 2);
                if (is_array($parts) && !empty($parts[0]) && strlen($parts[0]) === 64) {
                    $sha = strtolower($parts[0]);
                }
            }
            $out[$full] = [
                'size'          => (int) filesize($full),
                'last_modified' => date('c', $mtime),
                'sha256'        => $sha,
            ];
        }
        return $out;
    }
}
