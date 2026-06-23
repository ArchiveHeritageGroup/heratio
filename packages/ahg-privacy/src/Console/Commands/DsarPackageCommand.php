<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgPrivacy\Console\Commands;

use AhgPrivacy\Services\DsarPackagerService;
use Illuminate\Console\Command;

/**
 * One-job DSAR access packager (#1327): gather a verified DSAR's linked
 * records, apply the redaction profile, and write a single JSON access
 * package; marks the DSAR completed.
 */
class DsarPackageCommand extends Command
{
    protected $signature = 'privacy:dsar-package
        {dsar : DSAR id}
        {--out= : Output file path (default: <storage>/dsar-exports/dsar-<ref>-<ts>.json)}
        {--user= : Acting user id used as redaction context}';

    protected $description = 'Build a single subject-access export package for a verified DSAR (redaction applied).';

    public function handle(DsarPackagerService $packager): int
    {
        $dsarId = (int) $this->argument('dsar');
        $out = $this->option('out') ?: null;
        $user = $this->option('user') !== null ? (int) $this->option('user') : null;

        try {
            $result = $packager->package($dsarId, $out, $user);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Packaged DSAR %s: %d record(s), %d bytes -> %s',
            $result['reference'],
            $result['records'],
            $result['bytes'],
            $result['file']
        ));

        return self::SUCCESS;
    }
}
