<?php

/**
 * OcflExportCommand - export one OCFL object to a portable tarball.
 *
 * Output: storage/ocfl-exports/<sanitised-id>.tar (relative to base_path()).
 *
 * The tarball is plain POSIX-format (no compression) so it streams large
 * preservation masters efficiently and is inspectable with stock `tar`.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Console\Commands;

use AhgOcfl\Layout\StorageRoot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PharData;

class OcflExportCommand extends Command
{
    protected $signature = 'ocfl:export {ioId : The information_object id to export}';

    protected $description = 'Export an OCFL object to a tarball under storage/ocfl-exports/.';

    public function handle(StorageRoot $root): int
    {
        $ioId = (int) $this->argument('ioId');
        if ($ioId <= 0) {
            $this->error('ocfl:export requires a positive information_object id');
            return self::FAILURE;
        }

        $objectId = $this->resolveObjectId($ioId);
        if (! $root->exists($objectId)) {
            $this->error("OCFL object for IO {$ioId} not found ({$objectId}).");
            return self::FAILURE;
        }

        $exportDir = $this->resolveExportDir();
        if (! is_dir($exportDir)) {
            @mkdir($exportDir, 0775, true);
        }
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $objectId) ?? 'ocfl-object';
        $tarPath  = $exportDir.'/'.$safeName.'.tar';

        // Pull every file under the object root from the adapter and
        // stream into a PharData archive at $tarPath.
        $objectRoot = $root->objectRoot($objectId);
        $files      = $root->adapter->files($objectRoot);
        if ($files === []) {
            $this->error("OCFL object {$objectId} appears empty on the storage adapter.");
            return self::FAILURE;
        }

        if (file_exists($tarPath)) {
            @unlink($tarPath);
        }

        $phar = new PharData($tarPath);
        foreach ($files as $relPath) {
            $bytes = $root->adapter->get($relPath);
            // Re-root the path inside the tarball so unpacking gives a
            // self-contained <object-id>/... tree.
            $insideTar = $safeName.'/'.ltrim(substr($relPath, strlen($objectRoot)), '/');
            $phar->addFromString($insideTar, $bytes);
        }
        // Force the underlying file to flush before we report success.
        unset($phar);

        $this->info("Exported {$objectId} -> {$tarPath} (".count($files).' files).');
        return self::SUCCESS;
    }

    private function resolveObjectId(int $ioId): string
    {
        try {
            $row = DB::table('ahg_ocfl_object_map')->where('information_object_id', $ioId)->first();
            if ($row !== null && ! empty($row->ocfl_object_id)) {
                return (string) $row->ocfl_object_id;
            }
        } catch (\Throwable) {
            // map table missing
        }
        return "urn:heratio:io:{$ioId}";
    }

    private function resolveExportDir(): string
    {
        $configured = (string) config('ocfl.export_path', 'storage/ocfl-exports');
        if (str_starts_with($configured, '/')) {
            return rtrim($configured, '/');
        }
        return rtrim(base_path($configured), '/');
    }
}
