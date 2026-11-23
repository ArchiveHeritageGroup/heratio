<?php

/**
 * Replays the current MARC mapping over stored import records (#1476).
 * Reports only unless --apply is given, and --apply fills empty columns only.
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\MarcRecordReplayService;
use AhgLibrary\Services\MarcRecordStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReplayMarcRecordsCommand extends Command
{
    protected $signature = 'ahg:library-marc-replay {--item= : One library_item id} {--apply : Write mapped values into empty columns}';

    protected $description = 'Compare or re-apply the MARC mapping against stored import records';

    public function handle(MarcRecordReplayService $replay): int
    {
        $query = DB::table(MarcRecordStore::TABLE)->where('event', 'import')->distinct()->orderBy('library_item_id');
        if ($this->option('item')) {
            $query->where('library_item_id', (int) $this->option('item'));
        }

        $totals = ['items' => 0, 'fill' => 0, 'conflict' => 0, 'unusable' => 0, 'written' => 0];
        foreach ($query->pluck('library_item_id') as $itemId) {
            $totals['items']++;
            foreach ($replay->plan((int) $itemId)['fields'] as $field) {
                if (isset($totals[$field['status']])) {
                    $totals[$field['status']]++;
                }
            }
            if ($this->option('apply')) {
                $totals['written'] += $replay->apply((int) $itemId, $replay->fillableColumns((int) $itemId));
            }
        }

        $this->info(sprintf(
            '%d item(s): %d empty column(s) can be filled, %d differ from the catalogue, %d unusable, %d written.',
            $totals['items'], $totals['fill'], $totals['conflict'], $totals['unusable'], $totals['written']
        ));
        return self::SUCCESS;
    }
}
