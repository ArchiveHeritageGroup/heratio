<?php

namespace AhgPortableExport\Services\Deposit;

/**
 * The institution's own repository is this Heratio: the frozen package is
 * already on its storage and the release page serves it once released, so
 * nothing leaves the system.
 */
class InstitutionDepositDriver implements DepositDriver
{
    public function deposit(object $target, object $deposit, array $files, array $metadata, callable $remember): array
    {
        return ['external_id' => 'local', 'external_url' => $metadata['landing_url'] ?? null];
    }

    /** Nothing left this system, and the release page only serves released versions. */
    public function discard(object $target, object $deposit): void {}
}
