<?php

/**
 * ContentAddressing - sha512 / sha256 digest helper for OCFL content paths.
 *
 * OCFL v1.1 §3.5.3 lays out content addressing: every file is named by its
 * digest under `vN/content/`. This class provides the digest + the canonical
 * relative path inside a version's content directory.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Layout;

use InvalidArgumentException;

final class ContentAddressing
{
    public const ALG_SHA512 = 'sha512';
    public const ALG_SHA256 = 'sha256';

    public function __construct(
        public readonly string $algorithm = self::ALG_SHA512,
    ) {
        if (! in_array($algorithm, [self::ALG_SHA512, self::ALG_SHA256], true)) {
            throw new InvalidArgumentException(
                "OCFL v1.1 §6.1 allows only sha512 / sha256; got '{$algorithm}'"
            );
        }
    }

    /** Lower-case hex digest of the supplied bytes. */
    public function digestBytes(string $bytes): string
    {
        return hash($this->algorithm, $bytes);
    }

    /** Lower-case hex digest of a file on disk. */
    public function digestFile(string $path): string
    {
        $d = hash_file($this->algorithm, $path);
        if ($d === false) {
            throw new InvalidArgumentException("ContentAddressing: cannot hash {$path}");
        }
        return $d;
    }

    /**
     * Build the content path for a file inside a given version.
     *
     * Per OCFL v1.1 §3.3.2 the content path is implementation-defined;
     * we use `vN/content/<logical-path>` which is the simplest layout
     * the spec recommends for human-readable preservation copies.
     */
    public function contentPath(string $versionDir, string $logicalPath): string
    {
        $logical = ltrim(str_replace(['../', '..\\'], '', $logicalPath), '/');
        if ($logical === '') {
            throw new InvalidArgumentException('ContentAddressing: empty logical path');
        }
        return rtrim($versionDir, '/').'/content/'.$logical;
    }
}
