<?php

/**
 * OcflObject - one OCFL archival object (one logical archival item).
 *
 * Carries:
 *   - the OCFL object id (matches inventory.id)
 *   - the current Inventory
 *   - a content tree (logical path -> raw bytes or local file path) for the
 *     in-flight version being staged
 *
 * The object is immutable from the caller's perspective; new versions are
 * staged via stageContent() / commit() and persisted by StorageRoot.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Layout;

final class OcflObject
{
    /**
     * @param array<string, string> $stagedContent logical path => local file path
     */
    public function __construct(
        public readonly string $id,
        public readonly Inventory $inventory,
        public array $stagedContent = [],
    ) {
    }

    public static function fresh(string $id, string $digestAlgorithm = 'sha512'): self
    {
        // Empty placeholder inventory - completed at commit time when
        // StorageRoot calls Inventory::initial(...) with the real v1.
        return new self(
            id:        $id,
            inventory: new Inventory(
                id:              $id,
                head:            'v1',
                manifest:        ['__placeholder__' => []],
                versions:        ['v1' => Version::now([], 'placeholder')],
                digestAlgorithm: $digestAlgorithm,
            ),
            stagedContent: [],
        );
    }

    public function stageContent(string $logicalPath, string $localFilePath): void
    {
        $clean = ltrim(str_replace(['../', '..\\'], '', $logicalPath), '/');
        $this->stagedContent[$clean] = $localFilePath;
    }
}
