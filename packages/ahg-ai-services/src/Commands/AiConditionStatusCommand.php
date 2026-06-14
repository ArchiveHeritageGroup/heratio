<?php

/**
 * AiConditionStatusCommand - report the state of the AI condition-assessment
 * feature.
 *
 * NEEDS-DECISION (#1268): Heratio has no condition-assessment SERVICE class and
 * no AHG-gateway route for condition scanning. The only configuration that
 * exists (ai_condition_service_url) historically pointed at a GPU node port
 * (e.g. :5000 / :8100), which the standing gateway rule forbids an application
 * from calling directly. Until a gateway condition route + a backing service
 * exist, this command CANNOT verify a live AI condition service.
 *
 * What it does do honestly: surface the persisted assessment data + whether the
 * feature is configured, and return a NON-ZERO exit so it can never report a
 * false "healthy" result.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AiConditionStatusCommand extends Command
{
    protected $signature = 'ahg:ai-condition-status';
    protected $description = 'Check AI condition service health';

    public function handle(): int
    {
        $this->warn('AI condition service is NOT wired to the AHG gateway (NEEDS-DECISION, #1268).');
        $this->line('No condition-assessment service class or gateway route exists; a live health check is not possible.');
        $this->newLine();

        // Honest reporting of whatever persisted data exists.
        $url = $this->setting('ai_condition_service_url');
        $this->line('Configured ai_condition_service_url: ' . ($url !== null && $url !== '' ? $url : '(unset)'));
        if ($url !== null && (str_contains($url, ':5000') || str_contains($url, ':8100') || preg_match('~//192\.168\.~', $url))) {
            $this->error('Configured endpoint looks like a direct GPU node port - this would bypass the AHG gateway and must not be used.');
        }

        if (Schema::hasTable('ahg_ai_condition_assessment')) {
            try {
                $total     = (int) DB::table('ahg_ai_condition_assessment')->count();
                $confirmed = (int) DB::table('ahg_ai_condition_assessment')->where('is_confirmed', 1)->count();
                $this->line("Stored assessments: {$total} (confirmed: {$confirmed})");
            } catch (Throwable $e) {
                $this->line('Assessment table present but unreadable: ' . $e->getMessage());
            }
        } else {
            $this->line('ahg_ai_condition_assessment table not installed.');
        }

        // Non-zero: we cannot assert the service is healthy.
        return self::FAILURE;
    }

    private function setting(string $key): ?string
    {
        foreach ([['ahg_ner_settings', 'setting_key'], ['ahg_settings', 'setting_key']] as [$table, $col]) {
            try {
                if (!Schema::hasTable($table)) {
                    continue;
                }
                $v = DB::table($table)->where($col, $key)->value('setting_value');
                if ($v !== null && $v !== '') {
                    return (string) $v;
                }
            } catch (Throwable) {
                // keep probing
            }
        }
        return null;
    }
}
