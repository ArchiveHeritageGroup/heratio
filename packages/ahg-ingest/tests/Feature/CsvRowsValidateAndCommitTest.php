<?php

/**
 * CsvRowsValidateAndCommitTest - rows uploaded through the ingest wizard are
 * checked at validation and the valid ones are committed (10 Nov 2026). The
 * CSV parser stores rows with is_valid=0, validateSession() only counted, and
 * the commit takes only is_valid=1 rows, so a CSV import created nothing and
 * reported "0 IO(s), 0 error(s)".
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgIngest\Services\IngestCommitRunner;
use AhgIngest\Services\IngestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CsvRowsValidateAndCommitTest extends TestCase
{
    use DatabaseTransactions;

    public function test_valid_csv_rows_are_marked_and_committed_and_bad_ones_reported(): void
    {
        $id = (int) DB::table('ingest_session')->insertGetId([
            'user_id' => 1, 'title' => 'ZZ csv validate', 'sector' => 'archive', 'standard' => 'isadg', 'parent_placement' => 'top_level',
            'output_create_records' => 1, 'output_generate_sip' => 0, 'output_generate_aip' => 0, 'output_generate_dip' => 0,
            'derivative_thumbnails' => 0, 'derivative_reference' => 0, 'process_virus_scan' => 0, 'status' => 'validate', 'created_at' => now(),
        ]);
        $rows = [
            ['identifier' => 'ZZV-1', 'title' => 'ZZ csv row one', 'levelOfDescription' => 'File', 'eventStartDates' => '1960', 'eventEndDates' => '1964'],
            ['identifier' => 'ZZV-2', 'title' => '', 'levelOfDescription' => 'File'],
            ['identifier' => 'ZZV-3', 'title' => 'ZZ csv row three', 'levelOfDescription' => 'file', 'eventStartDates' => '1971|1972', 'eventEndDates' => '1971|1973'],
        ];
        foreach ($rows as $i => $r) {
            DB::table('ingest_row')->insert(['session_id' => $id, 'row_number' => $i + 1, 'title' => $r['title'], 'data' => json_encode($r),
                'enriched_data' => json_encode($r), 'is_valid' => 0, 'is_excluded' => 0, 'created_at' => now()]);
        }

        $stats = app(IngestService::class)->validateSession($id);

        $this->assertSame(2, $stats['valid']);
        $this->assertSame([1, 0, 1], DB::table('ingest_row')->where('session_id', $id)->orderBy('row_number')->pluck('is_valid')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('title', DB::table('ingest_validation')->where('session_id', $id)->where('row_number', 2)->value('field_name'));

        $res = app(IngestCommitRunner::class)->run($id);
        $this->assertSame(2, $res['created'], 'the two valid rows become records');
        $this->assertSame(2, DB::table('information_object_i18n')->whereIn('title', ['ZZ csv row one', 'ZZ csv row three'])->count());
    }
}
