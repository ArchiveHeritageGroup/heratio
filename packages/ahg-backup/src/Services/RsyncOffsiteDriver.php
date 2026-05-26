<?php

/**
 * RsyncOffsiteDriver - SSH/rsync off-site replication driver
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
 * `rsync -avz` over SSH off-site driver. Operator preference for any
 * environment where the off-site target is "another box I own" rather
 * than an object store. Idempotent by virtue of rsync; safe to re-run.
 *
 * Required config (config/backup.php -> offsite.rsync.*):
 *   - host        : remote hostname / IP
 *   - user        : SSH user
 *   - remote_path : absolute path on the remote host (created with
 *                   `rsync --mkpath` if missing)
 *   - ssh_key     : optional path to private key (-i)
 *   - port        : optional SSH port (default 22)
 *   - extra_args  : optional string of extra rsync flags
 *
 * Issue #671 Phase 3.
 */
class RsyncOffsiteDriver implements OffsiteDriverInterface
{
    /** @var array<string,mixed> */
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        foreach (['host', 'user', 'remote_path'] as $req) {
            if (empty($config[$req])) {
                throw new RuntimeException("RsyncOffsiteDriver: backup.offsite.rsync.{$req} is required.");
            }
        }
    }

    public function name(): string
    {
        return 'rsync';
    }

    public function push(string $localPath): array
    {
        if (!is_file($localPath)) {
            throw new RuntimeException("RsyncOffsiteDriver: local file not found: {$localPath}");
        }

        $size = (int) filesize($localPath);
        $sha = hash_file('sha256', $localPath);
        if ($sha === false) {
            throw new RuntimeException("RsyncOffsiteDriver: failed to hash {$localPath}");
        }

        $remoteRel = basename($localPath);
        $remoteFull = rtrim((string) $this->config['remote_path'], '/').'/'.$remoteRel;

        $cmd = $this->buildRsyncCommand($localPath, $remoteFull);
        exec($cmd, $out, $rc);
        if ($rc !== 0) {
            throw new RuntimeException(
                'RsyncOffsiteDriver: rsync exited '.$rc.': '.implode("\n", $out)
            );
        }

        // Drop a sidecar `.sha256` so verify() can re-check without
        // re-streaming the whole archive. Best-effort; failure here is
        // not fatal because verify() also handles the absent-sidecar case.
        $shaTmp = tempnam(sys_get_temp_dir(), 'ahg-backup-sha-');
        if ($shaTmp !== false) {
            file_put_contents($shaTmp, $sha."  ".$remoteRel."\n");
            $shaRemote = $remoteFull.'.sha256';
            $shaCmd = $this->buildRsyncCommand($shaTmp, $shaRemote);
            @exec($shaCmd, $shaOut, $shaRc);
            @unlink($shaTmp);
        }

        return [
            'remote_path' => $remoteFull,
            'size_bytes'  => $size,
            'sha256'      => $sha,
        ];
    }

    public function verify(string $remotePath, string $expectedSha256): bool
    {
        // Prefer the remote sha256sum binary so we don't have to pull
        // multi-GB archives back across the link just to check.
        $sshCmd = $this->sshCommandPrefix().' '.escapeshellarg(
            'sha256sum '.escapeshellarg($remotePath).' 2>/dev/null'
        );
        exec($sshCmd, $out, $rc);
        if ($rc === 0 && !empty($out)) {
            $first = trim((string) $out[0]);
            $parts = preg_split('/\s+/', $first, 2);
            if (is_array($parts) && !empty($parts[0])) {
                return hash_equals(strtolower($expectedSha256), strtolower($parts[0]));
            }
        }

        // Fallback: pull the file and hash it locally.
        $tmp = tempnam(sys_get_temp_dir(), 'ahg-backup-verify-');
        if ($tmp === false) {
            return false;
        }
        try {
            if (!$this->pull($remotePath, $tmp)) {
                return false;
            }
            $actual = hash_file('sha256', $tmp);
            return $actual !== false && hash_equals($expectedSha256, $actual);
        } finally {
            @unlink($tmp);
        }
    }

    public function pull(string $remotePath, string $localPath): bool
    {
        $cmd = $this->buildRsyncCommand($this->remoteSpec($remotePath), $localPath, true);
        exec($cmd, $out, $rc);
        return $rc === 0 && is_file($localPath);
    }

    public function list(?\DateTimeInterface $since = null): array
    {
        // `find ... -printf` is the most portable way to list with sizes
        // and mtimes in one shot. We pull just the metadata, never the
        // bytes.
        $remote = escapeshellarg(rtrim((string) $this->config['remote_path'], '/'));
        $find = "find {$remote} -maxdepth 1 -type f -printf '%p\\t%s\\t%T@\\n'";
        $cmd = $this->sshCommandPrefix().' '.escapeshellarg($find);
        exec($cmd, $out, $rc);

        $result = [];
        if ($rc !== 0) {
            return $result;
        }
        foreach ($out as $line) {
            $parts = explode("\t", $line);
            if (count($parts) < 3) {
                continue;
            }
            [$path, $size, $mtime] = $parts;
            $ts = (int) floatval($mtime);
            if ($since !== null && $ts < $since->getTimestamp()) {
                continue;
            }
            // Skip our own sidecar files when listing.
            if (str_ends_with($path, '.sha256')) {
                continue;
            }
            $result[$path] = [
                'size'          => (int) $size,
                'last_modified' => date('c', $ts),
                'sha256'        => null,
            ];
        }
        return $result;
    }

    /**
     * Build a `rsync -avz` invocation. When $pull is false the first arg
     * is the local source and the second is the remote destination (the
     * remote_path component, NOT a user@host:path spec - this helper
     * adds the user@host: prefix). When $pull is true the direction is
     * reversed.
     */
    private function buildRsyncCommand(string $src, string $dst, bool $pull = false): string
    {
        $ssh = $this->rsyncSshOption();
        $extra = (string) ($this->config['extra_args'] ?? '');

        if ($pull) {
            $srcSpec = $this->remoteSpec($src);
            $dstSpec = escapeshellarg($dst);
        } else {
            $srcSpec = escapeshellarg($src);
            $dstSpec = $this->remoteSpec($dst);
        }

        return sprintf(
            'rsync -avz --mkpath %s -e %s %s %s 2>&1',
            $extra,
            escapeshellarg($ssh),
            $srcSpec,
            $dstSpec
        );
    }

    private function remoteSpec(string $remotePath): string
    {
        return escapeshellarg(
            $this->config['user'].'@'.$this->config['host'].':'.$remotePath
        );
    }

    private function sshCommandPrefix(): string
    {
        $port = (int) ($this->config['port'] ?? 22);
        $key = (string) ($this->config['ssh_key'] ?? '');
        $cmd = 'ssh -o StrictHostKeyChecking=accept-new -p '.$port;
        if ($key !== '') {
            $cmd .= ' -i '.escapeshellarg($key);
        }
        $cmd .= ' '.escapeshellarg($this->config['user'].'@'.$this->config['host']);
        return $cmd;
    }

    private function rsyncSshOption(): string
    {
        $port = (int) ($this->config['port'] ?? 22);
        $key = (string) ($this->config['ssh_key'] ?? '');
        $ssh = 'ssh -o StrictHostKeyChecking=accept-new -p '.$port;
        if ($key !== '') {
            $ssh .= ' -i '.$key; // wrapped in escapeshellarg at caller site
        }
        return $ssh;
    }
}
