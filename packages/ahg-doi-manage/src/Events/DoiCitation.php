<?php

/**
 * DoiCitation event.
 *
 * Issue #654 Phase 3. Fired by DoiService (or any other writer) when a new
 * IO with a minted DOI gets a RelatedIdentifier relation that we want to
 * surface in DataCite Event Data. The listener registers it as a
 * citation event with the relevant relation-type-id (default
 * 'is-referenced-by').
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoiCitation
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $subjectDoi,
        public string $relatedIdentifier,
        public string $relationType = 'IsReferencedBy',
        public string $relatedIdentifierType = 'DOI',
    ) {}
}
