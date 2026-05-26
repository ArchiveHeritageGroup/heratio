<?php

/**
 * OcflStorageAdapter - thin wrapper over a Laravel Storage disk.
 *
 * The OCFL layer only ever touches the storage through this interface, so
 * the disk can be 'local', 's3', 'wasabi', or any other Flysystem adapter
 * configured by the operator in config/filesystems.php.
 *
 * In-process tests substitute an in-memory implementation that mimics the
 * same contract; see tests/Unit/StorageRootTest.php.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class OcflStorageAdapter
{
    protected ?Filesystem $diskInstance = null;

    public function __construct(public readonly string $disk = 'ocfl')
    {
    }

    /** Allow tests to inject a Filesystem stub. */
    public function setDiskInstance(Filesystem $fs): void
    {
        $this->diskInstance = $fs;
    }

    protected function fs(): Filesystem
    {
        return $this->diskInstance ?? Storage::disk($this->disk);
    }

    public function exists(string $path): bool
    {
        return $this->fs()->exists($path);
    }

    public function get(string $path): string
    {
        $contents = $this->fs()->get($path);
        return $contents === null ? '' : (string) $contents;
    }

    public function put(string $path, string $contents): void
    {
        $this->fs()->put($path, $contents);
    }

    /**
     * Write a file from a local path. Streams the upload so multi-GB
     * preservation masters do not balloon PHP memory.
     */
    public function putFromFile(string $path, string $localFile): void
    {
        $stream = @fopen($localFile, 'rb');
        if ($stream === false) {
            throw new \RuntimeException("OcflStorageAdapter: cannot open {$localFile}");
        }
        try {
            $this->fs()->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /** Recursively list all files under the given prefix. */
    public function files(string $prefix = ''): array
    {
        return $this->fs()->allFiles($prefix);
    }

    public function delete(string $path): void
    {
        $this->fs()->delete($path);
    }
}
