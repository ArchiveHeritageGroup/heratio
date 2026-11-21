<?php

/**
 * OfficeDisplayCopyTest - heratio#1627. An office master (here a tiny .docx
 * made with ZipArchive) gets a PDF display copy from LibreOffice: one PDF
 * reference row beside the master, no duplicate on a re-run, its text in the
 * record's searchable text, the record viewer showing it in the PDF viewer,
 * the ahg:office-pdfs sweep picking only masters without one, a replaced
 * master queueing a new copy, and a target the shared-uploads guard objects
 * to refused.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Jobs\GenerateOfficePdfJob;
use AhgCore\Services\DigitalObjectService as DOS;
use AhgCore\Services\OfficeDisplayCopyService as ODC;
use AhgCore\Support\StatusRow;
use AhgSearch\Services\RecordTextService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OfficeDisplayCopyTest extends TestCase
{
    use DatabaseTransactions;

    private string $root;

    private const PHRASE = 'Quarterly heritage register for the Zebediah collection';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['digital_object', 'information_object', 'property', 'property_i18n', 'slug'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $this->root = sys_get_temp_dir().'/ahg-officepdf-'.uniqid();
        mkdir($this->root, 0777, true);
        config(['heratio.uploads_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    private function needSoffice(): void
    {
        if (trim((string) shell_exec('command -v soffice')) === '') {
            $this->markTestSkipped('LibreOffice (soffice) not installed');
        }
    }

    private function objectRow(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A published record with a .docx master in uploads/r/<folder>/; returns [master, record, dir]. */
    private function docxMaster(?string $folder = null): array
    {
        $io = $this->objectRow('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'ZZ office register']);
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'zzoffice-'.$io]);
        StatusRow::set($io, 158, 160);
        $folder ??= (string) $io;
        $dir = $this->root.'/r/'.$folder;
        @mkdir($dir, 0777, true);
        $this->writeDocx($dir.'/master_register.docx', self::PHRASE);
        $id = $this->objectRow('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $id, 'object_id' => $io, 'usage_id' => DOS::USAGE_MASTER, 'name' => 'master_register.docx',
            'path' => '/uploads/r/'.$folder.'/', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'media_type_id' => DOS::MEDIA_TEXT, 'byte_size' => filesize($dir.'/master_register.docx')]);

        return [$id, $io, $dir];
    }

    private function writeDocx(string $file, string $text): void
    {
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body><w:p><w:r><w:t>'.htmlspecialchars($text).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
    }

    private function pdfRows(int $master)
    {
        return DB::table('digital_object')->where('parent_id', $master)->where('usage_id', DOS::USAGE_REFERENCE)->where('mime_type', 'application/pdf')->get();
    }

    // ---------------------------------------------------------------

    public function test_conversion_makes_one_pdf_reference_and_its_text_is_searchable(): void
    {
        $this->needSoffice();
        [$id, $io, $dir] = $this->docxMaster();
        $this->assertTrue(ODC::missing(100000)->contains('id', $id), 'the sweep selects a master without a copy');

        $pdfId = ODC::make($id);
        $this->assertNotNull($pdfId);
        $rows = $this->pdfRows($id);
        $this->assertCount(1, $rows);
        $this->assertSame('reference_register.pdf', $rows[0]->name);
        $this->assertSame('/uploads/r/'.$io.'/', $rows[0]->path);
        $this->assertSame($io, (int) $rows[0]->object_id);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($dir.'/reference_register.pdf'));
        $this->assertSame(md5_file($dir.'/reference_register.pdf'), $rows[0]->checksum);
        $this->assertFalse(ODC::missing(100000)->contains('id', $id), 'a master with a copy is left out of the sweep');

        // Re-run: same row, no duplicate, no second text property.
        $this->assertSame($pdfId, ODC::make($id));
        $this->assertCount(1, $this->pdfRows($id));
        $this->assertSame(1, DB::table('property')->where('object_id', $pdfId)->where('name', ODC::TEXT_PROPERTY)->count());

        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            $this->markTestSkipped('pdftotext not installed');
        }
        DB::table('ahg_settings')->whereIn('setting_key', ['privacy_screening_repositories', 'privacy_screening_collections'])->delete();
        $text = RecordTextService::forRecords([$io])[$io] ?? '';
        $this->assertStringContainsString(self::PHRASE, $text);
    }

    public function test_the_record_viewer_shows_the_pdf_copy_in_the_pdf_viewer(): void
    {
        $this->needSoffice();
        [$id, $io] = $this->docxMaster();
        Cache::flush();
        $before = $this->get('/zzoffice-'.$io)->assertOk()->getContent();
        $this->assertStringNotContainsString('pdf-viewer-container', $before, 'no copy yet: no PDF viewer');

        $this->assertNotNull(ODC::make($id));
        Cache::flush();
        $html = $this->get('/zzoffice-'.$io)->assertOk()->getContent();
        $this->assertStringContainsString('pdf-viewer-container', $html);
        $this->assertMatchesRegularExpression('#<iframe src="[^"]*/uploads/r/'.$io.'/reference_register\.pdf"#', $html);
        // A guest is not handed the .docx original to open or download.
        $this->assertStringNotContainsString('/uploads/r/'.$io.'/master_register.docx" download', $html);
    }

    public function test_a_replaced_office_master_queues_a_new_copy(): void
    {
        [$id] = $this->docxMaster();
        Queue::fake();
        DOS::refreshDerivatives($id);
        Queue::assertPushed(GenerateOfficePdfJob::class, fn ($j) => $j->masterId === $id && $j->refreshPictures);
    }

    public function test_a_target_the_shared_uploads_guard_refuses_is_not_written(): void
    {
        // Shared catalogue on; a repository-slug folder is never Heratio's to write in.
        config(['database.connections.shared_uploads' => ['driver' => 'mysql', 'database' => 'zz_shared_catalogue']]);
        [$id, , $dir] = $this->docxMaster('zz-repository-slug');
        $pdf = $this->root.'/made.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%%EOF\n");

        $this->assertNull(ODC::store($id, $pdf));
        $this->assertFileDoesNotExist($dir.'/reference_register.pdf');
        $this->assertCount(0, $this->pdfRows($id));
    }

    public function test_anything_but_an_office_master_is_left_alone(): void
    {
        [$id, , $dir] = $this->docxMaster();
        DB::table('digital_object')->where('id', $id)->update(['name' => 'master_register.jpg', 'mime_type' => 'image/jpeg']);
        rename($dir.'/master_register.docx', $dir.'/master_register.jpg');
        $pdf = $this->root.'/made.pdf';
        file_put_contents($pdf, "%PDF-1.4\n%%EOF\n");

        $this->assertNull(ODC::store($id, $pdf));
        $this->assertNull(ODC::make($id));
        $this->assertCount(0, $this->pdfRows($id));
    }
}
