<?php

/**
 * DoiViewed event.
 *
 * Issue #654 Phase 3. Fired by the RecordDoiView middleware when an IO
 * show route is matched and the underlying record has an active DOI.
 * RegisterDoiEventsListener forwards it to DataciteEventsService as a
 * Counter-flavoured 'unique-dataset-investigations-regular' view event.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoiViewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $doi,
        public ?int $informationObjectId = null,
        public ?string $url = null,
        public ?string $userAgent = null,
    ) {}
}
