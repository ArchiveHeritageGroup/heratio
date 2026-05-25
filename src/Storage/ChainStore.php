<?php

declare(strict_types=1);

namespace AhgInferenceReceipts\Storage;

use AhgInferenceReceipts\Receipt;

/**
 * Pluggable storage backend for a receipt chain.
 *
 * The library ships ArrayChainStore for tests + ephemeral use.
 * The Heratio plug-in supplies an Eloquent-backed implementation
 * over the `ai_inference_log` table.
 */
interface ChainStore
{
    /**
     * Append a receipt. Implementations should reject out-of-order writes
     * (seq must equal current count) and break ties under concurrency by
     * letting only one writer win (DB UNIQUE on entry_hash is the
     * canonical mechanism for the Eloquent backend).
     */
    public function append(Receipt $receipt): void;

    /**
     * Latest receipt by seq, or null if the chain is empty.
     */
    public function head(): ?Receipt;

    /**
     * Number of receipts in the chain.
     */
    public function count(): int;

    /**
     * Iterate receipts in seq order between $fromSeq (inclusive) and
     * $toSeq (inclusive). $toSeq null = no upper bound.
     *
     * @return iterable<Receipt>
     */
    public function range(int $fromSeq = 0, ?int $toSeq = null): iterable;
}
