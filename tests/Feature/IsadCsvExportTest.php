<?php

/**
 * IsadCsvExportTest - the archival description CSV export writes AtoM's full
 * column set (it wrote 7 columns), and a file it writes comes back in through
 * the CSV import with its fields, hierarchy, creator and dates.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Constants\TermId;
use AhgExport\Services\ExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IsadCsvExportTest extends TestCase
{
    use DatabaseTransactions;

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(?int $parent, string $title, array $i18n, int $lft, int $rgt): int
    {
        $id = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent ?? 1, 'identifier' => 'ZZX-'.$id, 'source_culture' => 'en', 'lft' => $lft, 'rgt' => $rgt]);
        DB::table('information_object_i18n')->insert($i18n + ['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'zzx-'.$id]);

        return $id;
    }

    /** @return array{0: list<string>, 1: list<array<string,string>>} */
    private function export(string $parentSlug): array
    {
        ob_start();
        app(ExportService::class)->streamInformationObjectCsv(['parent_slug' => $parentSlug, 'include_descendants' => 1, 'standard' => 'isad'])->sendContent();
        $csv = ltrim((string) ob_get_clean(), "\xEF\xBB\xBF");
        $lines = array_map('str_getcsv', array_filter(preg_split('/\r?\n(?=(?:[^"]*"[^"]*")*[^"]*$)/', $csv)));
        $head = array_shift($lines);

        return [$head, array_map(fn ($l) => array_combine($head, $l), $lines)];
    }

    public function test_full_columns_and_a_round_trip_through_the_import(): void
    {
        $parent = $this->record(null, 'ZZ Export fonds', ['scope_and_content' => 'Letters, "quoted", and minutes', 'archival_history' => 'Kept by the family', 'appraisal' => 'All kept'], 6000000, 6000003);
        $child = $this->record($parent, 'ZZ Export file', ['scope_and_content' => 'One file', 'access_conditions' => 'Open'], 6000001, 6000002);
        $actor = $this->objectRow('QubitActor');
        DB::table('actor')->insert(['id' => $actor, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $actor, 'culture' => 'en', 'authorized_form_of_name' => 'ZZ Creator, Ann']);
        $ev = $this->objectRow('QubitEvent');
        DB::table('event')->insert(['id' => $ev, 'object_id' => $parent, 'type_id' => TermId::EVENT_TYPE_CREATION, 'actor_id' => $actor, 'start_date' => '1950-01-01', 'end_date' => '1952-12-31', 'source_culture' => 'en']);
        DB::table('event_i18n')->insert(['id' => $ev, 'culture' => 'en', 'date' => '1950-1952']);
        $subject = $this->objectRow('QubitTerm');
        DB::table('term')->insert(['id' => $subject, 'taxonomy_id' => 35, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $subject, 'culture' => 'en', 'name' => 'ZZ Funerals']);
        DB::table('object_term_relation')->insert(['id' => $this->objectRow('QubitObjectTermRelation'), 'object_id' => $parent, 'term_id' => $subject]);

        [$head, $rows] = $this->export('zzx-'.$parent);

        $this->assertSame(ExportService::ISAD_CSV_COLUMNS, $head);
        $this->assertGreaterThan(45, count($head));
        $byTitle = collect($rows)->keyBy('title');
        $p = $byTitle['ZZ Export fonds'];
        $c = $byTitle['ZZ Export file'];
        $this->assertSame('Letters, "quoted", and minutes', $p['scopeAndContent']);
        $this->assertSame('All kept', $p['appraisal']);
        $this->assertSame('ZZ Funerals', $p['subjectAccessPoints']);
        $this->assertSame('ZZ Creator, Ann', $p['creators']);
        $this->assertSame('1950-1952', $p['dateRange']);
        $this->assertSame((string) $parent, $c['parentId'], 'the child points at its parent in the same file');
        $this->assertSame('Open', $c['accessConditions']);

        // Back in through the import, as new records.
        $file = tempnam(sys_get_temp_dir(), 'zzx').'.csv';
        $fh = fopen($file, 'w');
        fputcsv($fh, $head);
        foreach ($rows as $r) {
            $r['legacyId'] = 'rt-'.$r['legacyId'];
            $r['parentId'] = $r['parentId'] !== '' ? 'rt-'.$r['parentId'] : '';
            $r['qubitParentSlug'] = '';
            $r['title'] = 'RT '.$r['title'];
            $r['identifier'] = 'RT-'.$r['identifier'];
            fputcsv($fh, array_values($r));
        }
        fclose($fh);
        app(\AhgInformationObjectManage\Services\ArchivesCsvImporter::class)->import($file);
        @unlink($file);

        $rp = DB::table('information_object_i18n')->where('title', 'RT ZZ Export fonds')->value('id');
        $rc = DB::table('information_object_i18n')->where('title', 'RT ZZ Export file')->value('id');
        $this->assertNotNull($rp);
        $this->assertNotNull($rc);
        $this->assertSame((int) $rp, (int) DB::table('information_object')->where('id', $rc)->value('parent_id'));
        $this->assertSame('Letters, "quoted", and minutes', DB::table('information_object_i18n')->where('id', $rp)->value('scope_and_content'));
        $this->assertSame('1950-1952', DB::table('event_i18n as ei')->join('event as e', 'e.id', '=', 'ei.id')->where('e.object_id', $rp)->value('ei.date'));
    }
}
