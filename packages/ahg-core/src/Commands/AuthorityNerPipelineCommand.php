<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthorityNerPipelineCommand extends Command
{
    protected $signature = 'ahg:authority-ner-pipeline
        {--threshold=0.85 : Minimum NER confidence}
        {--types=PERSON,ORG : Comma-separated entity_type filter}
        {--limit=100 : Max NER rows to process}
        {--dry-run : Simulate without creating ahg_ner_authority_stub rows}
        {--connection=atom : Source DB for ahg_ner_entity}';

    protected $description = 'Create stub authority candidates from approved NER entities (status=approved, no existing actor link)';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_ner_authority_stub')) {
            $this->warn('ahg_ner_authority_stub missing.');
            return self::SUCCESS;
        }

        $conn = (string) $this->option('connection');
        $threshold = (float) $this->option('threshold');
        $types = array_filter(array_map('trim', explode(',', (string) $this->option('types'))));
        $limit = max(1, (int) $this->option('limit'));
        $dry = (bool) $this->option('dry-run');

        // Already-stubbed (entity_id, entity_value) pairs.
        $existing = DB::table('ahg_ner_authority_stub')
            ->pluck('ner_entity_id')
            ->flip();

        $candidates = DB::connection($conn)->table('ahg_ner_entity')
            ->whereIn('entity_type', $types)
            ->where('status', 'approved')
            ->where('confidence', '>=', $threshold)
            ->whereNull('linked_actor_id')
            ->orderByDesc('confidence')
            ->limit($limit * 5)
            ->get(['id','object_id','entity_type','entity_value','confidence']);

        $created = 0; $skipped = 0;
        foreach ($candidates as $c) {
            if (isset($existing[$c->id])) { $skipped++; continue; }
            if (! $dry) {
                DB::table('ahg_ner_authority_stub')->insert([
                    'ner_entity_id'    => $c->id,
                    'actor_id'         => 0,                  // placeholder — set when admin promotes the stub
                    'source_object_id' => $c->object_id,
                    'entity_type'      => $c->entity_type,
                    'entity_value'     => mb_substr($c->entity_value, 0, 500),
                    'confidence'       => $c->confidence,
                    'status'           => 'stub',
                    'created_at'       => now(),
                ]);
            }
            $created++;
            if ($created >= $limit) break;
        }
        $this->info(sprintf("done; created=%d skipped=%d (already-stubbed) threshold=%.2f types=%s%s",
            $created, $skipped, $threshold, implode(',', $types), $dry ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }
}
