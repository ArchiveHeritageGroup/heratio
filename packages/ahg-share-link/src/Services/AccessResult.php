<?php

/**
 * AccessResult — outcome of a recipient access validation. Mirror of the
 * AtoM-side service.
 *
 * @phase D
 */

namespace AhgShareLink\Services;

final class AccessResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?object $tokenRow,
        public readonly string $action,
        public readonly ?string $reason,
        public readonly int $httpStatus,
    ) {
    }

    public static function allow(object $tokenRow, string $action = 'view'): self
    {
        return new self(true, $tokenRow, $action, null, 200);
    }

    public static function deny(?object $tokenRow, string $action, string $reason): self
    {
        return new self(false, $tokenRow, $action, $reason, 410);
    }

    public static function notFound(): self
    {
        return new self(false, null, 'denied_unknown', 'Share link not found.', 410);
    }
}
