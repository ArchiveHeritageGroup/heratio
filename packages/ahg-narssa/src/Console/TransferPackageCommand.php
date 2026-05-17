<?php

/**
 * TransferPackageCommand - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgNarssa\Console;

use AhgNarssa\Services\TransferPackageService;
use Illuminate\Console\Command;

class TransferPackageCommand extends Command
{
    protected $signature = 'narssa:transfer-package
        {--io-ids= : Comma-separated list of information_object.id values to package}
        {--user-id= : User initiating the transfer (FK user.id)}
        {--title= : Optional title for the transfer batch}
        {--description= : Optional description for the transfer batch}
        {--from-approved : Package every approved transfer_narssa disposal_action not yet packaged}';

    protected $description = 'Build a NARSSA transfer .tar.gz containing manifest.csv + METS wrapper + EAD2002 per-item descriptions + SHA-256 checksums';

    public function handle(TransferPackageService $service): int
    {
        $initiatedBy = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $title       = $this->option('title');
        $description = $this->option('description');

        if ($this->option('from-approved')) {
            $result = $service->buildFromApprovedDisposals($initiatedBy);
            if (empty($result['transfer_id'])) {
                $this->info($result['message'] ?? 'Nothing to package.');
                return self::SUCCESS;
            }
        } else {
            $raw = (string) $this->option('io-ids');
            if ($raw === '') {
                $this->error('Provide --io-ids=N,N,... or --from-approved');
                return self::FAILURE;
            }
            $ioIds = array_filter(array_map('intval', explode(',', $raw)));
            if (empty($ioIds)) {
                $this->error('No valid information_object_id values supplied.');
                return self::FAILURE;
            }
            $result = $service->build($ioIds, $initiatedBy, $title, $description);
        }

        $this->info(sprintf(
            'Packaged %s with %d item(s) (%d digital objects, %d bytes). SHA-256 %s',
            $result['reference'],
            $result['item_count'],
            $result['digital_objects'] ?? 0,
            $result['total_bytes'],
            $result['package_sha256']
        ));
        $this->line('Package: ' . $result['package_path']);

        return self::SUCCESS;
    }
}
