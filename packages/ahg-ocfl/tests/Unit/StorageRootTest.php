<?php

/**
 * StorageRootTest - end-to-end exercise of the OCFL storage layer using an
 * in-memory adapter (no Laravel boot, no MySQL, no disk).
 *
 *   1. Two writers of the same content produce identical content paths
 *      (deterministic addressing).
 *   2. verify() returns no errors after a clean write.
 *   3. verify() flags a mutated content file.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgOcfl\Tests\Unit;

use AhgOcfl\Layout\ContentAddressing;
use AhgOcfl\Layout\OcflObject;
use AhgOcfl\Layout\StorageLayout;
use AhgOcfl\Layout\StorageRoot;
use AhgOcfl\Storage\OcflStorageAdapter;
use PHPUnit\Framework\TestCase;

/**
 * In-memory adapter that satisfies the small contract StorageRoot uses
 * without pulling Laravel / Flysystem in.
 */
final class InMemoryOcflAdapter extends OcflStorageAdapter
{
    /** @var array<string, string> */
    public array $files = [];

    public function __construct()
    {
        parent::__construct('in-memory');
    }

    public function exists(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function get(string $path): string
    {
        return $this->files[$path] ?? '';
    }

    public function put(string $path, string $contents): void
    {
        $this->files[$path] = $contents;
    }

    public function putFromFile(string $path, string $localFile): void
    {
        $bytes = file_get_contents($localFile);
        $this->files[$path] = $bytes === false ? '' : $bytes;
    }

    public function files(string $prefix = ''): array
    {
        if ($prefix === '') {
            return array_keys($this->files);
        }
        $prefix = rtrim($prefix, '/').'/';
        $out = [];
        foreach (array_keys($this->files) as $p) {
            if (str_starts_with($p, $prefix)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }
}

final class StorageRootTest extends TestCase
{
    private string $scratchDir;

    protected function setUp(): void
    {
        $this->scratchDir = sys_get_temp_dir().'/ahg-ocfl-test-'.bin2hex(random_bytes(4));
        @mkdir($this->scratchDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->scratchDir);
    }

    public function test_two_writers_produce_identical_content_paths(): void
    {
        $localA = $this->writeTempFile('a.txt', "hello ocfl\n");
        $localB = $this->writeTempFile('a-copy.txt', "hello ocfl\n");

        $rootA = $this->freshRoot();
        $rootB = $this->freshRoot();

        $objA = OcflObject::fresh('urn:heratio:io:99');
        $objA->stageContent('payload.txt', $localA);
        $invA = $rootA->write($objA, 'first writer', 'tester', null);

        $objB = OcflObject::fresh('urn:heratio:io:99');
        $objB->stageContent('payload.txt', $localB);
        $invB = $rootB->write($objB, 'second writer', 'tester', null);

        $this->assertSame(
            array_keys($invA->manifest),
            array_keys($invB->manifest),
            'Identical bytes must produce identical digest keys across writers.',
        );
        $this->assertSame(
            $invA->manifest,
            $invB->manifest,
            'Identical bytes must produce identical manifest entries (digest + content path).',
        );
    }

    public function test_verify_passes_clean_write_and_catches_mutation(): void
    {
        $local = $this->writeTempFile('master.tiff', random_bytes(1024));

        $adapter = new InMemoryOcflAdapter();
        $root    = new StorageRoot($adapter, StorageLayout::FLAT_ID, ContentAddressing::ALG_SHA512);

        $obj = OcflObject::fresh('urn:heratio:io:7');
        $obj->stageContent('master.tiff', $local);
        $inv = $root->write($obj, 'ingest', 'tester', null);

        $this->assertSame([], $root->verify($inv->id), 'Clean write must verify with zero errors.');

        // Mutate one content file in-place.
        $objectRoot = $root->objectRoot($inv->id);
        $contentPath = null;
        foreach ($adapter->files as $path => $_) {
            if (str_starts_with($path, $objectRoot.'/v1/content/')) {
                $contentPath = $path;
                break;
            }
        }
        $this->assertNotNull($contentPath, 'Test setup: should have written a content file under v1/content/.');
        $adapter->files[$contentPath] = 'TAMPERED';

        $errors = $root->verify($inv->id);
        $this->assertNotEmpty($errors, 'verify() must report at least one error after content tampering.');
        $joined = implode("\n", $errors);
        $this->assertStringContainsString('digest mismatch', $joined);
    }

    private function freshRoot(): StorageRoot
    {
        return new StorageRoot(
            new InMemoryOcflAdapter(),
            StorageLayout::FLAT_ID,
            ContentAddressing::ALG_SHA512,
        );
    }

    private function writeTempFile(string $name, string $contents): string
    {
        $path = $this->scratchDir.'/'.$name;
        file_put_contents($path, $contents);
        return $path;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
        }
        @rmdir($dir);
    }
}
