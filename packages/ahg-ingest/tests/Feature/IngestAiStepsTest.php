<?php

/**
 * IngestAiStepsTest - the ingest wizard's processing options do what they
 * say (19 Nov 2026). Virus scan, OCR, NER, summarise, spellcheck and
 * translate each called a method that did not exist, so every one silently
 * did nothing.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgAiServices\Jobs\IngestAiStepJob;
use AhgIngest\Services\IngestCommitRunner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IngestAiStepsTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->root = sys_get_temp_dir().'/ahg-ingest-ai-'.uniqid();
        mkdir($this->root.'/staging', 0777, true);
        config(['heratio.uploads_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    private function makeSession(array $flags, array $row): int
    {
        $id = (int) DB::table('ingest_session')->insertGetId(array_merge([
            'user_id' => 1, 'title' => 'ZZ ai steps', 'sector' => 'archive', 'standard' => 'isadg', 'parent_placement' => 'top_level',
            'output_create_records' => 1, 'output_generate_sip' => 0, 'output_generate_aip' => 0, 'output_generate_dip' => 0,
            'derivative_thumbnails' => 0, 'derivative_reference' => 0, 'process_virus_scan' => 0, 'status' => 'commit', 'created_at' => now(),
        ], $flags));
        DB::table('ingest_row')->insert(['session_id' => $id, 'row_number' => 1, 'title' => $row['title'], 'data' => json_encode($row),
            'enriched_data' => json_encode($row), 'digital_object_path' => $row['digitalObjectPath'] ?? null,
            'is_valid' => 1, 'is_excluded' => 0, 'created_at' => now()]);

        return $id;
    }

    public function test_ner_is_queued_for_the_new_record(): void
    {
        $id = $this->makeSession(['process_ner' => 1], ['title' => 'ZZ ner row', 'scopeAndContent' => 'Jan Smuts died at Doornkloof.']);

        $res = app(IngestCommitRunner::class)->run($id);

        $io = (int) DB::table('ingest_row')->where('session_id', $id)->value('created_atom_id');
        $this->assertSame(1, $res['created']);
        $this->assertTrue(DB::table('ahg_ai_pending_extraction')->where('object_id', $io)->where('task_type', 'ner')->where('status', 'pending')->exists());
    }

    public function test_an_infected_file_is_refused_before_it_is_attached(): void
    {
        if (trim((string) shell_exec('command -v clamscan')) === '') {
            $this->markTestSkipped('clamscan not installed');
        }
        // The EICAR test string: every scanner reports it, it does nothing.
        $f = $this->root.'/staging/eicar.txt';
        file_put_contents($f, 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');
        $id = $this->makeSession(['process_virus_scan' => 1], ['title' => 'ZZ infected', 'digitalObjectPath' => $f]);

        $res = app(IngestCommitRunner::class)->run($id);

        $this->assertSame(0, $res['created']);
        $this->assertSame(1, $res['errors']);
        $this->assertStringContainsString('Virus scan detected threat', (string) DB::table('ingest_job')->where('session_id', $id)->value('error_log'));
    }

    public function test_ocr_is_queued_for_the_attached_image(): void
    {
        if (trim((string) shell_exec('command -v convert')) === '') {
            $this->markTestSkipped('ImageMagick not installed');
        }
        $f = $this->root.'/staging/page.jpg';
        shell_exec('convert -size 300x200 xc:white '.escapeshellarg($f));
        $id = $this->makeSession(['process_ocr' => 1], ['title' => 'ZZ ocr row', 'digitalObjectPath' => $f]);

        $res = app(IngestCommitRunner::class)->run($id);

        $do = (int) DB::table('ingest_row')->where('session_id', $id)->value('created_do_id');
        $this->assertSame(0, $res['errors']);
        $this->assertGreaterThan(0, $do);
        Queue::assertPushed(IngestAiStepJob::class, fn ($j) => $j->step === 'ocr' && $j->doId === $do);
    }
}
