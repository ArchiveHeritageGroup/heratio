<?php

namespace AhgLibrary\Services\Ledger;

/** A ledger rule refused the action. The message is safe to show to staff. */
class LedgerException extends \RuntimeException
{
    /** @param string[] $variances */
    public function __construct(string $message, public readonly array $variances = [])
    {
        parent::__construct($message);
    }
}
