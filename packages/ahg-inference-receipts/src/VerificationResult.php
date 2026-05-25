<?php

declare(strict_types=1);

namespace AhgInferenceReceipts;

/**
 * Outcome of ReceiptChain::verify().
 */
final class VerificationResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly int $checkedCount,
        public readonly ?int $brokenAtSeq = null,
        public readonly ?string $reason = null,
    ) {
    }

    public static function ok(int $checkedCount): self
    {
        return new self(true, $checkedCount);
    }

    public static function fail(int $brokenAtSeq, string $reason): self
    {
        return new self(false, 0, $brokenAtSeq, $reason);
    }

    public function __toString(): string
    {
        if ($this->ok) {
            return "PASS ({$this->checkedCount} receipts verified)";
        }
        return "FAIL at seq {$this->brokenAtSeq}: {$this->reason}";
    }
}
