<?php

/**
 * TapJsonProcessors — Laravel logging-tap class. Attached to the 'json'
 * channel in config/logging.php via the `tap` config key. Walks the
 * channel's handlers and registers RequestContextProcessor on each so
 * every JSON record gets the request-scoped extras.
 *
 * Phase 1+2 of #677.
 */

namespace App\Logging;

use Illuminate\Log\Logger;

class TapJsonProcessors
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new RequestContextProcessor());
        }
    }
}
