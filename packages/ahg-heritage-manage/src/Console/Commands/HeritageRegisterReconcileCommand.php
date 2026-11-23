<?php

namespace AhgHeritageManage\Console\Commands;

use AhgHeritageManage\Services\HeritageRegisterReconciliationService;
use Illuminate\Console\Command;

class HeritageRegisterReconcileCommand extends Command
{
    protected $signature = 'heritage:register-reconcile';

    protected $description = 'Compare the two heritage asset registers. Read-only.';

    public function handle(HeritageRegisterReconciliationService $service): int
    {
        $r = $service->compare();
        if (! $r['available']) {
            $this->warn('One of the registers is not installed.');

            return self::SUCCESS;
        }

        $this->table(['Measure', 'Count'], [
            ['heritage_asset rows', $r['heritage_total']],
            ['ipsas_heritage_asset rows', $r['ipsas_total']],
            ['Same record in both', $r['matched']],
            ['Same record, different value', count($r['value_differences'])],
            ['Only in ipsas_heritage_asset', count($r['only_in_ipsas'])],
            ['ipsas rows with no linked record', $r['ipsas_without_record']],
            ['Only in heritage_asset', $r['only_in_heritage']],
        ]);

        return self::SUCCESS;
    }
}
