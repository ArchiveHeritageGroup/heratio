<?php

/**
 * OverdueNoticeChannelsTest - SMS + print channels, patron preference and
 * honest per-channel logging (#1474 item 2). No real gateway is ever called.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgLibrary\Controllers\NoticeChannelController;
use AhgLibrary\Services\LibraryNoticeChannelService;
use AhgLibrary\Services\LibraryNoticePrintService;
use AhgLibrary\Services\LibraryOverdueNoticeService;
use AhgSecurityClearance\Services\NullSmsGateway;
use AhgSecurityClearance\Services\OtpService;
use AhgSecurityClearance\Services\SmsGatewayInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OverdueNoticeChannelsTest extends LibraryFeatureTestCase
{
    private object $sms;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ahg_dropdown', function (Blueprint $t) {
            $t->increments('id');
            foreach (['taxonomy', 'taxonomy_label', 'taxonomy_section', 'code', 'label'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('sort_order')->default(0);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamp('created_at')->nullable();
        });
        foreach (['information_object_i18n'] as $table) {
            if (!Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->integer('id');
                    $t->string('culture')->default('en');
                    $t->string('title')->nullable();
                });
            }
        }
        (require dirname(__DIR__, 2) . '/database/migrations/2026_11_22_100000_create_library_notice_channel_tables.php')->up();

        $this->sms = new class implements SmsGatewayInterface {
            public array $sent = [];
            public bool $accept = true;
            public function send(string $to, string $body): bool
            {
                $this->sent[] = [$to, $body];
                return $this->accept;
            }
        };
        $this->useGateway($this->sms);
        Mail::fake();

        DB::table('library_loan_rule')->insert([
            'material_type' => 'monograph', 'patron_type' => 'student', 'loan_period_days' => 14,
            'max_renewals' => 2, 'fine_per_day' => 0.50, 'fine_cap' => 25.00, 'grace_period_days' => 0,
            'is_loanable' => 1, 'created_at' => now(),
        ]);
    }

    private function useGateway(SmsGatewayInterface $gateway): void
    {
        $this->app->instance(OtpService::class, new class($gateway) extends OtpService {
            public function __construct(private SmsGatewayInterface $g)
            {
            }
            public function smsGateway(): SmsGatewayInterface
            {
                return $this->g;
            }
        });
    }

    private function activate(string $channel): void
    {
        DB::table('library_notice_template')->where('channel', $channel)->update(['is_active' => 1]);
    }

    private function overdueLoan(array $patron = [], ?string $preference = null, int $days = 3): int
    {
        static $n = 0;
        $n++;
        $patronId = DB::table('library_patron')->insertGetId(array_merge([
            'card_number' => 'C' . $n, 'patron_type' => 'student', 'first_name' => 'Ann', 'last_name' => 'Reader',
            'email' => "ann{$n}@example.test", 'phone' => '+27 82 555 0100', 'membership_start' => '2026-01-01',
        ], $patron));
        if ($preference) {
            app(LibraryNoticeChannelService::class)->setPreference($patronId, $preference, 1);
        }
        return DB::table('library_checkout')->insertGetId([
            'copy_id' => $n, 'patron_id' => $patronId, 'checkout_date' => now()->subDays(20),
            'due_date' => now()->subDays($days)->toDateString(), 'status' => 'active',
        ]);
    }

    private function logs(int $checkoutId): array
    {
        return DB::table('library_overdue_notice_log')->where('checkout_id', $checkoutId)->orderBy('id')
            ->get()->map(fn ($r) => "{$r->channel}:{$r->status}")->all();
    }

    private function run_(bool $dry = false): array
    {
        return app(LibraryOverdueNoticeService::class)->processOverdue($dry);
    }

    public function test_default_patron_still_gets_email_and_inactive_channels_do_nothing(): void
    {
        $c = $this->overdueLoan();
        $stats = $this->run_();
        $this->assertSame(1, $stats['sent']);
        $this->assertSame(['email:sent'], $this->logs($c));
        $this->assertSame([], $this->sms->sent);
        $this->assertSame(0, DB::table('library_notice_print_item')->count());
    }

    public function test_sms_preference_sends_through_the_gateway_with_normalised_number(): void
    {
        $this->activate('sms');
        $c = $this->overdueLoan([], 'sms');
        $this->run_();
        $this->assertSame('+27825550100', $this->sms->sent[0][0]);
        $this->assertSame(['sms:sent'], $this->logs($c));
        Mail::assertNothingOutgoing();
    }

    public function test_null_gateway_is_never_logged_as_sent_and_falls_back_to_email(): void
    {
        $this->useGateway(new NullSmsGateway());
        $this->activate('sms');
        $c = $this->overdueLoan([], 'sms');
        $this->run_();
        $this->assertSame(['sms:skipped', 'email:sent'], $this->logs($c));
        $this->assertSame('no SMS gateway configured', DB::table('library_overdue_notice_log')->where('channel', 'sms')->value('error_message'));
    }

    public function test_gateway_refusal_is_failed_and_retried_next_run(): void
    {
        $this->activate('sms');
        $this->sms->accept = false;
        $c = $this->overdueLoan([], 'sms');
        $this->assertSame(1, $this->run_()['failed']);
        $this->sms->accept = true;
        $this->assertSame(1, $this->run_()['sent']);
        $this->assertSame(['sms:failed', 'sms:sent'], $this->logs($c));
    }

    public function test_sms_stops_after_the_attempt_cap_and_falls_through_to_email(): void
    {
        $this->activate('sms');
        $this->sms->accept = false;
        $c = $this->overdueLoan([], 'sms');

        $this->assertSame(1, $this->run_()['failed']);
        $this->assertSame(1, $this->run_()['sent']);
        $this->run_();

        $this->assertCount(2, $this->sms->sent);
        $this->assertSame(['sms:failed', 'sms:failed', 'email:sent'], $this->logs($c));
    }

    public function test_repeated_gateway_failures_stop_sms_for_the_run(): void
    {
        $this->activate('sms');
        $this->sms->accept = false;
        $loans = [];
        for ($i = 0; $i < 5; $i++) {
            $loans[] = $this->overdueLoan([], 'sms');
        }
        $stats = $this->run_();

        $this->assertCount(3, $this->sms->sent);
        $this->assertSame(3, $stats['failed']);
        $this->assertSame(2, $stats['sent']);
        $this->assertSame(['sms:skipped', 'email:sent'], $this->logs($loans[4]));
    }

    public function test_loan_flagged_overdue_still_escalates_through_tiers(): void
    {
        $c = $this->overdueLoan([], null, 8);
        DB::table('library_checkout')->where('id', $c)->update(['status' => 'overdue']);
        $this->run_();
        $this->assertSame('overdue_2', DB::table('library_overdue_notice_log')->where('checkout_id', $c)->value('notice_type'));

        DB::table('library_checkout')->where('id', $c)->update(['due_date' => now()->subDays(22)->toDateString()]);
        $this->run_();
        $this->assertSame(
            ['overdue_2', 'overdue_final'],
            DB::table('library_overdue_notice_log')->where('checkout_id', $c)->orderBy('id')->pluck('notice_type')->all()
        );
    }

    public function test_returned_loan_with_stale_overdue_status_gets_no_notice(): void
    {
        $c = $this->overdueLoan();
        DB::table('library_checkout')->where('id', $c)->update(['status' => 'overdue', 'return_date' => now()]);
        $this->run_();
        $this->assertSame([], $this->logs($c));
    }

    public function test_undeliverable_top_tier_falls_back_to_a_deliverable_lower_tier(): void
    {
        DB::table('library_notice_template')->where('notice_type', 'overdue_final')->where('channel', 'email')->update(['is_active' => 0]);
        DB::table('library_notice_template')->where('notice_type', 'overdue_final')->where('channel', 'sms')->update(['is_active' => 1]);
        $c = $this->overdueLoan(['phone' => 'ask at desk'], 'sms', 25);
        $this->run_();

        $sent = DB::table('library_overdue_notice_log')->where('checkout_id', $c)->where('status', 'sent')->first();
        $this->assertSame('overdue_2', $sent->notice_type);
        $this->assertSame('email', $sent->channel);

        // Never steps back below a tier already handled.
        $this->run_();
        $this->assertSame(1, DB::table('library_overdue_notice_log')->where('checkout_id', $c)->where('status', 'sent')->count());
    }

    public function test_print_run_keeps_a_loan_flagged_overdue(): void
    {
        $this->activate('print');
        $c = $this->overdueLoan(['address' => '1 Main Road'], 'print');
        $this->run_();
        DB::table('library_checkout')->where('id', $c)->update(['status' => 'overdue']);

        $result = app(LibraryNoticePrintService::class)->createRun(1);

        $this->assertSame(1, $result['included']);
        $this->assertSame(0, $result['withdrawn']);
        $this->assertSame(['print:batched'], $this->logs($c));
    }

    public function test_print_run_keeps_only_the_highest_queued_tier_per_loan(): void
    {
        $this->activate('print');
        $c = $this->overdueLoan(['address' => '1 Main Road'], 'print', 8);
        DB::table('library_notice_template')->where('channel', 'email')->update(['is_active' => 0]);
        DB::table('library_notice_template')->where('channel', 'print')->where('notice_type', 'overdue_2')->update(['trigger_days_overdue' => 30]);
        DB::table('library_notice_template')->where('channel', 'print')->where('notice_type', 'overdue_final')->update(['trigger_days_overdue' => 30]);
        $this->run_();
        DB::table('library_notice_template')->where('channel', 'print')->where('notice_type', 'overdue_2')->update(['trigger_days_overdue' => 7]);
        $this->run_();

        $result = app(LibraryNoticePrintService::class)->createRun(1);

        $this->assertSame(1, $result['included']);
        $this->assertSame(1, $result['superseded']);
        $this->assertSame('overdue_2', DB::table('library_notice_print_item')->where('checkout_id', $c)->value('notice_type'));
        $this->assertSame(
            'withdrawn: superseded by a later notice for this loan',
            DB::table('library_overdue_notice_log')->where('checkout_id', $c)->where('notice_type', 'overdue_1')->value('error_message')
        );
    }

    public function test_print_notice_states_when_its_figures_were_calculated(): void
    {
        $html = view('ahg-library::notices.print-run-pdf', ['items' => [(object) [
            'recipient_name' => 'A', 'postal_address' => null, 'subject' => 'S', 'body' => 'B', 'created_at' => '2026-11-02 06:00:00',
        ]]])->render();
        $this->assertStringContainsString('as calculated on 2026-11-02', $html);
    }

    public function test_unusable_phone_skips_once_across_runs(): void
    {
        $this->activate('sms');
        $c = $this->overdueLoan(['phone' => 'ask at desk', 'email' => null], 'sms');
        $this->run_();
        $this->run_();
        $this->assertSame(['sms:skipped', 'email:skipped'], $this->logs($c));
    }

    public function test_dry_run_sends_and_queues_nothing(): void
    {
        $this->activate('sms');
        $this->activate('print');
        $this->overdueLoan([], 'sms');
        $this->overdueLoan([], 'print');
        $this->assertSame(2, $this->run_(true)['skipped']);
        $this->assertSame([], $this->sms->sent);
        $this->assertSame(0, DB::table('library_notice_print_item')->count());
    }

    public function test_print_is_queued_not_sent_and_not_requeued(): void
    {
        $this->activate('print');
        $c = $this->overdueLoan(['address' => "1 Main Road\nTown"], 'print');
        $this->assertSame(1, $this->run_()['queued']);
        $this->assertSame(0, $this->run_()['queued']);
        $this->assertSame(['print:queued'], $this->logs($c));
        $this->assertNull(DB::table('library_overdue_notice_log')->where('checkout_id', $c)->value('sent_at'));
    }

    public function test_print_run_batches_live_loans_and_withdraws_returned_ones(): void
    {
        $this->activate('print');
        $live = $this->overdueLoan(['address' => '1 Main Road'], 'print');
        $returned = $this->overdueLoan([], 'print');
        $this->run_();
        DB::table('library_checkout')->where('id', $returned)->update(['status' => 'returned']);

        $result = app(LibraryNoticePrintService::class)->createRun(1);

        $this->assertSame(1, $result['included']);
        $this->assertSame(1, $result['withdrawn']);
        $this->assertSame(['print:batched'], $this->logs($live));
        $this->assertSame(['print:skipped'], $this->logs($returned));
        $this->assertSame(0, app(LibraryNoticePrintService::class)->queuedCount());

        $pdf = app(LibraryNoticePrintService::class)->renderPdf($result['batch_id']);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_pdf_escapes_patron_supplied_markup(): void
    {
        $html = view('ahg-library::notices.print-run-pdf', ['items' => [(object) [
            'recipient_name' => '<b>x</b>', 'postal_address' => '<img src=http://evil.test/a>',
            'subject' => 'S', 'body' => '<script>1</script>',
        ]]])->render();
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_preference_validation_rejects_unknown_channel(): void
    {
        $this->overdueLoan();
        $patron = (int) DB::table('library_patron')->value('id');
        $controller = app(NoticeChannelController::class);

        try {
            $controller->updatePatron(Request::create('/x', 'PUT', ['channel' => 'carrier-pigeon']), $patron);
            $this->fail('Unknown channel was accepted');
        } catch (ValidationException) {
            $this->assertNull(app(LibraryNoticeChannelService::class)->preferenceFor($patron));
        }

        $controller->updatePatron(Request::create('/x', 'PUT', ['channel' => 'sms']), $patron);
        $this->assertSame('sms', app(LibraryNoticeChannelService::class)->preferenceFor($patron));
        $controller->updatePatron(Request::create('/x', 'PUT', ['channel' => '']), $patron);
        $this->assertNull(app(LibraryNoticeChannelService::class)->preferenceFor($patron));
    }

    public function test_every_route_requires_admin_and_writes_require_acl(): void
    {
        foreach (['index', 'run-create', 'run-pdf', 'patron', 'patron-update'] as $name) {
            $mw = Route::getRoutes()->getByName("library.notice-channel.{$name}")->gatherMiddleware();
            $this->assertContains('auth', $mw, $name);
            $this->assertContains('admin', $mw, $name);
        }
        foreach (['run-create', 'run-pdf', 'patron-update'] as $name) {
            $this->assertContains('acl:update', Route::getRoutes()->getByName("library.notice-channel.{$name}")->gatherMiddleware(), $name);
        }
    }

    public function test_staff_pages_render_with_real_data(): void
    {
        $this->withoutMiddleware();
        $this->withoutExceptionHandling([\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class]);
        // The real theme layout reads core tables this SQLite schema does not have.
        \Illuminate\Support\Facades\View::replaceNamespace('theme', dirname(__DIR__) . '/__fixtures__/notice-theme');
        \Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
        $this->activate('print');
        $this->overdueLoan(['address' => '1 Main Road'], 'print');
        $this->run_();
        $run = app(LibraryNoticePrintService::class)->createRun(1);
        $patron = (int) DB::table('library_patron')->value('id');

        $this->get('/library-manage/notice-channels')->assertOk()->assertSee('In a print run');
        $this->get("/library-manage/notice-channels/patron/{$patron}")->assertOk()->assertSee('Preferred channel');
        $this->get("/library-manage/notice-channels/print-runs/{$run['batch_id']}/pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/library-manage/notice-channels/print-runs/999/pdf')->assertNotFound();
    }

    public function test_seeded_sms_and_print_templates_are_inactive(): void
    {
        $this->assertSame(0, DB::table('library_notice_template')->whereIn('channel', ['sms', 'print'])->where('is_active', 1)->count());
        $this->assertSame(6, DB::table('library_notice_template')->whereIn('channel', ['sms', 'print'])->count());
    }
}
