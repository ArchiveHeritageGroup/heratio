<?php

/**
 * EuropeanaExportService - bulk EDM export + sitemap + zip bundle.
 *
 * Serialises every published Information Object via EdmSerializer,
 * writes one RDF/XML file per record under storage/europeana/, then
 * emits a sitemap.xml that lists every record (this is what the
 * Europeana harvester crawls) and finally bundles the lot into
 * europeana-bundle-YYYY-MM-DD.zip for hand-off to a provider's
 * data-ingest team.
 *
 * Run-history lands in ahg_europeana_export (one row per run) so
 * the admin dashboard can show last-run timestamp, record count,
 * bundle path, and any error message.
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

namespace AhgFederation\Edm;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class EuropeanaExportService
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';

    public function __construct(private EdmSerializer $serializer = new EdmSerializer())
    {
    }

    /**
     * Run a full bulk export. `$out` is the directory to write per-IO
     * files into, defaulting to storage/europeana/. `$sinceIso` allows
     * incremental runs (only re-emit IOs updated since the timestamp).
     *
     * Returns an associative array describing the run for callers
     * (artisan command + admin controller).
     */
    public function run(string $out, ?string $sinceIso = null, string $culture = 'en'): array
    {
        $absOut = $this->resolveOut($out);
        $this->ensureDirectory($absOut);

        $logId = $this->openRun($absOut);
        $records = 0;
        $files = [];

        try {
            $ids = $this->serializer->listPublishedRecordIds($sinceIso);
            foreach ($ids as $id) {
                $xml = $this->serializer->serializeRecord((int) $id, $culture);
                if ($xml === '') {
                    continue;
                }
                $filename = $this->recordFilename((int) $id);
                $abs = $absOut.DIRECTORY_SEPARATOR.$filename;
                file_put_contents($abs, $xml);
                $files[] = $filename;
                $records++;
            }

            $sitemap = $this->writeSitemap($absOut, $files);
            $bundle = $this->writeBundle($absOut, $files, $sitemap);
            $bundleSize = is_file($bundle) ? (int) filesize($bundle) : 0;

            $this->closeRun($logId, self::STATUS_SUCCESS, [
                'record_count' => $records,
                'bundle_path' => $bundle,
                'bundle_size_bytes' => $bundleSize,
                'error' => null,
            ]);

            return [
                'status' => self::STATUS_SUCCESS,
                'out' => $absOut,
                'record_count' => $records,
                'bundle' => $bundle,
                'bundle_size_bytes' => $bundleSize,
                'sitemap' => $sitemap,
            ];
        } catch (\Throwable $e) {
            Log::error('europeana export failed', ['err' => $e->getMessage()]);
            $this->closeRun($logId, self::STATUS_ERROR, [
                'record_count' => $records,
                'bundle_path' => null,
                'bundle_size_bytes' => 0,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => self::STATUS_ERROR,
                'out' => $absOut,
                'record_count' => $records,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Most-recent successful run, for the admin index page. NULL when
     * no run has completed yet.
     */
    public function lastRun(): ?object
    {
        try {
            return DB::table('ahg_europeana_export')
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Run history (newest first) for the admin index page.
     */
    public function history(int $limit = 10): array
    {
        try {
            return DB::table('ahg_europeana_export')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------

    protected function recordFilename(int $ioId): string
    {
        return sprintf('record-%07d.xml', $ioId);
    }

    protected function resolveOut(string $out): string
    {
        if (str_starts_with($out, '/')) {
            return rtrim($out, '/');
        }
        $base = function_exists('storage_path') ? storage_path('') : sys_get_temp_dir();
        $rel = preg_replace('#^storage/?#', '', $out);
        return rtrim($base, '/').'/'.ltrim((string) $rel, '/');
    }

    protected function ensureDirectory(string $abs): void
    {
        if (! is_dir($abs)) {
            if (! mkdir($abs, 0755, true) && ! is_dir($abs)) {
                throw new \RuntimeException("Cannot create export directory: {$abs}");
            }
        }
    }

    protected function writeSitemap(string $outDir, array $files): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        $base = rtrim((string) (function_exists('url') ? url('/') : ''), '/');
        $today = gmdate('Y-m-d');
        foreach ($files as $filename) {
            $loc = $base.'/europeana/'.$filename;
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'."\n";
            $xml .= '    <lastmod>'.$today.'</lastmod>'."\n";
            $xml .= '  </url>'."\n";
        }
        $xml .= '</urlset>'."\n";

        $path = $outDir.DIRECTORY_SEPARATOR.'sitemap.xml';
        file_put_contents($path, $xml);
        return $path;
    }

    protected function writeBundle(string $outDir, array $files, string $sitemapPath): string
    {
        $bundle = $outDir.DIRECTORY_SEPARATOR.'europeana-bundle-'.gmdate('Y-m-d').'.zip';
        if (is_file($bundle)) {
            @unlink($bundle);
        }
        $zip = new ZipArchive();
        if ($zip->open($bundle, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot create zip: {$bundle}");
        }
        foreach ($files as $filename) {
            $abs = $outDir.DIRECTORY_SEPARATOR.$filename;
            if (is_file($abs)) {
                $zip->addFile($abs, 'records/'.$filename);
            }
        }
        if (is_file($sitemapPath)) {
            $zip->addFile($sitemapPath, 'sitemap.xml');
        }
        $zip->close();
        return $bundle;
    }

    protected function openRun(string $outDir): ?int
    {
        try {
            return (int) DB::table('ahg_europeana_export')->insertGetId([
                'started_at' => now(),
                'status' => self::STATUS_RUNNING,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('europeana export: cannot open run row', ['err' => $e->getMessage()]);
            return null;
        }
    }

    protected function closeRun(?int $id, string $status, array $fields): void
    {
        if ($id === null) {
            return;
        }
        try {
            DB::table('ahg_europeana_export')
                ->where('id', $id)
                ->update([
                    'finished_at' => now(),
                    'status' => $status,
                    'record_count' => (int) ($fields['record_count'] ?? 0),
                    'bundle_path' => $fields['bundle_path'] ?? null,
                    'bundle_size_bytes' => (int) ($fields['bundle_size_bytes'] ?? 0),
                    'error' => $fields['error'] ?? null,
                ]);
        } catch (\Throwable $e) {
            Log::warning('europeana export: cannot close run row', ['err' => $e->getMessage()]);
        }
    }
}
