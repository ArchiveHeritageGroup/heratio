<?php

/**
 * VoiceSearchSttTest - heratio#1767. Server speech-to-text fallback for voice
 * search in browsers without the speech API. The AI gateway is faked; no
 * live call is made.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VoiceSearchSttTest extends TestCase
{
    use DatabaseTransactions;

    private const UNAVAILABLE = 'Voice search is not available right now; please type your search';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('ahg_settings')) {
            $this->markTestSkipped('ahg_settings table missing');
        }
        $this->settings([
            'voice_enabled' => 'true',
            'voice_stt_enabled' => 'true',
            'voice_stt_model' => 'whisper-large-v3',
            'voice_stt_path' => '/ai/v1/audio/transcriptions',
            'voice_stt_require_auth' => 'false',
            'voice_stt_max_kb' => '256',
            'voice_stt_rate_limit' => '3',
            'voice_stt_timeout' => '5',
        ]);
        RateLimiter::clear('voice-stt:127.0.0.1');
    }

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('ahg_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'setting_group' => 'voice_ai']
            );
        }
    }

    private function clip(int $kb = 8, string $mime = 'audio/webm', string $name = 'speech.webm'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, $mime);
    }

    public function test_transcription_is_passed_through_and_the_temp_file_is_deleted(): void
    {
        Http::fake(['*/ai/v1/audio/transcriptions' => Http::response(['text' => ' search for maps '], 200)]);
        $clip = $this->clip();
        $path = $clip->getRealPath();
        $this->assertFileExists($path);

        $this->post('/voice/stt', ['audio' => $clip])
            ->assertOk()
            ->assertExactJson(['ok' => true, 'text' => 'search for maps']);

        $this->assertFileDoesNotExist($path);
        Http::assertSent(function (ClientRequest $r) {
            $body = $r->body();

            return str_ends_with($r->url(), '/ai/v1/audio/transcriptions')
                && $r->isMultipart()
                && str_contains($body, 'name="file"')
                && str_contains($body, 'whisper-large-v3')
                && str_contains($body, 'name="language"');
        });
    }

    public function test_gateway_404_gives_the_friendly_message_and_logs_once(): void
    {
        Http::fake(['*' => Http::response(['detail' => 'Not Found'], 404)]);
        Log::spy();

        $first = $this->post('/voice/stt', ['audio' => $this->clip()]);
        $first->assertStatus(503)->assertJson(['ok' => false, 'message' => self::UNAVAILABLE]);
        $this->post('/voice/stt', ['audio' => $this->clip()])->assertStatus(503);

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_gateway_timeout_gives_the_friendly_message_not_a_500(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->post('/voice/stt', ['audio' => $this->clip()])
            ->assertStatus(503)
            ->assertJson(['ok' => false, 'message' => self::UNAVAILABLE]);
    }

    public function test_gateway_5xx_gives_the_friendly_message(): void
    {
        Http::fake(['*' => Http::response('upstream down', 502)]);

        $this->post('/voice/stt', ['audio' => $this->clip()])
            ->assertStatus(503)
            ->assertJson(['message' => self::UNAVAILABLE]);
    }

    public function test_oversize_upload_is_rejected(): void
    {
        Http::fake();
        $this->post('/voice/stt', ['audio' => $this->clip(300)])->assertStatus(422)->assertJson(['error' => 'invalid_audio']);
        Http::assertNothingSent();
    }

    public function test_wrong_type_upload_is_rejected(): void
    {
        Http::fake();
        $this->post('/voice/stt', ['audio' => $this->clip(4, 'text/plain', 'notes.txt')])->assertStatus(422);
        $this->post('/voice/stt', ['audio' => $this->clip(4, 'audio/mpeg', 'song.mp3')])->assertStatus(422);
        $this->post('/voice/stt', [])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_rate_limit_applies(): void
    {
        Http::fake(['*' => Http::response(['text' => 'go home'], 200)]);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/voice/stt', ['audio' => $this->clip()])->assertOk();
        }
        $this->post('/voice/stt', ['audio' => $this->clip()])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
        Http::assertSentCount(3);
    }

    public function test_setting_off_gives_404_disabled(): void
    {
        Http::fake();
        $this->settings(['voice_stt_enabled' => 'false']);

        $this->post('/voice/stt', ['audio' => $this->clip()])->assertNotFound()->assertJson(['error' => 'disabled']);
        $this->getJson('/voice/stt/config')->assertOk()->assertJson(['enabled' => false]);
        Http::assertNothingSent();
    }

    public function test_no_model_set_keeps_the_fallback_off(): void
    {
        Http::fake();
        $this->settings(['voice_stt_model' => '']);

        $this->post('/voice/stt', ['audio' => $this->clip()])->assertNotFound();
        $this->getJson('/voice/stt/config')->assertJson(['enabled' => false]);
    }

    public function test_require_auth_refuses_guests(): void
    {
        Http::fake();
        $this->settings(['voice_stt_require_auth' => 'true']);

        $this->post('/voice/stt', ['audio' => $this->clip()])->assertStatus(401);
        $this->getJson('/voice/stt/config')->assertJson(['enabled' => false]);
        Http::assertNothingSent();
    }

    public function test_config_reports_limits_when_on(): void
    {
        $this->getJson('/voice/stt/config')
            ->assertOk()
            ->assertJson(['enabled' => true, 'max_seconds' => 15, 'max_bytes' => 256 * 1024])
            ->assertJsonPath('messages.unavailable', self::UNAVAILABLE);
    }

    public function test_path_setting_cannot_point_away_from_the_gateway(): void
    {
        Http::fake(['*' => Http::response(['text' => 'ok'], 200)]);
        $this->settings(['voice_stt_path' => 'http://192.0.2.10:9000/asr']);

        $this->post('/voice/stt', ['audio' => $this->clip()])->assertOk();
        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/ai/v1/audio/transcriptions')
            && ! str_contains($r->url(), '192.0.2.10'));
    }

    public function test_voice_commands_follow_their_setting(): void
    {
        \Illuminate\Support\Facades\DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'voice_commands_enabled'], ['setting_value' => 'true', 'setting_group' => 'voice_ai']);
        \AhgCore\Services\AhgSettingsService::clearCache();
        $this->get('/')->assertSee('voiceCommands.js', false);

        \Illuminate\Support\Facades\DB::table('ahg_settings')->where('setting_key', 'voice_commands_enabled')->update(['setting_value' => 'false']);
        \AhgCore\Services\AhgSettingsService::clearCache();
        $this->get('/')->assertDontSee('voiceCommands.js', false)->assertDontSee('voiceSttFallback.js', false);
    }
}
