<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PortableImportCommand extends Command
{
    protected $signature = 'ahg:portable-import
        {--zip= : Path to ZIP archive (required unless --path given)}
        {--path= : Path to extracted package directory}
        {--mode=merge : Import mode (merge, replace, skip-existing)}
        {--culture=en : Default culture for unmatched i18n}
        {--user-id=1 : User id to credit the import to}';

    protected $description = 'Import a portable package — extract, validate manifest, queue ingest';

    public function handle(): int
    {
        $zip = $this->option('zip');
        $path = $this->option('path');
        if (! $zip && ! $path) { $this->error('Provide --zip=PATH or --path=DIR'); return self::FAILURE; }

        // If zip given, extract under storage/imports/<id>/
        $extractDir = $path;
        if ($zip) {
            if (! is_file($zip)) { $this->error("zip not found: {$zip}"); return self::FAILURE; }
            $extractDir = base_path('storage/imports/' . pathinfo($zip, PATHINFO_FILENAME));
            @mkdir($extractDir, 0775, true);
            $z = new \ZipArchive;
            if ($z->open($zip) !== true) { $this->error('cannot open zip'); return self::FAILURE; }
            $z->extractTo($extractDir);
            $z->close();
            $this->info("extracted {$zip} → {$extractDir}");
        }
        $manifestPath = rtrim((string) $extractDir, '/') . '/manifest.json';
        if (! is_readable($manifestPath)) {
            $this->error("manifest.json missing in {$extractDir} — package invalid.");
            return self::FAILURE;
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->error('manifest.json malformed.');
            return self::FAILURE;
        }

        // Persist a row in ingest_session so the existing ingest pipeline can take over.
        $sessionId = DB::table('ingest_session')->insertGetId([
            'user_id'       => (int) $this->option('user-id'),
            'session_kind'  => 'portable_import',
            'source_path'   => $extractDir,
            'manifest_json' => json_encode($manifest),
            'mode'          => (string) $this->option('mode'),
            'culture'       => (string) $this->option('culture'),
            'status'        => 'pending',
            'created_at'    => now(),
        ]);
        $this->info("queued ingest_session={$sessionId} for portable import. Run ahg:ingest-commit --session={$sessionId} to process.");
        return self::SUCCESS;
    }
}
