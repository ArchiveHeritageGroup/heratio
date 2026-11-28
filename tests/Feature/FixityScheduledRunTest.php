<?php

/**
 * FixityScheduledRunTest - the scheduled fixity run (ahg:preservation-fixity-run) reports the
 * truth for each kind of file and records what it checked.
 *
 * Before v1.167.36 it looked for files at their folder without the file name and counted a pass
 * as a failure; before the ledger fix it wrote no integrity_ledger row, so every run re-checked
 * the same first batch and the rest were never checked.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FixityScheduledRunTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private int $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/fixity-run-'.getmypid().'-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root]);

        $this->repo = $this->object('QubitRepository');
        DB::table('actor')->insert(['id' => $this->repo, 'source_culture' => 'en']);
        DB::table('repository')->insert(['id' => $this->repo, 'source_culture' => 'en']);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    private function object(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A record in the test repository with one master file; returns the digital object id. */
    private function file(string $name, ?string $contents, ?string $baseline): int
    {
        $io = $this->object('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'repository_id' => $this->repo, 'source_culture' => 'en']);
        $do = $this->object('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $io, 'name' => $name, 'path' => "/uploads/r/$io/"]);
        if ($contents !== null) {
            @mkdir("{$this->root}/r/$io", 0777, true);
            file_put_contents("{$this->root}/r/$io/$name", $contents);
        }
        if ($baseline !== null) {
            DB::table('preservation_checksum')->insert(['digital_object_id' => $do, 'algorithm' => 'sha256', 'checksum_value' => $baseline, 'generated_at' => now()->subDay()]);
        }

        return $do;
    }

    public function test_each_kind_of_file_gets_its_true_outcome_and_a_ledger_row(): void
    {
        $good = $this->file('good.jpg', 'good', hash('sha256', 'good'));
        $changed = $this->file('changed.jpg', 'changed now', hash('sha256', 'as ingested'));
        $missing = $this->file('missing.jpg', null, hash('sha256', 'gone'));
        $fresh = $this->file('fresh.jpg', 'fresh', null);

        $schedule = DB::table('preservation_workflow_schedule')->insertGetId([
            'name' => 'Fixity test '.uniqid(), 'workflow_type' => 'fixity_check', 'is_enabled' => 1, 'batch_limit' => 10,
            'options' => json_encode(['algorithm' => 'sha256', 'repository_id' => $this->repo, 'stale_days' => 0]),
        ]);

        $this->artisan('ahg:preservation-fixity-run', ['--schedule' => $schedule, '--force' => true])
            ->expectsOutputToContain('processed=4 ok=1 fail=2 skip=1')
            ->assertExitCode(1); // a real mismatch or missing file fails the run

        $outcome = fn (int $do) => DB::table('integrity_ledger')->where('digital_object_id', $do)->orderByDesc('id')->value('outcome');
        $this->assertSame('pass', $outcome($good));
        $this->assertSame('fail', $outcome($changed));
        $this->assertSame('missing', $outcome($missing));
        $this->assertSame('no_baseline', $outcome($fresh));

        $run = DB::table('preservation_workflow_run')->where('schedule_id', $schedule)->orderByDesc('id')->first();
        $this->assertSame(1, (int) $run->objects_succeeded);
        $this->assertSame(2, (int) $run->objects_failed);
    }

    public function test_the_next_run_moves_on_to_files_not_yet_checked(): void
    {
        $first = $this->file('a.jpg', 'a', hash('sha256', 'a'));
        $second = $this->file('b.jpg', 'b', hash('sha256', 'b'));
        $schedule = DB::table('preservation_workflow_schedule')->insertGetId([
            'name' => 'Fixity rotation '.uniqid(), 'workflow_type' => 'fixity_check', 'is_enabled' => 1, 'batch_limit' => 1,
            'options' => json_encode(['algorithm' => 'sha256', 'repository_id' => $this->repo, 'stale_days' => 1]),
        ]);

        $this->artisan('ahg:preservation-fixity-run', ['--schedule' => $schedule, '--force' => true])->assertExitCode(0);
        $this->artisan('ahg:preservation-fixity-run', ['--schedule' => $schedule, '--force' => true])->assertExitCode(0);

        $checked = DB::table('integrity_ledger')->whereIn('digital_object_id', [$first, $second])->distinct()->pluck('digital_object_id')->all();
        $this->assertEqualsCanonicalizing([$first, $second], array_map('intval', $checked));
    }
}
