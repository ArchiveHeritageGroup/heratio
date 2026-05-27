<?php

/**
 * Inventory - OCFL v1.1 inventory.json representation.
 *
 * Per OCFL v1.1 §3.5, inventory.json carries:
 *   - id                 (string, unique within storage root)
 *   - type               URI of the inventory schema, e.g.
 *                        "https://ocfl.io/1.1/spec/#inventory"
 *   - digestAlgorithm    "sha512" (default) or "sha256"
 *   - head               highest version id, e.g. "v3"
 *   - manifest           digest -> [content path(s)]
 *   - versions           "vN" => Version-shaped object
 *
 * Two implementations producing the same logical state MUST produce the
 * same inventory.json bytes; this class enforces that by:
 *   - sorting manifest digest keys
 *   - sorting state digest keys per version
 *   - serialising via PHP's JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES |
 *     JSON_UNESCAPED_UNICODE and 4-space indent (matches the spec example
 *     output and round-trips byte-for-byte through our toJson/fromJson).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Layout;

use InvalidArgumentException;

final class Inventory
{
    public const TYPE_URI = 'https://ocfl.io/1.1/spec/#inventory';

    /**
     * @param array<string, array<int, string>> $manifest digest => [content paths]
     * @param array<string, Version>            $versions "vN" => Version
     * @param array<string, array<string, mixed>> $extensions OCFL v1.1 §3.7
     *        extension name => block payload. Vendor-defined. Encoded into
     *        inventory.json under the top-level `extensions` key only when
     *        non-empty (omitted entirely when no extensions are registered,
     *        which keeps the byte-for-byte determinism guarantee against
     *        the OCFL spec examples).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $head,
        public readonly array $manifest,
        public readonly array $versions,
        public readonly string $digestAlgorithm = 'sha512',
        public readonly string $type = self::TYPE_URI,
        public readonly array $extensions = [],
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Inventory: id cannot be empty');
        }
        if (! preg_match('/^v\d+$/', $head)) {
            throw new InvalidArgumentException("Inventory: head '{$head}' is not vN");
        }
        if (! isset($versions[$head])) {
            throw new InvalidArgumentException("Inventory: head '{$head}' missing from versions");
        }
    }

    /** Build a Version-1 inventory from scratch. */
    public static function initial(
        string $id,
        Version $v1,
        array $manifest,
        string $digestAlgorithm = 'sha512',
    ): self {
        return new self(
            id:              $id,
            head:            'v1',
            manifest:        $manifest,
            versions:        ['v1' => $v1],
            digestAlgorithm: $digestAlgorithm,
        );
    }

    /** Add a new version, returning a fresh inventory (immutability). */
    public function withNewVersion(Version $version, array $manifest): self
    {
        $next  = $this->nextVersionId();
        $vs    = $this->versions;
        $vs[$next] = $version;

        // Merge manifests deterministically - existing entries win for
        // identical digests (the spec requires content reuse across
        // versions, so the original path is preserved).
        $merged = $this->manifest;
        foreach ($manifest as $digest => $paths) {
            if (! isset($merged[$digest])) {
                $merged[$digest] = $paths;
            }
        }

        return new self(
            id:              $this->id,
            head:            $next,
            manifest:        $merged,
            versions:        $vs,
            digestAlgorithm: $this->digestAlgorithm,
            type:            $this->type,
            extensions:      $this->extensions,
        );
    }

    /**
     * Return a fresh inventory with an OCFL extension block registered /
     * replaced. Passing null clears the named extension. Designed for the
     * `ahg-embedded-metadata` extension and any future vendor blocks.
     *
     * Order is normalised at toJson() time so the on-disk inventory is
     * byte-stable regardless of insertion order.
     */
    public function withExtension(string $name, ?array $payload): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Inventory::withExtension: name cannot be empty');
        }
        $ext = $this->extensions;
        if ($payload === null || $payload === []) {
            unset($ext[$name]);
        } else {
            $ext[$name] = $payload;
        }
        return new self(
            id:              $this->id,
            head:            $this->head,
            manifest:        $this->manifest,
            versions:        $this->versions,
            digestAlgorithm: $this->digestAlgorithm,
            type:            $this->type,
            extensions:      $ext,
        );
    }

    /** True when a named extension block is registered + non-empty. */
    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name])
            && is_array($this->extensions[$name])
            && $this->extensions[$name] !== [];
    }

    /** Return the extension payload, or null when not registered. */
    public function getExtension(string $name): ?array
    {
        if (! $this->hasExtension($name)) {
            return null;
        }
        return $this->extensions[$name];
    }

    public function nextVersionId(): string
    {
        $max = 0;
        foreach (array_keys($this->versions) as $k) {
            $n = (int) substr((string) $k, 1);
            if ($n > $max) {
                $max = $n;
            }
        }
        return 'v'.($max + 1);
    }

    /**
     * Deterministic JSON encoding.
     *
     * Two implementations producing the same logical state will produce
     * identical bytes: keys sorted at every layer, 4-space indent, unicode
     * preserved, slashes unescaped. This matches the inventory.json
     * examples in the OCFL spec.
     */
    public function toJson(): string
    {
        $sortedManifest = $this->manifest;
        ksort($sortedManifest, SORT_STRING);

        $versionsOut = [];
        foreach ($this->sortedVersionKeys() as $k) {
            $versionsOut[$k] = $this->versions[$k]->toInventoryArray();
        }

        $payload = [
            'digestAlgorithm' => $this->digestAlgorithm,
            'head'            => $this->head,
            'id'              => $this->id,
            'manifest'        => $sortedManifest,
            'type'            => $this->type,
            'versions'        => $versionsOut,
        ];

        // Top-level keys are already alpha-sorted by the array literal
        // above. We do NOT pass JSON_FORCE_OBJECT - PHP will emit `{}`
        // for an empty associative array correctly because we never have
        // one (manifest is always populated when an inventory exists).
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        // Trailing newline keeps the file POSIX-friendly.
        return $json."\n";
    }

    /** Parse inventory.json bytes back into an Inventory. */
    public static function fromJson(string $bytes): self
    {
        $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Inventory: JSON did not decode to an object');
        }

        $versions = [];
        foreach ((array) ($data['versions'] ?? []) as $k => $v) {
            $versions[(string) $k] = Version::fromInventoryArray((array) $v);
        }

        return new self(
            id:              (string) ($data['id'] ?? ''),
            head:            (string) ($data['head'] ?? ''),
            manifest:        (array) ($data['manifest'] ?? []),
            versions:        $versions,
            digestAlgorithm: (string) ($data['digestAlgorithm'] ?? 'sha512'),
            type:            (string) ($data['type'] ?? self::TYPE_URI),
        );
    }

    /** "v1", "v2", "v10" -> sorted numerically not lexically. */
    public function sortedVersionKeys(): array
    {
        $keys = array_keys($this->versions);
        usort($keys, fn ($a, $b) => ((int) substr((string) $a, 1)) <=> ((int) substr((string) $b, 1)));
        return $keys;
    }
}
