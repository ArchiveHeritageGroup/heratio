<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

/** A reference set that cannot form a tree. Raised before anything is written. */
class HierarchyRejected extends \RuntimeException
{
    /** @param list<array{code:string,ref:string,detail:string}> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(count($problems).' reference code problem(s)');
    }
}
