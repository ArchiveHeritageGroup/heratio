<?php

/**
 * OcflVerifyCommand - validate fixity + structure for one or all OCFL objects.
 *
 * Exit 0 on success, 1 if any object reports an error. Mirrors the verifier
 * exit-code pattern shipped with #693 (EU AI Act Article 12 log verifier).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Console\Commands;

use AhgOcfl\Layout\StorageRoot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OcflVerifyCommand extends Command
{
    protected $signature = 'ocfl:verify {ioId? : Optional information_object id (omit to verify the whole root)}';

    protected $description = 'Validate fixity + structure for one OCFL object or the entire storage root.';

    public function handle(StorageRoot $root): int
    {
        if (! $root->isInitialized()) {
            $this->error('Storage root is not initialised (run `php artisan ocfl:init` first).');
            return self::FAILURE;
        }

        $ioId = $this->argument('ioId');
        if ($ioId !== null && $ioId !== '') {
            return $this->verifyOne($root, (int) $ioId);
        }

        return $this->verifyAll($root);
    }

    private function verifyOne(StorageRoot $root, int $ioId): int
    {
        $objectId = $this->resolveObjectId($ioId);
        if (! $root->exists($objectId)) {
            $this->error("OCFL object for IO {$ioId} not found ({$objectId}).");
            return self::FAILURE;
        }
        $errors = $root->verify($objectId);
        return $this->reportErrors([$objectId => $errors]);
    }

    private function verifyAll(StorageRoot $root): int
    {
        $ids = $root->list();
        if ($ids === []) {
            $this->info('Storage root is empty; nothing to verify.');
            return self::SUCCESS;
        }
        $all = [];
        $this->getOutput()->progressStart(count($ids));
        foreach ($ids as $id) {
            $all[$id] = $root->verify($id);
            $this->getOutput()->progressAdvance();
        }
        $this->getOutput()->progressFinish();
        return $this->reportErrors($all);
    }

    private function resolveObjectId(int $ioId): string
    {
        try {
            $row = DB::table('ahg_ocfl_object_map')->where('information_object_id', $ioId)->first();
            if ($row !== null && ! empty($row->ocfl_object_id)) {
                return (string) $row->ocfl_object_id;
            }
        } catch (\Throwable) {
            // Map table may not exist yet on a stripped install.
        }
        return "urn:heratio:io:{$ioId}";
    }

    /** @param array<string, array<int, string>> $results */
    private function reportErrors(array $results): int
    {
        $failed = 0;
        foreach ($results as $id => $errors) {
            if ($errors === []) {
                $this->line("  OK  {$id}");
                continue;
            }
            $failed++;
            $this->error("FAIL  {$id}");
            foreach ($errors as $e) {
                $this->line('      - '.$e);
            }
        }
        if ($failed > 0) {
            $this->error("Verification failed for {$failed} object(s).");
            return self::FAILURE;
        }
        $this->info('All objects verified OK.');
        return self::SUCCESS;
    }
}
