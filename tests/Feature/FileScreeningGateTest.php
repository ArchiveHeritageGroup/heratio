<?php

/**
 * FileScreeningGateTest - in a repository that requires screening, a
 * published record's files are refused to visitors until a scan that read
 * the files finds nothing, or a person releases them (heratio#1574). A 1983
 * personal form was readable in an ANC thumbnail on 29 Oct 2026.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgCore\Support\StatusRow;
use AhgPrivacy\Services\FileScreeningGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FileScreeningGateTest extends TestCase
{
    use DatabaseTransactions;

    private string $dir;

    private int $repo;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        $this->dir = 'uploads/r/screen-'.uniqid();
        $this->repo = $this->obj('QubitRepository');
        DB::table('actor')->insert(['id' => $this->repo, 'source_culture' => 'en']);
        DB::table('repository')->insert(['id' => $this->repo, 'source_culture' => 'en']);
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'privacy_screening_repositories'], ['setting_value' => (string) $this->repo]);
    }

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(?int $repo, string $file): int
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'repository_id' => $repo]);
        StatusRow::set($id, 158, 160);
        $do = $this->obj('QubitDigitalObject');
        DB::table('digital_object')->insert(['id' => $do, 'object_id' => $id, 'usage_id' => 140, 'name' => $file, 'path' => '/'.$this->dir.'/', 'mime_type' => 'application/pdf']);
        DB::table('digital_object')->insert(['id' => $this->obj('QubitDigitalObject'), 'parent_id' => $do, 'usage_id' => 142, 'name' => pathinfo($file, PATHINFO_FILENAME).'_142.jpg', 'path' => '/'.$this->dir.'/', 'mime_type' => 'image/jpeg']);

        return $id;
    }

    private function scan(int $ioId, int $hits, string $coverage): void
    {
        DB::table('ahg_pii_scan_report')->insert(['information_object_id' => $ioId, 'scan_started_at' => now(), 'scan_finished_at' => now(),
            'hits_total' => $hits, 'jurisdiction' => 'popia', 'coverage' => $coverage, 'status' => 'pending']);
    }

    private function gate(string $name, ?User $as = null)
    {
        return ($as ? $this->actingAs($as) : $this)
            ->withHeader('X-Original-URI', '/'.$this->dir.'/'.$name)->get(route('io.privacy.file-check'));
    }

    private function admin(): User
    {
        $id = $this->obj('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'screen-'.$id, 'email' => uniqid('screen-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_unscreened_files_are_withheld_from_visitors_but_not_staff(): void
    {
        $this->record($this->repo, 'form.pdf');
        $this->record(null, 'elsewhere.pdf');

        $this->gate('form.pdf')->assertForbidden();
        $this->gate('form_142.jpg')->assertForbidden();
        $this->gate('elsewhere.pdf')->assertNoContent();
        $this->gate('form.pdf', $this->admin())->assertNoContent();
    }

    public function test_only_a_clean_scan_of_the_files_clears_them(): void
    {
        $io = $this->record($this->repo, 'form.pdf');

        $this->scan($io, 0, 'metadata');
        $this->gate('form.pdf')->assertForbidden();

        $this->scan($io, 2, 'files');
        $this->gate('form.pdf')->assertForbidden();

        $this->scan($io, 0, 'files');
        $this->gate('form.pdf')->assertNoContent();
    }

    public function test_a_person_can_withhold_and_release_with_an_audited_ground(): void
    {
        $io = $this->record($this->repo, 'form.pdf');
        $this->scan($io, 0, 'files');
        $gate = app(FileScreeningGate::class);

        $gate->decide($io, false, 'popia', 'health details on page 1');
        $this->assertTrue($gate->withholds($io));

        $gate->decide($io, true, 'popia', 'redaction checked');
        $this->assertFalse($gate->withholds($io));

        $this->assertSame(['files_withheld', 'files_released'], DB::table('privacy_audit_log')
            ->where('entity_type', 'information_object_files')->where('entity_id', $io)->orderBy('id')->pluck('action')->all());
    }

    public function test_the_scan_page_offers_the_release_decision(): void
    {
        $io = $this->record($this->repo, 'form.pdf');
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'ZZ Screen form']);

        $this->actingAs($this->admin())->get(route('ahgprivacy.pii-scan-object', ['id' => $io]))->assertOk()
            ->assertSee('Files for visitors')->assertSee('Release files to visitors');
    }
}
