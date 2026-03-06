<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StatsCommand extends Command
{
    protected $signature = 'heratio:stats';

    protected $description = 'Show database statistics for all major entity types';

    public function handle(): int
    {
        $this->info('Gathering database statistics...');
        $this->newLine();

        try {
            $stats = [
                'Information objects' => DB::table('information_object')->where('id', '!=', 1)->count(),
                'Actors' => DB::table('actor')->whereNotIn('id', [3, 4])->count(),
                'Repositories' => DB::table('repository')->count(),
                'Accessions' => DB::table('accession')->count(),
                'Digital objects' => DB::table('digital_object')->where('usage_id', 140)->count(),
                'Terms' => DB::table('term')->count(),
                'Users' => DB::table('user')->count(),
                'Jobs' => DB::table('job')->count(),
            ];

            $rows = collect($stats)->map(fn ($count, $entity) => [$entity, number_format($count)])->values()->toArray();

            $this->table(['Entity', 'Count'], $rows);

            $total = array_sum($stats);
            $this->newLine();
            $this->info('Total records (excluding system objects): ' . number_format($total));

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to gather statistics: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
