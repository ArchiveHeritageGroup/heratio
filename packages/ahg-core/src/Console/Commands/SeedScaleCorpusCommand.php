<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generate a production-scale synthetic information_object corpus for stress-
 * testing the nested-set -> closure migration (heratio#1331/#1333) on the dev
 * box. Builds a realistic archival forest (fonds -> subfonds -> series -> file
 * -> item) of ~N nodes.
 *
 * Design choices that make N=340k feasible in one pass:
 *  - lft/rgt are assigned BY CONSTRUCTION during a depth-first walk (pre-order
 *    enter = lft, post-order exit = rgt), so no slow per-row rebuild is needed.
 *  - Roots are parent_id=NULL with lft/rgt starting past the current global
 *    max(rgt), so existing rows are left completely untouched (valid disjoint
 *    forest in the global nested set).
 *  - Each node is a full CTI record: object + information_object + _i18n +
 *    slug + published status, batch-inserted in chunks (post-order flush keeps
 *    memory bounded to one chunk).
 *  - Every row is marked identifier='SCALE-<id>' / slug='scale-<id>' so the
 *    whole corpus is collision-free and cleanly purgeable (--purge).
 *
 * NOT for production data. Generates synthetic descriptions only.
 */
class SeedScaleCorpusCommand extends Command
{
    protected $signature = 'ahg:seed-scale-corpus
        {count=340000 : Approximate number of information_objects to create}
        {--chunk=2000 : Rows per batched insert}
        {--purge : Delete a previously-seeded scale corpus (identifier LIKE SCALE-%) and exit}
        {--build-closure : Run ahg:build-closure --all after seeding}
        {--verify : After seeding, verify nested-set vs closure parity}';

    protected $description = 'Seed a production-scale synthetic IO corpus for closure/nested-set stress testing (#1333). Dev only.';

    /** Level taxonomy ids (taxonomy 34, verified in heratio_dev) + branching factor + child level. */
    private const LEVELS = [
        236 => ['kids' => 3, 'next' => 237, 'name' => 'Fonds'],     // root
        237 => ['kids' => 4, 'next' => 239, 'name' => 'Subfonds'],
        239 => ['kids' => 8, 'next' => 241, 'name' => 'Series'],
        241 => ['kids' => 6, 'next' => 242, 'name' => 'File'],
        242 => ['kids' => 0, 'next' => null, 'name' => 'Item'],     // leaf
    ];

    private const ROOT_LEVEL = 236;
    private const PUB_TYPE = 158;
    private const PUB_PUBLISHED = 160;

    private int $created = 0;
    private int $target = 0;
    private int $nextId = 0;
    private int $lftCounter = 0;
    private string $now = '';
    private int $chunk = 2000;

    /** @var array<int,array{0:int,1:?int,2:int,3:int,4:int}> id,parent,level,lft,rgt */
    private array $buffer = [];

    public function handle(): int
    {
        @ini_set('memory_limit', '2G');

        if ($this->option('purge')) {
            return $this->purge();
        }

        $this->target = max(1, (int) $this->argument('count'));
        $this->chunk = max(100, (int) $this->option('chunk'));
        $this->now = Carbon::now()->toDateTimeString();

        $this->nextId = (int) DB::table('object')->max('id');
        $this->lftCounter = (int) (DB::table('information_object')->max('rgt') ?? 0) + 1;

        $startId = $this->nextId;
        $this->info("Seeding ~{$this->target} information_objects (forest: fonds->subfonds->series->file->item).");
        $this->line("  start object.id={$startId}, start lft={$this->lftCounter}, chunk={$this->chunk}");
        $t0 = microtime(true);

        DB::connection()->disableQueryLog();

        // Rows are flushed in post-order (a node's rgt is only known after its
        // children), so within a batch a child can precede its parent. The
        // forest is internally consistent by construction, so disable the
        // self-referential parent_id FK check for the bulk load (standard
        // bulk-import technique) and restore it in finally.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            while ($this->created < $this->target) {
                $this->buildNode(null, self::ROOT_LEVEL);
            }
            $this->flush(true);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $elapsed = round(microtime(true) - $t0, 1);
        $maxRgt = DB::table('information_object')->max('rgt');
        $this->newLine();
        $this->info("Created {$this->created} IOs in {$elapsed}s. object.id {$startId}..{$this->nextId}, max rgt now {$maxRgt}.");

        if ($this->option('build-closure')) {
            $this->newLine();
            $this->line('Building closure tables (ahg:build-closure --all)...');
            $this->call('ahg:build-closure', ['--all' => true]);
        }

        if ($this->option('verify')) {
            $this->newLine();
            $this->call('ahg:build-closure', ['--verify' => true]);
        }

        $this->newLine();
        $this->info('Done. Purge anytime with: php artisan ahg:seed-scale-corpus --purge');

        return self::SUCCESS;
    }

    /**
     * Create one node (pre-order lft), recurse for children, then close it
     * (post-order rgt) and buffer the complete row. Recursion depth is bounded
     * by the tree depth (~5), not the node count.
     */
    private function buildNode(?int $parentId, int $level): void
    {
        if ($this->created >= $this->target) {
            return;
        }

        $id = ++$this->nextId;
        $this->created++;
        $lft = $this->lftCounter++;

        $spec = self::LEVELS[$level];
        $childLevel = $spec['next'];
        if ($childLevel !== null) {
            for ($i = 0; $i < $spec['kids'] && $this->created < $this->target; $i++) {
                $this->buildNode($id, $childLevel);
            }
        }

        $rgt = $this->lftCounter++;
        $this->buffer[] = [$id, $parentId, $level, $lft, $rgt];

        if (count($this->buffer) >= $this->chunk) {
            $this->flush();
        }

        if ($this->created % 20000 === 0) {
            $this->line("  ... {$this->created}/{$this->target}");
        }
    }

    /** Flush the buffered nodes into all five CTI tables in one batched insert each. */
    private function flush(bool $final = false): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $objects = $ios = $i18n = $slugs = $statuses = [];
        foreach ($this->buffer as [$id, $parentId, $level, $lft, $rgt]) {
            $levelName = self::LEVELS[$level]['name'];

            $objects[] = [
                'id' => $id,
                'class_name' => 'QubitInformationObject',
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'serial_number' => 0,
            ];
            $ios[] = [
                'id' => $id,
                'source_culture' => 'en',
                'parent_id' => $parentId,
                'level_of_description_id' => $level,
                'identifier' => 'SCALE-'.$id,
                'lft' => $lft,
                'rgt' => $rgt,
            ];
            $i18n[] = [
                'id' => $id,
                'culture' => 'en',
                'title' => $levelName.' '.$id,
                'scope_and_content' => 'Synthetic scale-test record ('.$levelName.'). Generated for #1333 closure/nested-set load testing.',
            ];
            $slugs[] = [
                'object_id' => $id,
                'slug' => 'scale-'.$id,
                'serial_number' => 0,
            ];
            $statuses[] = [
                'object_id' => $id,
                'type_id' => self::PUB_TYPE,
                'status_id' => self::PUB_PUBLISHED,
                'serial_number' => 0,
            ];
        }

        DB::transaction(function () use ($objects, $ios, $i18n, $slugs, $statuses) {
            // Explicit ids into the auto-increment object PK is intentional (CTI shared key).
            DB::table('object')->insert($objects);
            DB::table('information_object')->insert($ios);
            DB::table('information_object_i18n')->insert($i18n);
            DB::table('slug')->insert($slugs);
            DB::table('status')->insert($statuses);
        });

        $this->buffer = [];
    }

    /** Remove a previously-seeded scale corpus by its SCALE- identifier marker. */
    private function purge(): int
    {
        $ids = DB::table('information_object')
            ->where('identifier', 'like', 'SCALE-%')
            ->pluck('id');

        $n = $ids->count();
        if ($n === 0) {
            $this->info('No scale corpus found (identifier LIKE SCALE-%). Nothing to purge.');

            return self::SUCCESS;
        }

        $this->warn("Purging {$n} scale-test IOs and all CTI children...");
        $t0 = microtime(true);

        $hasClosure = DB::getSchemaBuilder()->hasTable('information_object_closure');

        // FK checks off so the explicit per-table deletes don't fan out across
        // the many ON DELETE CASCADE references to object/information_object
        // (some child FK columns are unindexed -> a full scan per delete). The
        // SCALE corpus only touches these six tables, so we delete them directly.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($ids->chunk(5000) as $batch) {
                $chunkIds = $batch->all();
                DB::transaction(function () use ($chunkIds, $hasClosure) {
                    DB::table('status')->whereIn('object_id', $chunkIds)->delete();
                    DB::table('slug')->whereIn('object_id', $chunkIds)->delete();
                    DB::table('information_object_i18n')->whereIn('id', $chunkIds)->delete();
                    if ($hasClosure) {
                        DB::table('information_object_closure')->whereIn('descendant', $chunkIds)->delete();
                        DB::table('information_object_closure')->whereIn('ancestor', $chunkIds)->delete();
                    }
                    DB::table('information_object')->whereIn('id', $chunkIds)->delete();
                    DB::table('object')->whereIn('id', $chunkIds)->delete();
                });
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $elapsed = round(microtime(true) - $t0, 1);
        $this->info("Purged {$n} scale IOs in {$elapsed}s. Run ahg:build-closure --all to rebuild closure if needed.");

        return self::SUCCESS;
    }
}
