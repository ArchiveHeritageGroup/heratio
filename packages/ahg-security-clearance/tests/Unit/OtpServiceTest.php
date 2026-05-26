<?php

/**
 * OtpServiceTest - email / SMS OTP MFA service unit tests (issue #722).
 *
 * Exercises the four contract guarantees:
 *   - enrolment round-trip (enrol -> verifyEnrolment marks verified_at)
 *   - send + verify happy path
 *   - rate limit (60-second cooldown between sendChallenge calls)
 *   - attempt cap (5 wrong codes inside 15 min triggers lockout)
 *
 * Tests run against the same heratio_test DB used by every other package.
 * Each test cleans the OTP tables for the synthetic user_id used here so
 * the suite stays idempotent.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Unit;

use AhgSecurityClearance\Services\NullSmsGateway;
use AhgSecurityClearance\Services\OtpService;
use AhgSecurityClearance\Services\SmsGatewayInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private const SYNTHETIC_USER_ID = 999000722;

    private OtpService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('ahg_otp_factor') || ! Schema::hasTable('ahg_otp_challenge')) {
            $this->markTestSkipped('OTP tables not present in test DB; run ServiceProvider boot to install.');
        }

        // Force the null SMS driver inside the service container so SMS
        // assertions never hit a real provider.
        $this->app->bind(SmsGatewayInterface::class, NullSmsGateway::class);
        Mail::fake();

        $this->cleanupForUser(self::SYNTHETIC_USER_ID);
        Cache::flush();

        $this->svc = new OtpService();
    }

    protected function tearDown(): void
    {
        $this->cleanupForUser(self::SYNTHETIC_USER_ID);
        Cache::flush();
        parent::tearDown();
    }

    private function cleanupForUser(int $userId): void
    {
        DB::table('ahg_otp_challenge')->where('user_id', $userId)->delete();
        DB::table('ahg_otp_factor')->where('user_id', $userId)->delete();
    }

    // ─── enrolment ──────────────────────────────────────────────────────────

    public function test_enrolment_creates_factor_and_sends_first_code(): void
    {
        $factor = $this->svc->enrol(self::SYNTHETIC_USER_ID, OtpService::TYPE_EMAIL, 'Alice@Example.COM', 'Work');

        $this->assertIsObject($factor);
        $this->assertSame('alice@example.com', $factor->destination, 'email destinations are lowercased');
        $this->assertNull($factor->verified_at, 'verified_at must remain null until the user types the code');

        $challenges = DB::table('ahg_otp_challenge')
            ->where('user_id', self::SYNTHETIC_USER_ID)
            ->where('factor_id', $factor->id)
            ->get();
        $this->assertCount(1, $challenges, 'enrol must dispatch exactly one challenge');
        $this->assertNotEmpty($challenges->first()->code_hash, 'challenge stores a SHA-256 hash');
    }

    public function test_verify_enrolment_promotes_factor_with_correct_code(): void
    {
        $factor = $this->svc->enrol(self::SYNTHETIC_USER_ID, OtpService::TYPE_EMAIL, 'bob@example.com', 'Bob');
        $plain = $this->forceKnownCode($factor->id, '123456');

        $ok = $this->svc->verifyEnrolment(self::SYNTHETIC_USER_ID, $factor->id, $plain);

        $this->assertTrue($ok, 'verifyEnrolment must accept the matching code');
        $row = DB::table('ahg_otp_factor')->where('id', $factor->id)->first();
        $this->assertNotNull($row->verified_at, 'verified_at must be set after verifyEnrolment');
    }

    public function test_verify_enrolment_rejects_wrong_code(): void
    {
        $factor = $this->svc->enrol(self::SYNTHETIC_USER_ID, OtpService::TYPE_EMAIL, 'carol@example.com', 'Carol');
        $this->forceKnownCode($factor->id, '654321');

        $ok = $this->svc->verifyEnrolment(self::SYNTHETIC_USER_ID, $factor->id, '111111');

        $this->assertFalse($ok);
        $row = DB::table('ahg_otp_factor')->where('id', $factor->id)->first();
        $this->assertNull($row->verified_at, 'verified_at must stay null on bad code');
    }

    // ─── verify (post-enrol) ───────────────────────────────────────────────

    public function test_verify_round_trip_for_already_enrolled_factor(): void
    {
        $factor = $this->makeVerifiedFactor('dave@example.com');

        // sendChallenge() is called inside enrol() initially, but for an
        // already-verified factor we need a fresh code; rotate to a known
        // plaintext.
        $plain = $this->forceKnownCode($factor->id, '424242');

        $this->assertTrue($this->svc->verify(self::SYNTHETIC_USER_ID, $factor->id, $plain));
    }

    public function test_verify_marks_challenge_consumed_so_replay_fails(): void
    {
        $factor = $this->makeVerifiedFactor('erin@example.com');
        $plain = $this->forceKnownCode($factor->id, '777777');

        $this->assertTrue($this->svc->verify(self::SYNTHETIC_USER_ID, $factor->id, $plain));
        $this->assertFalse($this->svc->verify(self::SYNTHETIC_USER_ID, $factor->id, $plain),
            'a consumed code must not be replayable');
    }

    // ─── rate limit ────────────────────────────────────────────────────────

    public function test_send_challenge_is_throttled_to_one_per_60s(): void
    {
        $factor = $this->makeVerifiedFactor('frank@example.com');

        // Refresh handle to a plain object that the service can pass around.
        $row = DB::table('ahg_otp_factor')->where('id', $factor->id)->first();

        $this->svc->sendChallenge(self::SYNTHETIC_USER_ID, $row);
        $countAfterFirst = DB::table('ahg_otp_challenge')->where('factor_id', $factor->id)->count();

        // Second send within the throttle window must NOT produce a new row.
        $this->svc->sendChallenge(self::SYNTHETIC_USER_ID, $row);
        $countAfterSecond = DB::table('ahg_otp_challenge')->where('factor_id', $factor->id)->count();

        $this->assertSame($countAfterFirst, $countAfterSecond,
            'throttle must suppress a second sendChallenge inside 60s');
    }

    // ─── attempt cap ───────────────────────────────────────────────────────

    public function test_verify_locks_factor_after_max_attempts(): void
    {
        $factor = $this->makeVerifiedFactor('gabby@example.com');
        $plain = $this->forceKnownCode($factor->id, '888888');

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->assertFalse($this->svc->verify(self::SYNTHETIC_USER_ID, $factor->id, '000001'));
        }

        // The correct code must now also be rejected because the factor is
        // locked out.
        $this->assertFalse($this->svc->verify(self::SYNTHETIC_USER_ID, $factor->id, $plain),
            'factor must lock out after MAX_ATTEMPTS failed verifies');
    }

    public function test_delete_factor_clears_lockout_state(): void
    {
        $factor = $this->makeVerifiedFactor('hugh@example.com');

        for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
            $this->svc->verify(self::SYNTHETIC_USER_ID, $factor->id, '000001');
        }

        $this->assertTrue($this->svc->deleteFactor(self::SYNTHETIC_USER_ID, $factor->id));
        $this->assertFalse($this->svc->userHasOtp(self::SYNTHETIC_USER_ID));
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    /**
     * Insert a row + matching consumed-stub so the test path can pretend
     * the factor was set up earlier (skipping the enrol() send so we can
     * mint a code with a known plaintext).
     */
    private function makeVerifiedFactor(string $email): object
    {
        $id = DB::table('ahg_otp_factor')->insertGetId([
            'user_id' => self::SYNTHETIC_USER_ID,
            'factor_type' => OtpService::TYPE_EMAIL,
            'destination' => strtolower($email),
            'label' => 'Test',
            'verified_at' => now(),
            'last_used_at' => null,
            'created_at' => now(),
        ]);

        return (object) [
            'id' => $id,
            'user_id' => self::SYNTHETIC_USER_ID,
            'factor_type' => OtpService::TYPE_EMAIL,
            'destination' => strtolower($email),
            'label' => 'Test',
            'verified_at' => now(),
        ];
    }

    /**
     * Force a challenge row to a known plaintext so we can drive verify()
     * deterministically. Wipes any existing challenges for the factor
     * first so the matchCode() lookup grabs ours.
     */
    private function forceKnownCode(int $factorId, string $plain): string
    {
        DB::table('ahg_otp_challenge')->where('factor_id', $factorId)->delete();
        DB::table('ahg_otp_challenge')->insert([
            'user_id' => self::SYNTHETIC_USER_ID,
            'factor_id' => $factorId,
            'code_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(OtpService::CHALLENGE_TTL_MINUTES),
            'attempts' => 0,
            'consumed_at' => null,
            'created_at' => now(),
        ]);

        return $plain;
    }
}
