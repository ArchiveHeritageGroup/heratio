<?php

namespace AhgLibrary\Services\Ledger;

/**
 * The market-specific half of the sub-ledger. A per-market compliance module
 * binds its own implementation of this interface to supply the tax treatment,
 * document numbering and required tax-invoice fields. The sub-ledger itself
 * holds no market rule.
 */
interface LedgerTaxProvider
{
    /** Stable identifier stored on every document this provider calculates. */
    public function key(): string;

    /**
     * Tax for each line, in input order.
     *
     * @param  array<int, array{net_cents:int, tax_code:string}>  $lines
     * @return array<int, array{rate:int, tax_cents:int}>
     *
     * @throws LedgerException when a code has no rate on the document date
     */
    public function calculate(array $lines, string $documentDate): array;

    /** Next document number. Called inside the posting transaction. */
    public function nextNumber(string $docType, string $documentDate): string;

    /**
     * library_ledger_document columns that must be filled before posting.
     *
     * @return string[]
     */
    public function requiredFields(string $docType): array;
}
