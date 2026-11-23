<?php

/**
 * IllRoutingTest - supplier rota, failover and inbound EDI (#1475).
 *
 * Runs on both table shapes: the package migration and the core schema that
 * installed databases actually have. Nothing is transmitted.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Models\IllRequest;
use AhgLibrary\Models\TradingPartner;
use AhgLibrary\Services\EdiAdapter;
use AhgLibrary\Services\EdiDecoderService;
use AhgLibrary\Services\IllInboundEdiService;
use AhgLibrary\Services\IllRoutingService;
use AhgLibrary\Services\LibraryIllService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IllRoutingTest extends LibraryFeatureTestCase
{
    protected LibraryIllService $ill;
    protected IllRoutingService $routing;
    protected IllInboundEdiService $inbound;

    protected function setUp(): void
    {
        $this->migrationFiles[] = '2026_11_22_100000_create_library_ill_routing_tables.php';
        parent::setUp();

        Schema::create('library_trading_partner', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('vendor_id')->nullable();
            $t->string('edi_partner_code', 20)->unique();
            $t->string('edi_type', 20)->default('CUSTOM');
            $t->string('message_profile', 20)->default('CUSTOM');
            $t->string('endpoint_type', 20)->default('MANUAL');
            $t->text('endpoint_config')->nullable();
            $t->string('outbound_directory')->default('/outbox/');
            $t->string('inbound_directory')->default('/inbox/');
            $t->boolean('acknowledgement_required')->default(true);
            $t->boolean('test_mode')->default(true);
            $t->timestamp('last_inbound_at')->nullable();
            $t->timestamp('last_outbound_at')->nullable();
            $t->timestamp('last_error_at')->nullable();
            $t->text('last_error_message')->nullable();
            $t->boolean('is_active')->default(true);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('ahg_settings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('setting_key', 100)->unique();
            $t->text('setting_value')->nullable();
            $t->string('setting_group', 50)->default('general');
            $t->timestamp('updated_at')->nullable();
        });
        AhgSettingsService::clearCache();

        $this->useServices();
    }

    protected function tearDown(): void
    {
        AhgSettingsService::clearCache();
        parent::tearDown();
    }

    private function useServices(?IllRoutingService $routing = null): void
    {
        $this->ill = new LibraryIllService();
        $this->routing = $routing ?? $this->failingFor([]);
        $this->inbound = new IllInboundEdiService(new EdiDecoderService(), $this->ill, $this->routing);
    }

    private function useCoreSchema(): void
    {
        Schema::drop('library_ill_request');
        Schema::create('library_ill_request', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('request_number', 50)->unique();
            $t->string('direction', 20);
            $t->unsignedBigInteger('patron_id')->nullable();
            $t->string('partner_library', 255);
            $t->string('title', 500);
            $t->string('author', 255)->nullable();
            $t->string('isbn', 17)->nullable();
            $t->string('issn', 9)->nullable();
            $t->string('publication_year', 10)->nullable();
            $t->string('pages', 50)->nullable();
            $t->string('status', 30)->default('requested');
            $t->date('request_date');
            $t->date('due_date')->nullable();
            $t->text('notes')->nullable();
            $t->string('request_type', 30)->default('BORROW');
            $t->string('borrowing_protocol', 20)->default('AARC');
            $t->string('material_type', 30)->default('BOOK');
            $t->text('responder_note')->nullable();
            $t->string('edi_message_id', 50)->nullable();
            $t->unsignedBigInteger('trading_partner_id')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->string('closed_reason', 200)->nullable();
            $t->timestamps();
        });
        $this->useServices();
    }

    private function supplier(string $code, int $priority = 100, array $entry = [], array $partner = []): int
    {
        $id = (int) DB::table('library_trading_partner')->insertGetId($partner + [
            'edi_partner_code' => $code, 'test_mode' => 0, 'is_active' => 1,
        ]);
        DB::table('library_ill_rota_entry')->insert($entry + [
            'trading_partner_id' => $id, 'priority' => $priority, 'timeout_hours' => 24, 'is_active' => 1,
        ]);

        return $id;
    }

    private function request(array $data = []): int
    {
        return $this->ill->create($data + ['type' => 'borrow', 'title' => 'A Book', 'library_name' => '']);
    }

    private function partner(int $id): TradingPartner
    {
        return TradingPartner::findOrFail($id);
    }

    private function reply(int $requestId, string $status, array $extra = []): string
    {
        return json_encode(['ill_number' => $this->ill->get($requestId)->ill_number, 'status' => $status] + $extra);
    }

    private function attempt(int $requestId, int $partnerId): ?object
    {
        return DB::table('library_ill_routing_attempt')
            ->where('ill_request_id', $requestId)->where('trading_partner_id', $partnerId)->first();
    }

    private function autoOn(): void
    {
        AhgSettingsService::set(IllRoutingService::SETTING_AUTO, '1', 'library');
    }

    // ── Ranking ─────────────────────────────────────────────────────────

    public function test_routes_to_lowest_priority_number_first(): void
    {
        $this->supplier('SECOND', 20);
        $first = $this->supplier('FIRST', 10);
        $id = $this->request();

        $result = $this->routing->route($id);

        $this->assertSame('sent', $result['outcome']);
        $this->assertSame('FIRST', $result['partner']);
        $row = $this->ill->get($id);
        $this->assertSame('requested', $row->status);
        $this->assertSame($first, (int) $row->trading_partner_id);
    }

    public function test_equal_priority_prefers_better_fill_rate(): void
    {
        $poor = $this->supplier('POOR', 10);
        $good = $this->supplier('GOOD', 10);
        foreach ([[$poor, 'declined'], [$good, 'accepted']] as $i => [$partner, $status]) {
            DB::table('library_ill_routing_attempt')->insert([
                'ill_request_id' => 900 + $i, 'trading_partner_id' => $partner, 'status' => $status,
                'sent_at' => now()->subDay(), 'created_at' => now()->subDay(),
            ]);
        }

        $this->assertSame('GOOD', $this->routing->route($this->request())['partner']);
    }

    public function test_equal_partners_rotate_to_least_recently_used(): void
    {
        $a = $this->supplier('AAA', 10);
        $this->supplier('BBB', 10);
        DB::table('library_ill_routing_attempt')->insert([
            'ill_request_id' => 901, 'trading_partner_id' => $a, 'status' => 'withdrawn',
            'sent_at' => now()->subHour(), 'created_at' => now()->subHour(),
        ]);

        $this->assertSame('BBB', $this->routing->route($this->request())['partner']);
    }

    public function test_material_filter_and_inactive_partners_are_skipped(): void
    {
        $this->supplier('JOURNALS', 1, ['material_types' => 'JOURNAL']);
        $this->supplier('OFFLINE', 2, [], ['is_active' => 0]);
        $this->supplier('BENCHED', 3, ['is_active' => 0]);
        $this->supplier('BOOKS', 4, ['material_types' => 'book, thesis']);

        $this->assertSame('BOOKS', $this->routing->route($this->request())['partner']);
    }

    // ── Guards ──────────────────────────────────────────────────────────

    public function test_second_route_does_not_ask_another_library(): void
    {
        $this->supplier('A', 1);
        $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);

        $this->assertSame('already_open', $this->routing->route($id)['outcome']);
        $this->assertSame(1, DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->count());
    }

    public function test_lending_and_manually_sent_requests_are_not_routed(): void
    {
        $this->supplier('A', 1);
        $lend = $this->request(['type' => 'lend']);
        $manual = $this->request();
        $this->ill->transitionTo($manual, 'requested');

        $this->assertSame('not_routable', $this->routing->route($lend)['outcome']);
        $this->assertSame('not_routable', $this->routing->route($manual)['outcome']);
    }

    public function test_no_supplier_leaves_request_pending(): void
    {
        $id = $this->request();

        $this->assertSame('no_supplier', $this->routing->route($id)['outcome']);
        $this->assertSame('pending', $this->ill->get($id)->status);
    }

    // ── Decline ─────────────────────────────────────────────────────────

    public function test_decline_escalates_to_next_supplier_when_auto_on(): void
    {
        $this->autoOn();
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);

        $result = $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));

        $this->assertSame('applied', $result['outcome']);
        $this->assertSame('declined', $this->attempt($id, $a)->status);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
        $this->assertSame($b, (int) $this->ill->get($id)->trading_partner_id);
        $this->assertSame('requested', $this->ill->get($id)->status);
    }

    public function test_decline_with_auto_off_waits_for_staff(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);

        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));

        $this->assertNull($this->attempt($id, $b));
        $this->assertSame('sent', $this->routing->route($id)['outcome']);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
    }

    public function test_last_decline_marks_request_unfulfilled(): void
    {
        $this->autoOn();
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);

        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));

        $row = $this->ill->get($id);
        $this->assertSame('unfulfilled', $row->status);
        $this->assertNotNull($row->closed_at);
    }

    // ── Timeout ─────────────────────────────────────────────────────────

    public function test_timeout_fails_over_to_next_supplier(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);

        $this->assertSame(0, $this->routing->sweepTimeouts()['timed_out']);

        $this->travel(25)->hours();
        $summary = $this->routing->sweepTimeouts();

        $this->assertSame(1, $summary['timed_out']);
        $this->assertSame(1, $summary['rerouted']);
        $this->assertSame('timed_out', $this->attempt($id, $a)->status);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
    }

    public function test_timeout_dry_run_changes_nothing(): void
    {
        $a = $this->supplier('A', 1);
        $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);
        $this->travel(25)->hours();

        $this->assertSame(1, $this->routing->sweepTimeouts(true)['timed_out']);
        $this->assertSame('sent', $this->attempt($id, $a)->status);
        $this->assertSame(1, DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->count());
    }

    public function test_timeout_on_cancelled_request_withdraws_without_rerouting(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);
        $this->ill->transitionTo($id, 'cancelled');
        $this->travel(25)->hours();

        $this->routing->sweepTimeouts();

        $this->assertSame('withdrawn', $this->attempt($id, $a)->status);
        $this->assertNull($this->attempt($id, $b));
    }

    public function test_command_does_nothing_while_auto_is_off(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $this->travel(25)->hours();

        $this->artisan('ahg:library-ill-route-timeouts')->assertExitCode(0);

        $this->assertSame('sent', $this->attempt($id, $a)->status);
    }

    // ── Send failure ────────────────────────────────────────────────────

    private function failingFor(array $codes): IllRoutingService
    {
        return new class($this->ill, $codes) extends IllRoutingService {
            public array $sent = [];

            public function __construct(LibraryIllService $ill, public array $failing)
            {
                parent::__construct($ill);
            }

            protected function transmit(TradingPartner $partner, object $ill): array
            {
                $this->sent[] = $partner->edi_partner_code;

                return in_array($partner->edi_partner_code, $this->failing, true)
                    ? ['ok' => false, 'message' => 'unreachable']
                    : ['ok' => true, 'message' => 'ok', 'edi_message_id' => 'M-' . $partner->edi_partner_code];
            }
        };
    }

    public function test_send_failure_moves_to_next_supplier(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $this->useServices($this->failingFor(['A']));
        $id = $this->request();

        $this->assertSame('B', $this->routing->route($id)['partner']);
        $this->assertSame('send_failed', $this->attempt($id, $a)->status);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
    }

    public function test_failed_sends_stop_after_the_limit(): void
    {
        $a = $this->supplier('A', 1);
        $routing = $this->failingFor(['A']);
        $this->useServices($routing);
        $id = $this->request();

        $this->assertSame('needs_attention', $this->routing->route($id)['outcome']);
        $this->routing->sweepTimeouts();
        $this->routing->sweepTimeouts();
        $this->routing->sweepTimeouts();

        $this->assertSame(3, (int) $this->attempt($id, $a)->send_failures);
        $this->assertCount(3, $routing->sent);
        // An outage must not close a request no library ever saw.
        $this->assertSame('unreachable', $this->routing->route($id)['outcome']);
        $this->assertSame('pending', $this->ill->get($id)->status);
        $this->assertSame($id, $this->routing->attentionList()->first()->id);

        $this->assertSame('exhausted', $this->routing->closeUnreachable($id)['outcome']);
        $row = $this->ill->get($id);
        $this->assertSame('unfulfilled', $row->status);
        $this->assertNotNull($row->closed_at);
    }

    public function test_retry_gives_an_unreachable_request_fresh_tries(): void
    {
        $a = $this->supplier('A', 1);
        $routing = $this->failingFor(['A']);
        $this->useServices($routing);
        $id = $this->request();
        $this->routing->route($id);
        $this->routing->sweepTimeouts();
        $this->routing->sweepTimeouts();
        $this->assertSame('unreachable', $this->routing->route($id)['outcome']);

        $routing->failing = [];

        $this->assertSame('sent', $this->routing->retryFailed($id)['outcome']);
        $this->assertSame('requested', $this->ill->get($id)->status);
        $this->assertSame('sent', $this->attempt($id, $a)->status);
    }

    public function test_failed_partner_that_left_the_rota_does_not_block_closure(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $routing = $this->failingFor(['A']);
        $this->useServices($routing);
        $id = $this->request();
        $this->routing->route($id);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
        $this->inbound->process($this->partner($b), $this->reply($id, 'unfulfilled'));

        DB::table('library_trading_partner')->where('id', $a)->update(['is_active' => 0]);

        $this->assertSame('exhausted', $this->routing->route($id)['outcome']);
        $this->assertSame('unfulfilled', $this->ill->get($id)->status);
        $this->assertSame(1, (int) $this->attempt($id, $a)->send_failures);
    }

    // ── Transport honesty ───────────────────────────────────────────────

    public function test_test_mode_partner_is_never_treated_as_sent(): void
    {
        $this->supplier('TESTER', 1, [], ['test_mode' => 1]);
        $live = $this->supplier('LIVE', 2);
        $id = $this->request();

        $this->assertSame('LIVE', $this->routing->route($id)['partner']);
        $this->assertSame($live, (int) $this->ill->get($id)->trading_partner_id);
        $this->assertSame(1, DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->count());
        $this->assertSame('TESTER', $this->routing->skippedSuppliers()->first()->edi_partner_code);
    }

    public function test_only_test_mode_partners_leaves_request_pending(): void
    {
        $this->supplier('TESTER', 1, [], ['test_mode' => 1]);
        $this->useServices(new IllRoutingService($this->ill));
        $id = $this->request();

        $this->assertSame('no_supplier', $this->routing->route($id)['outcome']);
        $this->assertSame('pending', $this->ill->get($id)->status);
        $this->assertSame(0, DB::table('library_ill_routing_attempt')->count());
    }

    public function test_partner_switched_to_test_mode_mid_send_is_not_recorded_as_sent(): void
    {
        $a = $this->supplier('A', 1);
        $routing = new class($this->ill) extends IllRoutingService {
            protected function transmit(TradingPartner $partner, object $ill): array
            {
                return ['ok' => true, 'message' => 'TEST', 'edi_message_id' => 'TEST-1', 'test_mode' => true];
            }
        };
        $this->useServices($routing);
        $id = $this->request();

        $this->routing->route($id);

        $this->assertSame('send_failed', $this->attempt($id, $a)->status);
        $this->assertSame('pending', $this->ill->get($id)->status);
    }

    public function test_sftp_partner_is_skipped_without_the_library(): void
    {
        if (class_exists(\phpseclib3\Net\SFTP::class)) {
            $this->markTestSkipped('phpseclib3 is installed here.');
        }
        $this->supplier('SFTP', 1, [], ['endpoint_type' => 'SFTP']);
        $this->supplier('HTTP', 2, [], ['endpoint_type' => 'HTTP_HTTPS']);

        $this->assertSame('HTTP', $this->routing->route($this->request())['partner']);
        $this->assertStringContainsString('SFTP', $this->routing->skippedSuppliers()->first()->reason);
    }

    public function test_manual_transport_writes_under_the_configured_path(): void
    {
        $root = sys_get_temp_dir() . '/ill-edi-' . bin2hex(random_bytes(4));
        config()->set('heratio.ill_edi_outbound_path', $root);
        $partner = $this->partner($this->supplier('MAN', 1, [], ['outbound_directory' => '/outbox/']));

        try {
            $result = (new EdiAdapter($partner))->sendIllRequest(new IllRequest(['ill_number' => 'ILL-1', 'title' => 'T']));

            $this->assertTrue($result['ok']);
            $this->assertCount(1, glob($root . '/outbox/*.edi'));
        } finally {
            array_map('unlink', glob($root . '/outbox/*.edi') ?: []);
            @rmdir($root . '/outbox');
            @rmdir($root);
        }
    }

    public function test_manual_transport_reports_an_unwritable_path_and_refuses_traversal(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ill');
        config()->set('heratio.ill_edi_outbound_path', $file);
        $blocked = $this->partner($this->supplier('BLOCKED', 1, [], ['outbound_directory' => '/outbox/']));
        $escaping = $this->partner($this->supplier('ESCAPE', 2, [], ['outbound_directory' => '../../etc']));
        $request = new IllRequest(['ill_number' => 'ILL-2', 'title' => 'T']);

        try {
            $this->assertFalse((new EdiAdapter($blocked))->sendIllRequest($request)['ok']);
            $this->assertFalse((new EdiAdapter($escaping))->sendIllRequest($request)['ok']);
        } finally {
            unlink($file);
        }
    }

    // ── Staff recovery ──────────────────────────────────────────────────

    public function test_staff_can_stop_waiting_and_move_to_next_supplier(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);
        $this->assertSame('already_open', $this->routing->route($id)['outcome']);

        $this->assertSame('withdrawn', $this->routing->stopWaiting($id)['outcome']);
        $this->assertSame('withdrawn', $this->attempt($id, $a)->status);

        $this->assertSame('B', $this->routing->route($id)['partner']);
        $this->assertSame('stale', $this->inbound->process($this->partner($a), $this->reply($id, 'shipped'))['outcome']);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
    }

    public function test_stopping_after_the_deadline_counts_as_a_timeout(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $this->travel(25)->hours();

        $this->routing->stopWaiting($id);

        $this->assertSame('timed_out', $this->attempt($id, $a)->status);
    }

    public function test_last_decline_with_auto_off_offers_the_exhausted_action(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));

        $state = $this->routing->routingState($this->ill->get($id), $this->routing->attemptsFor($id));

        $this->assertTrue($state['can_exhaust']);
        $this->assertSame('exhausted', $this->routing->route($id)['outcome']);
        $this->assertSame('unfulfilled', $this->ill->get($id)->status);
    }

    public function test_sweep_picks_up_declines_recorded_while_auto_was_off(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);
        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));
        $this->assertNull($this->attempt($id, $b));

        $this->assertSame(1, $this->routing->sweepTimeouts()['retried']);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
    }

    public function test_sweep_closes_a_request_whose_suppliers_all_declined(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));

        $this->assertSame(1, $this->routing->sweepTimeouts()['exhausted']);
        $this->assertSame('unfulfilled', $this->ill->get($id)->status);
    }

    public function test_accepted_supplier_past_deadline_is_flagged_not_rerouted(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);
        $this->inbound->process($this->partner($a), $this->reply($id, 'will supply'));
        $this->assertNotNull($this->attempt($id, $a)->expires_at);

        $this->travel(15)->days();
        $summary = $this->routing->sweepTimeouts();

        $this->assertSame(1, $summary['overdue_accepted']);
        $this->assertSame('accepted', $this->attempt($id, $a)->status);
        $this->assertNull($this->attempt($id, $b));
        $this->assertSame($id, $this->routing->attentionList()->first()->id);
        $this->assertTrue($this->routing->routingState($this->ill->get($id), $this->routing->attemptsFor($id))['overdue']);
    }

    public function test_shipping_clears_the_fulfilment_deadline(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $this->inbound->process($this->partner($a), $this->reply($id, 'will supply'));
        $this->inbound->process($this->partner($a), $this->reply($id, 'shipped'));

        $this->assertNull($this->attempt($id, $a)->expires_at);
        $this->travel(15)->days();
        $this->assertSame(0, $this->routing->sweepTimeouts()['overdue_accepted']);
    }

    // ── Hand sends ──────────────────────────────────────────────────────

    public function test_hand_send_after_failed_routing_takes_request_off_the_rota(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2, ['is_active' => 0]);
        $routing = $this->failingFor(['A']);
        $this->useServices($routing);
        $id = $this->request();
        $this->routing->route($id);

        $claim = $this->routing->beginHandSend($id, $b);
        $this->routing->finishHandSend($claim, true, 'HAND-1');
        $this->ill->transitionTo($id, 'requested');
        $routing->sent = [];

        $this->assertSame('not_routable', $this->routing->route($id)['outcome']);
        $this->routing->sweepTimeouts();

        $this->assertSame([], $routing->sent);
        $this->assertSame('manual', $this->attempt($id, $b)->status);
        $this->assertSame('send_failed', $this->attempt($id, $a)->status);
    }

    public function test_hand_send_is_refused_while_a_rota_supplier_is_live(): void
    {
        $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);

        $this->assertNull($this->routing->beginHandSend($id, $b));
        $this->assertNull($this->attempt($id, $b));
    }

    public function test_failed_hand_send_restores_the_rota(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $this->useServices($this->failingFor(['A', 'B']));
        $id = $this->request();
        $this->routing->route($id);
        $before = $this->attempt($id, $a);

        $this->routing->finishHandSend($this->routing->beginHandSend($id, $a), false);
        $new = $this->routing->beginHandSend($id, $this->supplier('C', 3));
        $this->routing->finishHandSend($new, false);

        $this->assertSame($before->status, $this->attempt($id, $a)->status);
        $this->assertSame(2, DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->count());
        $this->assertSame('send_failed', $this->attempt($id, $b)->status);
    }

    // ── Inbound ─────────────────────────────────────────────────────────

    public function test_accept_then_ship_stores_due_date(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);

        $this->inbound->process($this->partner($a), $this->reply($id, 'will supply'));
        $this->assertSame('accepted', $this->attempt($id, $a)->status);

        $result = $this->inbound->process($this->partner($a), $this->reply($id, 'shipped', ['due_date' => '2027-01-15', 'note' => 'Sent by courier']));

        $this->assertSame('applied', $result['outcome']);
        $row = $this->ill->get($id);
        $this->assertSame('shipped', $row->status);
        $this->assertStringStartsWith('2027-01-15', (string) $row->due_date);
        $this->assertSame('Sent by courier', $row->responder_note);
    }

    public function test_real_edifact_reply_advances_request(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $number = $this->ill->get($id)->ill_number;
        $raw = "UNB+UNOC:3+A:ZZZ+HERATIO:ZZZ+260602+MR1'UNH+MR1+ILLIC:24:3:UN'BGM+34+{$number}+AC'DTM+261:270115:102'UNT+4+MR1'UNZ+1+MR1'";

        $this->assertSame('applied', $this->inbound->process($this->partner($a), $raw)['outcome']);
        $this->assertSame('shipped', $this->ill->get($id)->status);
    }

    public function test_real_x12_rejection_is_a_decline(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $number = $this->ill->get($id)->ill_number;
        $raw = "ISA*00*          *00*          *ZZ*A              *ZZ*HERATIO        *260602*1200*U*00401*000000001*0*P*>~ST*855*0001~BAK*00*RJ*{$number}*20260602~SE*3*0001~IEA*1*000000001~";

        $this->inbound->process($this->partner($a), $raw);

        $this->assertSame('declined', $this->attempt($id, $a)->status);
    }

    public function test_reply_matches_by_message_reference_when_number_missing(): void
    {
        $a = $this->supplier('A', 1);
        $this->useServices($this->failingFor([]));
        $id = $this->request();
        $this->routing->route($id);

        $result = $this->inbound->process($this->partner($a), json_encode(['msg_ref' => 'M-A', 'status' => 'shipped']));

        $this->assertSame('applied', $result['outcome']);
        $this->assertSame('shipped', $this->ill->get($id)->status);
    }

    public function test_partner_cannot_touch_a_request_it_was_not_sent(): void
    {
        $a = $this->supplier('A', 1);
        $this->supplier('B', 2);
        $intruder = $this->supplier('INTRUDER', 99, ['is_active' => 0]);
        $id = $this->request();
        $this->routing->route($id);

        $result = $this->inbound->process($this->partner($intruder), $this->reply($id, 'shipped'));

        $this->assertSame('no_match', $result['outcome']);
        $this->assertSame('requested', $this->ill->get($id)->status);
        $this->assertSame('sent', $this->attempt($id, $a)->status);
    }

    public function test_late_reply_from_timed_out_supplier_is_not_applied(): void
    {
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $id = $this->request();
        $this->routing->route($id);
        $this->travel(25)->hours();
        $this->routing->sweepTimeouts();

        $result = $this->inbound->process($this->partner($a), $this->reply($id, 'shipped'));

        $this->assertSame('stale', $result['outcome']);
        $this->assertSame('requested', $this->ill->get($id)->status);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
    }

    public function test_identical_payload_is_processed_once(): void
    {
        $this->autoOn();
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);
        $this->supplier('C', 3);
        $id = $this->request();
        $this->routing->route($id);
        $raw = $this->reply($id, 'unfulfilled');

        $this->inbound->process($this->partner($a), $raw);
        $second = $this->inbound->process($this->partner($a), $raw);

        $this->assertTrue($second['duplicate']);
        $this->assertSame('sent', $this->attempt($id, $b)->status);
        $this->assertSame(2, DB::table('library_ill_routing_attempt')->where('ill_request_id', $id)->count());
    }

    public function test_reply_pasted_on_wrong_request_can_be_applied_on_the_right_one(): void
    {
        $a = $this->supplier('A', 1);
        $first = $this->request();
        $second = $this->request();
        $this->routing->route($first);
        $this->routing->route($second);
        $raw = $this->reply($second, 'shipped');

        $this->assertSame('no_match', $this->inbound->process($this->partner($a), $raw, $first)['outcome']);
        $result = $this->inbound->process($this->partner($a), $raw, $second);

        $this->assertSame('applied', $result['outcome']);
        $this->assertFalse($result['duplicate']);
        $this->assertSame('shipped', $this->ill->get($second)->status);
        $this->assertSame(1, DB::table('library_ill_edi_inbound')->count());
    }

    public function test_decline_that_arrived_too_early_can_be_resent(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        DB::table('library_ill_request')->where('id', $id)->update(['status' => 'pending']);
        $raw = $this->reply($id, 'unfulfilled');

        $this->assertSame('invalid_transition', $this->inbound->process($this->partner($a), $raw)['outcome']);
        DB::table('library_ill_request')->where('id', $id)->update(['status' => 'requested']);

        $this->assertSame('applied', $this->inbound->process($this->partner($a), $raw)['outcome']);
        $this->assertSame('declined', $this->attempt($id, $a)->status);
    }

    public function test_supplier_cannot_claim_we_received_the_item(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $this->inbound->process($this->partner($a), $this->reply($id, 'shipped'));

        $result = $this->inbound->process($this->partner($a), $this->reply($id, 'received'));

        $this->assertSame('ignored', $result['outcome']);
        $this->assertSame('shipped', $this->ill->get($id)->status);
    }

    public function test_impossible_due_date_is_dropped(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $raw = "UNB+UNOC:3+A:ZZZ+H:ZZZ+260602+MR9'BGM+34+{$this->ill->get($id)->ill_number}+AC'DTM+261:271345:102'UNZ+1+MR9'";

        $this->inbound->process($this->partner($a), $raw);

        $this->assertNull($this->ill->get($id)->due_date);
    }

    public function test_unknown_status_is_logged_and_changes_nothing(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);

        $result = $this->inbound->process($this->partner($a), $this->reply($id, 'banana'));

        $this->assertSame('unmapped', $result['outcome']);
        $this->assertSame('requested', $this->ill->get($id)->status);
        $this->assertSame(1, DB::table('library_ill_edi_inbound')->where('outcome', 'unmapped')->count());
    }

    public function test_free_text_mentioning_a_status_does_not_move_the_request(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $number = $this->ill->get($id)->ill_number;
        $raw = "UNB+UNOC:3+A:ZZZ+H:ZZZ+260602+MR7'BGM+99+{$number}+AC'FTX+GEN+++This item cannot be shipped'UNZ+1+MR7'";

        $this->assertSame('unmapped', $this->inbound->process($this->partner($a), $raw)['outcome']);
        $this->assertSame('requested', $this->ill->get($id)->status);
    }

    public function test_staff_reply_cannot_change_a_different_request(): void
    {
        $a = $this->supplier('A', 1);
        $first = $this->request();
        $second = $this->request();
        $this->routing->route($first);
        $this->routing->route($second);

        $result = $this->inbound->process($this->partner($a), $this->reply($second, 'shipped'), $first);

        $this->assertSame('no_match', $result['outcome']);
        $this->assertSame('requested', $this->ill->get($second)->status);
    }

    public function test_failed_processing_can_be_retried(): void
    {
        $a = $this->supplier('A', 1);
        $id = $this->request();
        $this->routing->route($id);
        $raw = $this->reply($id, 'shipped');

        $broken = new class extends EdiDecoderService {
            public function decode(string $raw): array
            {
                return ['status' => 'shipped', 'ill_number' => ['not', 'a', 'string']];
            }
        };
        $failing = new IllInboundEdiService($broken, $this->ill, $this->routing);
        try {
            $failing->process($this->partner($a), $raw);
            $this->fail('Expected the malformed decode to throw');
        } catch (\Throwable) {
            // expected
        }

        $this->assertSame(0, DB::table('library_ill_edi_inbound')->count());
        $this->assertSame('applied', $this->inbound->process($this->partner($a), $raw)['outcome']);
    }

    // ── Endpoint ────────────────────────────────────────────────────────

    private function postInbound(string $raw, ?string $token)
    {
        $server = ['CONTENT_TYPE' => 'application/edifact'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        return $this->call('POST', '/api/library/ill/edi/inbound', [], [], [], $server, $raw);
    }

    public function test_endpoint_requires_a_valid_token(): void
    {
        $a = $this->supplier('A', 1, ['inbound_token_hash' => hash('sha256', 'good-token')]);
        $id = $this->request();
        $this->routing->route($id);
        $raw = $this->reply($id, 'shipped');

        $this->postInbound($raw, null)->assertStatus(401);
        $this->postInbound($raw, 'wrong')->assertStatus(401);
        $this->assertSame('requested', $this->ill->get($id)->status);

        $this->postInbound($raw, 'good-token')->assertOk()->assertJson(['ok' => true, 'outcome' => 'applied']);
        $this->assertSame('shipped', $this->ill->get($id)->status);
        $this->assertNotNull($this->partner($a)->last_inbound_at);
    }

    public function test_endpoint_refuses_token_of_deactivated_entry_and_oversize_body(): void
    {
        $this->supplier('OFF', 1, ['inbound_token_hash' => hash('sha256', 'off-token'), 'is_active' => 0]);
        $this->supplier('ON', 2, ['inbound_token_hash' => hash('sha256', 'on-token')]);

        $this->postInbound('{}', 'off-token')->assertStatus(401);
        $this->postInbound(str_repeat('x', 262145), 'on-token')->assertStatus(413);
    }

    public function test_staff_screens_require_login(): void
    {
        $this->get('/library-manage/ill-routing')->assertRedirect();
        $this->post('/library-manage/ill-requests/1/routing')->assertRedirect();
        $this->assertSame(0, DB::table('library_ill_routing_attempt')->count());
    }

    // ── Core schema ─────────────────────────────────────────────────────

    public function test_full_cycle_on_core_schema(): void
    {
        $this->useCoreSchema();
        $this->autoOn();
        $a = $this->supplier('A', 1);
        $b = $this->supplier('B', 2);

        $id = $this->request(['title' => 'Core Shape', 'requester_note' => 'Urgent']);
        $row = $this->ill->get($id);
        $this->assertSame('pending', $row->status);
        $this->assertStringStartsWith('ILL-', $row->ill_number);
        $this->assertSame('borrow', $row->type);
        $this->assertSame('borrowing', DB::table('library_ill_request')->where('id', $id)->value('direction'));
        $this->assertSame('Urgent', $row->notes);
        $this->assertNull($row->volume);
        $this->assertSame(0, $row->renewal_count);

        $this->assertSame('A', $this->routing->route($id)['partner']);
        $this->assertSame('A', $this->ill->get($id)->partner_library);

        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));
        $this->assertSame('B', $this->ill->get($id)->partner_library);

        $this->assertSame('applied', $this->inbound->process($this->partner($b), $this->reply($id, 'shipped'))['outcome']);
        $this->assertSame('shipped', $this->ill->get($id)->status);
        $this->assertGreaterThan(0, DB::table('library_ill_audit')->where('ill_number', $row->ill_number)->count());
    }

    public function test_request_list_renders_a_core_schema_row(): void
    {
        $this->useCoreSchema();
        $id = $this->request(['title' => 'Listed On Core']);

        $controller = new \AhgLibrary\Controllers\IllRequestController($this->ill, new EdiAdapter(), $this->routing);
        $view = $controller->index(\Illuminate\Http\Request::create('/library-manage/ill-requests'));
        $row = $view->getData()['requests']->first();

        $this->assertSame($id, (int) $row->id);
        $this->assertSame('borrow', $row->type);
        $this->assertStringStartsWith('ILL-', $row->ill_number);
        $this->assertNull($row->needed_by_date);

        // The real page body; the site layout needs catalogue tables this harness lacks.
        $source = file_get_contents(view()->getFinder()->find('ahg-library::circulation.ill-requests.index'));
        $body = preg_replace('#</?x-[a-z-]*layout[^>]*>#', '', $source);
        $html = \Illuminate\Support\Facades\Blade::render($body, $view->getData());
        $this->assertStringContainsString('Listed On Core', $html);
        $this->assertStringContainsString($row->ill_number, $html);
    }

    private function renderBody(string $name, array $data): string
    {
        $source = file_get_contents(view()->getFinder()->find($name));
        $body = preg_replace('#</?x-[a-z-]*layout[^>]*>#', '', $source);

        return \Illuminate\Support\Facades\Blade::render($body, $data + ['errors' => new \Illuminate\Support\ViewErrorBag()]);
    }

    private function routingController(): \AhgLibrary\Controllers\IllRoutingController
    {
        return new \AhgLibrary\Controllers\IllRoutingController($this->routing, $this->inbound, $this->ill);
    }

    public function test_routing_screen_offers_the_right_action_for_each_state(): void
    {
        $a = $this->supplier('A', 1);
        $this->supplier('TESTER', 5, [], ['test_mode' => 1]);
        $id = $this->request();

        $page = fn () => $this->renderBody('ahg-library::ill-routing.request', $this->routingController()->requestRouting($id)->getData());

        $html = $page();
        $this->assertStringContainsString('Send to next supplier', $html);
        $this->assertStringContainsString('TESTER', $html);
        $this->assertStringContainsString('test mode', $html);

        $this->routing->route($id);
        $html = $page();
        $this->assertStringContainsString('Stop waiting for this supplier', $html);
        $this->assertStringNotContainsString('Send to next supplier', $html);

        $this->inbound->process($this->partner($a), $this->reply($id, 'unfulfilled'));
        $html = $page();
        $this->assertStringContainsString('Mark unfulfilled', $html);
        $this->assertStringContainsString(route('library.ill-routing.route', $id), $html);
    }

    public function test_routing_screen_offers_retry_and_close_when_unreachable(): void
    {
        $this->supplier('A', 1);
        $this->useServices($this->failingFor(['A']));
        $id = $this->request();
        $this->routing->route($id);
        $this->routing->sweepTimeouts();
        $this->routing->sweepTimeouts();

        $html = $this->renderBody('ahg-library::ill-routing.request', $this->routingController()->requestRouting($id)->getData());

        $this->assertStringContainsString(route('library.ill-routing.retry', $id), $html);
        $this->assertStringContainsString(route('library.ill-routing.close', $id), $html);
    }

    public function test_rota_screen_lists_requests_needing_attention(): void
    {
        $this->supplier('A', 1);
        $this->useServices($this->failingFor(['A']));
        $id = $this->request();
        $this->routing->route($id);
        $this->routing->sweepTimeouts();
        $this->routing->sweepTimeouts();

        $html = $this->renderBody('ahg-library::ill-routing.index', $this->routingController()->index()->getData());

        $this->assertStringContainsString('Requests needing attention', $html);
        $this->assertStringContainsString($this->ill->get($id)->ill_number, $html);
    }

    public function test_hand_send_to_a_test_mode_partner_changes_nothing(): void
    {
        $tester = $this->supplier('TESTER', 1, [], ['test_mode' => 1]);
        $id = $this->request();
        $controller = new \AhgLibrary\Controllers\IllRequestController($this->ill, new EdiAdapter(), $this->routing);

        $controller->sendEdi(\Illuminate\Http\Request::create('/x', 'POST', ['trading_partner_id' => $tester]), $id);

        $this->assertSame('pending', $this->ill->get($id)->status);
        $this->assertNull($this->ill->get($id)->trading_partner_id);
        $this->assertNull($this->attempt($id, $tester));
    }

    public function test_hand_send_is_blocked_by_the_controller_while_a_supplier_is_live(): void
    {
        $this->supplier('A', 1);
        $b = $this->supplier('B', 2, [], ['endpoint_type' => 'MANUAL']);
        $id = $this->request();
        $this->routing->route($id);
        $controller = new \AhgLibrary\Controllers\IllRequestController($this->ill, new EdiAdapter(), $this->routing);

        $controller->sendEdi(\Illuminate\Http\Request::create('/x', 'POST', ['trading_partner_id' => $b]), $id);

        $this->assertNull($this->attempt($id, $b));
    }

    public function test_status_note_is_kept_on_core_schema(): void
    {
        $this->useCoreSchema();
        $id = $this->request();

        $this->assertTrue($this->ill->transitionTo($id, 'requested', 'Sent by post'));
        $this->assertStringContainsString('Sent by post', (string) $this->ill->get($id)->notes);
    }

    // ── Outbound message ────────────────────────────────────────────────

    public function test_outbound_message_carries_the_reference_a_reply_needs(): void
    {
        $partner = new TradingPartner(['edi_type' => 'EANCOM', 'message_profile' => 'EANCOM_S93', 'edi_partner_code' => 'A']);
        $request = new IllRequest(['ill_number' => 'ILL-20261122-0001', 'title' => 'Findable Title', 'isbn' => '9780123456789']);

        $raw = (new EdiAdapter($partner))->buildIllRequestMessage($request)['raw'];

        $this->assertStringContainsString('BGM+24+ILL-20261122-0001+AC', $raw);
        $this->assertStringContainsString('Findable Title', $raw);
        $this->assertStringContainsString('9780123456789', $raw);
    }
}
