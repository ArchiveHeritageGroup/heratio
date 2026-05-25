<?php

declare(strict_types=1);

namespace AhgInferenceReceipts\Storage;

use AhgInferenceReceipts\Receipt;
use RuntimeException;

/**
 * In-memory ChainStore. For tests, ephemeral chains, and as the reference
 * implementation of the contract.
 */
final class ArrayChainStore implements ChainStore
{
    /** @var Receipt[] */
    private array $receipts = [];

    public function append(Receipt $receipt): void
    {
        $expectedSeq = count($this->receipts);
        if ($receipt->seq !== $expectedSeq) {
            throw new RuntimeException("ArrayChainStore: expected seq {$expectedSeq}, got {$receipt->seq}");
        }
        $this->receipts[] = $receipt;
    }

    public function head(): ?Receipt
    {
        $n = count($this->receipts);
        return $n === 0 ? null : $this->receipts[$n - 1];
    }

    public function count(): int
    {
        return count($this->receipts);
    }

    public function range(int $fromSeq = 0, ?int $toSeq = null): iterable
    {
        $n = count($this->receipts);
        $upper = $toSeq === null ? $n - 1 : min($toSeq, $n - 1);
        for ($i = max(0, $fromSeq); $i <= $upper; $i++) {
            yield $this->receipts[$i];
        }
    }
}
