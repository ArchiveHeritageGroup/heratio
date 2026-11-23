<?php

/**
 * LibrarySip2Test - #1475 item 2. Feeds real SIP2 messages to the handler and
 * checks both the reply and what changed in the circulation tables.
 */

namespace Tests\Feature;

use AhgCore\Services\AhgSettingsService;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\Sip2\Sip2Handler;
use AhgLibrary\Services\Sip2\Sip2Message;
use AhgLibrary\Services\Sip2\Sip2Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LibrarySip2Test extends TestCase
{
    use DatabaseTransactions;

    private const TS = '20261122    120000';

    private Sip2Handler $handler;

    private Sip2Session $session;

    private int $patronId;

    private int $copyId;

    private string $card;

    private string $barcode;

    private string $login;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['library_sip2_account', 'library_sip2_payment', 'library_patron_pin', 'library_fine'] as $t) {
            if (! Schema::hasTable($t)) {
                $this->fail("{$t} missing - load packages/ahg-library/database/install_sip2.sql");
            }
        }

        LibraryCirculationService::forgetSchemaCache();
        $this->handler = app(Sip2Handler::class);
        $this->session = new Sip2Session();

        $suffix = bin2hex(random_bytes(5));
        $this->card = 'SIP' . $suffix;
        $this->barcode = 'BC' . $suffix;
        $this->login = 'kiosk' . $suffix;

        $this->patronId = DB::table('library_patron')->insertGetId([
            'card_number' => $this->card, 'first_name' => 'Ada', 'last_name' => 'Reader',
            'membership_start' => '2026-01-01', 'max_checkouts' => 2, 'borrowing_status' => 'active',
        ]);
        DB::table('library_patron_pin')->insert([
            'patron_id' => $this->patronId, 'pin_hash' => password_hash('4321', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);
        $itemId = DB::table('library_item')->insertGetId(['information_object_id' => 0, 'material_type' => 'sip2test']);
        $this->copyId = DB::table('library_copy')->insertGetId([
            'library_item_id' => $itemId, 'copy_number' => 1, 'barcode' => $this->barcode, 'status' => 'available',
        ]);
        DB::table('library_sip2_account')->insert([
            'login' => $this->login, 'password_hash' => password_hash('terminal-secret', PASSWORD_BCRYPT, ['cost' => 4]),
            'institution_id' => 'MAIN', 'can_block' => 1, 'can_pay' => 1,
        ]);
        RateLimiter::clear('sip2:login:' . sha1($this->login));
        RateLimiter::clear('sip2:pin:' . $this->patronId);
    }

    private function send(string $message): string
    {
        return rtrim($this->handler->handle($message, $this->session), "\r");
    }

    private function signIn(): void
    {
        $this->assertSame('941', $this->send("9300CN{$this->login}|COterminal-secret|CPMAIN|"));
    }

    private function checkoutMessage(string $pin = '4321'): string
    {
        return '11NN' . self::TS . self::TS . "AOMAIN|AA{$this->card}|AB{$this->barcode}|ACx|AD{$pin}|";
    }

    private function checkinMessage(): string
    {
        return '09N' . self::TS . self::TS . "APfront|AOMAIN|AB{$this->barcode}|ACx|";
    }

    private function feeMessage(string $amount, string $txn, string $currency = 'ZAR'): string
    {
        return '37' . self::TS . '0100' . $currency . "BV{$amount}|AOMAIN|AA{$this->card}|BK{$txn}|";
    }

    private function fine(float $amount, ?int $checkoutId = null, string $date = '2026-10-01'): int
    {
        $id = DB::table('library_fine')->insertGetId([
            'patron_id' => $this->patronId, 'checkout_id' => $checkoutId, 'fine_type' => 'overdue',
            'amount' => $amount, 'currency' => 'ZAR', 'status' => 'outstanding', 'fine_date' => $date,
        ]);
        $owed = DB::table('library_fine')->where('patron_id', $this->patronId)->where('status', 'outstanding')->sum('amount');
        DB::table('library_patron')->where('id', $this->patronId)->update(['total_fines_owed' => $owed]);

        return $id;
    }

    public function test_checksum_is_verified_and_echoed(): void
    {
        $body = "9300CN{$this->login}|COterminal-secret|CPMAIN|AY3AZ";
        $reply = $this->send($body . Sip2Message::checksum($body));
        $this->assertStringStartsWith('941AY3AZ', $reply);
        $this->assertSame(Sip2Message::checksum(substr($reply, 0, -4)), substr($reply, -4));

        $this->assertSame('96', $this->send($body . '0000'));
    }

    public function test_login_rejects_wrong_password_and_locks_after_repeats(): void
    {
        $this->assertSame('96', $this->send('9900402.00'));
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('940', $this->send("9300CN{$this->login}|COwrong|CPMAIN|"));
        }
        $this->assertSame('940', $this->send("9300CN{$this->login}|COterminal-secret|CPMAIN|"));
        $this->assertNull($this->session->account);
    }

    public function test_acs_status_reports_supported_messages(): void
    {
        $this->signIn();
        $reply = $this->send('9900402.00');
        $this->assertStringStartsWith('98YYYNNN030003', $reply);
        $this->assertStringContainsString('|BXYYYYYYYYNYNNNNNN|', $reply);
    }

    public function test_patron_status_hides_personal_data_without_the_pin(): void
    {
        $this->signIn();
        $without = $this->send('23000' . self::TS . "AOMAIN|AA{$this->card}|ACx|AD9999|");
        $this->assertStringContainsString('|CQN|', $without);
        $this->assertStringNotContainsString('Reader', $without);

        $with = $this->send('23000' . self::TS . "AOMAIN|AA{$this->card}|ACx|AD4321|");
        $this->assertStringContainsString('|AEAda Reader|', $with);
        $this->assertStringContainsString('|CQY|', $with);
    }

    public function test_pin_guessing_locks_the_card(): void
    {
        $this->signIn();
        for ($i = 0; $i < 5; $i++) {
            $this->send('23000' . self::TS . "AOMAIN|AA{$this->card}|AD000{$i}|");
        }
        $reply = $this->send('23000' . self::TS . "AOMAIN|AA{$this->card}|AD4321|");
        $this->assertStringContainsString('|CQN|', $reply);
    }

    public function test_patron_information_counts_and_lists_loans(): void
    {
        $this->signIn();
        $this->send($this->checkoutMessage());
        DB::table('library_checkout')->where('copy_id', $this->copyId)->update(['due_date' => '2026-01-01']);

        $reply = $this->send('63000' . self::TS . " YY       AOMAIN|AA{$this->card}|AD4321|");
        $this->assertSame('0000', substr($reply, 37, 4));
        $this->assertSame('0001', substr($reply, 41, 4));
        $this->assertSame('0001', substr($reply, 45, 4));
        $this->assertStringContainsString("|AT{$this->barcode}|", $reply);
        $this->assertStringContainsString("|AU{$this->barcode}|", $reply);
    }

    public function test_checkout_creates_a_loan_and_a_retry_does_not_duplicate_it(): void
    {
        $this->signIn();
        $reply = $this->send($this->checkoutMessage());
        $this->assertStringStartsWith('121NUY', $reply);
        $this->assertSame('checked_out', DB::table('library_copy')->where('id', $this->copyId)->value('status'));

        $again = $this->send($this->checkoutMessage());
        $this->assertStringStartsWith('121NUY', $again);
        $this->assertStringContainsString('Already on loan', $again);
        $this->assertSame(1, DB::table('library_checkout')->where('copy_id', $this->copyId)->count());
    }

    public function test_checkout_is_refused_for_a_wrong_pin_and_for_excess_fines(): void
    {
        $this->signIn();
        $this->assertStringStartsWith('120', $this->send($this->checkoutMessage('0000')));

        $this->fine(5000.00);
        $reply = $this->send($this->checkoutMessage());
        $this->assertStringStartsWith('120', $reply);
        $this->assertStringContainsString('Outstanding fines', $reply);
        $this->assertSame(0, DB::table('library_checkout')->where('copy_id', $this->copyId)->count());
    }

    public function test_checkin_returns_the_loan_and_flags_a_waiting_hold(): void
    {
        $this->signIn();
        $this->send($this->checkoutMessage());

        $other = DB::table('library_patron')->insertGetId([
            'card_number' => $this->card . 'H', 'first_name' => 'Next', 'last_name' => 'Patron',
            'membership_start' => '2026-01-01', 'borrowing_status' => 'active',
        ]);
        $itemId = DB::table('library_copy')->where('id', $this->copyId)->value('library_item_id');
        $this->assertNotNull(app(LibraryCirculationService::class)->placeHold((int) $itemId, $other));

        $reply = $this->send($this->checkinMessage());
        $this->assertStringStartsWith('101YUY', $reply);
        $this->assertStringContainsString('|CV01|', $reply);
        $this->assertSame('returned', DB::table('library_checkout')->where('copy_id', $this->copyId)->value('status'));
        $this->assertSame('on_hold', DB::table('library_copy')->where('id', $this->copyId)->value('status'));
    }

    public function test_fee_paid_allocates_oldest_first_and_a_retry_is_not_charged_twice(): void
    {
        $this->signIn();
        $old = $this->fine(3.00, null, '2026-09-01');
        $new = $this->fine(4.00, null, '2026-10-01');

        $this->assertStringStartsWith('38Y', $this->send($this->feeMessage('5.00', 'TX1')));
        $this->assertSame('paid', DB::table('library_fine')->where('id', $old)->value('status'));
        $this->assertEquals(2.00, DB::table('library_fine')->where('id', $new)->value('paid_amount'));
        $this->assertEquals(2.00, DB::table('library_patron')->where('id', $this->patronId)->value('total_fines_owed'));

        // A new connection repeating the same transaction id takes nothing more.
        $this->session = new Sip2Session();
        $this->signIn();
        $this->assertStringContainsString('already recorded', $this->send($this->feeMessage('5.00', 'TX1')));
        $this->assertEquals(5.00, DB::table('library_patron')->where('id', $this->patronId)->value('total_fines_paid'));
        $this->assertStringStartsWith('38N', $this->send($this->feeMessage('1.00', 'TX1')));
    }

    public function test_fee_paid_refuses_overpayment_bad_amounts_and_wrong_currency(): void
    {
        $this->signIn();
        $this->fine(3.00);
        foreach ([$this->feeMessage('3.01', 'A1'), $this->feeMessage('-1', 'A2'), $this->feeMessage('0.00', 'A3'),
            $this->feeMessage('1.00', 'A4', 'XXX'), $this->feeMessage('1.00', '')] as $message) {
            $this->assertStringStartsWith('38N', $this->send($message));
        }
        $this->assertEquals(0, DB::table('library_patron')->where('id', $this->patronId)->value('total_fines_paid'));
    }

    public function test_a_paid_fine_is_not_raised_again_while_the_loan_stays_overdue(): void
    {
        $this->signIn();
        $this->send($this->checkoutMessage());
        $loan = DB::table('library_checkout')->where('copy_id', $this->copyId)->first();
        DB::table('library_checkout')->where('id', $loan->id)->update(['due_date' => date('Y-m-d', strtotime('-3 days'))]);
        DB::table('library_loan_rule')->insert([
            'branch_id' => 0, 'material_type' => 'sip2test', 'patron_type' => '*', 'loan_period_days' => 7,
            'renewal_period_days' => 7, 'max_renewals' => 1, 'fine_per_day' => 1.00, 'grace_period_days' => 0, 'is_loanable' => 1,
        ]);

        $circulation = app(LibraryCirculationService::class);
        $circulation->calculateAllOverdueFines();
        $this->assertStringStartsWith('38Y', $this->send($this->feeMessage('3.00', 'TX2')));
        $circulation->calculateAllOverdueFines();

        $this->assertSame(1, DB::table('library_fine')->where('checkout_id', $loan->id)->count());
        $this->assertSame('paid', DB::table('library_fine')->where('checkout_id', $loan->id)->value('status'));
        $this->assertEquals(0, DB::table('library_patron')->where('id', $this->patronId)->value('total_fines_owed'));

        DB::table('library_checkout')->where('id', $loan->id)->update(['due_date' => date('Y-m-d', strtotime('-5 days'))]);
        $circulation->calculateAllOverdueFines();
        $this->assertEquals(2.00, DB::table('library_patron')->where('id', $this->patronId)->value('total_fines_owed'));
    }

    public function test_block_patron_suspends_only_when_the_terminal_may(): void
    {
        $this->signIn();
        $block = '01Y' . self::TS . "AOMAIN|ALCard retained|AA{$this->card}|ACx|";

        DB::table('library_sip2_account')->where('login', $this->login)->update(['can_block' => 0]);
        $this->session = new Sip2Session();
        $this->signIn();
        $this->assertStringContainsString('not enabled', $this->send($block));
        $this->assertSame('active', DB::table('library_patron')->where('id', $this->patronId)->value('borrowing_status'));

        DB::table('library_sip2_account')->where('login', $this->login)->update(['can_block' => 1]);
        $this->session = new Sip2Session();
        $this->signIn();
        $reply = $this->send($block);
        $this->assertStringStartsWith('24', $reply);
        $this->assertStringNotContainsString('Reader', $reply);
        $this->assertSame('suspended', DB::table('library_patron')->where('id', $this->patronId)->value('borrowing_status'));
    }

    public function test_resend_repeats_the_last_reply_without_repeating_the_work(): void
    {
        $this->signIn();
        $first = $this->send($this->checkoutMessage());
        $this->assertSame($first, $this->send('97'));
        $this->assertSame(1, DB::table('library_checkout')->where('copy_id', $this->copyId)->count());
    }

    public function test_reply_fields_cannot_be_split_by_stored_data(): void
    {
        DB::table('library_patron')->where('id', $this->patronId)->update(['first_name' => "Ada|BLN\rX"]);
        $this->signIn();
        $reply = $this->send('23000' . self::TS . "AOMAIN|AA{$this->card}|AD4321|");
        $this->assertStringNotContainsString("\r", $reply);
        $this->assertStringContainsString('AEAda BLN X Reader|', $reply);
    }

    public function test_setup_command_stores_hashed_secrets_only(): void
    {
        $login = $this->login . 'b';
        $this->artisan('ahg:library-sip2-setup', ['action' => 'account', 'name' => $login, '--institution' => 'MAIN'])
            ->expectsQuestion('Terminal password', 'a-long-terminal-pass')
            ->assertExitCode(0);
        $row = DB::table('library_sip2_account')->where('login', $login)->first();
        $this->assertTrue(password_verify('a-long-terminal-pass', $row->password_hash));
        $this->assertSame(0, (int) $row->can_pay);
        $this->assertSame(0, (int) $row->can_block);

        $this->artisan('ahg:library-sip2-setup', ['action' => 'pin', 'name' => $this->card])
            ->expectsQuestion('New PIN', '123')
            ->assertExitCode(1);
        $this->artisan('ahg:library-sip2-setup', ['action' => 'pin', 'name' => $this->card])
            ->expectsQuestion('New PIN', '987654')
            ->assertExitCode(0);
        $this->assertTrue(password_verify('987654', DB::table('library_patron_pin')->where('patron_id', $this->patronId)->value('pin_hash')));
    }

    private function flagLoanOverdue(): void
    {
        DB::table('library_checkout')->where('copy_id', $this->copyId)->update(['due_date' => '2026-01-01']);
        $this->assertSame(0, Artisan::call('ahg:library-overdue-check'));
        $this->assertSame('overdue', DB::table('library_checkout')->where('copy_id', $this->copyId)->value('status'));
    }

    public function test_checkin_returns_a_loan_the_overdue_job_has_flagged(): void
    {
        $this->signIn();
        $this->send($this->checkoutMessage());
        $this->flagLoanOverdue();

        $reply = $this->send($this->checkinMessage());
        $this->assertStringStartsWith('101YUN', $reply);
        $this->assertStringNotContainsString('not on loan', $reply);
        $this->assertSame('returned', DB::table('library_checkout')->where('copy_id', $this->copyId)->value('status'));
        $this->assertSame('available', DB::table('library_copy')->where('id', $this->copyId)->value('status'));
    }

    public function test_flagged_loans_still_count_in_patron_information_and_the_loan_cap(): void
    {
        DB::table('library_patron')->where('id', $this->patronId)->update(['max_checkouts' => 1]);
        $this->signIn();
        $this->send($this->checkoutMessage());
        $this->flagLoanOverdue();

        $reply = $this->send('63000' . self::TS . " YY       AOMAIN|AA{$this->card}|AD4321|");
        $this->assertSame('Y', $reply[7], 'too many items charged');
        $this->assertSame('0001', substr($reply, 41, 4));
        $this->assertSame('0001', substr($reply, 45, 4));
        $this->assertStringContainsString("|AT{$this->barcode}|", $reply);
        $this->assertStringContainsString("|AU{$this->barcode}|", $reply);

        $second = 'BD' . bin2hex(random_bytes(5));
        DB::table('library_copy')->insert([
            'library_item_id' => DB::table('library_copy')->where('id', $this->copyId)->value('library_item_id'),
            'copy_number' => 2, 'barcode' => $second, 'status' => 'available',
        ]);
        $refused = $this->send('11NN' . self::TS . self::TS . "AOMAIN|AA{$this->card}|AB{$second}|ACx|AD4321|");
        $this->assertStringStartsWith('120', $refused);
        $this->assertStringContainsString('Loan limit reached', $refused);
    }

    public function test_disabling_a_terminal_ends_its_open_session(): void
    {
        $this->signIn();
        DB::table('library_sip2_account')->where('login', $this->login)->update(['is_active' => 0]);

        $block = '01Y' . self::TS . "AOMAIN|ALx|AA{$this->card}|ACx|";
        $this->assertStringStartsWith('96', $this->send($block));
        $this->assertNull($this->session->account);
        $this->assertStringStartsWith('96', $this->send('97'));
        $this->assertSame('active', DB::table('library_patron')->where('id', $this->patronId)->value('borrowing_status'));
    }

    public function test_a_disabled_terminal_cannot_replay_its_login(): void
    {
        $body = "9300CN{$this->login}|COterminal-secret|CPMAIN|AY3AZ";
        $login = $body . Sip2Message::checksum($body);
        $this->assertStringStartsWith('941', $this->send($login));

        DB::table('library_sip2_account')->where('login', $this->login)->update(['is_active' => 0]);

        $this->assertStringStartsWith('940', $this->send($login));
        $this->assertNull($this->session->account);
    }

    public function test_a_removed_permission_applies_to_an_open_session(): void
    {
        $this->signIn();
        DB::table('library_sip2_account')->where('login', $this->login)->update(['can_block' => 0]);

        $reply = $this->send('01Y' . self::TS . "AOMAIN|ALx|AA{$this->card}|ACx|");
        $this->assertStringContainsString('not enabled', $reply);
        $this->assertSame('active', DB::table('library_patron')->where('id', $this->patronId)->value('borrowing_status'));
    }

    public function test_a_changed_setting_applies_without_a_restart(): void
    {
        $this->signIn();
        DB::table('library_patron')->where('id', $this->patronId)->update(['total_fines_owed' => 10.00]);
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => 'library_patron_fine_threshold'], ['setting_value' => '50.00']);
        AhgSettingsService::clearCache();
        $this->assertStringStartsWith('121', $this->send($this->checkoutMessage()));
        $this->send($this->checkinMessage());

        DB::table('ahg_settings')->where('setting_key', 'library_patron_fine_threshold')->update(['setting_value' => '1.23']);
        $this->assertStringStartsWith('120', $this->send($this->checkoutMessage()));
        AhgSettingsService::clearCache();
    }

    public function test_opac_login_requires_the_pin_when_the_patron_has_one(): void
    {
        $post = fn (?string $pin) => $this->post('/opac/patron/authenticate', ['card_number' => $this->card, 'pin' => $pin]);

        $post('')->assertRedirect(route('opac.patron.login'));
        $this->assertNull(session('patron_id'));
        $post('0000')->assertRedirect(route('opac.patron.login'));
        $this->assertNull(session('patron_id'));

        $post('4321')->assertRedirect(route('opac.patron.account'));
        $this->assertSame($this->patronId, session('patron_id'));
    }

    public function test_opac_login_is_refused_when_the_patron_has_no_pin(): void
    {
        DB::table('library_patron_pin')->delete();

        $this->post('/opac/patron/authenticate', ['card_number' => $this->card, 'pin' => ''])
            ->assertRedirect(route('opac.patron.login'))
            ->assertSessionHas('error');
        $this->assertNull(session('patron_id'));
    }

    public function test_opac_login_locks_after_repeated_wrong_pins(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/opac/patron/authenticate', ['card_number' => $this->card, 'pin' => '0000']);
        }
        $this->post('/opac/patron/authenticate', ['card_number' => $this->card, 'pin' => '4321'])
            ->assertRedirect(route('opac.patron.login'));
        $this->assertNull(session('patron_id'));
        RateLimiter::clear('sip2:pin:' . $this->patronId);
    }

    public function test_feed_mode_answers_messages_from_a_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sip2');
        file_put_contents($path, "9300CN{$this->login}|COterminal-secret|CPMAIN|\r9900402.00\r");
        try {
            $this->assertSame(0, Artisan::call('ahg:library-sip2-serve', ['--feed' => $path]));
            $output = Artisan::output();
        } finally {
            unlink($path);
        }
        $this->assertStringContainsString('941', $output);
        $this->assertStringContainsString('98YYYNNN', $output);
    }
}
