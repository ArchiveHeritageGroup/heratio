<?php

/**
 * ChatbotDisclosureTest - the chatbot never quotes a record the public may not see.
 *
 * The retriever trusted its indexes: the Qdrant index held every description,
 * and the Elasticsearch fallback did not filter on publication, so any signed-in
 * user could get drafts, embargoed or classified descriptions back in an answer
 * and its citations. Every hit is now re-checked through DisclosureGate, which
 * also gained embargo and security classification.
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

namespace Tests\Unit;

use AhgAiChatbot\Services\ChatbotService;
use AhgAiChatbot\Services\QdrantRetriever;
use AhgCore\Services\DisclosureGate;
use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChatbotDisclosureTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string,int> published | draft | embargoed | classified => io id */
    private array $io = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['object', 'information_object', 'information_object_i18n', 'slug', 'status',
                  'embargo', 'object_security_classification', 'security_classification'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->markTestSkipped("table {$t} missing");
            }
        }
        $classification = DB::table('security_classification')->where('level', '>', 0)->value('id');
        if (! $classification) {
            $this->markTestSkipped('no security classification above PUBLIC seeded');
        }

        $lft = (int) DB::table('information_object')->max('rgt') + 1000;
        foreach (['published', 'draft', 'embargoed', 'classified'] as $i => $kind) {
            $id = (int) DB::table('object')->insertGetId([
                'class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('information_object')->insert([
                'id' => $id, 'parent_id' => 1, 'source_culture' => 'en',
                'lft' => $lft + $i * 2, 'rgt' => $lft + $i * 2 + 1,
            ]);
            DB::table('information_object_i18n')->insert([
                'id' => $id, 'culture' => 'en', 'title' => "Disclosure test {$kind}", 'scope_and_content' => "About {$kind}.",
            ]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => "disclosure-test-{$kind}-{$id}"]);
            StatusRow::set($id, DisclosureGate::STATUS_TYPE_PUBLICATION, $kind === 'draft' ? 159 : DisclosureGate::STATUS_PUBLISHED);
            $this->io[$kind] = $id;
        }

        DB::table('embargo')->insert([
            'object_id' => $this->io['embargoed'], 'embargo_type' => 'full', 'status' => 'active',
            'start_date' => now()->subDay()->toDateString(), 'end_date' => null,
        ]);
        DB::table('object_security_classification')->insert([
            'object_id' => $this->io['classified'], 'classification_id' => $classification, 'active' => 1,
        ]);
    }

    private function slug(string $kind): string
    {
        return "disclosure-test-{$kind}-{$this->io[$kind]}";
    }

    public function test_gate_allows_only_the_published_record(): void
    {
        $this->assertSame([$this->io['published']], (new DisclosureGate())->filterIds(array_values($this->io)));
    }

    public function test_lapsed_embargo_and_public_classification_do_not_withhold(): void
    {
        DB::table('embargo')->where('object_id', $this->io['embargoed'])->update(['end_date' => now()->subDay()->toDateString()]);
        DB::table('object_security_classification')->where('object_id', $this->io['classified'])
            ->update(['classification_id' => DB::table('security_classification')->where('level', 0)->value('id')]);

        $allowed = (new DisclosureGate())->filterIds(array_values($this->io));
        sort($allowed);
        $expected = [$this->io['published'], $this->io['embargoed'], $this->io['classified']];
        sort($expected);
        $this->assertSame($expected, $allowed);
    }

    public function test_vector_search_returns_only_the_published_record(): void
    {
        Http::fake([
            '*/ollama/api/embed' => Http::response(['embeddings' => [[0.1, 0.2, 0.3]]]),
            '*/points/search' => Http::response(['result' => array_map(fn ($kind) => [
                'id' => $this->io[$kind], 'score' => 0.9,
                'payload' => ['title' => "Disclosure test {$kind}", 'slug' => $this->slug($kind),
                              'database' => DB::connection()->getDatabaseName()],
            ], array_keys($this->io))]),
            '*' => Http::response([], 404),
        ]);

        $records = (new QdrantRetriever())->search('disclosure test')['records'];

        $this->assertSame([$this->io['published']], array_column($records, 'id'));
    }

    public function test_a_hit_indexed_from_another_database_is_dropped(): void
    {
        // Same id as the published record here, but the point belongs to another
        // catalogue (anc_records is an AtoM database), so it is not this record.
        Http::fake([
            '*/ollama/api/embed' => Http::response(['embeddings' => [[0.1, 0.2, 0.3]]]),
            '*/points/search' => Http::response(['result' => [[
                'id' => $this->io['published'], 'score' => 0.9,
                'payload' => ['title' => 'Someone else', 'slug' => 'someone-else', 'database' => 'atom'],
            ]]]),
            '*/_search' => Http::response(['hits' => ['hits' => []]]),
            '*' => Http::response([], 404),
        ]);

        $this->assertSame([], (new QdrantRetriever())->search('disclosure test')['records']);
    }

    public function test_keyword_fallback_returns_only_the_published_record(): void
    {
        Http::fake([
            '*/ollama/api/embed' => Http::response(['embeddings' => [[0.1, 0.2, 0.3]]]),
            '*/points/search' => Http::response(['result' => []]),
            '*/_search' => Http::response(['hits' => ['hits' => array_map(fn ($kind) => [
                '_score' => 1.0,
                '_source' => ['slug' => $this->slug($kind), 'sourceCulture' => 'en',
                              'i18n' => ['en' => ['title' => "Disclosure test {$kind}"]]],
            ], array_keys($this->io))]]),
            '*' => Http::response([], 404),
        ]);

        $records = (new QdrantRetriever())->search('disclosure test')['records'];

        $this->assertSame([$this->io['published']], array_column($records, 'id'));
    }

    public function test_page_record_context_is_withheld_unless_public(): void
    {
        $chatbot = app(ChatbotService::class);

        $this->assertSame($this->io['published'], $chatbot->resolveCurrentRecord('/'.$this->slug('published'))['id'] ?? null);
        foreach (['draft', 'embargoed', 'classified'] as $kind) {
            $this->assertNull($chatbot->resolveCurrentRecord('/'.$this->slug($kind)), "{$kind} page leaked into context");
        }
    }
}
