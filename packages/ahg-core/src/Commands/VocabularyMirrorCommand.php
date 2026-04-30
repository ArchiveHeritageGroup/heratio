<?php

/**
 * VocabularyMirrorCommand — fetch an upstream SKOS/RDF vocabulary dump,
 * cache it locally under data/vocabularies/{vocab}.{ext}, and (optionally)
 * chain into ahg:vocabulary-import to load it into Fuseki + prime the cache.
 *
 * Phase 3 of issue #36 / issue #37. Generic across vocabularies — used for
 * LCSH (https://id.loc.gov/download), LCNAF, Getty AAT, and any other
 * upstream that publishes a SKOS dump over HTTP(S).
 *
 * Examples:
 *   php artisan ahg:vocabulary-mirror \
 *     https://id.loc.gov/static/data/authoritiessubjects.skos.nt.gz \
 *     --vocabulary=lcsh --format=ntriples
 *
 *   php artisan ahg:vocabulary-mirror \
 *     https://id.loc.gov/static/data/authoritiesnames.skos.nt.gz \
 *     --vocabulary=lcnaf --format=ntriples --rate-limit=1M
 *
 *   php artisan ahg:vocabulary-mirror data/vocabularies/icip.ttl \
 *     --vocabulary=icip --format=turtle --no-import
 *
 *   php artisan ahg:vocabulary-mirror https://example.org/vocab.ttl \
 *     --vocabulary=foo --format=turtle --dry-run
 *
 * Idempotency: re-running with the same source URL is a no-op when the
 * remote ETag / Last-Modified / Content-Length matches the locally cached
 * mirror. Pass --force to redownload regardless.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class VocabularyMirrorCommand extends Command
{
    protected $signature = 'ahg:vocabulary-mirror
                            {source : URL or local path to RDF/Turtle/RDF-XML/N-Triples (.gz allowed for URLs)}
                            {--vocabulary= : Short name (lcsh, lcnaf, aat, …) — required}
                            {--format=ntriples : RDF format hint passed to vocabulary-import}
                            {--target-dir= : Local mirror dir (default: data/vocabularies/)}
                            {--rate-limit= : Max bytes/sec for download (e.g. 1M, 500k). Honoured via curl --limit-rate.}
                            {--force : Redownload even if local mirror is current}
                            {--no-import : Fetch only — do not chain into ahg:vocabulary-import}
                            {--dry-run : Probe remote + report what would happen, do not write}';

    protected $description = 'Mirror an upstream SKOS/RDF vocabulary dump locally and optionally import it';

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        $vocab = (string) $this->option('vocabulary');
        $format = (string) $this->option('format');
        $targetDir = rtrim((string) ($this->option('target-dir') ?: base_path('data/vocabularies')), '/');
        $rateLimit = (string) $this->option('rate-limit');
        $force = (bool) $this->option('force');
        $noImport = (bool) $this->option('no-import');
        $dryRun = (bool) $this->option('dry-run');

        if ($vocab === '') {
            $this->error('--vocabulary is required (e.g. lcsh, lcnaf, aat, icip)');
            return self::FAILURE;
        }
        if (! preg_match('/^[a-z][a-z0-9-]*$/i', $vocab)) {
            $this->error("--vocabulary must be a short slug (alphanumeric + dashes): got '{$vocab}'");
            return self::FAILURE;
        }

        // Make sure the target dir exists; we don't pre-create vendor dirs in dry-run.
        if (! $dryRun && ! is_dir($targetDir)) {
            if (! mkdir($targetDir, 0775, true) && ! is_dir($targetDir)) {
                $this->error("Could not create target dir: {$targetDir}");
                return self::FAILURE;
            }
        }

        // Local mirror path: data/vocabularies/{vocab}.{ext}. The extension comes
        // from the source URL or path so we round-trip transparently to the import
        // command (which reads the same file).
        $ext = $this->guessExtension($source, $format);
        $mirrorPath = "{$targetDir}/{$vocab}.{$ext}";

        // Source is either an HTTP(S) URL (we fetch + cache) or a local file (we
        // copy/link into the target dir if it isn't already there).
        $isUrl = preg_match('#^https?://#i', $source) === 1;

        $this->line("vocabulary : {$vocab}");
        $this->line("source     : {$source}");
        $this->line("mirror     : {$mirrorPath}");
        $this->line("format     : {$format}");
        $this->line("rate limit : " . ($rateLimit !== '' ? $rateLimit : '(none)'));
        $this->line('mode       : ' . ($isUrl ? 'remote fetch' : 'local copy'));
        $this->newLine();

        if ($isUrl) {
            $needsFetch = $force || $this->remoteIsNewer($source, $mirrorPath);
            if (! $needsFetch) {
                $this->info("Local mirror is current — skipping download (use --force to override).");
            } else {
                if ($dryRun) {
                    $this->warn('DRY RUN — would download ' . $source . ' to ' . $mirrorPath);
                } else {
                    $ok = $this->downloadWithCurl($source, $mirrorPath, $rateLimit);
                    if (! $ok) {
                        return self::FAILURE;
                    }
                }
            }
        } else {
            // Local source — verify it exists and (in real-run) make sure the
            // mirror path points at it. If the source is already inside the
            // target dir, no-op; otherwise copy.
            if (! is_file($source)) {
                $this->error("Local source not found: {$source}");
                return self::FAILURE;
            }
            $absSource = realpath($source);
            $absMirror = realpath($mirrorPath) ?: $mirrorPath;
            if ($absSource !== $absMirror) {
                if ($dryRun) {
                    $this->warn("DRY RUN — would copy {$source} to {$mirrorPath}");
                } else {
                    if (! @copy($source, $mirrorPath)) {
                        $this->error("Copy failed: {$source} → {$mirrorPath}");
                        return self::FAILURE;
                    }
                    $this->info("Copied to {$mirrorPath}");
                }
            } else {
                $this->info("Source already at mirror path; nothing to copy.");
            }
        }

        if ($dryRun) {
            $this->warn('DRY RUN — skipping ahg:vocabulary-import.');
            return self::SUCCESS;
        }

        // Optional uncompress: if the mirror ended up as .gz, decompress to a
        // sibling file the import command can read.
        $importPath = $mirrorPath;
        if (str_ends_with($mirrorPath, '.gz')) {
            $importPath = substr($mirrorPath, 0, -3);
            if (! is_file($importPath) || filemtime($mirrorPath) > filemtime($importPath)) {
                $this->info("Decompressing {$mirrorPath} → {$importPath}");
                if (! $this->gunzip($mirrorPath, $importPath)) {
                    $this->error('Decompression failed.');
                    return self::FAILURE;
                }
            }
        }

        if ($noImport) {
            $this->info('Fetched. Skipping import (--no-import).');
            $this->line("Run: php artisan ahg:vocabulary-import {$importPath} --vocabulary={$vocab} --format={$format}");
            return self::SUCCESS;
        }

        // Chain into the existing import command.
        $this->newLine();
        $this->info('Chaining into ahg:vocabulary-import…');
        $exit = $this->call('ahg:vocabulary-import', [
            'source'       => $importPath,
            '--vocabulary' => $vocab,
            '--format'     => $format,
        ]);
        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * HEAD the remote and decide whether the local mirror is stale. We compare
     * Content-Length first (cheap, almost always present). If both ETag and
     * a stored sidecar `.etag` file are present, that's preferred. If neither
     * tells us, we fall back to "redownload".
     */
    private function remoteIsNewer(string $url, string $mirrorPath): bool
    {
        if (! is_file($mirrorPath)) {
            return true;
        }
        try {
            $resp = Http::timeout(20)->head($url);
        } catch (\Throwable $e) {
            $this->warn("HEAD probe failed ({$e->getMessage()}); assuming stale and redownloading.");
            return true;
        }
        if (! $resp->successful()) {
            $this->warn("HEAD returned HTTP {$resp->status()}; assuming stale and redownloading.");
            return true;
        }
        $remoteLen = (int) ($resp->header('Content-Length') ?: 0);
        $localLen = (int) filesize($mirrorPath);
        if ($remoteLen > 0 && $remoteLen === $localLen) {
            $remoteEtag = trim((string) $resp->header('ETag'));
            $sidecar = $mirrorPath . '.etag';
            $localEtag = is_file($sidecar) ? trim((string) file_get_contents($sidecar)) : '';
            if ($remoteEtag === '' || $localEtag === '' || $remoteEtag === $localEtag) {
                return false;
            }
        }
        return true;
    }

    /**
     * Stream the remote into the local mirror via curl, honouring an optional
     * rate limit. We shell out to curl rather than using Laravel's HTTP client
     * because LoC dumps can be multi-GB and we want streaming + resumable.
     */
    private function downloadWithCurl(string $url, string $mirrorPath, string $rateLimit): bool
    {
        $tmp = $mirrorPath . '.partial';
        $cmd = ['curl', '-sSL', '--fail', '-o', $tmp];
        if ($rateLimit !== '') {
            $cmd[] = '--limit-rate';
            $cmd[] = $rateLimit;
        }
        // Resume support — only works if the partial exists from a previous run.
        if (is_file($tmp)) {
            $cmd[] = '-C';
            $cmd[] = '-';
        }
        // Capture ETag for the sidecar so the next run can short-circuit.
        $cmd[] = '-D';
        $cmd[] = $mirrorPath . '.headers';
        $cmd[] = $url;

        $this->info('curl ' . implode(' ', array_map('escapeshellarg', $cmd)));
        $start = microtime(true);
        $proc = proc_open($cmd, [], $pipes);
        if ($proc === false) {
            $this->error('Could not spawn curl.');
            return false;
        }
        $exit = proc_close($proc);
        if ($exit !== 0) {
            $this->error("curl exited {$exit}; partial left at {$tmp}.");
            return false;
        }

        // Promote partial → final, write ETag sidecar from headers.
        if (! @rename($tmp, $mirrorPath)) {
            $this->error("Could not move {$tmp} → {$mirrorPath}");
            return false;
        }
        $headersPath = $mirrorPath . '.headers';
        if (is_file($headersPath)) {
            $headers = (string) file_get_contents($headersPath);
            if (preg_match('/^ETag:\s*(\S+)/im', $headers, $m)) {
                file_put_contents($mirrorPath . '.etag', $m[1]);
            }
            @unlink($headersPath);
        }

        $bytes = filesize($mirrorPath);
        $secs = max(0.001, microtime(true) - $start);
        $this->info(sprintf(
            '  ✓ downloaded %s bytes in %.1fs (%s/s)',
            number_format($bytes ?: 0),
            $secs,
            $this->humanBytes(($bytes ?: 0) / $secs)
        ));
        return true;
    }

    private function gunzip(string $gzPath, string $outPath): bool
    {
        $in = @gzopen($gzPath, 'rb');
        if (! $in) return false;
        $out = @fopen($outPath, 'wb');
        if (! $out) { gzclose($in); return false; }
        while (! gzeof($in)) {
            $chunk = gzread($in, 1 << 20);
            if ($chunk === false) break;
            fwrite($out, $chunk);
        }
        gzclose($in);
        fclose($out);
        return true;
    }

    /**
     * Pick a reasonable extension for the local mirror filename. URL extension
     * wins (lcsh.skos.nt.gz → 'nt.gz' → 'nt' after gunzip step); falls back to
     * the format hint, then 'rdf'.
     */
    private function guessExtension(string $source, string $formatHint): string
    {
        $base = basename(parse_url($source, PHP_URL_PATH) ?: $source);
        if (preg_match('/\.([a-z0-9.]+)$/i', $base, $m)) {
            return strtolower($m[1]);
        }
        return match ($formatHint) {
            'turtle' => 'ttl',
            'ntriples' => 'nt',
            'nquads' => 'nq',
            'trig' => 'trig',
            'jsonld' => 'jsonld',
            default => 'rdf',
        };
    }

    private function humanBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return sprintf('%.1f%s', $bytes, $units[$i]);
    }
}
