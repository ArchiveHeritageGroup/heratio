<?php

/**
 * PruneIdempotencyCommand
 *
 * Artisan: `php artisan api:prune-idempotency`
 *
 * Deletes expired Idempotency-Key rows from ahg_api_idempotency_key.
 * Run daily from cron alongside other Heratio prunes.
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneIdempotencyCommand extends Command
{
    protected $signature = 'api:prune-idempotency {--all : Delete every row, ignoring expires_at}';

    protected $description = 'Delete expired Idempotency-Key rows from ahg_api_idempotency_key.';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_api_idempotency_key')) {
            $this->warn('ahg_api_idempotency_key table missing — nothing to prune.');

            return self::SUCCESS;
        }

        $q = DB::table('ahg_api_idempotency_key');
        if (! $this->option('all')) {
            $q = $q->where('expires_at', '<', now());
        }

        $deleted = $q->delete();

        $this->info(sprintf('Pruned %d idempotency-key row(s).', $deleted));

        return self::SUCCESS;
    }
}
