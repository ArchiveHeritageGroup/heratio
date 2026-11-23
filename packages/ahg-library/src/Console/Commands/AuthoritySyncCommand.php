<?php

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\AuthorityVocabularySyncService;
use AhgLibrary\Services\LcshVocabularyClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Re-syncs linked authority records, oldest first. Not scheduled. */
class AuthoritySyncCommand extends Command
{
    protected $signature = 'ahg:library-authority-sync {--limit=100 : Records to sync}';
    protected $description = 'Refresh authority records linked to an external vocabulary';

    public function handle(AuthorityVocabularySyncService $sync): int
    {
        $ids = DB::table('library_authority_record')
            ->where('scheme', LcshVocabularyClient::SCHEME)->whereNotNull('external_id')
            ->orderByRaw('last_synced_at IS NOT NULL')->orderBy('last_synced_at')
            ->limit(max(1, (int) $this->option('limit')))->pluck('authority_id');

        $failed = 0;
        foreach ($ids as $id) {
            $result = $sync->sync((int) $id);
            if (! $result['ok']) {
                $failed++;
                $this->warn("Authority {$id}: {$result['error']}");
            }
        }
        $this->info(count($ids).' processed, '.$failed.' failed.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
