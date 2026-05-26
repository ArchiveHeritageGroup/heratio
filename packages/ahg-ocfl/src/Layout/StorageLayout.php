<?php

/**
 * StorageLayout - resolve an OCFL object id to its object-root path inside
 * the storage root.
 *
 * OCFL v1.1 §3.1.2 supports any deterministic layout the implementer
 * chooses, declared in `ocfl_layout.json`. We ship three:
 *
 *   - flat-id        the id (urlencoded) is the directory name. Cheap, but
 *                    only practical for < ~10k objects on most filesystems.
 *   - pairtree       classic Namaste pairtree (`12/34/56...`). Good for
 *                    medium volumes; tolerated by most OCFL clients.
 *   - hashed-n-tuple sha256(id) split into three 3-char chunks (`abc/def/.../id`).
 *                    Scales to millions per storage root.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Layout;

use InvalidArgumentException;

final class StorageLayout
{
    public const FLAT_ID         = 'flat-id';
    public const PAIRTREE        = 'pairtree';
    public const HASHED_N_TUPLE  = 'hashed-n-tuple';

    public function __construct(public readonly string $layout = self::FLAT_ID)
    {
        if (! in_array($layout, [self::FLAT_ID, self::PAIRTREE, self::HASHED_N_TUPLE], true)) {
            throw new InvalidArgumentException(
                "Unknown OCFL storage layout '{$layout}'"
            );
        }
    }

    /** Path inside the storage root for the given object id. */
    public function pathFor(string $objectId): string
    {
        $id = trim($objectId);
        if ($id === '') {
            throw new InvalidArgumentException('OCFL object id cannot be empty');
        }

        return match ($this->layout) {
            self::FLAT_ID        => $this->safeName($id),
            self::PAIRTREE       => $this->pairtree($id),
            self::HASHED_N_TUPLE => $this->hashedNTuple($id),
        };
    }

    /** Layout descriptor written into the storage root as ocfl_layout.json. */
    public function descriptor(): array
    {
        return [
            'extension'   => $this->layout,
            'description' => match ($this->layout) {
                self::FLAT_ID        => 'Flat object id (one directory per object, name = url-encoded id)',
                self::PAIRTREE       => 'Pairtree (Namaste) - two-character pairs of the url-encoded id',
                self::HASHED_N_TUPLE => 'Hashed n-tuple - sha256(id) split into 3 x 3-char segments',
            },
        ];
    }

    private function safeName(string $id): string
    {
        // RFC 3986 unreserved + a couple of safe-on-disk characters.
        return rawurlencode($id);
    }

    private function pairtree(string $id): string
    {
        $encoded = $this->safeName($id);
        $pairs   = str_split($encoded, 2);
        return implode('/', $pairs).'/'.$encoded;
    }

    private function hashedNTuple(string $id): string
    {
        $h = hash('sha256', $id);
        return substr($h, 0, 3).'/'.substr($h, 3, 3).'/'.substr($h, 6, 3).'/'.$this->safeName($id);
    }
}
