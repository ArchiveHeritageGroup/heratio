<?php

/**
 * PiiRedactionSuggesterTest - the personal information in a record's PDF is
 * found, placed as pending auto_pii redaction regions, and blacked out in the
 * copy visitors get; a VAT number is not taken for a phone; a re-run adds
 * nothing.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgInformationObjectManage\Services\RedactionRenderService;
use AhgPrivacy\Services\PiiRedactionSuggester;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PiiRedactionSuggesterTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('privacy_visual_redaction') || (new Process(['python3', '-c', 'import fitz']))->run() !== 0) {
            $this->markTestSkipped('privacy_visual_redaction or PyMuPDF missing.');
        }
        $this->dir = sys_get_temp_dir().'/pii-suggest-'.uniqid();
        mkdir($this->dir.'/t', 0777, true);
        config(['heratio.uploads_path' => $this->dir]);
        // A small fictitious form, built here so no sample file is committed.
        $py = <<<'PY'
import fitz, sys
d = fitz.open(); p = d.new_page()
y = 72
for line in ["SAMPLE supplier form", "VAT no. 4999000222", "Contact ID number 7804125123081",
             "Cell 082 555 0193", "Email jane.doe@example.org", "Card 4111 1111 1111 1111",
             "Home address 22 Kiaat Street, Middelburg, 1050", "Account no. 62 0001 2345 6",
             "Date of birth 12 April 1978"]:
    p.insert_text((72, y), line, fontsize=11); y += 22
d.save(sys.argv[1])
PY;
        (new Process(['python3', '-c', $py, $this->dir.'/t/form.pdf']))->mustRun();
    }

    protected function tearDown(): void
    {
        (new Process(['rm', '-rf', $this->dir]))->run();
        parent::tearDown();
    }

    public function test_pii_in_a_pdf_becomes_pending_regions_and_is_blacked_out(): void
    {
        $obj = fn (string $c) => (int) DB::table('object')->insertGetId(['class_name' => $c, 'created_at' => now(), 'updated_at' => now()]);
        $io = $obj('QubitInformationObject');
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('digital_object')->insert(['id' => $obj('QubitDigitalObject'), 'object_id' => $io, 'usage_id' => 140,
            'name' => 'form.pdf', 'path' => '/uploads/r/t/', 'mime_type' => 'application/pdf']);

        $r = app(PiiRedactionSuggester::class)->suggest($io);
        $this->assertArrayNotHasKey('error', $r);

        $labels = DB::table('privacy_visual_redaction')->where('object_id', $io)->where('source', 'auto_pii')
            ->where('status', 'pending')->pluck('label')->all();
        foreach (['national id', 'phone', 'email', 'credit card', 'address', 'bank account', 'date of birth'] as $type) {
            $this->assertContains($type, $labels, "{$type} suggested");
        }
        $this->assertSame(1, count(array_keys($labels, 'phone')), 'the VAT number is not a phone');
        $this->assertSame(0, app(PiiRedactionSuggester::class)->suggest($io)['added'], 'a re-run adds nothing');

        // The copy visitors get no longer carries the ID number as text.
        $out = app(RedactionRenderService::class)->render($io);
        $this->assertNotNull($out);
        $text = (new Process(['pdftotext', $out, '-']))->mustRun()->getOutput();
        $this->assertStringNotContainsString('7804125123081', $text);
        $this->assertStringContainsString('SAMPLE supplier form', $text, 'the rest of the page stays');

        // The editor's button: a visitor may not run it; an administrator may.
        DB::table('slug')->insert(['object_id' => $io, 'slug' => 'pii-suggest-'.$io]);
        $this->post(route('privacy.redaction.suggest-pii', 'pii-suggest-'.$io))->assertRedirect();
        $this->assertSame(0, DB::table('privacy_visual_redaction')->where('object_id', $io)->where('source', 'manual')->count());
        $uid = (int) DB::table('acl_user_group')->where('group_id', 100)->value('user_id');
        $this->actingAs(\AhgCore\Models\User::findOrFail($uid))
            ->from('/privacy/redaction/pii-suggest-'.$io)
            ->post(route('privacy.redaction.suggest-pii', 'pii-suggest-'.$io))
            ->assertRedirect('/privacy/redaction/pii-suggest-'.$io)->assertSessionHas('success');
    }
}
