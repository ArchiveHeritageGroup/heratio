<?php

/**
 * DonorRemindersCommandTest — issue #1262.
 *
 * Verifies ahg:donor-reminders actually dispatches mail to the resolved
 * recipient, flips the reminder to sent only on success, records the real
 * outcome in donor_agreement_reminder_log, honours --dry-run, and on a
 * recipient-resolution failure logs outcome=failed while leaving the
 * reminder due for retry.
 *
 * Runs against the pre-built heratio_test DB and rolls back each test
 * (DatabaseTransactions, NOT RefreshDatabase — the donor tables and ~995
 * base tables are created out of band). Mirrors
 * packages/ahg-research/tests/Feature/ResearchUserProvisionerTest.php.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Mail\DonorAgreementReminderMail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class DonorRemindersCommandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAgreement(array $overrides = []): int
    {
        // Self-seed: CI's freshly-loaded heratio_test carries no agreement_type
        // rows (not in the base seed set). Use an existing type or mint one so the
        // test is self-sufficient. Rolled back with DatabaseTransactions.
        $typeId = (int) (DB::table('agreement_type')->value('id') ?? DB::table('agreement_type')->insertGetId([
            'name' => 'Test Agreement Type',
            'slug' => 'test-agreement-type-'.Str::lower(Str::random(6)),
        ]));

        return (int) DB::table('donor_agreement')->insertGetId(array_merge([
            'agreement_number' => 'TEST-'.Str::upper(Str::random(8)),
            'agreement_type_id' => $typeId,
            'title' => 'Test Donor Agreement',
            'status' => 'active',
            'expiry_date' => now()->addMonth()->toDateString(),
        ], $overrides));
    }

    private function makeReminder(int $agreementId, array $overrides = []): int
    {
        return (int) DB::table('donor_agreement_reminder')->insertGetId(array_merge([
            'donor_agreement_id' => $agreementId,
            'reminder_type' => 'expiry_warning',
            'subject' => 'Agreement expiring soon',
            'description' => 'Please review and renew.',
            'reminder_date' => now()->subDay()->toDateString(),
            'priority' => 'high',
            'notify_email' => 1,
            'notify_system' => 1,
            'status' => 'active',
            'is_sent' => 0,
        ], $overrides));
    }

    public function test_due_reminder_is_emailed_marked_sent_and_logged(): void
    {
        Mail::fake();

        $email = 'donor_'.strtolower(Str::random(6)).'@example.test';
        $agreementId = $this->makeAgreement();
        $reminderId = $this->makeReminder($agreementId, [
            'notification_recipients' => $email,
        ]);

        $exit = $this->artisan('ahg:donor-reminders')->run();
        $this->assertSame(0, $exit);
        Mail::assertSent(DonorAgreementReminderMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });

        $reminder = DB::table('donor_agreement_reminder')->where('id', $reminderId)->first();
        $this->assertEquals('completed', $reminder->status);
        $this->assertEquals(1, (int) $reminder->is_sent);
        $this->assertNotNull($reminder->sent_at);

        $log = DB::table('donor_agreement_reminder_log')
            ->where('donor_agreement_reminder_id', $reminderId)->first();
        $this->assertNotNull($log);
        $this->assertEquals('sent', $log->status);
        $this->assertEquals('email', $log->notification_method);
        $this->assertStringContainsString($email, (string) $log->sent_to);
    }

    public function test_dry_run_sends_nothing_and_does_not_change_state(): void
    {
        Mail::fake();

        $email = 'donor_'.strtolower(Str::random(6)).'@example.test';
        $agreementId = $this->makeAgreement();
        $reminderId = $this->makeReminder($agreementId, [
            'notification_recipients' => $email,
        ]);

        $exit = $this->artisan('ahg:donor-reminders --dry-run')->run();
        $this->assertSame(0, $exit);

        // Dry-run must not dispatch our reminder (other pre-existing test-DB
        // rows are irrelevant here; scope the assertion to our recipient).
        Mail::assertNotSent(DonorAgreementReminderMail::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });

        $reminder = DB::table('donor_agreement_reminder')->where('id', $reminderId)->first();
        $this->assertEquals('active', $reminder->status);
        $this->assertEquals(0, (int) $reminder->is_sent);
        $this->assertNull($reminder->sent_at);

        $this->assertEquals(
            0,
            DB::table('donor_agreement_reminder_log')->where('donor_agreement_reminder_id', $reminderId)->count()
        );
    }

    public function test_no_recipient_records_failed_and_leaves_reminder_due(): void
    {
        Mail::fake();

        // No actor_id/donor_id and no notification_recipients -> nothing to
        // resolve (staff fallback settings are empty in the test DB).
        $agreementId = $this->makeAgreement(['actor_id' => null, 'donor_id' => null]);
        $reminderId = $this->makeReminder($agreementId, [
            'notification_recipients' => null,
        ]);

        $exit = $this->artisan('ahg:donor-reminders')->run();
        $this->assertSame(0, $exit);

        $reminder = DB::table('donor_agreement_reminder')->where('id', $reminderId)->first();
        // Still due for retry: status unchanged, not marked sent.
        $this->assertEquals('active', $reminder->status);
        $this->assertEquals(0, (int) $reminder->is_sent);
        $this->assertNull($reminder->sent_at);

        $log = DB::table('donor_agreement_reminder_log')
            ->where('donor_agreement_reminder_id', $reminderId)->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
        $this->assertNotEmpty($log->error_message);
    }
}
