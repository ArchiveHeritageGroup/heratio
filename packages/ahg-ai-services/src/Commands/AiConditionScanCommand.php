<?php

/**
 * AiConditionScanCommand - bulk AI condition assessment.
 *
 * NEEDS-DECISION (#1268): there is NO condition-assessment service in Heratio
 * (AiController's condition methods are themselves unbuilt stubs) and NO AHG
 * gateway route for condition scanning. The only configuration that ever
 * existed pointed at a GPU node port (:5000 / :8100), and the standing AHG
 * gateway rule forbids an application from calling a node port directly.
 *
 * Rather than fake a successful scan (the original stub printed "Done." and
 * returned 0 without doing anything), this command now fails loudly with a
 * clear TODO and a NON-ZERO exit. It must not be implemented until:
 *   1. a condition-assessment route is added to the AHG gateway, AND
 *   2. a backing ConditionService is built in this package that calls it.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;

class AiConditionScanCommand extends Command
{
    protected $signature = 'ahg:ai-condition-scan {--repository=} {--limit=50} {--confidence=0.25} {--batch=10}';
    protected $description = 'AI condition assessment bulk scan';

    public function handle(): int
    {
        $this->error('ahg:ai-condition-scan is NOT implemented (NEEDS-DECISION, #1268).');
        $this->warn('Blocked on: (1) an AHG gateway condition-assessment route, and (2) a backing ConditionService.');
        $this->warn('The only known condition endpoint is a direct GPU node port (:5000/:8100), which the gateway rule forbids.');
        $this->line('No scan was performed. Resolve the gateway route + service before wiring this command.');

        // TODO(#1268): implement once a ConditionService exists that routes
        // through ai.theahg.co.za/ai/v1/<condition-route>. Never call a node port.
        return self::FAILURE;
    }
}
