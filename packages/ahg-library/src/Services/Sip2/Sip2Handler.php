<?php

/**
 * Sip2Handler - answers SIP2 messages from self-check terminals (#1475):
 * login 93, status 99, resend 97, patron status 23, patron information 63,
 * checkout 11, checkin 09, fee paid 37 and block patron 01. Every circulation
 * decision stays in LibraryCirculationService.
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Services\Sip2;

use AhgCore\Services\AhgSettingsService;
use AhgCore\Support\I18nFallback;
use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Services\LibraryPatronService;
use AhgLibrary\Support\LibrarySettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class Sip2Handler
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    /** Patron status, checkout, checkin, block, ACS status, resend, login, patron info, fee paid. */
    private const SUPPORTED = 'YYYYYYYYNYNNNNNN';

    /** Compared against when no real hash exists, so response time does not reveal which. */
    private const DUMMY_HASH = '$2y$10$XDEIntvDoHMCtNAGhFZcVuUwq.ZYpxHbm/o7SgzOkT/LHhs7hZExq';

    public function __construct(
        private LibraryCirculationService $circulation,
        private LibraryPatronService $patrons,
        private Sip2FeeLedger $ledger,
    ) {
    }

    public function handle(string $raw, Sip2Session $session): string
    {
        $message = Sip2Message::parse($raw);
        if ($message === null) {
            return Sip2Message::frame('96', null);
        }

        // The listener runs for weeks: desk changes to settings and terminal
        // rights must apply to the next message, not the next restart.
        AhgSettingsService::clearCache();
        if ($message->code !== '93' && $session->account !== null && ! $this->refreshAccount($session)) {
            return Sip2Message::frame('96', null);
        }

        if ($message->code === '97') {
            return $session->lastResponse ?? Sip2Message::frame('96', null);
        }

        // The same sequenced message again is the terminal retrying after a
        // lost reply: repeat the answer rather than repeat the transaction.
        // A login is always checked afresh, so a disabled terminal cannot replay one.
        if ($message->code !== '93' && $message->sequence !== null && $raw === $session->lastRequest && $session->lastResponse !== null) {
            return $session->lastResponse;
        }

        if ($message->code !== '93' && $session->account === null) {
            return Sip2Message::frame('96', null);
        }

        try {
            $body = match ($message->code) {
                '93' => $this->login($message, $session),
                '99' => $this->acsStatus($session->account),
                '23' => $this->patronStatus($message, $session->account),
                '63' => $this->patronInformation($message, $session->account),
                '11' => $this->checkout($message, $session->account),
                '09' => $this->checkin($message, $session->account),
                '37' => $this->feePaid($message, $session->account),
                '01' => $this->blockPatron($message, $session->account),
            };
        } catch (\Throwable $e) {
            // Class only: a query exception's text can carry card numbers.
            Log::error('SIP2 message failed', ['code' => $message->code, 'exception' => $e::class]);

            return Sip2Message::frame('96', null);
        }

        $response = Sip2Message::frame($body, $message->sequence);
        $session->lastRequest = $raw;
        $session->lastResponse = $response;

        return $response;
    }

    /** False ends the exchange. A disabled or removed terminal also loses its session and resend buffer. */
    private function refreshAccount(Sip2Session $session): bool
    {
        try {
            $account = DB::table('library_sip2_account')
                ->where('id', $session->account->id)->where('is_active', 1)->first();
        } catch (\Throwable $e) {
            Log::error('SIP2 account check failed', ['exception' => $e::class]);

            return false;
        }

        if (! $account) {
            $session->account = $session->lastRequest = $session->lastResponse = null;

            return false;
        }
        $session->account = $account;

        return true;
    }

    private function login(Sip2Message $m, Sip2Session $session): string
    {
        $session->account = null;
        $login = (string) $m->field('CN');
        $key = 'sip2:login:' . sha1($login);

        if ($m->fixed !== '00' || RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return '940';
        }

        $account = DB::table('library_sip2_account')->where('login', $login)->where('is_active', 1)->first();
        if (! password_verify((string) $m->field('CO'), $account->password_hash ?? self::DUMMY_HASH) || ! $account) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            return '940';
        }

        RateLimiter::clear($key);
        $session->account = $account;

        return '941';
    }

    private function acsStatus(object $account): string
    {
        return '98Y'
            . ($account->can_checkin ? 'Y' : 'N')
            . ($account->can_checkout ? 'Y' : 'N')
            . 'NNN' . '030' . '003' . Sip2Message::timestamp() . '2.00'
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AM', LibrarySettings::libraryName())
            . Sip2Message::f('BX', self::SUPPORTED);
    }

    private function patronStatus(Sip2Message $m, object $account, ?string $screen = null, ?bool $verified = null): string
    {
        $card = (string) $m->field('AA');
        $patron = $this->patrons->getByCardNumber($card);
        $verified ??= $patron !== null && $this->pinOk($patron, $m->field('AD'), $account);
        $show = $patron !== null && $verified;

        $body = '24' . $this->statusFlags($show ? $patron : null) . substr($m->fixed, 0, 3) . Sip2Message::timestamp()
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AA', $card)
            . Sip2Message::f('AE', $show ? $this->name($patron) : '')
            . Sip2Message::f('BL', $patron ? 'Y' : 'N')
            . Sip2Message::f('CQ', $verified ? 'Y' : 'N');

        if ($show) {
            $body .= Sip2Message::f('BH', LibrarySettings::currency())
                . Sip2Message::f('BV', number_format((float) $patron->total_fines_owed, 2, '.', ''));
        }

        return $screen !== null ? $body . Sip2Message::f('AF', $screen) : $body;
    }

    private function patronInformation(Sip2Message $m, object $account): string
    {
        $card = (string) $m->field('AA');
        $patron = $this->patrons->getByCardNumber($card);
        $verified = $patron !== null && $this->pinOk($patron, $m->field('AD'), $account);
        $show = $patron !== null && $verified;

        $charged = $overdue = $holds = [];
        $ready = 0;
        if ($show) {
            $charged = DB::table('library_checkout as c')
                ->join('library_copy as cp', 'c.copy_id', '=', 'cp.id')
                ->where('c.patron_id', $patron->id)->whereIn('c.status', LibraryCirculationService::OPEN_LOAN_STATUSES)
                ->get(['cp.barcode', 'c.due_date', 'c.status'])->all();
            $today = date('Y-m-d');
            $overdue = array_filter($charged, static fn ($r) => $r->status === 'overdue' || substr((string) $r->due_date, 0, 10) < $today);
            $holds = $this->patrons->getActiveHolds((int) $patron->id);
            $ready = count(array_filter($holds, static fn ($h) => $h->status === 'ready'));
        }

        $count = static fn (int $n) => str_pad((string) min($n, 9999), 4, '0', STR_PAD_LEFT);
        $body = '64' . $this->statusFlags($show ? $patron : null) . substr($m->fixed, 0, 3) . Sip2Message::timestamp()
            . $count($ready) . $count(count($overdue)) . $count(count($charged))
            . $count(0) . $count(0) . $count(count($holds) - $ready)
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AA', $card)
            . Sip2Message::f('AE', $show ? $this->name($patron) : '')
            . Sip2Message::f('BL', $patron ? 'Y' : 'N')
            . Sip2Message::f('CQ', $verified ? 'Y' : 'N');

        if (! $show) {
            return $body;
        }

        $body .= Sip2Message::f('BH', LibrarySettings::currency())
            . Sip2Message::f('BV', number_format((float) $patron->total_fines_owed, 2, '.', ''))
            . Sip2Message::f('BZ', $count((int) $patron->max_holds))
            . Sip2Message::f('CB', $count((int) $patron->max_checkouts));

        // Summary positions: 1 = overdue items, 2 = charged items.
        $summary = substr($m->fixed, 21, 10);
        if (($summary[1] ?? ' ') === 'Y') {
            foreach ($overdue as $row) {
                $body .= Sip2Message::f('AT', $row->barcode);
            }
        }
        if (($summary[2] ?? ' ') === 'Y') {
            foreach ($charged as $row) {
                $body .= Sip2Message::f('AU', $row->barcode);
            }
        }

        return $body;
    }

    private function checkout(Sip2Message $m, object $account): string
    {
        $barcode = (string) $m->field('AB');
        $card = (string) $m->field('AA');

        $refuse = fn (string $why, string $title = '') => '120NUN' . Sip2Message::timestamp()
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AA', $card)
            . Sip2Message::f('AB', $barcode)
            . Sip2Message::f('AJ', $title)
            . Sip2Message::f('AH', '')
            . Sip2Message::f('AF', $why);

        if (! $account->can_checkout) {
            return $refuse('Checkout is not enabled on this terminal');
        }
        $patron = $this->patrons->getByCardNumber($card);
        if (! $patron || ! $this->pinOk($patron, $m->field('AD'), $account)) {
            return $refuse('Card or PIN not accepted');
        }
        $copy = $barcode !== '' ? DB::table('library_copy')->where('barcode', $barcode)->first() : null;
        if (! $copy) {
            return $refuse('Item not found');
        }
        $title = $this->title($copy);

        $loan = DB::table('library_checkout')->where('copy_id', $copy->id)->whereIn('status', LibraryCirculationService::OPEN_LOAN_STATUSES)->first();
        if ($loan && (int) $loan->patron_id !== (int) $patron->id) {
            return $refuse('Item is on loan', $title);
        }

        $screen = 'Checked out';
        if ($loan) {
            $screen = 'Already on loan to you';
        } else {
            $id = $this->circulation->checkout((int) $copy->id, (int) $patron->id, $account->user_id, $account->branch_id);
            if ($id === null) {
                return $refuse($this->refusalReason($patron, $copy), $title);
            }
            $loan = DB::table('library_checkout')->where('id', $id)->first();
        }

        return '121NUY' . Sip2Message::timestamp()
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AA', $card)
            . Sip2Message::f('AB', $barcode)
            . Sip2Message::f('AJ', $title)
            . Sip2Message::f('AH', substr((string) $loan->due_date, 0, 10))
            . Sip2Message::f('AF', $screen);
    }

    private function checkin(Sip2Message $m, object $account): string
    {
        $barcode = (string) $m->field('AB');
        $copy = $barcode !== '' ? DB::table('library_copy')->where('barcode', $barcode)->first() : null;

        $reply = fn (bool $ok, bool $alert, string $screen, string $title = '', string $location = '', ?string $alertType = null) => '10'
            . ($ok ? '1' : '0') . ($ok ? 'Y' : 'N') . 'U' . ($alert ? 'Y' : 'N') . Sip2Message::timestamp()
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AB', $barcode)
            . Sip2Message::f('AQ', $location)
            . Sip2Message::f('AJ', $title)
            . ($alertType !== null ? Sip2Message::f('CV', $alertType) : '')
            . Sip2Message::f('AF', $screen);

        if (! $account->can_checkin) {
            return $reply(false, true, 'Checkin is not enabled on this terminal');
        }
        if (! $copy) {
            return $reply(false, true, 'Item not found');
        }

        $title = $this->title($copy);
        $location = (string) ($copy->shelf_location ?? '');
        $loan = DB::table('library_checkout')->where('copy_id', $copy->id)->whereIn('status', LibraryCirculationService::OPEN_LOAN_STATUSES)->first();
        if (! $loan) {
            return $reply(true, false, 'Item was not on loan', $title, $location);
        }
        if (! $this->circulation->return((int) $loan->id, $account->user_id)) {
            return $reply(false, true, 'Item could not be returned', $title, $location);
        }

        $status = DB::table('library_copy')->where('id', $copy->id)->value('status');
        if ($status === LibraryCirculationService::STATUS_ON_HOLD) {
            return $reply(true, true, 'Returned - hold waiting', $title, $location, '01');
        }

        return $reply(true, false, 'Returned', $title, $location);
    }

    private function feePaid(Sip2Message $m, object $account): string
    {
        $card = (string) $m->field('AA');
        $txn = (string) $m->field('BK');
        $reply = fn (bool $ok, string $screen) => '38' . ($ok ? 'Y' : 'N') . Sip2Message::timestamp()
            . Sip2Message::f('AO', $account->institution_id)
            . Sip2Message::f('AA', $card)
            . Sip2Message::f('BK', $txn)
            . Sip2Message::f('AF', $screen);

        if (! $account->can_pay) {
            return $reply(false, 'Payment is not enabled on this terminal');
        }

        $paymentType = substr($m->fixed, 20, 2);
        $currency = substr($m->fixed, 22, 3);
        $amount = (string) $m->field('BV');
        $fineId = $m->field('CG');

        if (! ctype_digit($paymentType) || $txn === '' || strlen($txn) > 100
            || ! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $amount)
            || ($fineId !== null && $fineId !== '' && ! ctype_digit($fineId))) {
            return $reply(false, 'Payment details not valid');
        }
        $cents = Sip2FeeLedger::cents($amount);
        if ($cents <= 0) {
            return $reply(false, 'Payment details not valid');
        }
        if ($currency !== LibrarySettings::currency()) {
            return $reply(false, 'Currency not accepted');
        }
        $patron = $this->patrons->getByCardNumber($card);
        if (! $patron) {
            return $reply(false, 'Card not accepted');
        }

        [$ok, $screen] = $this->ledger->pay(
            $account, (int) $patron->id, $cents, $currency, $paymentType, $txn,
            $fineId !== null && $fineId !== '' ? (int) $fineId : null
        );

        return $reply($ok, $screen);
    }

    private function blockPatron(Sip2Message $m, object $account): string
    {
        if (! $account->can_block) {
            return $this->patronStatus($m->withLanguage(), $account, 'Block is not enabled on this terminal', false);
        }

        $patron = $this->patrons->getByCardNumber((string) $m->field('AA'));
        if (! $patron) {
            return $this->patronStatus($m->withLanguage(), $account, 'Card not found', false);
        }

        if ($patron->borrowing_status !== 'suspended') {
            $reason = trim('SIP2 terminal ' . $account->login . ': ' . mb_substr((string) $m->field('AL'), 0, 255));
            $this->patrons->suspend((int) $patron->id, $reason);
        }

        return $this->patronStatus($m->withLanguage(), $account, 'Patron blocked', false);
    }

    /** A PIN is required unless this terminal is configured otherwise; repeated failures lock the card. */
    private function pinOk(object $patron, ?string $pin, object $account): bool
    {
        $required = (bool) $account->require_patron_pin;
        if ($pin === null || $pin === '') {
            return ! $required;
        }

        $key = 'sip2:pin:' . $patron->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return false;
        }

        $stored = DB::table('library_patron_pin')->where('patron_id', $patron->id)->value('pin_hash');
        if (! $stored) {
            password_verify($pin, self::DUMMY_HASH);

            return ! $required;
        }
        if (! password_verify($pin, $stored)) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            return false;
        }
        RateLimiter::clear($key);

        return true;
    }

    /** The 14-position patron status. An unverified patron gets the four privileges denied and nothing else. */
    private function statusFlags(?object $patron): string
    {
        if ($patron === null) {
            return 'YYYY' . str_repeat(' ', 10);
        }

        $active = $patron->borrowing_status === 'active';
        $loans = DB::table('library_checkout')->where('patron_id', $patron->id)->whereIn('status', LibraryCirculationService::OPEN_LOAN_STATUSES)->count();
        $tooMany = $loans >= (int) $patron->max_checkouts;
        $fines = (float) $patron->total_fines_owed >= LibrarySettings::patronFineThreshold();

        $flags = str_repeat(' ', 14);
        $flags[0] = (! $active || $tooMany || $fines) ? 'Y' : ' ';
        $flags[1] = $active ? ' ' : 'Y';
        $flags[2] = $active ? ' ' : 'Y';
        $flags[3] = $active ? ' ' : 'Y';
        $flags[5] = $tooMany ? 'Y' : ' ';
        $flags[10] = $fines ? 'Y' : ' ';

        return $flags;
    }

    private function refusalReason(object $patron, object $copy): string
    {
        if ($patron->borrowing_status !== 'active') {
            return 'Account is not active';
        }
        if ((float) $patron->total_fines_owed >= LibrarySettings::patronFineThreshold()) {
            return 'Outstanding fines must be paid';
        }
        $loans = DB::table('library_checkout')->where('patron_id', $patron->id)->whereIn('status', LibraryCirculationService::OPEN_LOAN_STATUSES)->count();
        if ($loans >= (int) $patron->max_checkouts) {
            return 'Loan limit reached';
        }

        return $copy->status === LibraryCirculationService::STATUS_ON_HOLD
            ? 'Item is on hold - please see staff'
            : 'Item is not available';
    }

    private function name(object $patron): string
    {
        return trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? ''));
    }

    private function title(object $copy): string
    {
        $query = DB::table('library_item as li')->where('li.id', $copy->library_item_id);
        I18nFallback::join($query, 'information_object_i18n as i18n', 'li.information_object_id', app()->getLocale());

        return (string) ($query->select(I18nFallback::col('i18n', 'title', 'title'))->first()->title ?? '');
    }
}
