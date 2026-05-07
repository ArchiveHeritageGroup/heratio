<?php

/**
 * VocabSyncCommand - artisan command to pull vocabularies from federation peers
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
use AhgFederation\Services\VocabSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VocabSyncCommand extends Command
{
    protected $signature = 'ahg:federation-vocab-sync
                            {--peer= : Peer id (defaults to all peers with sync enabled)}
                            {--taxonomy= : Taxonomy id (defaults to all configured taxonomies)}
                            {--direction=pull : pull (default), push, or bidirectional. Push not yet supported.}
                            {--dry-run : Resolve targets and print plan; do not call peers}';

    protected $description = 'Run a vocabulary sync against one or more federation peers.';

    public function handle(VocabSyncService $service): int
    {
        if (!SettingHelper::getScoped('federation', 'federation_enabled', '0')) {
            $this->error('Federation is disabled (federation_enabled=0).');
            return self::FAILURE;
        }

        $configs = $this->resolveConfigs();
        if ($configs->isEmpty()) {
            $this->warn('No vocabulary sync configurations match.');
            return self::SUCCESS;
        }

        $direction = $this->option('direction');
        if ($direction === VocabSyncService::DIRECTION_PUSH || $direction === VocabSyncService::DIRECTION_BIDIRECTIONAL) {
            $this->error('Push and bidirectional sync are not yet supported (Heratio peers do not yet expose a /api/federation/vocab/import endpoint).');
            return self::FAILURE;
        }

        $this->line(sprintf('Vocab sync plan: %d configuration%s', $configs->count(), $configs->count() === 1 ? '' : 's'));
        foreach ($configs as $cfg) {
            $this->line(sprintf('  - peer=%d  taxonomy=%d  direction=%s  conflict=%s',
                $cfg->peer_id, $cfg->taxonomy_id, $cfg->sync_direction, $cfg->conflict_resolution));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run; no requests sent.');
            return self::SUCCESS;
        }

        $exit = self::SUCCESS;

        foreach ($configs as $cfg) {
            $this->line('');
            $this->line("Syncing peer={$cfg->peer_id} taxonomy={$cfg->taxonomy_id} ...");

            try {
                $result = $service->syncWithPeer((int) $cfg->peer_id, (int) $cfg->taxonomy_id, $direction);
                $this->info($result->getSummary());
                if (!$result->isSuccessful()) {
                    $exit = self::FAILURE;
                }
            } catch (\Throwable $e) {
                $this->error("Sync failed: " . $e->getMessage());
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }

    protected function resolveConfigs(): \Illuminate\Support\Collection
    {
        $q = DB::table('federation_vocab_sync')->where('sync_enabled', 1);

        if ($this->option('peer')) { $q->where('peer_id', (int) $this->option('peer')); }
        if ($this->option('taxonomy')) { $q->where('taxonomy_id', (int) $this->option('taxonomy')); }

        return $q->orderBy('peer_id')->orderBy('taxonomy_id')->get();
    }
}
