<?php

/**
 * LibraryOverdueNoticeService - render + send overdue notices (#1093).
 *
 * Walks open checkouts (status active or overdue) that are past due, picks the highest notice tier the
 * patron qualifies for (overdue_1 / overdue_2 / overdue_final based on the
 * template's trigger_days_overdue), renders the template tokens, delivers on
 * the patron's channel, and writes one row per attempt to
 * library_overdue_notice_log.
 *
 * Channels (#1474): the patron's preference is tried first, then email, then
 * print. A channel that cannot be used (no address, no active template, no real
 * SMS gateway) is logged as skipped and the next is tried. A transport failure
 * is logged as failed and retried on the next run. SMS is capped: after
 * SMS_MAX_ATTEMPTS failures for one checkout + tier the next channel is used,
 * and after SMS_RUN_FAILURE_LIMIT failures in a row SMS is dropped for the rest
 * of the run. Print notices are logged as queued, never sent - see
 * LibraryNoticePrintService.
 *
 * Idempotency: a patron is not re-sent the same notice tier for the same
 * checkout on any channel - the log is consulted first. Schema is guarded so
 * the service is safe to call on a fresh install before migrations run.
 *
 * Template tokens (all optional in the body):
 *   {{patron_name}} {{title}} {{barcode}} {{due_date}} {{days_overdue}}
 *   {{currency}} {{fine_per_day}} {{fine_amount}} {{library_name}}
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use AhgLibrary\Services\LibraryCirculationService;
use AhgLibrary\Support\LibraryBranch;
use AhgLibrary\Support\LibrarySettings;
use AhgSecurityClearance\Services\SmsGatewayInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use AhgCore\Support\I18nFallback;

class LibraryOverdueNoticeService
{
    /** Tiers ordered most-severe first so the highest qualifying tier wins. */
    public const TIER_ORDER = ['overdue_final', 'overdue_2', 'overdue_1'];

    /** Longest SMS sent: three concatenated GSM-7 segments. */
    protected const SMS_MAX_CHARS = 459;

    /** Log statuses meaning this tier has been dealt with for the checkout. */
    protected const HANDLED_STATUSES = ['sent', 'queued', 'batched'];

    /**
     * The gateway answers only yes or no, and a timeout is a no even if the
     * provider took the message. Each retry can therefore duplicate an SMS, so
     * retries stay low.
     */
    protected const SMS_MAX_ATTEMPTS = 2;

    /** Consecutive gateway failures that end SMS use for the run (10 s timeout each). */
    protected const SMS_RUN_FAILURE_LIMIT = 3;

    /** SMS gateway for this run; resolved once. False = not looked up yet. */
    private $smsGateway = false;

    private int $smsFailuresInARow = 0;

    /**
     * Process all overdue checkouts and dispatch notices.
     *
     * @param  bool $dryRun  when true, render + log status='skipped' but do not
     *                       actually mail or persist a 'sent' row.
     * @return array{candidates:int, sent:int, queued:int, skipped:int, failed:int}
     */
    public function processOverdue(bool $dryRun = false): array
    {
        $stats = ['candidates' => 0, 'sent' => 0, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
        $this->smsGateway = false;
        $this->smsFailuresInARow = 0;

        if (!Schema::hasTable('library_checkout') || !Schema::hasTable('library_notice_template')) {
            return $stats;
        }

        $templates = $this->activeTemplates();
        if (empty($templates)) {
            return $stats;
        }

        $today = Carbon::today();

        DB::table('library_checkout as c')
            ->join('library_patron as p', 'c.patron_id', '=', 'p.id')
            ->leftJoin('library_copy as cp', 'c.copy_id', '=', 'cp.id')
            ->leftJoin('library_item as li', 'cp.library_item_id', '=', 'li.id')
            ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i18n', 'li.information_object_id', app()->getLocale()))
            ->whereIn('c.status', LibraryNoticePrintService::OPEN_LOAN_STATUSES)
            ->whereNull('c.return_date')
            ->whereDate('c.due_date', '<', $today->toDateString())
            ->select(
                'c.id as checkout_id', 'c.due_date',
                'p.id as patron_id', 'p.first_name', 'p.last_name', 'p.email', 'p.phone', 'p.address', 'p.patron_type',
                'cp.barcode', 'li.call_number', 'li.material_type', I18nFallback::col('i18n', 'title', 'title')
            )
            // #1473: a notice must say which branch the loan was made at, and
            // must quote THAT branch's fine rate. Selected only where the
            // column exists so an instance mid-deploy still sends notices.
            ->when(Schema::hasColumn('library_checkout', 'branch_id'), fn ($q) => $q->addSelect('c.branch_id'))
            ->when(Schema::hasColumn('library_copy', 'branch_id'), fn ($q) => $q->addSelect('cp.branch_id as copy_branch_id'))
            ->when(Schema::hasTable('library_patron_notice_preference'), fn ($q) => $q
                ->leftJoin('library_patron_notice_preference as pref', 'pref.patron_id', '=', 'p.id')
                ->addSelect('pref.channel as preferred_channel'))
            ->orderBy('c.id')
            ->chunk(200, function ($rows) use (&$stats, $templates, $today, $dryRun) {
                foreach ($rows as $row) {
                    $stats['candidates']++;
                    $daysOverdue = (int) $today->diffInDays(Carbon::parse($row->due_date), false) * -1;
                    if ($daysOverdue <= 0) {
                        continue;
                    }

                    $chain = $this->channelChain($row->preferred_channel ?? null);
                    $noticeType = $this->selectTier($row, $templates, $chain, $daysOverdue);
                    if (!$noticeType) {
                        continue;
                    }

                    $outcome = $this->dispatch($row, $noticeType, $chain, $templates, $daysOverdue, $dryRun);
                    $stats[$outcome]++;
                }
            });

        return $stats;
    }

    /**
     * Render a template's subject + body for a checkout row. Public so the
     * admin template editor can show a live preview.
     */
    public function render(object $template, array $tokens): array
    {
        return [
            'subject' => $this->replaceTokens($template->subject, $tokens),
            'body'    => $this->replaceTokens($template->body, $tokens),
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────

    /** Try each channel in order until one takes the notice. */
    protected function dispatch(object $row, string $noticeType, array $chain, array $templates, int $daysOverdue, bool $dryRun): string
    {
        $tokens = $this->buildTokens($row, $daysOverdue);
        $explicit = !empty($row->preferred_channel) ? $chain[0] : null;
        $failed = false;

        foreach ($chain as $channel) {
            $tpl = $templates[$channel][$noticeType] ?? null;
            if (!$tpl || $daysOverdue < (int) $tpl->trigger_days_overdue) {
                if ($channel === $explicit) {
                    $this->logSkipOnce($row, $noticeType, $channel, $daysOverdue, "no active {$channel} template for this tier");
                }
                continue;
            }

            [$recipient, $unusable] = $this->addressFor($channel, $row, $noticeType);
            if ($unusable !== null) {
                $this->logSkipOnce($row, $noticeType, $channel, $daysOverdue, $unusable);
                continue;
            }

            if ($dryRun) {
                $this->logNotice($row, $noticeType, $channel, $daysOverdue, 'skipped', 'dry-run', $recipient);
                return 'skipped';
            }

            $outcome = $this->deliver($channel, $row, $noticeType, $this->render($tpl, $tokens), $daysOverdue, $recipient);
            if ($channel === 'sms') {
                $this->smsFailuresInARow = $outcome === 'failed' ? $this->smsFailuresInARow + 1 : 0;
                if ($outcome === 'failed' && $this->smsAttempts($row, $noticeType) >= self::SMS_MAX_ATTEMPTS) {
                    $failed = true;
                    continue;
                }
            }
            return $outcome;
        }

        return $failed ? 'failed' : 'skipped';
    }

    protected function smsAttempts(object $row, string $noticeType): int
    {
        return DB::table('library_overdue_notice_log')
            ->where('checkout_id', $row->checkout_id)
            ->where('notice_type', $noticeType)
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->count();
    }

    /** @return array{0:?string, 1:?string} [recipient, reason the channel cannot be used] */
    protected function addressFor(string $channel, object $row, string $noticeType): array
    {
        switch ($channel) {
            case 'sms':
                if (!$this->smsGateway()) {
                    return [null, 'no SMS gateway configured'];
                }
                if ($this->smsFailuresInARow >= self::SMS_RUN_FAILURE_LIMIT) {
                    return [null, 'SMS gateway failing, not used for the rest of this run'];
                }
                if (Schema::hasTable('library_overdue_notice_log') && $this->smsAttempts($row, $noticeType) >= self::SMS_MAX_ATTEMPTS) {
                    return [null, 'SMS not confirmed after ' . self::SMS_MAX_ATTEMPTS . ' attempts'];
                }
                $phone = app(LibraryNoticeChannelService::class)->normalisePhone($row->phone ?? null);
                return $phone ? [$phone, null] : [null, 'no usable phone number on patron record'];
            case 'print':
                if (!Schema::hasTable('library_notice_print_item')) {
                    return [null, 'print queue not installed'];
                }
                $address = trim((string) ($row->address ?? ''));
                return [$address !== '' ? mb_substr($address, 0, 255) : 'counter collection', null];
            default:
                return $row->email ? [$row->email, null] : [null, 'no email on patron record'];
        }
    }

    protected function deliver(string $channel, object $row, string $noticeType, array $rendered, int $daysOverdue, string $recipient): string
    {
        try {
            if ($channel === 'print') {
                return $this->queueForPrint($row, $noticeType, $rendered, $daysOverdue, $recipient);
            }

            if ($channel === 'sms') {
                if (!$this->smsGateway()->send($recipient, mb_substr($rendered['body'], 0, self::SMS_MAX_CHARS))) {
                    $this->logNotice($row, $noticeType, 'sms', $daysOverdue, 'failed', 'SMS gateway did not confirm the message (refused, or timed out and possibly delivered)', $recipient);
                    return 'failed';
                }
            } else {
                Mail::raw($rendered['body'], function ($m) use ($recipient, $rendered) {
                    $m->to($recipient)->subject($rendered['subject']);
                });
            }

            $this->logNotice($row, $noticeType, $channel, $daysOverdue, 'sent', null, $recipient);
            return 'sent';
        } catch (\Throwable $e) {
            Log::error('[library] overdue notice send failed', [
                'checkout_id' => $row->checkout_id,
                'channel'     => $channel,
                'error'       => $e->getMessage(),
            ]);
            $this->logNotice($row, $noticeType, $channel, $daysOverdue, 'failed', $e->getMessage(), $recipient);
            return 'failed';
        }
    }

    protected function queueForPrint(object $row, string $noticeType, array $rendered, int $daysOverdue, string $recipient): string
    {
        $print = app(LibraryNoticePrintService::class);

        return DB::transaction(function () use ($print, $row, $noticeType, $rendered, $daysOverdue, $recipient) {
            if (!$print->enqueue($row, $noticeType, $rendered)) {
                return 'skipped';
            }
            $logId = $this->logNotice($row, $noticeType, 'print', $daysOverdue, 'queued', null, $recipient);
            if ($logId) {
                $print->attachLog((int) $row->checkout_id, $noticeType, $logId);
            }
            return 'queued';
        });
    }

    protected function smsGateway(): ?SmsGatewayInterface
    {
        if ($this->smsGateway === false) {
            $this->smsGateway = app(LibraryNoticeChannelService::class)->deliverableSmsGateway();
        }
        return $this->smsGateway;
    }

    /** @return string[] preferred channel first, then email, then print */
    protected function channelChain(?string $preferred): array
    {
        $first = in_array($preferred, LibraryNoticeChannelService::SUPPORTED, true) ? [$preferred] : [];
        return array_values(array_unique([...$first, 'email', 'print']));
    }

    protected function buildTokens(object $row, int $daysOverdue): array
    {
        $materialType = $row->material_type ?: 'monograph';

        // The branch that lent the item decides the rate, not the system default.
        $branchId = $row->branch_id ?? $row->copy_branch_id ?? null;
        $branchId = $branchId !== null ? (int) $branchId : null;

        $rule = app(LibraryCirculationService::class)
            ->ruleFor($materialType, (string) ($row->patron_type ?? '*'), $branchId);

        $perDay = $rule ? (float) $rule->fine_per_day : 1.00;
        $grace  = $rule ? (int) $rule->grace_period_days : 0;
        $cap    = $rule && $rule->fine_cap !== null ? (float) $rule->fine_cap : null;
        // #1475: quote the same chargeable days the fine itself is built from.
        $closedDays = app(LibraryCalendarService::class)
            ->closedDaysBetween((string) $row->due_date, date('Y-m-d'), $branchId);
        $fineDays = max(0, $daysOverdue - $closedDays - $grace);
        $fineAmount = $perDay * $fineDays;
        if ($cap !== null && $fineAmount > $cap) {
            $fineAmount = $cap;
        }

        return [
            'patron_name'  => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
            'title'        => $row->title ?: ($row->call_number ?: 'library item'),
            'barcode'      => $row->barcode ?? '',
            'due_date'     => $row->due_date,
            'days_overdue' => (string) $daysOverdue,
            'currency'     => LibrarySettings::currency(),
            'fine_per_day' => number_format($perDay, 2),
            'fine_amount'  => number_format($fineAmount, 2),
            'library_name' => LibrarySettings::libraryName(),
            // {{branch_name}} falls back to the library name so an existing
            // template that has never heard of branches still reads correctly.
            'branch_name'  => LibraryBranch::name($branchId) ?? LibrarySettings::libraryName(),
        ];
    }

    protected function replaceTokens(string $text, array $tokens): string
    {
        foreach ($tokens as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string) $value, $text);
        }
        return $text;
    }

    /**
     * The most severe due tier that a channel can actually deliver to this
     * patron. Never steps back below a tier already handled. If nothing is
     * deliverable, returns the most severe unhandled tier so dispatch records why.
     */
    protected function selectTier(object $row, array $templates, array $chain, int $daysOverdue): ?string
    {
        $undeliverable = null;

        foreach (self::TIER_ORDER as $type) {
            $due = array_values(array_filter($chain, function ($channel) use ($templates, $type, $daysOverdue) {
                $tpl = $templates[$channel][$type] ?? null;
                return $tpl && $daysOverdue >= (int) $tpl->trigger_days_overdue;
            }));
            if (!$due) {
                continue;
            }
            if ($this->alreadySent((int) $row->checkout_id, $type)) {
                return $undeliverable;
            }
            foreach ($due as $channel) {
                if ($this->addressFor($channel, $row, $type)[1] === null) {
                    return $type;
                }
            }
            $undeliverable ??= $type;
        }

        return $undeliverable;
    }

    /** @return array<string,array<string,object>> keyed by channel, then notice_type */
    protected function activeTemplates(): array
    {
        $rows = DB::table('library_notice_template')
            ->whereIn('channel', LibraryNoticeChannelService::SUPPORTED)
            ->where('is_active', 1)
            ->whereIn('notice_type', self::TIER_ORDER)
            ->get();

        $byChannel = [];
        foreach ($rows as $r) {
            $byChannel[$r->channel][$r->notice_type] = $r;
        }
        return $byChannel;
    }

    protected function alreadySent(int $checkoutId, string $noticeType): bool
    {
        if (!Schema::hasTable('library_overdue_notice_log')) {
            return false;
        }
        return DB::table('library_overdue_notice_log')
            ->where('checkout_id', $checkoutId)
            ->where('notice_type', $noticeType)
            ->whereIn('status', self::HANDLED_STATUSES)
            ->exists();
    }

    /** A channel that stays unusable is recorded once, not on every daily run. */
    protected function logSkipOnce(object $row, string $noticeType, string $channel, int $daysOverdue, string $reason): void
    {
        if (Schema::hasTable('library_overdue_notice_log') && DB::table('library_overdue_notice_log')
            ->where('checkout_id', $row->checkout_id)
            ->where('notice_type', $noticeType)
            ->where('channel', $channel)
            ->where('status', 'skipped')
            ->where('error_message', $reason)
            ->exists()) {
            return;
        }
        $this->logNotice($row, $noticeType, $channel, $daysOverdue, 'skipped', $reason, null);
    }

    protected function logNotice(object $row, string $noticeType, string $channel, int $daysOverdue, string $status, ?string $error, ?string $recipient): ?int
    {
        if (!Schema::hasTable('library_overdue_notice_log')) {
            return null;
        }
        try {
            return (int) DB::table('library_overdue_notice_log')->insertGetId([
                'checkout_id'   => $row->checkout_id,
                'patron_id'     => $row->patron_id,
                'notice_type'   => $noticeType,
                'channel'       => $channel,
                'recipient'     => $recipient,
                'days_overdue'  => $daysOverdue,
                'status'        => $status,
                'error_message' => $error,
                'sent_at'       => $status === 'sent' ? now() : null,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[library] notice-log write failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
