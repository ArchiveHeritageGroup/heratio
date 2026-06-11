<?php

/**
 * UnionPublishCommand - ahg:federation-publish
 *
 * Pushes this institution's opt-in, published discovery metadata into the
 * portable union index (federation_union_record) for the registered
 * self-member. Idempotent (upsert by member_id + record_ref), bounded
 * (streamed in id batches), and loud: prints how many records were shared,
 * how many were skipped, and why.
 *
 * Respects the opt-in gate - if sharing is disabled (default) or no
 * self-member has been registered, it shares nothing and says so. Writes to
 * the database only, so running as www-data is fine; no repo-file writes.
 *
 * Fresh code under #1203 - does not touch the locked F3 SharePoint
 * federation console commands or services.
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

namespace AhgFederation\Console;

use AhgFederation\Services\UnionCatalogueService;
use Illuminate\Console\Command;

class UnionPublishCommand extends Command
{
    protected $signature = 'ahg:federation-publish
        {--batch=500 : Number of records to stream per database page}';

    protected $description = 'Publish this institution\'s opt-in published records into the federated union index';

    public function handle(UnionCatalogueService $service): int
    {
        $batch = (int) $this->option('batch');
        if ($batch < 1) {
            $batch = 500;
        }

        $this->info('Federated union-catalogue publish');
        $this->line('  Sharing enabled: '.($service->isSharingEnabled() ? 'yes' : 'no (opt-in is OFF)'));

        $self = $service->selfMember();
        if ($self) {
            $this->line('  Self-member: #'.$self->id.' '.$self->name);
        } else {
            $this->line('  Self-member: not registered');
        }

        $summary = $service->publish($batch);

        $this->newLine();
        $this->line('  Examined: '.$summary['examined']);
        $this->line('  Shared:   '.$summary['shared']);
        $this->line('  Skipped:  '.$summary['skipped']);

        if (! empty($summary['reasons'])) {
            $this->newLine();
            $this->line('  Reasons:');
            foreach ($summary['reasons'] as $reason => $count) {
                $this->line('    - '.$reason.': '.$count);
            }
        }

        if (! $summary['enabled']) {
            $this->warn('Sharing is OFF. Enable opt-in sharing in the union-catalogue admin before publishing.');

            return self::SUCCESS;
        }

        if ($summary['self_member_id'] === null) {
            $this->warn('No self-member registered. Add a member marked "this institution" first.');

            return self::SUCCESS;
        }

        $this->info('Done. '.$summary['shared'].' record(s) in the union index for this institution.');

        return self::SUCCESS;
    }
}
