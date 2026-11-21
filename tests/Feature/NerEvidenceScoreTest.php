<?php

/**
 * NerEvidenceScoreTest - heratio#1501: NER entities the extractor left unscored
 * get a separate, labelled evidence score; `confidence` is never filled in; a
 * plain backfill makes no network call; the model check goes through the
 * gateway only when asked; "Explore this collection" reads the score.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgAiServices\Services\NerEvidenceScorer;
use AhgCore\Services\AhgSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NerEvidenceScoreTest extends TestCase
{
    use DatabaseTransactions;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(string $text = ''): int
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'lft' => 9200000 + $id, 'rgt' => 9200001 + $id]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'Test record ' . $id, 'scope_and_content' => $text]);

        return $id;
    }

    private function entity(int $objectId, string $type, string $value, string $status = 'pending', ?float $confidence = null): int
    {
        return (int) DB::table('ahg_ner_entity')->insertGetId([
            'object_id' => $objectId, 'entity_type' => $type, 'entity_value' => $value,
            'confidence' => $confidence, 'status' => $status, 'created_at' => now(),
        ]);
    }

    /** Letters only, so the shape rules see a plain capitalised word. */
    private function tag(): string
    {
        return ucfirst(substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz', 2)), 0, 10));
    }

    private function row(int $id): object
    {
        return DB::table('ahg_ner_entity')->where('id', $id)->first();
    }

    public function test_an_omitted_confidence_is_null_not_one(): void
    {
        $id = (int) DB::table('ahg_ner_entity')->insertGetId([
            'object_id' => $this->record(), 'entity_type' => 'PERSON', 'entity_value' => 'Zx Nobody', 'created_at' => now(),
        ]);
        $this->assertNull($this->row($id)->confidence);
    }

    public function test_rules_score_from_catalogue_evidence_and_never_touch_confidence(): void
    {
        $tag = $this->tag();
        $other = $this->record();
        $this->entity($other, 'PERSON', "{$tag} Accepted Person", 'linked');
        $this->entity($other, 'ORG', "{$tag} Rejected Body", 'reject');

        $actor = $this->obj('QubitActor');
        DB::table('actor')->insert(['id' => $actor, 'source_culture' => 'en']);
        DB::table('actor_i18n')->insert(['id' => $actor, 'culture' => 'en', 'authorized_form_of_name' => "{$tag} Authority Person"]);
        $term = $this->obj('QubitTerm');
        DB::table('term')->insert(['id' => $term, 'taxonomy_id' => 42, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $term, 'culture' => 'en', 'name' => "{$tag}ville"]);

        $io = $this->record();
        $ids = [
            'accepted' => $this->entity($io, 'PERSON', "{$tag} Accepted Person"),
            'rejected' => $this->entity($io, 'ORG', "{$tag} Rejected Body"),
            'authority' => $this->entity($io, 'PERSON', "{$tag} Authority Person"),
            'place' => $this->entity($io, 'GPE', "{$tag}ville"),
            'placeAsPerson' => $this->entity($io, 'PERSON', "{$tag}ville"),
            'lower' => $this->entity($io, 'PERSON', 'the ' . strtolower($tag) . ' committee'),
            'leadIn' => $this->entity($io, 'ORG', "the {$tag} University"),
            'year' => $this->entity($io, 'DATE', "{$tag} 1871"),
            'noYear' => $this->entity($io, 'DATE', "{$tag} Tuesday"),
            'scored' => $this->entity($io, 'PERSON', "{$tag} Scored Person", 'pending', 0.42),
        ];

        $n = app(NerEvidenceScorer::class)->scoreIds(array_values($ids));
        $this->assertSame(9, $n, 'the extractor-scored row is left alone');

        $expect = ['accepted' => 0.85, 'rejected' => 0.15, 'authority' => 0.80, 'place' => 0.80,
            'placeAsPerson' => 0.25, 'lower' => 0.25, 'leadIn' => 0.40, 'year' => 0.60, 'noYear' => 0.30];
        foreach ($expect as $k => $score) {
            $r = $this->row($ids[$k]);
            $this->assertEqualsWithDelta($score, (float) $r->evidence_score, 0.0001, $k);
            $this->assertSame('rules', $r->evidence_method, $k);
            $this->assertNotEmpty($r->evidence_reasons, $k);
            $this->assertNull($r->confidence, "{$k}: confidence stays the extractor's alone");
        }
        $scored = $this->row($ids['scored']);
        $this->assertEqualsWithDelta(0.42, (float) $scored->confidence, 0.0001);
        $this->assertNull($scored->evidence_score);
    }

    public function test_plain_backfill_makes_no_network_call(): void
    {
        Http::fake();
        $io = $this->record();
        $id = $this->entity($io, 'PERSON', $this->tag() . ' Thandiwe Dlamini');

        $this->artisan('ahg:ai-ner-score', ['--object-id' => $io])->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('rules', $this->row($id)->evidence_method);
        $this->assertEqualsWithDelta(0.50, (float) $this->row($id)->evidence_score, 0.0001);
    }

    public function test_verify_asks_the_gateway_and_stores_the_model_probability(): void
    {
        Http::fake(['*' => Http::response(['response' => ' No', 'logprobs' => [['token' => ' No', 'top_logprobs' => [
            ['token' => ' No', 'logprob' => log(0.9)], ['token' => ' Yes', 'logprob' => log(0.1)], ['token' => ' The', 'logprob' => log(0.001)],
        ]]]])]);
        $io = $this->record('Letter to Comrade Tambo re: Morogoro, ANC Lusaka 1969.');
        $place = $this->tag();
        DB::table('information_object_i18n')->where('id', $io)->update(['scope_and_content' => "Letter to Comrade Tambo re: {$place}, ANC Lusaka 1969."]);
        $id = $this->entity($io, 'PERSON', $place);

        $this->artisan('ahg:ai-ner-score', ['--object-id' => $io, '--verify' => true, '--model' => 'mistral:7b'])->assertSuccessful();

        $r = $this->row($id);
        $this->assertSame('llm_verify', $r->evidence_method);
        $this->assertEqualsWithDelta(0.1, (float) $r->evidence_score, 0.0001);
        $this->assertNull($r->confidence);
        Http::assertSent(function (Request $req) {
            return str_ends_with($req->url(), '/ai/v1/ollama/api/generate')
                && ! preg_match('~:11434|:5004|192\.168\.~', $req->url())
                && $req['logprobs'] === true
                && str_contains($req['prompt'], 'Comrade Tambo re:')
                && str_contains($req['prompt'], 'the name of a person');
        });

        // A later rules run never overwrites the model check.
        app(NerEvidenceScorer::class)->scoreIds([$id], true);
        $this->assertSame('llm_verify', $this->row($id)->evidence_method);
    }

    public function test_yes_probability_reads_both_answers(): void
    {
        $this->assertNull(NerEvidenceScorer::yesProbability([['token' => 'The', 'logprob' => -0.1]]));
        $this->assertEqualsWithDelta(0.75, NerEvidenceScorer::yesProbability([
            ['token' => ' Yes', 'logprob' => log(0.6)], ['token' => 'yes', 'logprob' => log(0.15)], ['token' => ' no', 'logprob' => log(0.25)],
        ]), 0.0001);
    }

    public function test_explorer_threshold_reads_the_evidence_score(): void
    {
        $tag = $this->tag();
        $other = $this->record();
        $this->entity($other, 'PERSON', "{$tag} Backed Person", 'linked');
        $io = $this->record();
        $backed = $this->entity($io, 'PERSON', "{$tag} Backed Person");
        $shape = $this->entity($io, 'PERSON', "{$tag} Shape Person");
        app(NerEvidenceScorer::class)->scoreIds([$backed, $shape]);

        $names = function (string $min) use ($io): array {
            DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'explorer_ner_min_confidence'], ['setting_value' => $min, 'setting_group' => 'discovery']);
            AhgSettingsService::clearCache();
            $gen = (new \ReflectionMethod(\AhgApi\Services\ExplorerDataService::class, 'names'))->invoke(null, [$io]);

            return array_map(fn ($r) => $r[2], array_filter(iterator_to_array($gen, false), fn ($r) => $r[0] === $io));
        };

        $this->assertNotContains("{$tag} Backed Person", $names('0'), '0 keeps reviewed names only');
        $at08 = $names('0.8');
        $this->assertContains("{$tag} Backed Person", $at08);
        $this->assertNotContains("{$tag} Shape Person", $at08);
        $this->assertContains("{$tag} Shape Person", $names('0.5'));
        AhgSettingsService::clearCache();
    }
}
