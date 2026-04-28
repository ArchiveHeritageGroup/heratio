<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\OaisLifecycleService;
use Illuminate\Console\Command;

class PreservationPackageCommand extends Command
{
    protected $signature = 'ahg:preservation-package
        {--type=aip : Package type (sip, aip, dip)}
        {--object-id= : information_object id (required)}
        {--from-aip= : For type=dip: source aip package id}
        {--output= : Output directory override}
        {--include-derivatives : Include derivative files}
        {--created-by=cli : Audit string for who created the package}';

    protected $description = 'Generate OAIS packages (BagIt) — SIP/AIP/DIP via OaisLifecycleService';

    public function handle(OaisLifecycleService $svc): int
    {
        $type = strtolower((string) $this->option('type'));
        $oid = $this->option('object-id');
        $createdBy = (string) $this->option('created-by');
        $opts = array_filter([
            'output' => $this->option('output'),
            'include_derivatives' => (bool) $this->option('include-derivatives'),
        ]);

        try {
            switch ($type) {
                case 'sip':
                    if (! $oid) { $this->error('--object-id is required for type=sip'); return self::FAILURE; }
                    $id = $svc->createSip((int) $oid, $opts, $createdBy);
                    $this->info("created SIP package id={$id}");
                    return self::SUCCESS;

                case 'aip':
                    if (! $oid) { $this->error('--object-id is required for type=aip'); return self::FAILURE; }
                    $r = $svc->createAipFromIO((int) $oid, $opts, $createdBy);
                    $this->info("created AIP package id=" . ($r['package_id'] ?? '?') . " bag=" . ($r['bag_path'] ?? '?'));
                    return self::SUCCESS;

                case 'dip':
                    $aipId = $this->option('from-aip');
                    if (! $aipId) { $this->error('--from-aip=<id> is required for type=dip'); return self::FAILURE; }
                    $r = $svc->createDipFromAip((int) $aipId, $opts, $createdBy);
                    $this->info("created DIP package id=" . ($r['package_id'] ?? '?'));
                    return self::SUCCESS;

                default:
                    $this->error("unknown --type={$type} (expected sip|aip|dip)");
                    return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('package failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
