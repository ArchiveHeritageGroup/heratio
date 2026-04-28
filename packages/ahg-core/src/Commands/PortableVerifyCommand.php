<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class PortableVerifyCommand extends Command
{
    protected $signature = 'ahg:portable-verify
        {--path= : Path to portable export package (zip or extracted dir)}';

    protected $description = 'Verify portable package integrity (manifest schema + per-file SHA-256 against manifest)';

    public function handle(): int
    {
        $path = (string) $this->option('path');
        if (! $path) { $this->error('--path= required'); return self::FAILURE; }

        $dir = $path;
        $cleanup = false;
        if (is_file($path) && str_ends_with(strtolower($path), '.zip')) {
            $dir = sys_get_temp_dir() . '/portable-verify-' . uniqid();
            mkdir($dir, 0775, true);
            $z = new \ZipArchive;
            if ($z->open($path) !== true) { $this->error('cannot open zip'); return self::FAILURE; }
            $z->extractTo($dir);
            $z->close();
            $cleanup = true;
        }
        if (! is_dir($dir)) { $this->error("not a directory: {$dir}"); return self::FAILURE; }

        $manifestPath = rtrim($dir, '/') . '/manifest.json';
        if (! is_readable($manifestPath)) { $this->error('manifest.json missing'); return self::FAILURE; }
        $m = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($m)) { $this->error('manifest.json malformed'); return self::FAILURE; }

        $required = ['version', 'created_at', 'files'];
        foreach ($required as $k) if (! array_key_exists($k, $m)) { $this->error("manifest missing key: {$k}"); return self::FAILURE; }

        $ok = 0; $bad = 0; $missing = 0;
        foreach (($m['files'] ?? []) as $entry) {
            $rel = (string) ($entry['path'] ?? '');
            $expected = (string) ($entry['sha256'] ?? '');
            $full = rtrim($dir, '/') . '/' . ltrim($rel, '/');
            if (! is_file($full)) { $missing++; $this->line("  MISSING {$rel}"); continue; }
            $actual = hash_file('sha256', $full);
            if ($expected && $actual === $expected) $ok++;
            else { $bad++; $this->line("  CHECKSUM-MISMATCH {$rel}"); }
        }

        $this->info("manifest: " . count($m['files'] ?? []) . " files; ok={$ok} mismatch={$bad} missing={$missing}");

        if ($cleanup) {
            // best-effort cleanup of temp extract dir
            shell_exec('rm -rf ' . escapeshellarg($dir));
        }
        return ($bad === 0 && $missing === 0) ? self::SUCCESS : self::FAILURE;
    }
}
