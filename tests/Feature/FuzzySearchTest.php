<?php

/**
 * FuzzySearchTest - heratio#1583: a browse search that finds nothing gets a
 * did-you-mean and, where one finds anything, the corrected query or close
 * matches, always labelled. Database path: a cached vocabulary of published,
 * disclosable words; a draft's words are never suggested. Index path: the
 * fuzzy retry and the collated suggester, checked as query bodies (no live
 * Elasticsearch needed).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgDisplay\Services\SearchVocabularyService;
use AhgSearch\Services\BrowseQueryBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuzzySearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('ahg_display_count_cache_ttl', '0');
        $this->setting('ahg_display_vocab_cache_ttl', '0');
        $this->resetStatics();
    }

    protected function tearDown(): void
    {
        $this->resetStatics();
        \AhgCore\Services\AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function resetStatics(): void
    {
        $r = new \ReflectionClass(\AhgDisplay\Controllers\DisplayController::class);
        $r->setStaticPropertyValue('searchEs', null);
        $r->setStaticPropertyValue('textMatchIdCache', []);
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
        \AhgCore\Services\AhgSettingsService::clearCache();
    }

    private function record(string $title, bool $published = true): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        $lft = (int) DB::table('information_object')->max('rgt') + 10;
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'lft' => $lft, 'rgt' => $lft + 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'fuzzy-'.$id]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => $published ? 160 : 159]);

        return $id;
    }

    /** A made-up word of letters only, so it is in no other record. */
    private function word(): string
    {
        return 'zq'.substr(str_shuffle('bcdfghjklmnprstvwxy'), 0, 8);
    }

    /** One letter changed near the end, first letter kept. */
    private function misspell(string $w): string
    {
        $last = substr($w, -2, 1);

        return substr($w, 0, -2).($last === 'a' ? 'e' : 'a').substr($w, -1);
    }

    public function test_database_path_corrects_a_misspelt_word_and_labels_it(): void
    {
        $word = $this->word();
        $this->record(ucfirst($word).' station records');
        $typo = $this->misspell($word);

        $page = $this->get('/glam/browse?topLevel=0&type=all&query='.$typo)->assertOk();
        $page->assertSee(__('Showing results for'), false)
            ->assertSee(ucfirst($word).' station records', false)
            ->assertSee(__('Search instead for'), false)
            ->assertSee('noCorrect=1', false);
    }

    public function test_database_path_offers_did_you_mean_when_autocorrect_is_off(): void
    {
        $this->setting('ahg_display_search_autocorrect', '0');
        $word = $this->word();
        $this->record(ucfirst($word).' ledger');
        $typo = $this->misspell($word);

        $page = $this->get('/glam/browse?topLevel=0&type=all&query='.$typo)->assertOk();
        $page->assertSee(__('Did you mean:'), false)->assertSee('query='.$word, false)
            ->assertDontSee(__('Showing results for'), false);
    }

    public function test_no_correct_shows_the_exact_result(): void
    {
        $word = $this->word();
        $this->record(ucfirst($word).' ledger');

        $this->get('/glam/browse?topLevel=0&type=all&noCorrect=1&query='.$this->misspell($word))->assertOk()
            ->assertDontSee(__('Showing results for'), false)->assertDontSee(__('Did you mean:'), false);
    }

    public function test_a_drafts_words_are_never_suggested(): void
    {
        $draft = $this->word();
        $this->record(ucfirst($draft).' unpublished minutes', false);
        $typo = $this->misspell($draft);

        $svc = app(SearchVocabularyService::class);
        $this->assertNull($svc->correct($typo, 'en'), 'a draft contributes no words');
        $this->assertArrayNotHasKey($draft, $svc->build('en')['z'][strlen($draft)] ?? []);

        $this->get('/glam/browse?topLevel=0&type=all&query='.$typo)->assertOk()
            ->assertDontSee(__('Did you mean:'), false)->assertDontSee(__('Showing results for'), false)
            ->assertDontSee($draft, false);
    }

    public function test_closest_word_prefers_fewer_edits_then_frequency(): void
    {
        $svc = new SearchVocabularyService;
        $vocab = ['a' => [7 => ['archive' => 5, 'archivs' => 1], 8 => ['archives' => 9, 'archiver' => 2]]];

        $this->assertNull($svc->closest('archive', $vocab), 'a known word is left alone');
        $this->assertSame('archive', $svc->closest('archivw', $vocab), 'one edit beats two');
        $this->assertSame('archives', $svc->closest('archivez', $vocab), 'equal edits: the more frequent word');
        $this->assertNull($svc->closest('archiev', $vocab), 'a seven-letter word gets one edit, not two');
        $this->assertNull($svc->closest('brchive', $vocab), 'the first letter must match');
        $this->assertNull($svc->closest('arxyzqe', $vocab), 'too far');
    }

    public function test_builder_shapes_the_fuzzy_retry_and_advanced_queries(): void
    {
        $fields = ['i18n.en.title^3', 'identifier^2'];

        $fuzzy = BrowseQueryBuilder::fuzzy('"Johanesburg" mines*', $fields);
        $this->assertSame('AUTO', $fuzzy['multi_match']['fuzziness']);
        $this->assertSame('Johanesburg mines', $fuzzy['multi_match']['query']);
        $this->assertSame($fields, $fuzzy['multi_match']['fields']);

        $this->assertArrayHasKey('simple_query_string', BrowseQueryBuilder::text('mining labour', $fields));
        foreach (['mining AND gold', 'mining NOT gold', 'wom?n', 'ph*to', '/19[0-9]{2}/'] as $q) {
            $body = BrowseQueryBuilder::idSearchBody(BrowseQueryBuilder::text($q, $fields), 100);
            $this->assertArrayHasKey('query_string', $body['query'], $q);
            $this->assertSame(BrowseQueryBuilder::ADVANCED_TIMEOUT, $body['timeout'], "$q runs under a time limit");
            $this->assertFalse($body['query']['query_string']['allow_leading_wildcard']);
        }
        $this->assertArrayNotHasKey('timeout', BrowseQueryBuilder::idSearchBody(BrowseQueryBuilder::text('mining', $fields), 10));

        $suggest = BrowseQueryBuilder::suggest('minning', [11, 12]);
        $collate = $suggest['suggest']['browse_phrase']['phrase']['collate']['query']['source']['bool'];
        $this->assertSame([['term' => ['publicationStatusId' => 160]]], $collate['filter'], 'only published records back a suggestion');
        $this->assertContains(['ids' => ['values' => ['11', '12']]], $collate['must_not']);
        $this->assertFalse($suggest['suggest']['browse_phrase']['phrase']['collate']['prune']);
    }

    public function test_index_path_runs_the_fuzzy_query_on_zero_hits_and_labels_it(): void
    {
        $this->setting('ahg_display_search_es', '1');
        $word = $this->word();
        $id = $this->record(ucfirst($word).' correspondence');
        $typo = $this->misspell($word);

        Http::fake(function (Request $req) use ($id, $word) {
            $body = $req->data();
            if (isset($body['query']['multi_match'])) {
                return Http::response(['hits' => ['total' => ['value' => 1], 'hits' => [['_id' => (string) $id]]]]);
            }
            if (isset($body['suggest'])) {
                return Http::response(['suggest' => ['browse_phrase' => [['options' => [['text' => $word]]]]]]);
            }

            return Http::response(['hits' => ['total' => ['value' => 0], 'hits' => []]]);
        });

        $page = $this->get('/glam/browse?topLevel=0&type=all&query='.$typo)->assertOk();
        $page->assertSee(__('Showing close matches for'), false)
            ->assertSee(ucfirst($word).' correspondence', false)
            ->assertSee(__('Did you mean:'), false);

        Http::assertSent(fn (Request $req) => ($req->data()['query']['multi_match']['fuzziness'] ?? null) === 'AUTO'
            && $req->data()['query']['multi_match']['query'] === $typo);
    }
}
