<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorityCompletenessScanCommand extends Command
{
    protected $signature = 'ahg:authority-completeness-scan
        {--limit=0 : Max actors to scan (0 = all)}
        {--connection=atom : Source DB connection (atom for ANC corpus, default heratio)}';

    protected $description = 'Calculate completeness scores for authority (actor) records via ISAAR-CPF field coverage';

    public function handle(): int
    {
        $autoRecalc = Schema::hasTable('ahg_authority_config')
            ? DB::table('ahg_authority_config')->where('config_key', 'completeness_auto_recalc')->value('config_value')
            : null;
        if ($autoRecalc === '0') {
            $this->info('completeness_auto_recalc disabled in ahg_authority_config — skipping.');
            return self::SUCCESS;
        }

        $conn = (string) $this->option('connection');
        $limit = (int) $this->option('limit');


        // Score each actor by % of ISAAR(CPF) fields populated in actor + actor_i18n.
        // Real column names per atom.actor_i18n: history, places, legal_status,
        // functions, mandates, internal_structures, general_context.
        $base = DB::connection($conn)->table('actor as a')
            ->join('actor_i18n as ai18n', function ($j) {
                $j->on('a.id', '=', 'ai18n.id')->where('ai18n.culture', '=', 'en');
            });
        $total = (clone $base)->count();
        if ($limit > 0) $base->limit($limit);

        $scanned = 0; $sumScore = 0.0;
        foreach ($base->select(
            'a.id',
            'ai18n.authorized_form_of_name',
            'ai18n.history',
            'ai18n.places',
            'ai18n.legal_status',
            'ai18n.functions',
            'ai18n.mandates',
            'ai18n.internal_structures',
            'ai18n.general_context'
        )->cursor() as $row) {
            $cells = [
                $row->authorized_form_of_name, $row->history, $row->places,
                $row->legal_status, $row->functions, $row->mandates,
                $row->internal_structures, $row->general_context,
            ];
            $populated = count(array_filter($cells, fn($v) => trim((string) $v) !== ''));
            $score = $populated / count($cells);
            $sumScore += $score;
            $scanned++;
            if ($scanned % 1000 === 0) $this->line("  scanned {$scanned}/{$total}");
        }
        $avg = $scanned > 0 ? round($sumScore / $scanned, 3) : 0.0;
        $this->info("done; actors_scanned={$scanned}/{$total} avg_completeness={$avg}");
        return self::SUCCESS;
    }
}
