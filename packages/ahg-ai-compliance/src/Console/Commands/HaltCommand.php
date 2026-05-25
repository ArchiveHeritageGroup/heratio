<?php
/**
 * Heratio - global / per-service AI kill switch (EU AI Act Article 14(4)(e)).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Console\Commands;

use AhgAiCompliance\Services\OversightService;
use Illuminate\Console\Command;

final class HaltCommand extends Command
{
    protected $signature = 'ai-compliance:halt
        {service? : llm/htr/ner/donut/guardrail/translate/facedetect - omit for all}
        {--resume : Lift the halt instead of applying it}
        {--reason= : Why (recorded in policy + chain)}';

    protected $description = 'EU AI Act Article 14(4)(e) - bring an AI service to a halt (or resume one)';

    public function handle(OversightService $oversight): int
    {
        $service = $this->argument('service');
        $reason  = (string) ($this->option('reason') ?? ($this->option('resume') ? 'resume' : 'CLI halt'));
        $resume  = (bool) $this->option('resume');

        if (!$service) {
            // All-services path
            $n = $resume ? $oversight->resumeAll() : $oversight->haltAll($reason);
            $verb = $resume ? 'Resumed' : 'Halted';
            $this->info("{$verb} {$n} service(s).");
            return self::SUCCESS;
        }

        $p = $resume ? $oversight->resume($service) : $oversight->halt($service, $reason);
        if ($p === null) {
            $this->error("Service '{$service}' has no oversight policy entry.");
            $this->line('Run ai-compliance:install-key first, then reload to seed default policies.');
            return self::FAILURE;
        }

        $verb = $resume ? 'Resumed' : 'Halted';
        $this->info("{$verb} {$service}.");
        return self::SUCCESS;
    }
}
