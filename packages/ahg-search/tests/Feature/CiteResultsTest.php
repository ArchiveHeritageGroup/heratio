<?php

/**
 * CiteResultsTest - "Cite these results" (heratio#1585): a filtered GLAM browse
 * and an assistant answer can be cited in Chicago, APA, BibTeX and RIS; the link
 * restores the view; and a citation opened by an anonymous reader shows only
 * published records, never a hidden title.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Models\User;
use AhgSearch\Services\ResultCitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CiteResultsTest extends TestCase
{
    use DatabaseTransactions;

    private string $term;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_cited_view') || ! Schema::hasColumn('ahg_ai_chatbot_message', 'receipt_seq')) {
            $this->markTestSkipped('ahg_cited_view missing: run the migrations.');
        }
        Cache::forget('cite:index-generation');
        $this->term = 'zzcite'.substr(uniqid(), -6);
    }

    private function record(string $title, bool $published): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en', 'identifier' => 'CITE-'.$id]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'cite-'.$id]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => $published ? 160 : 159]);

        return $id;
    }

    private function admin(): User
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'cite-'.$id, 'email' => uniqid('cite-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");

        return User::findOrFail($id);
    }

    public function test_a_browse_view_is_cited_in_four_formats_and_an_anonymous_reader_sees_only_published_records(): void
    {
        $this->record("Open {$this->term} letter", true);
        $this->record("Hidden {$this->term} memo", false);
        $admin = $this->admin();

        $params = ['query' => $this->term, 'sort' => 'title', 'topLevel' => '0']; // stored key-sorted
        $res = $this->actingAs($admin)->postJson(route('results.cite.browse'), ['params' => $params + ['page' => 3]])->assertOk();
        $code = $res->json('code');
        $this->assertSame(2, $res->json('result_count'), 'the signed-in citer counts both records');
        foreach (ResultCitationService::FORMATS as $f) {
            $this->assertNotSame('', (string) $res->json("formats.{$f}"));
            $this->assertStringContainsString(route('results.cite.show', $code), (string) $res->json("formats.{$f}"), $f);
        }
        $this->assertStringStartsWith('@misc{heratio-', $res->json('formats.bibtex'));
        $this->assertStringContainsString("TY  - DBASE\r\n", $res->json('formats.ris'));

        $row = DB::table('ahg_cited_view')->where('code', $code)->first();
        $this->assertEquals($params, json_decode($row->params, true), 'page state is dropped, the view parameters kept');
        $this->assertSame('staff', $row->viewer_scope);
        $this->assertStringNotContainsString('Hidden', json_encode((array) $row), 'no title is stored');

        // An anonymous reader: the link re-runs under their rights.
        $this->app['auth']->forgetGuards();
        $page = $this->get(route('results.cite.show', $code))->assertOk()->assertDontSee('Hidden '.$this->term);
        $page->assertSee(__('Run now, the same search finds :n records for you.', ['n' => 1]), false); // only the published one
        $page->assertSee(e(route('glam.browse', $params)), false); // the link restores the same view
        $this->get(route('glam.browse', $params))->assertOk()->assertSee('Open '.$this->term)->assertDontSee('Hidden '.$this->term);
        $this->get(route('results.cite.format', [$code, 'ris']))->assertOk()->assertHeader('Content-Type', 'application/x-research-info-systems; charset=utf-8');
        $this->get(route('results.cite.format', [$code, 'nope']))->assertNotFound();
        $this->get(route('results.cite.show', 'zzzzzzzz'))->assertNotFound();
    }

    public function test_an_assistant_answer_is_cited_with_model_receipt_and_records_and_only_by_its_asker(): void
    {
        $open = $this->record("Open {$this->term} report", true);
        $hidden = $this->record("Hidden {$this->term} file", false);
        $admin = $this->admin();
        $session = hash('sha256', 'user:'.$admin->id);
        DB::table('ahg_ai_chatbot_message')->insert(['session_id' => $session, 'role' => 'user', 'content' => 'What do you hold on '.$this->term.'?', 'created_at' => now()]);
        $msg = (int) DB::table('ahg_ai_chatbot_message')->insertGetId([
            'session_id' => $session, 'role' => 'assistant', 'content' => 'Two records [1] [2].', 'model' => 'qwen3:8b',
            'sources' => json_encode([['slug' => 'cite-'.$open, 'title' => 'x'], ['slug' => 'cite-'.$hidden, 'title' => 'y']]),
            'receipt_seq' => 4242, 'receipt_hash' => str_repeat('ab', 32), 'created_at' => now(),
        ]);

        // Someone else cannot cite it.
        $this->actingAs($this->admin())->postJson(route('results.cite.chatbot', $msg))->assertNotFound();

        $res = $this->actingAs($admin)->postJson(route('results.cite.chatbot', $msg))->assertOk();
        $this->assertSame($res->json('code'), $this->actingAs($admin)->postJson(route('results.cite.chatbot', $msg))->json('code'), 'one citation per answer');
        foreach (ResultCitationService::FORMATS as $f) {
            $this->assertStringContainsString('qwen3:8b', (string) $res->json("formats.{$f}"), $f);
        }
        $this->assertStringContainsString('inference receipt #4242', $res->json('formats.ris'));
        $this->assertStringContainsString('CITE-'.$hidden, $res->json('formats.ris'), 'the signed-in asker sees every record');

        $this->app['auth']->forgetGuards();
        $this->get(route('results.cite.show', $res->json('code')))->assertOk()
            ->assertSee('Open '.$this->term)->assertDontSee('Hidden '.$this->term)->assertDontSee('CITE-'.$hidden)
            ->assertSee(__('Some cited records are not available to you.'));
        $this->assertStringNotContainsString('CITE-'.$hidden, $this->get(route('results.cite.format', [$res->json('code'), 'bibtex']))->getContent());
    }
}
