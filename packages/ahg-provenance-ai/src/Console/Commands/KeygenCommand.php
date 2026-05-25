<?php

/**
 * KeygenCommand - Console command for Heratio
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

namespace AhgProvenanceAi\Console\Commands;

use AhgProvenanceAi\Services\InferenceSigner;
use Illuminate\Console\Command;

/**
 * Generate the Ed25519 keypair used to sign AI inference manifests
 * (heratio#136). Run once per install:
 *
 *     sudo -u www-data php artisan ahg:provenance-ai:keygen
 *
 * The private key lands in storage/app/ai-signing/ (gitignored). Until this
 * is run, inferences are simply written unsigned.
 */
class KeygenCommand extends Command
{
    protected $signature = 'ahg:provenance-ai:keygen {--force : Replace an existing keypair}';

    protected $description = 'Generate the Ed25519 keypair that signs AI inference manifests (heratio#136)';

    public function handle(InferenceSigner $signer): int
    {
        try {
            $keyId = $signer->generateKeypair((bool) $this->option('force'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Ed25519 inference-signing keypair generated.');
        $this->line('  signer_key_id: '.$keyId);
        $this->line('  location:      '.storage_path('app/ai-signing').'  (gitignored)');
        $this->newLine();
        $this->warn('Keep ed25519.private safe and backed up - it is not in git and not in the database.');

        return self::SUCCESS;
    }
}
