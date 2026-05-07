<?php

/**
 * HarvestCommand - artisan command to run federation OAI-PMH harvests
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

use AhgCore\Services\SettingHelper;
use AhgFederation\Services\HarvestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HarvestCommand extends Command
{
    protected $signature = 'ahg:federation-harvest
                            {--peer= : Harvest a specific peer id (defaults to all active peers)}
                            {--all-active : Harvest every active peer (default when --peer is omitted)}
                            {--full : Run a full harvest, ignoring last_harvest_at}
                            {--metadata-prefix= : Override metadata format (oai_dc, oai_heritage, oai_ead)}
                            {--from= : OAI from datestamp (UTC, e.g. 2026-01-01T00:00:00Z)}
                            {--until= : OAI until datestamp}
                            {--set= : OAI set spec}
                            {--dry-run : Resolve targets and print plan; do not call peers}';

    protected $description = 'Run an OAI-PMH harvest against one or more federation peers.';

    public function handle(HarvestService $service): int
    {
        if (!SettingHelper::getScoped('federation', 'federation_enabled', '0')) {
            $this->error('Federation is disabled (federation_enabled=0). Enable it in Plugin Management before harvesting.');
            return self::FAILURE;
        }

        $peers = $this->resolvePeers();
        if ($peers->isEmpty()) {
            $this->warn('No active federation peers to harvest.');
            return self::SUCCESS;
        }

        $this->line(sprintf('Federation harvest plan: %d peer%s', $peers->count(), $peers->count() === 1 ? '' : 's'));
        foreach ($peers as $peer) {
            $this->line(sprintf('  - [%d] %s (%s)', $peer->id, $peer->name, $peer->base_url));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run; no requests sent.');
            return self::SUCCESS;
        }

        $options = array_filter([
            'metadataPrefix' => $this->option('metadata-prefix'),
            'from' => $this->option('from'),
            'until' => $this->option('until'),
            'set' => $this->option('set'),
            'fullHarvest' => $this->option('full'),
        ], fn ($v) => $v !== null && $v !== false);

        $exit = self::SUCCESS;

        foreach ($peers as $peer) {
            $this->line('');
            $this->line("Harvesting [{$peer->id}] {$peer->name} ...");

            $sessionId = DB::table('federation_harvest_session')->insertGetId([
                'peer_id' => $peer->id,
                'started_at' => now(),
                'status' => 'running',
                'metadata_prefix' => $options['metadataPrefix'] ?? ($peer->default_metadata_prefix ?? 'oai_dc'),
                'harvest_from' => $options['from'] ?? null,
                'harvest_until' => $options['until'] ?? null,
                'harvest_set' => $options['set'] ?? ($peer->default_set ?? null),
                'is_full_harvest' => !empty($options['fullHarvest']) ? 1 : 0,
                'initiated_by' => null,
            ]);

            try {
                $result = $service->harvestPeer((int) $peer->id, $options);

                DB::table('federation_harvest_session')
                    ->where('id', $sessionId)
                    ->update([
                        'completed_at' => now(),
                        'status' => $result->isSuccessful() ? 'completed' : 'partial',
                        'records_total' => $result->stats['total'],
                        'records_created' => $result->stats['created'],
                        'records_updated' => $result->stats['updated'],
                        'records_deleted' => $result->stats['deleted'],
                        'records_skipped' => $result->stats['skipped'],
                        'records_errors' => $result->stats['errors'],
                        'error_message' => $result->stats['errors'] > 0
                            ? implode("\n", array_slice($result->stats['errorMessages'], 0, 20))
                            : null,
                    ]);

                $this->info($result->getSummary());

                if ($result->stats['errors'] > 0) {
                    $exit = self::FAILURE;
                }
            } catch (\Throwable $e) {
                DB::table('federation_harvest_session')
                    ->where('id', $sessionId)
                    ->update([
                        'completed_at' => now(),
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                $this->error("Harvest failed for {$peer->name}: " . $e->getMessage());
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }

    protected function resolvePeers(): \Illuminate\Support\Collection
    {
        $peerOpt = $this->option('peer');
        $query = DB::table('federation_peer')->where('is_active', 1)->orderBy('name');

        if ($peerOpt !== null && $peerOpt !== '') {
            $query->where('id', (int) $peerOpt);
        }

        return $query->get();
    }
}
