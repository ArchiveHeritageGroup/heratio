<?php

/**
 * SaTranslationGatewayTest - heratio#1419 / #1444: the translation clients send
 * the gateway contract codes for the 11 official South African languages, call
 * /ai/v1/translate once (not /translate/translate), and degrade cleanly when the
 * gateway cannot serve a language - no wrong-language guess, no general-LLM
 * fallback for an SA language. All HTTP is faked.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgAiServices\Services\LlmService;
use AhgAiServices\Support\TranslationLanguages as L;
use AhgCore\Models\User;
use AhgCore\Services\AclService;
use AhgCore\Services\AnswerLocalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaTranslationGatewayTest extends TestCase
{
    use DatabaseTransactions;

    private const GW = 'https://ai.theahg.co.za/ai/v1/translate';

    /** The gateway's real /translate/languages answer on 21 Nov 2026. */
    private const LIVE_LANGUAGES = [
        'ct2_pairs' => ['bnt->en', 'af->en', 'en->af', 'en->bnt'],
        'languages' => [['code' => 'af', 'name' => 'Afrikaans'], ['code' => 'bnt', 'name' => 'bnt'], ['code' => 'en', 'name' => 'English']],
        'model' => 'opus-mt-ct2',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        foreach ([
            'translation_enabled' => '1',
            'translation_mode' => 'mt',
            'mt_endpoint' => self::GW,
            'mzansilm_enabled' => '0',
            'api_key' => 'test-gateway-key',
        ] as $k => $v) {
            DB::table('ahg_ner_settings')->updateOrInsert(['setting_key' => $k], ['setting_value' => $v]);
        }
        DB::table('ahg_translation_settings')->updateOrInsert(['setting_key' => 'mt.endpoint'], ['setting_value' => self::GW]);
        DB::table('ahg_translation_settings')->updateOrInsert(['setting_key' => 'mt.api_key'], ['setting_value' => '']);
    }

    private function fakeGateway(array $languages = self::LIVE_LANGUAGES, int $langStatus = 200): void
    {
        Http::fake([
            self::GW . '/languages' => Http::response($languages, $langStatus),
            self::GW => function (Request $r) {
                $t = $r['target'] ?? '';
                if (in_array($t, ['nso', 've', 'nr'], true)) {
                    return Http::response(['code' => 'TRANSLATION_FAILED', 'error' => "No translator available for en -> {$t}"], 500);
                }

                return Http::response(['success' => true, 'model' => 'opus-mt-ct2', 'translated' => "[{$t}] " . $r['text']]);
            },
            '*' => Http::response(['error' => 'unexpected call'], 599),
        ]);
    }

    public function test_codes_normalise_to_the_gateway_contract_for_all_11_languages(): void
    {
        $this->assertCount(11, L::SA_OFFICIAL);
        foreach (['zu_ZA' => 'zu', 'zu-ZA' => 'zu', 'zul_Latn' => 'zu', 'XHO' => 'xh', 'nso' => 'nso', 'nso_ZA' => 'nso',
            'tsn' => 'tn', 'sot_Latn' => 'st', 'tso' => 'ts', 'ssw' => 'ss', 'ven' => 've', 'nbl' => 'nr', 'afr' => 'af', 'en_GB' => 'en'] as $in => $out) {
            $this->assertSame($out, L::normalise($in), $in);
        }
        $this->assertNotSame('nd', L::normalise('nr'), 'isiNdebele is never folded into Northern Ndebele');
        $this->assertSame('zul_Latn', L::floresCode('zu_ZA'));
        $this->assertNull(L::floresCode('ve'), 'NLLB-200 has no Tshivenda code');
        $this->assertNull(L::floresCode('nr'), 'NLLB-200 has no isiNdebele code');
        $this->assertFalse(L::isSaOfficial('nd'));
    }

    public function test_translate_url_carries_one_translate_segment(): void
    {
        $this->assertSame(self::GW, L::translateUrl(self::GW));
        $this->assertSame(self::GW, L::translateUrl(self::GW . '/'));
        $this->assertSame(self::GW, L::translateUrl('https://ai.theahg.co.za/ai/v1'));
        $this->assertSame(self::GW . '/languages', L::languagesUrl(self::GW));
    }

    public function test_the_bnt_family_counts_only_for_the_languages_it_serves(): void
    {
        $this->fakeGateway();
        $this->assertSame(['af', 'zu', 'xh', 'ts', 'sn', 'en'], L::gatewayLanguages(self::GW, 'k'));
        foreach (['af', 'zu', 'xh', 'ts', 'en'] as $ok) {
            $this->assertTrue(L::gatewaySupports($ok, self::GW, 'k'), $ok);
        }
        foreach (['st', 'tn', 'ss', 'nso', 've', 'nr'] as $no) {
            $this->assertFalse(L::gatewaySupports($no, self::GW, 'k'), $no);
        }
    }

    public function test_llm_service_posts_once_to_the_gateway_with_the_short_code(): void
    {
        $this->fakeGateway();
        $out = app(LlmService::class)->translate('Download the report', 'zu_ZA', 'en');

        $this->assertSame('[zu] Download the report', $out);
        Http::assertSent(fn (Request $r) => $r->url() === self::GW && $r['target'] === 'zu' && $r['source'] === 'en'
            && $r->hasHeader('Authorization', 'Bearer test-gateway-key'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/translate/translate'));
    }

    public function test_an_sa_language_the_gateway_cannot_serve_keeps_the_original_and_skips_the_llm(): void
    {
        $this->fakeGateway();
        $this->assertNull(app(LlmService::class)->translate('Download the report', 'st', 'en'));
        Http::assertNotSent(fn (Request $r) => $r->url() === self::GW);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'ollama') || str_contains($r->url(), 'chat/completions'));
    }

    public function test_an_explicit_no_translator_answer_degrades_when_the_list_is_unreadable(): void
    {
        $this->fakeGateway([], 404);
        $this->assertNull(app(LlmService::class)->translate('Download the report', 've', 'en'));
        Http::assertSent(fn (Request $r) => $r->url() === self::GW && $r['target'] === 've');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'ollama') || str_contains($r->url(), 'chat/completions'));
    }

    public function test_answer_localizer_stays_english_for_an_unserved_language(): void
    {
        $this->fakeGateway();
        $loc = app(AnswerLocalizer::class);
        $this->assertSame('Opening hours', $loc->localize('Opening hours', 'tn'));
        $this->assertSame('[af] Opening hours', $loc->localize('Opening hours', 'af_ZA'));
    }

    public function test_the_string_suggest_route_says_unsupported_for_isindebele(): void
    {
        $this->fakeGateway();
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitUser', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('actor')->insert(['id' => $id, 'source_culture' => 'en']);
        DB::table('user')->insert(['id' => $id, 'username' => 'sat-'.$id, 'email' => uniqid('sat-', true).'@example.test', 'password_hash' => Hash::make('x'), 'active' => 1]);
        DB::table('acl_user_group')->insert(['user_id' => $id, 'group_id' => 100]);
        Cache::forget("acl_groups_{$id}");
        AclService::forgetUser();
        $user = User::findOrFail($id);

        $this->actingAs($user)->getJson('/admin/translation/strings/mt-suggest?locale=nr&text=Search')
            ->assertStatus(422)->assertJson(['ok' => false, 'unsupported' => true]);
        // The refusal happens before translateText()'s own (curl) call to the gateway.
        Http::assertNotSent(fn (Request $r) => $r->url() === self::GW);
    }
}
