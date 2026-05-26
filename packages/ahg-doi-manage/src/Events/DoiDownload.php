<?php

/**
 * DoiDownload event.
 *
 * Issue #654 Phase 3. Fired when a digital object attached to an
 * information_object with a minted DOI is downloaded. Listener registers
 * a Counter-flavoured 'unique-dataset-requests-regular' event.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DoiDownload
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $doi,
        public ?int $informationObjectId = null,
        public ?int $digitalObjectId = null,
        public ?string $filename = null,
    ) {}
}
