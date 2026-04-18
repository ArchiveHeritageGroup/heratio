<?php

/**
 * FtpService - Service for Heratio
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



namespace AhgFtpUpload\Services;

use Illuminate\Support\Facades\DB;

/**
 * FTP/SFTP service for uploading digital objects.
 * Migrated from AhgFtpPlugin\Services\FtpService in AtoM.
 *
 * - FTP: Uses PHP ftp_* functions
 * - SFTP: Uses sshpass + sftp/scp commands (ssh2 extension not available)
 */
class FtpService
{
    protected string $protocol;
    protected string $host;
    protected int $port;
    protected string $username;
    protected string $password;
    protected string $remotePath;
    protected bool $passiveMode;

    /** @var resource|null FTP connection */
    protected $ftpConn = null;

    public function __construct(array $config = [])
    {
        $this->protocol = $config['protocol'] ?? 'sftp';
        $this->host = $config['host'] ?? '';
        $this->port = (int) ($config['port'] ?? ($this->protocol === 'sftp' ? 22 : 21));
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->remotePath = rtrim($config['remote_path'] ?? '/uploads', '/');
        $this->passiveMode = ($config['passive_mode'] ?? 'true') === 'true' || $config['passive_mode'] === true;
    }

    /**
     * Create instance from ahg_settings table.
     */
    public static function fromSettings(): self
    {
        $get = function ($key, $default = '') {
            try {
                $row = DB::table('ahg_settings')
                    ->where('setting_key', $key)
                    ->value('setting_value');

                return $row !== null ? $row : $default;
            } catch (\Exception $e) {
                return $default;
            }
        };

        return new self([
            'protocol' => $get('ftp_protocol', 'sftp'),
            'host' => $get('ftp_host', ''),
            'port' => $get('ftp_port', '22'),
            'username' => $get('ftp_username', ''),
            'password' => $get('ftp_password', ''),
            'remote_path' => $get('ftp_remote_path', '/uploads'),
            'passive_mode' => $get('ftp_passive_mode', 'true'),
        ]);
    }

    /**
     * Test connectivity.
     */
    public function testConnection(): array
    {
        if (empty($this->host)) {
            return ['success' => false, 'message' => 'Host is not configured'];
        }

        if ($this->protocol === 'sftp') {
            return $this->testSftp();
        }

        return $this->testFtp();
    }

    /**
     * Upload a local file to the remote server.
     */
    public function upload(string $localPath, string $remoteFilename): array
    {
        $remoteFilename = $this->sanitizeFilename($remoteFilename);
        if ($remoteFilename === '') {
            return ['success' => false, 'message' => 'Invalid filename'];
        }

        $remoteFull = $this->remotePath . '/' . $remoteFilename;

        if ($this->protocol === 'sftp') {
            return $this->sftpUpload($localPath, $remoteFull);
        }

        return $this->ftpUpload($localPath, $remoteFull);
    }

    /**
     * List files in the remote directory.
     */
    public function listFiles(): array
    {
        if (empty($this->host)) {
            return ['success' => false, 'message' => 'Host is not configured', 'files' => []];
        }

        if ($this->protocol === 'sftp') {
            return $this->sftpListFiles();
        }

        return $this->ftpListFiles();
    }

    /**
     * Delete a file from the remote server.
     */
    public function deleteFile(string $filename): array
    {
        $filename = $this->sanitizeFilename($filename);
        if ($filename === '') {
            return ['success' => false, 'message' => 'Invalid filename'];
        }

        $remoteFull = $this->remotePath . '/' . $filename;

        if ($this->protocol === 'sftp') {
            return $this->sftpDelete($remoteFull);
        }

        return $this->ftpDelete($remoteFull);
    }

    /**
     * Disconnect active FTP connections.
     */
    public function disconnect(): void
    {
        if ($this->ftpConn) {
            @ftp_close($this->ftpConn);
            $this->ftpConn = null;
        }
    }

    /**
     * Get the configured remote path (for display in templates).
     */
    public function getRemotePath(): string
    {
        return $this->remotePath;
    }

    /**
     * Check if FTP is configured (has a host set).
     */
    public function isConfigured(): bool
    {
        return !empty($this->host);
    }

    // =========================================================================
    // FTP methods (PHP ftp_* functions)
    // =========================================================================

    protected function connectFtp(): array
    {
        $conn = @ftp_connect($this->host, $this->port, 10);
        if (!$conn) {
            return ['success' => false, 'message' => "Cannot connect to {$this->host}:{$this->port}"];
        }

        if (!@ftp_login($conn, $this->username, $this->password)) {
            @ftp_close($conn);

            return ['success' => false, 'message' => 'FTP login failed — check credentials'];
        }

        if ($this->passiveMode) {
            ftp_pasv($conn, true);
        }

        $this->ftpConn = $conn;

        return ['success' => true, 'message' => 'Connected'];
    }

    protected function testFtp(): array
    {
        $result = $this->connectFtp();
        if (!$result['success']) {
            return $result;
        }

        $list = @ftp_nlist($this->ftpConn, $this->remotePath);
        $this->disconnect();

        if ($list === false) {
            return ['success' => false, 'message' => "Connected but remote path '{$this->remotePath}' is not accessible"];
        }

        return ['success' => true, 'message' => 'Connection successful! Remote path accessible. ' . count($list) . ' file(s) found.'];
    }

    protected function ftpUpload(string $localPath, string $remoteFull): array
    {
        $result = $this->connectFtp();
        if (!$result['success']) {
            return $result;
        }

        $ok = @ftp_put($this->ftpConn, $remoteFull, $localPath, FTP_BINARY);
        $this->disconnect();

        if (!$ok) {
            return ['success' => false, 'message' => 'FTP upload failed'];
        }

        return ['success' => true, 'message' => 'File uploaded successfully'];
    }

