<?php

/**
 * LlmHealthCheckCommand - probe every active LLM provider configuration
 * via LlmService and report reachability.
 *
 * All checks route through LlmService, which resolves endpoints from the
 * operator AI settings and sends inference through the AHG AI gateway
 * (ai.theahg.co.za/ai/v1) - this command never opens a GPU node port.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\LlmService;
use Illuminate\Console\Command;
use Throwable;

class LlmHealthCheckCommand extends Command
{
    protected $signature = 'ahg:llm-health {--json : Emit JSON instead of a table}';
    protected $description = 'Check LLM provider health';

    public function handle(LlmService $llm): int
    {
        $this->info('Checking LLM provider health...');

        try {
            $health = $llm->getAllHealth();
        } catch (Throwable $e) {
            $this->error('Health check failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($health)) {
            $this->warn('No active LLM provider configurations found.');
            return self::FAILURE;
        }

        $allOk = true;
        $rows  = [];
        foreach ($health as $name => $result) {
            $ok = $this->isHealthy($result);
            $allOk = $allOk && $ok;
            $detail = is_array($result)
                ? (string) ($result['error'] ?? $result['message'] ?? $result['model'] ?? '')
                : (string) $result;
            $rows[] = [(string) $name, $ok ? 'OK' : 'FAIL', $detail];
        }

        if ($this->option('json')) {
            $this->line(json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Provider', 'Status', 'Detail'], $rows);
        }

        if (!$allOk) {
            $this->error('One or more providers are unhealthy.');
            return self::FAILURE;
        }

        $this->info('All providers healthy.');
        return self::SUCCESS;
    }

    private function isHealthy(mixed $result): bool
    {
        if (!is_array($result)) {
            return false;
        }
        if (array_key_exists('success', $result)) {
            return (bool) $result['success'];
        }
        return ($result['status'] ?? null) === 'ok';
    }
}
