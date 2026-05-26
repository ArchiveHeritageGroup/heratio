<?php

/**
 * OcflIngestCommand - snapshot an information_object's digital_object content
 * into the OCFL storage root.
 *
 * If the OCFL object for the given IO id does not yet exist, this writes v1;
 * otherwise it writes a new vN with content reuse for unchanged digests.
 *
 * The OCFL object id is `urn:heratio:io:{id}` (stable, namespaced).
 *
 * User attribution comes from Auth::id() when running under a request, or
 * from config('ocfl.cli_user_name') / OCFL_CLI_USER_NAME when running from
 * artisan / a queue worker.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Console\Commands;

use AhgCore\Models\DigitalObject;
use AhgOcfl\Layout\OcflObject;
use AhgOcfl\Layout\StorageRoot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OcflIngestCommand extends Command
{
    protected $signature = 'ocfl:ingest
        {ioId : The information_object id to snapshot}
        {--message= : Free-text version message}';

    protected $description = 'Snapshot an information_object\'s digital files into OCFL (new object or new version).';

    public function handle(StorageRoot $root): int
    {
        $ioId = (int) $this->argument('ioId');
        if ($ioId <= 0) {
            $this->error('ocfl:ingest requires a positive information_object id');
            return self::FAILURE;
        }

        // Pull every digital_object row tied to this IO. Use the model
        // when the table is present; fall back to a raw query otherwise
        // (keeps the package importable in tests without the schema).
        $digitalObjects = $this->loadDigitalObjects($ioId);
        if ($digitalObjects === []) {
            $this->warn("No digital_object rows for information_object {$ioId}; nothing to ingest.");
            return self::SUCCESS;
        }

        $objectId = "urn:heratio:io:{$ioId}";
        $ocfl     = OcflObject::fresh($objectId, $root->digester->algorithm);

        $uploads = rtrim((string) config('heratio.uploads_path', '/usr/share/nginx/heratio'), '/');
        $stagedCount = 0;
        foreach ($digitalObjects as $do) {
            $localPath = $uploads.'/'.ltrim((string) $do->path, '/').$do->name;
            if (! is_file($localPath) || ! is_readable($localPath)) {
                $this->warn("Skipping unreadable file: {$localPath}");
                continue;
            }
            $logical = ltrim((string) $do->path, '/').$do->name;
            $ocfl->stageContent($logical, $localPath);
            $stagedCount++;
        }

        if ($stagedCount === 0) {
            $this->error("No readable digital_object files for IO {$ioId}; aborting ingest.");
            return self::FAILURE;
        }

        [$userName, $userAddress] = $this->resolveUser();

        $inventory = $root->write(
            $ocfl,
            (string) ($this->option('message') ?: "Ingest of information_object {$ioId} ({$stagedCount} files)"),
            $userName,
            $userAddress,
        );

        // Upsert the IO -> OCFL object map (best-effort; the
        // information_object FK may be wrapped in a transaction by the
        // caller, so a failure here is non-fatal).
        $this->upsertObjectMap($ioId, $inventory->id, $inventory->head);

        $this->info("Wrote OCFL object {$inventory->id} head={$inventory->head} ({$stagedCount} files, alg={$inventory->digestAlgorithm}).");
        return self::SUCCESS;
    }

    /** @return array<int, object> */
    protected function loadDigitalObjects(int $ioId): array
    {
        try {
            return DigitalObject::query()
                ->where('object_id', $ioId)
                ->whereNotNull('path')
                ->whereNotNull('name')
                ->get()
                ->all();
        } catch (\Throwable) {
            // Fall back to raw query (handles a stripped test DB).
            try {
                return DB::table('digital_object')
                    ->where('object_id', $ioId)
                    ->whereNotNull('path')
                    ->whereNotNull('name')
                    ->get()
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        }
    }

    /** @return array{0:?string,1:?string} */
    protected function resolveUser(): array
    {
        if (Auth::check()) {
            $u = Auth::user();
            $name = method_exists($u, 'getAuthIdentifierName')
                ? (string) $u->getAuthIdentifierName()
                : (string) ($u->email ?? $u->name ?? Auth::id());
            return [$name !== '' ? $name : (string) Auth::id(), null];
        }

        return [
            (string) config('ocfl.cli_user_name', 'cli'),
            config('ocfl.cli_user_address'),
        ];
    }

    protected function upsertObjectMap(int $ioId, string $objectId, string $head): void
    {
        try {
            $now = now();
            DB::table('ahg_ocfl_object_map')->updateOrInsert(
                ['information_object_id' => $ioId],
                [
                    'ocfl_object_id' => $objectId,
                    'storage_root'   => (string) config('ocfl.disk', 'ocfl'),
                    'head_version'   => $head,
                    'updated_at'     => $now,
                    'created_at'     => $now,
                ],
            );
        } catch (\Throwable $e) {
            $this->warn('ocfl:ingest: could not update ahg_ocfl_object_map - '.$e->getMessage());
        }
    }
}