    protected function ftpListFiles(): array
    {
        $result = $this->connectFtp();
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'], 'files' => []];
        }

        $rawList = @ftp_rawlist($this->ftpConn, $this->remotePath);
        $this->disconnect();

        if ($rawList === false) {
            return ['success' => false, 'message' => 'Cannot list remote directory', 'files' => []];
        }

        $files = [];
        foreach ($rawList as $line) {
            if (str_starts_with($line, 'd')) {
                continue;
            }
            if (preg_match('/^\S+\s+\d+\s+\S+\s+\S+\s+(\d+)\s+(\w+\s+\d+\s+[\d:]+)\s+(.+)$/', $line, $m)) {
                $name = trim($m[3]);
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $files[] = [
                    'name' => $name,
                    'size' => (int) $m[1],
                    'modified' => $m[2],
                ];
            }
        }

        return ['success' => true, 'files' => $files];
    }

    protected function ftpDelete(string $remoteFull): array
    {
        $result = $this->connectFtp();
        if (!$result['success']) {
            return $result;
        }

        $ok = @ftp_delete($this->ftpConn, $remoteFull);
        $this->disconnect();

        if (!$ok) {
            return ['success' => false, 'message' => 'FTP delete failed'];
        }

        return ['success' => true, 'message' => 'File deleted successfully'];
    }

    // =========================================================================
    // SFTP methods (sshpass + sftp/scp commands)
    // =========================================================================

    protected function sshpassPrefix(): string
    {
        $escaped = escapeshellarg($this->password);

        return "sshpass -p {$escaped}";
    }

    protected function sshOpts(): string
    {
        return '-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10';
    }

    protected function testSftp(): array
    {
        $prefix = $this->sshpassPrefix();
        $opts = $this->sshOpts();
        $userHost = escapeshellarg($this->username . '@' . $this->host);
        $port = (int) $this->port;

        $cmd = "{$prefix} sftp -P {$port} {$opts} {$userHost} 2>&1 <<SFTPEOF
ls {$this->remotePath}
bye
SFTPEOF";

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $error = implode(' ', $output);
            if (stripos($error, 'Permission denied') !== false) {
                return ['success' => false, 'message' => 'SFTP authentication failed — check credentials'];
            }
            if (stripos($error, 'Connection refused') !== false) {
                return ['success' => false, 'message' => "Connection refused on {$this->host}:{$port}"];
            }

            return ['success' => false, 'message' => 'SFTP connection failed: ' . $error];
        }

        return ['success' => true, 'message' => 'SFTP connection successful! Remote path accessible.'];
    }

    protected function sftpUpload(string $localPath, string $remoteFull): array
    {
        $prefix = $this->sshpassPrefix();
        $opts = $this->sshOpts();
        $port = (int) $this->port;
        $local = escapeshellarg($localPath);
        $target = escapeshellarg($this->username . '@' . $this->host . ':' . $remoteFull);

        $cmd = "{$prefix} scp -P {$port} {$opts} {$local} {$target} 2>&1";

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            return ['success' => false, 'message' => 'SFTP upload failed: ' . implode(' ', $output)];
        }

        return ['success' => true, 'message' => 'File uploaded successfully'];
    }

    protected function sftpListFiles(): array
    {
        $prefix = $this->sshpassPrefix();
        $opts = $this->sshOpts();
        $port = (int) $this->port;
        $userHost = escapeshellarg($this->username . '@' . $this->host);

        $cmd = "{$prefix} sftp -P {$port} {$opts} {$userHost} 2>/dev/null <<SFTPEOF\nls -l {$this->remotePath}\nbye\nSFTPEOF";

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            return ['success' => false, 'message' => 'Cannot list remote directory', 'files' => []];
        }

        $files = [];
        foreach ($output as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, 'sftp>') || str_starts_with($line, 'd')) {
                continue;
            }
            if (preg_match('/^-\S+\s+\S+\s+\S+\s+\S+\s+(\d+)\s+(\w+\s+\d+\s+[\d:]+)\s+(.+)$/', $line, $m)) {
                $name = basename(trim($m[3]));
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $files[] = [
                    'name' => $name,
                    'size' => (int) $m[1],
                    'modified' => $m[2],
                ];
            }
        }

        return ['success' => true, 'files' => $files];
    }

    protected function sftpDelete(string $remoteFull): array
    {
        $prefix = $this->sshpassPrefix();
        $opts = $this->sshOpts();
        $port = (int) $this->port;
        $userHost = escapeshellarg($this->username . '@' . $this->host);

        $cmd = "{$prefix} sftp -P {$port} {$opts} {$userHost} 2>&1 <<SFTPEOF\nrm {$remoteFull}\nbye\nSFTPEOF";

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $outputStr = implode(' ', $output);
        if (stripos($outputStr, 'No such file') !== false || stripos($outputStr, 'Couldn\'t') !== false) {
            return ['success' => false, 'message' => 'SFTP delete failed: ' . $outputStr];
        }

        if ($exitCode !== 0) {
            return ['success' => false, 'message' => 'SFTP delete failed: ' . $outputStr];
        }

        return ['success' => true, 'message' => 'File deleted successfully'];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function sanitizeFilename(string $filename): string
    {
        $filename = basename($filename);
        if (str_contains($filename, '..') || str_contains($filename, "\0")) {
            return '';
        }
        if (str_starts_with($filename, '.')) {
            return '';
        }

        return $filename;
    }

    /**
     * Format bytes into human-readable size.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);

        return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
    }
}
