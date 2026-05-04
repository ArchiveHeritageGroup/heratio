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
     *
     * $relativeDir, when non-empty, is a sanitised path relative to
     * $this->remotePath (no leading slash, no '..', no empty/hidden segments).
     * Used by the folder-upload path so dragged folder hierarchies survive
     * onto the FTP/SFTP target. Intermediate directories are created on demand.
     */
    public function upload(string $localPath, string $remoteFilename, string $relativeDir = ''): array
    {
        $remoteFilename = $this->sanitizeFilename($remoteFilename);
        if ($remoteFilename === '') {
            return ['success' => false, 'message' => 'Invalid filename'];
        }

        $remoteDir = $this->remotePath;
        if ($relativeDir !== '') {
            $relativeDir = $this->sanitizeRelativePath($relativeDir);
            if ($relativeDir === '') {
                return ['success' => false, 'message' => 'Invalid folder path'];
            }
            $remoteDir = $this->remotePath . '/' . $relativeDir;
            $mk = $this->ensureRemoteDir($remoteDir);
            if (!$mk['success']) {
                return $mk;
            }
        }

        $remoteFull = $remoteDir . '/' . $remoteFilename;

        if ($this->protocol === 'sftp') {
            return $this->sftpUpload($localPath, $remoteFull);
        }

        return $this->ftpUpload($localPath, $remoteFull);
    }

    /**
     * Ensure a remote directory exists (mkdir -p semantics).
     *
     * SFTP: runs a single `ssh ... mkdir -p` invocation.
     * FTP:  walks each segment, attempting ftp_mkdir, ignoring "already exists".
     *
     * $remoteAbs must be the full absolute path (already prefixed with
     * $this->remotePath by the caller).
     */
    protected function ensureRemoteDir(string $remoteAbs): array
    {
        if ($this->protocol === 'sftp') {
            return $this->sftpEnsureDir($remoteAbs);
        }

        return $this->ftpEnsureDir($remoteAbs);
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

    /**
     * FTP mkdir-p — walks each segment of the relative path beneath
     * $this->remotePath and creates it if missing. Ignores already-exists.
     */
    protected function ftpEnsureDir(string $remoteAbs): array
    {
        $result = $this->connectFtp();
        if (!$result['success']) {
            return $result;
        }

        $base = rtrim($this->remotePath, '/');
        $rel = ltrim(substr($remoteAbs, strlen($base)), '/');
        $segments = explode('/', $rel);
        $cursor = $base;
        foreach ($segments as $seg) {
            if ($seg === '') {
                continue;
            }
            $cursor .= '/' . $seg;
            // If the dir already exists, ftp_chdir will succeed silently.
            if (@ftp_chdir($this->ftpConn, $cursor)) {
                continue;
            }
            // Otherwise create it. ftp_mkdir returns false on "already exists" too,
            // so we re-test with chdir afterwards.
            @ftp_mkdir($this->ftpConn, $cursor);
            if (!@ftp_chdir($this->ftpConn, $cursor)) {
                $this->disconnect();

                return ['success' => false, 'message' => "Cannot create remote folder: {$cursor}"];
            }
        }
        $this->disconnect();

        return ['success' => true, 'message' => 'Folders ready'];
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

    /**
     * SFTP mkdir-p via interactive sftp + heredoc.
     *
     * Two earlier approaches didn't work:
     *   1. `ssh ... mkdir -p` — rejected by sftp-only servers
     *      ("This service allows sftp connections only.")
     *   2. `sftp -b <batchfile>` — sets BatchMode=yes implicitly which
     *      forbids password auth (forces pubkey), so sshpass can't feed
     *      the password and we get "Permission denied (publickey,password)".
     *
     * The pattern that DOES work — and that the existing sftpListFiles uses —
     * is interactive sftp piped via heredoc. sshpass intercepts the password
     * prompt; sftp processes the commands as if a user typed them.
     *
     * SFTP commands used:
     *   -mkdir <path>   →  mkdir but IGNORE failure (leading dash).
     *                     Per-segment, so already-existing parents don't
     *                     abort. Trailing `bye` ensures clean exit.
     *
     * We don't run a final `ls` here — the existing sftpUpload (scp) that
     * follows will fail loudly if the destination dir doesn't exist, and
     * that error reaches the user with the right context.
     *
     * Quoting: paths are wrapped in double-quotes inside the SFTP commands.
     * The PHP heredoc terminator is unquoted so {$cumulative} interpolates,
     * but that means bash also expands $VAR and `cmd` in the body — which is
     * why sanitizeRelativePath blocks $ and ` (defence in depth). The base
     * $this->remotePath is from settings (trusted) and not user-influenced.
     */
    protected function sftpEnsureDir(string $remoteAbs): array
    {
        $base = rtrim($this->remotePath, '/');
        if (!str_starts_with($remoteAbs, $base)) {
            return ['success' => false, 'message' => 'Path is not under remote root'];
        }
        $rel = ltrim(substr($remoteAbs, strlen($base)), '/');
        if ($rel === '') {
            return ['success' => true, 'message' => 'Already at root'];
        }

        $segments = explode('/', $rel);
        $cumulative = $base;
        $lines = [];
        foreach ($segments as $seg) {
            if ($seg === '') {
                continue;
            }
            $cumulative .= '/' . $seg;
            $lines[] = '-mkdir "' . $cumulative . '"';
        }
        $lines[] = 'bye';
        $body = implode("\n", $lines);

        $prefix = $this->sshpassPrefix();
        $opts = $this->sshOpts();
        $port = (int) $this->port;
        $userHost = escapeshellarg($this->username . '@' . $this->host);

        $cmd = "{$prefix} sftp -P {$port} {$opts} {$userHost} 2>&1 <<SFTPEOF\n{$body}\nSFTPEOF";

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        // Filter informational SSH lines (host-key notices, the sftp prompt
        // echo) from any user-facing error message.
        $informational = function (string $line): bool {
            $l = trim($line);
            if ($l === '') return true;
            if (stripos($l, 'Permanently added') !== false) return true;
            if (stripos($l, 'Warning: ') === 0) return true;
            if (stripos($l, 'Connected to ') === 0) return true;
            if (stripos($l, 'sftp>') === 0) return true;
            return false;
        };

        // sftp interactive returns 0 even when -mkdir lines fail (the dash
        // suppresses errors). What we DO want to catch is connection-level
        // failure. Detect by checking exit code AND the absence of "Connected".
        $connected = false;
        foreach ($output as $line) {
            if (stripos(trim($line), 'Connected to ') === 0) {
                $connected = true;
                break;
            }
        }
        if (!$connected) {
            $real = array_values(array_filter($output, fn ($line) => !$informational($line)));
            return [
                'success' => false,
                'message' => 'Cannot create remote folder: ' . trim(implode(' ', $real ?: $output)),
            ];
        }

        // For per-segment mkdir failures (e.g. permission denied creating a
        // brand-new dir), sftp prints "Couldn't create directory: ..." but
        // the dash form swallows the exit code. Surface those if seen — but
        // ignore "already exists" / "File exists" since that's the happy path.
        $errorLines = [];
        foreach ($output as $line) {
            $l = trim($line);
            if ($informational($l)) continue;
            if (stripos($l, 'File exists') !== false) continue;
            if (stripos($l, 'already exists') !== false) continue;
            if (stripos($l, "Couldn't") !== false || stripos($l, 'Permission denied') !== false ||
                stripos($l, 'remote mkdir') !== false || stripos($l, 'Failure') !== false) {
                $errorLines[] = $l;
            }
        }
        if (!empty($errorLines)) {
            return [
                'success' => false,
                'message' => 'Cannot create remote folder: ' . implode(' ', $errorLines),
            ];
        }

        return ['success' => true, 'message' => 'Folders ready'];
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
     * Sanitise a folder-upload relative path. Allows internal slashes (used
     * to express hierarchy) but rejects any segment that is empty, '.',
     * '..', hidden ('.foo'), contains NUL, or contains a backslash.
     * Returns the canonical 'a/b/c' form or '' if any segment fails.
     */
    protected function sanitizeRelativePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        // Reject NUL, backslash, double-quote (the SFTP commands wrap paths
        // in double-quotes; a literal " would close the quote early and
        // break the batch), CR/LF (could inject extra commands), and $ /
        // backtick (the unquoted bash heredoc the SFTP commands ride through
        // would otherwise expand them as parameter / command substitution).
        if (str_contains($path, "\0") || str_contains($path, '\\') || str_contains($path, '"') ||
            str_contains($path, "\r") || str_contains($path, "\n") ||
            str_contains($path, '$')  || str_contains($path, '`')) {
            return '';
        }
        $path = trim($path, '/');
        $segments = explode('/', $path);
        $clean = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.' || $seg === '..' || str_starts_with($seg, '.')) {
                return '';
            }
            $clean[] = $seg;
        }
        if (empty($clean)) {
            return '';
        }

        return implode('/', $clean);
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
