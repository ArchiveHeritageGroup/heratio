<?php

/**
 * OcflInitCommand - initialise an OCFL v1.1 storage root.
 *
 * Writes the `0=ocfl_1.1` namaste declaration and the layout descriptor
 * (`ocfl_layout.json`) into the configured disk.
 *
 * Usage:
 *   php artisan ocfl:init                  # use config('ocfl.disk')
 *   php artisan ocfl:init /mnt/nas/ocfl    # one-shot override of the disk root
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Console\Commands;

use AhgOcfl\Layout\StorageRoot;
use AhgOcfl\Storage\OcflStorageAdapter;
use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class OcflInitCommand extends Command
{
    protected $signature = 'ocfl:init {path? : Optional absolute path to override the configured disk root}';

    protected $description = 'Initialise an OCFL v1.1 storage root (namaste + layout descriptor).';

    public function handle(StorageRoot $root): int
    {
        $path = $this->argument('path');
        if (is_string($path) && $path !== '') {
            // Build an ad-hoc local disk pointed at the operator's path
            // so they can spin up a root anywhere on the filesystem
            // without touching config/filesystems.php first.
            $adapter = $this->buildLocalAdapter($path);
            $root    = new StorageRoot(
                $adapter,
                (string) config('ocfl.storage_layout', 'flat-id'),
                (string) config('ocfl.digest_algorithm', 'sha512'),
            );
            $this->info("Targeting ad-hoc local storage root: {$path}");
        } else {
            $this->info('Targeting configured disk: '.config('ocfl.disk'));
        }

        if ($root->isInitialized()) {
            $this->warn('Storage root already initialised (namaste declaration present). No changes made.');
            return self::SUCCESS;
        }

        $root->initialize();
        $this->info('Storage root initialised. Layout: '.$root->layout->layout.', digest: '.$root->digester->algorithm);
        return self::SUCCESS;
    }

    /**
     * Build a Laravel FilesystemAdapter pointed at an arbitrary local path,
     * so the operator can initialise a fresh root without first registering
     * the disk in config/filesystems.php.
     */
    protected function buildLocalAdapter(string $path): OcflStorageAdapter
    {
        @mkdir($path, 0775, true);

        $adapter = new OcflStorageAdapter('ocfl-adhoc');
        // The League\Flysystem v3 adapter ships with Laravel 12.
        $fly = new Flysystem(new LocalFilesystemAdapter($path));
        $disk = new FilesystemAdapter($fly, new LocalFilesystemAdapter($path), [
            'root' => $path,
        ]);
        $adapter->setDiskInstance($disk);
        return $adapter;
    }
}
