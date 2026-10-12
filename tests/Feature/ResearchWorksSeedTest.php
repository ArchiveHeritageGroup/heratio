<?php

/**
 * ResearchWorksSeedTest - ahg:research-works-seed is safe to re-run: the
 * second run of the same manifest adds nothing, and a title-only work gets
 * no file.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ResearchWorksSeedTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_seed_is_idempotent_and_title_only_works_carry_no_file(): void
    {
        foreach (['research_researcher', 'research_bibliography_entry', 'research_lecture', 'research_lecture_resource'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("$t table not present in this install.");
            }
        }
        $dir = sys_get_temp_dir().'/rws-'.uniqid();
        mkdir($dir.'/src', 0775, true);
        file_put_contents($dir.'/src/Talk Slides.pdf', '%PDF-1.4 test');
        config(['heratio.storage_path' => $dir.'/storage']);

        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        $email = uniqid('rws-', true).'@example.test';
        DB::table('user')->insert(['id' => $id, 'username' => 'rws-'.$id, 'email' => $email, 'active' => 1]);

        file_put_contents($dir.'/manifest.json', json_encode([
            'user_email' => $email,
            'researcher' => ['first_name' => 'Test', 'last_name' => 'Author'],
            'seed_target_directory' => true,
            'manuscripts' => ['title' => 'Test manuscripts'],
            'works' => [
                ['title' => 'A delivered talk', 'status' => 'Presented',
                    'files' => [['src' => 'src/Talk Slides.pdf', 'label' => 'Slides', 'kind' => 'slides']],
                    'talk' => ['date' => '2026-07-01', 'location' => 'Somewhere']],
                ['title' => 'Under review', 'entry_type' => 'article', 'status' => 'Under review', 'files' => false,
                    'article' => ['status' => 'submitted', 'target_journal' => ['title' => 'RWS Test Journal of Things']]],
            ],
        ]));

        foreach ([1, 2] as $_) {
            $this->artisan('ahg:research-works-seed', ['manifest' => $dir.'/manifest.json'])->assertSuccessful();
        }

        $rid = DB::table('research_researcher')->where('user_id', $id)->value('id');
        $this->assertSame('approved', DB::table('research_researcher')->where('id', $rid)->value('status'));
        $bib = DB::table('research_bibliography')->where('researcher_id', $rid)->value('id');
        $this->assertSame(2, DB::table('research_bibliography_entry')->where('bibliography_id', $bib)->count());
        $this->assertNull(DB::table('research_bibliography_entry')->where('bibliography_id', $bib)->where('title', 'Under review')->value('url'));

        $lecture = DB::table('research_lecture')->where('researcher_id', $rid)->get();
        $this->assertCount(1, $lecture);
        $this->assertSame('/uploads/research-works/a-delivered-talk/talk-slides.pdf', $lecture[0]->slides_url);
        $this->assertSame(1, DB::table('research_lecture_resource')->where('lecture_id', $lecture[0]->id)->count());
        $journal = DB::table('research_journal')->where('researcher_id', $rid)->where('title', 'Test manuscripts')->get();
        $this->assertCount(1, $journal);
        $this->assertSame('manuscript', $journal[0]->kind);
        $article = DB::table('research_journal_article')->where('journal_id', $journal[0]->id)->get();
        $this->assertCount(1, $article);
        $this->assertSame('submitted', $article[0]->status);
        $this->assertSame(1, DB::table('research_target_journal')->where('title', 'RWS Test Journal of Things')->count());
        $this->assertSame((int) $article[0]->target_journal_id, (int) DB::table('research_target_journal')->where('title', 'RWS Test Journal of Things')->value('id'));
        $this->assertSame(1, DB::table('research_target_journal')->where('title', 'Journal of the South African Society of Archivists')->count());
        $this->assertFileExists($dir.'/storage/uploads/research-works/a-delivered-talk/talk-slides.pdf');
    }
}
