<?php

/**
 * TranslationEditFallbackTest - editing a record in a language it is not yet
 * described in: the form starts from the source-language text and its
 * dropdowns are not empty; saving stores only what the translator changed.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\AclService;
use AhgInformationObjectManage\Services\InformationObjectService;
use AhgCore\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TranslationEditFallbackTest extends TestCase
{
    use DatabaseTransactions;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(): array
    {
        $io = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $io, 'parent_id' => 1, 'source_culture' => 'en', 'lft' => 9400000, 'rgt' => 9400001]);
        DB::table('information_object_i18n')->insert(['id' => $io, 'culture' => 'en', 'title' => 'Funeral of the General', 'scope_and_content' => 'Cuttings on the funeral.']);
        $slug = 'tef-'.$io;
        DB::table('slug')->insert(['object_id' => $io, 'slug' => $slug]);

        return [$io, $slug];
    }

    public function test_saving_in_another_language_keeps_only_changed_fields(): void
    {
        [$io] = $this->record();
        InformationObjectService::update($io, ['title' => 'Begrafnis van die Generaal', 'scope_and_content' => 'Cuttings on the funeral.'], 'af');

        $af = DB::table('information_object_i18n')->where('id', $io)->where('culture', 'af')->first();
        $this->assertSame('Begrafnis van die Generaal', $af->title);
        $this->assertNull($af->scope_and_content, 'unchanged source text is not copied into the translation');
        $this->assertSame('Cuttings on the funeral.', DB::table('information_object_i18n')->where('id', $io)->where('culture', 'en')->value('scope_and_content'));
    }

    public function test_the_edit_form_in_another_language_is_filled(): void
    {
        [, $slug] = $this->record();
        $id = $this->obj('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'tef-'.$id, 'email' => uniqid('tef-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        $page = $this->actingAs(User::findOrFail($id))->get('/informationobject/'.$slug.'/edit?sf_culture=af');
        $page->assertOk();
        app()->setLocale('af');
        $page->assertSee('Funeral of the General');
        $page->assertSee('Cuttings on the funeral.');
        // The level dropdown falls back to English names when a level has no Afrikaans one.
        $level = DB::table('term_i18n')->join('level_of_description_sector as s', 's.term_id', '=', 'term_i18n.id')
            ->where('s.sector', 'archive')->where('term_i18n.culture', 'en')->value('term_i18n.name');
        if ($level) {
            $page->assertSee($level);
        }
    }

    public function test_saving_the_edit_form_in_afrikaans_creates_the_translation_and_keeps_english(): void
    {
        [$io, $slug] = $this->record();
        DB::table('information_object_i18n')->where('id', $io)->where('culture', 'en')->update(['archival_history' => 'Kept by Ouma Smuts.']);
        $note = $this->obj('QubitNote');
        DB::table('note')->insert(['id' => $note, 'object_id' => $io, 'type_id' => 125, 'source_culture' => 'en']);
        DB::table('note_i18n')->insert(['id' => $note, 'culture' => 'en', 'content' => 'An English note.']);

        $id = $this->obj('QubitUser');
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'tef-'.$id, 'email' => uniqid('tef-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();

        $this->actingAs(User::findOrFail($id))->withSession(['locale' => 'af'])
            ->put('/informationobject/'.$slug, [
                'title' => 'Begrafnis van die Generaal',
                'archival_history' => 'Deur Ouma Smuts bewaar.',
                'scope_and_content' => 'Cuttings on the funeral.',
                'notes' => [],
            ]);

        $af = DB::table('information_object_i18n')->where('id', $io)->where('culture', 'af')->first();
        $this->assertNotNull($af, 'the first save in Afrikaans creates its row');
        $this->assertSame('Begrafnis van die Generaal', $af->title);
        $this->assertSame('Deur Ouma Smuts bewaar.', $af->archival_history);
        $this->assertNull($af->scope_and_content, 'unchanged text keeps following English');
        $en = DB::table('information_object_i18n')->where('id', $io)->where('culture', 'en')->first();
        $this->assertSame('Funeral of the General', $en->title);
        $this->assertSame('Kept by Ouma Smuts.', $en->archival_history);
        $this->assertSame('An English note.', DB::table('note_i18n')->where('id', $note)->where('culture', 'en')->value('content'), 'English notes survive a save in Afrikaans');
    }
}
