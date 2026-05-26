<?php

/**
 * StorageRoot - an OCFL v1.1 storage root.
 *
 * Responsibilities:
 *   - initialise the root with `0=ocfl_1.1` namaste + `ocfl_layout.json`
 *   - resolve an object id to its on-disk path via StorageLayout
 *   - read / write inventory.json + content files for an object
 *   - verify fixity for one or all objects
 *
 * Operates over the OcflStorageAdapter so the backing store can be local
 * disk, S3, Wasabi, or any other Flysystem adapter.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Layout;

use AhgOcfl\Storage\OcflStorageAdapter;
use RuntimeException;

final class StorageRoot
{
    public const NAMASTE_FILE     = '0=ocfl_1.1';
    public const NAMASTE_CONTENT  = "ocfl_1.1\n";
    public const LAYOUT_FILE      = 'ocfl_layout.json';

    public readonly StorageLayout $layout;
    public readonly ContentAddressing $digester;

    public function __construct(
        public readonly OcflStorageAdapter $adapter,
        string $layout = StorageLayout::FLAT_ID,
        string $digestAlgorithm = ContentAddressing::ALG_SHA512,
    ) {
        $this->layout   = new StorageLayout($layout);
        $this->digester = new ContentAddressing($digestAlgorithm);
    }

    public function isInitialized(): bool
    {
        return $this->adapter->exists(self::NAMASTE_FILE);
    }

    public function initialize(): void
    {
        $this->adapter->put(self::NAMASTE_FILE, self::NAMASTE_CONTENT);

        $layoutDescriptor = $this->layout->descriptor();
        // Deterministic JSON for the layout descriptor too.
        $this->adapter->put(
            self::LAYOUT_FILE,
            json_encode(
                $layoutDescriptor,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            )."\n",
        );
    }

    /** List object ids by walking the root for inventory.json files. */
    public function list(): array
    {
        $ids  = [];
        $seen = [];
        foreach ($this->adapter->files('') as $path) {
            // Cheap pre-filter: only object-root inventories (not the
            // per-version snapshots under vN/inventory.json). The
            // object-root inventory sits directly under <object-path>/,
            // which means there is no `/vN/` segment in the relative
            // path from the storage root.
            if (! str_ends_with($path, '/inventory.json') && $path !== 'inventory.json') {
                continue;
            }
            if (preg_match('#/v\d+/inventory\.json$#', $path) === 1) {
                continue;
            }
            try {
                $inv = Inventory::fromJson($this->adapter->get($path));
                if (! isset($seen[$inv->id])) {
                    $ids[] = $inv->id;
                    $seen[$inv->id] = true;
                }
            } catch (\Throwable) {
                // Skip malformed inventories - verify() flags them.
            }
        }
        sort($ids, SORT_STRING);
        return $ids;
    }

    public function exists(string $objectId): bool
    {
        return $this->adapter->exists($this->objectRoot($objectId).'/inventory.json');
    }

    public function read(string $objectId): OcflObject
    {
        $invPath = $this->objectRoot($objectId).'/inventory.json';
        if (! $this->adapter->exists($invPath)) {
            throw new RuntimeException("OCFL object '{$objectId}' not found at {$invPath}");
        }
        $inv = Inventory::fromJson($this->adapter->get($invPath));
        return new OcflObject($objectId, $inv);
    }

    /**
     * Write an OCFL object (new v1 or new vN) into the storage root.
     *
     * The caller supplies an OcflObject with:
     *   - id matching the storage-root entry
     *   - stagedContent: logical path -> local file path
     *   - inventory: the CURRENT inventory if any (used for vN reuse)
     *
     * Returns the freshly-written inventory (head reflects the new version).
     */
    public function write(
        OcflObject $object,
        string $message,
        ?string $userName,
        ?string $userAddress,
    ): Inventory {
        if (! $this->isInitialized()) {
            $this->initialize();
        }

        $root = $this->objectRoot($object->id);

        // Determine whether we're creating v1 or appending vN.
        $existing = $this->adapter->exists($root.'/inventory.json')
            ? Inventory::fromJson($this->adapter->get($root.'/inventory.json'))
            : null;

        $nextVersionId = $existing === null ? 'v1' : $existing->nextVersionId();
        $versionDir    = $nextVersionId;

        // Hash each staged file, build state + manifest for THIS version.
        $newManifest = [];
        $state       = [];
        foreach ($object->stagedContent as $logicalPath => $localFile) {
            $digest = $this->digester->digestFile($localFile);

            // State: digest -> [logical paths]
            if (! isset($state[$digest])) {
                $state[$digest] = [];
            }
            $state[$digest][] = $logicalPath;

            // Manifest: digest -> [content paths]. Skip if existing
            // inventory already has the same digest (content reuse per
            // OCFL v1.1 §3.5.3.1).
            $existingPaths = $existing?->manifest[$digest] ?? null;
            if ($existingPaths === null) {
                $contentPath = $this->digester->contentPath($versionDir, $logicalPath);
                $newManifest[$digest] = [$contentPath];

                $this->adapter->putFromFile($root.'/'.$contentPath, $localFile);
            }
        }

        // Build the new Version + Inventory.
        $version = Version::now(
            state:       $state,
            message:     $message,
            userName:    $userName,
            userAddress: $userAddress,
        );

        $inventory = $existing === null
            ? Inventory::initial($object->id, $version, $newManifest, $this->digester->algorithm)
            : $existing->withNewVersion($version, $newManifest);

        // Write the version directory's own inventory.json (per OCFL
        // v1.1 §3.5: every version dir also gets a snapshot inventory).
        $invBytes = $inventory->toJson();
        $this->adapter->put($root.'/'.$versionDir.'/inventory.json', $invBytes);
        $this->adapter->put($root.'/'.$versionDir.'/inventory.json.'.$this->digester->algorithm, $this->sidecar($invBytes));

        // Write the canonical (head) inventory + sidecar at the object root.
        $this->adapter->put($root.'/inventory.json', $invBytes);
        $this->adapter->put($root.'/inventory.json.'.$this->digester->algorithm, $this->sidecar($invBytes));

        // Object namaste declaration at the object root (idempotent).
        $this->adapter->put($root.'/0=ocfl_object_1.1', "ocfl_object_1.1\n");

        return $inventory;
    }

    /**
     * Verify fixity + basic structure for one object.
     *
     * Returns an array of error strings; empty array means the object is
     * spec-conformant for the checks we run.
     */
    public function verify(string $objectId): array
    {
        $errors = [];
        $root   = $this->objectRoot($objectId);

        if (! $this->adapter->exists($root.'/0=ocfl_object_1.1')) {
            $errors[] = "{$objectId}: missing 0=ocfl_object_1.1 namaste";
        }

        $invPath = $root.'/inventory.json';
        if (! $this->adapter->exists($invPath)) {
            $errors[] = "{$objectId}: missing inventory.json";
            return $errors;
        }

        try {
            $bytes = $this->adapter->get($invPath);
            $inv   = Inventory::fromJson($bytes);
        } catch (\Throwable $e) {
            return ["{$objectId}: inventory.json unreadable - ".$e->getMessage()];
        }

        // Sidecar fixity for the inventory itself.
        $sidecarPath = $invPath.'.'.$inv->digestAlgorithm;
        if ($this->adapter->exists($sidecarPath)) {
            $expected = trim(explode(' ', $this->adapter->get($sidecarPath))[0] ?? '');
            $actual   = $this->digester->digestBytes($bytes);
            if ($expected !== '' && $expected !== $actual) {
                $errors[] = "{$objectId}: inventory.json sidecar mismatch (expected {$expected}, got {$actual})";
            }
        }

        // Per-file fixity from the manifest.
        foreach ($inv->manifest as $digest => $paths) {
            foreach ((array) $paths as $contentPath) {
                $full = $root.'/'.$contentPath;
                if (! $this->adapter->exists($full)) {
                    $errors[] = "{$objectId}: missing content file {$contentPath}";
                    continue;
                }
                $actual = hash($inv->digestAlgorithm, $this->adapter->get($full));
                if ($actual !== $digest) {
                    $errors[] = "{$objectId}: digest mismatch for {$contentPath} (expected {$digest}, got {$actual})";
                }
            }
        }

        return $errors;
    }

    public function objectRoot(string $objectId): string
    {
        return $this->layout->pathFor($objectId);
    }

    /** OCFL sidecar format: `<digest> inventory.json` followed by newline. */
    private function sidecar(string $bytes): string
    {
        return $this->digester->digestBytes($bytes).' inventory.json'."\n";
    }
}
