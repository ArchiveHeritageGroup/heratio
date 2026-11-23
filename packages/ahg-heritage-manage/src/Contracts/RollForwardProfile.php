<?php

namespace AhgHeritageManage\Contracts;

/**
 * Wording a market module supplies for the carrying-amount reconciliation.
 * The arithmetic stays in HeritageRollForwardService and is the same everywhere.
 */
interface RollForwardProfile
{
    /** heritage_accounting_standard.code this profile serves. */
    public function standardCode(): string;

    public function title(): string;

    /** Line key (opening, additions, ...) to label. */
    public function lineLabels(): array;
}
