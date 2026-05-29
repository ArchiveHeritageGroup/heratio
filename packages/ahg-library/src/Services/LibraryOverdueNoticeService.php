<?php

/**
 * LibraryOverdueNoticeService - render + send overdue notices (#1093).
 *
 * Walks active checkouts that are past due, picks the highest notice tier the
 * patron qualifies for (overdue_1 / overdue_2 / overdue_final based on the
 * template's trigger_days_overdue), renders the template tokens, sends via
 * Laravel Mail, and writes one row per send to library_overdue_notice_log.
 *
 * Idempotency: a patron is not re-sent the same notice tier for the same
 * checkout - the log table is consulted before each send. Schema is guarded so
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

use AhgLibrary\Support\LibrarySettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class LibraryOverdueNoticeService
{
    /** Tiers ordered most-severe first so the highest qualifying tier wins. */
    protected const TIER_ORDER = ['overdue_final', 'overdue_2', 'overdue_1'];

    /**
     * Process all overdue checkouts and dispatch notices.
     *
     * @param  bool $dryRun  when true, render + log status='skipped' but do not
     *                       actually mail or persist a 'sent' row.
     * @return array{candidates:int, sent:int, skipped:int, failed:int}
     */
    public function processOverdue(bool $dryRun = false): array
    {
        $stats = ['candidates' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];

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
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('li.information_object_id', '=', 'i18n.id')
                  ->where('i18n.culture', '=', app()->getLocale());
            })
            ->where('c.status', 'active')
            ->whereDate('c.due_date', '<', $today->toDateString())
            ->select(
                'c.id as checkout_id', 'c.due_date',
                'p.id as patron_id', 'p.first_name', 'p.last_name', 'p.email', 'p.patron_type',
                'cp.barcode', 'li.call_number', 'li.material_type', 'i18n.title'
            )
            ->orderBy('c.id')
            ->chunk(200, function ($rows) use (&$stats, $templates, $today, $dryRun) {
                foreach ($rows as $row) {
                    $stats['candidates']++;
                    $daysOverdue = (int) $today->diffInDays(Carbon::parse($row->due_date), false) * -1;
                    if ($daysOverdue <= 0) {
                        continue;
                    }

                    $tpl = $this->selectTemplate($templates, $daysOverdue);
                    if (!$tpl) {
                        continue;
                    }

                    if ($this->alreadySent((int) $row->checkout_id, $tpl->notice_type)) {
                        continue;
                    }

                    $outcome = $this->sendOne($row, $tpl, $daysOverdue, $dryRun);
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

    protected function sendOne(object $row, object $tpl, int $daysOverdue, bool $dryRun): string
    {
        $tokens = $this->buildTokens($row, $daysOverdue);
        $rendered = $this->render($tpl, $tokens);
        $recipient = $row->email ?: null;

        if ($dryRun) {
            $this->logNotice($row, $tpl, $daysOverdue, 'skipped', 'dry-run', $recipient);
            return 'skipped';
        }

        if (!$recipient) {
            $this->logNotice($row, $tpl, $daysOverdue, 'skipped', 'no email on patron record', null);
            return 'skipped';
        }

        try {
            Mail::raw($rendered['body'], function ($m) use ($recipient, $rendered) {
                $m->to($recipient)->subject($rendered['subject']);
            });
            $this->logNotice($row, $tpl, $daysOverdue, 'sent', null, $recipient);
            return 'sent';
        } catch (\Throwable $e) {
            Log::error('[library] overdue notice send failed', [
                'checkout_id' => $row->checkout_id,
                'error'       => $e->getMessage(),
            ]);
            $this->logNotice($row, $tpl, $daysOverdue, 'failed', $e->getMessage(), $recipient);
            return 'failed';
        }
    }

    protected function buildTokens(object $row, int $daysOverdue): array
    {
        $materialType = $row->material_type ?: 'monograph';
        $rule = DB::table('library_loan_rule')
            ->where('material_type', $materialType)
            ->whereIn('patron_type', [$row->patron_type ?? '*', '*'])
            ->orderByRaw("CASE WHEN patron_type = ? THEN 0 ELSE 1 END", [$row->patron_type ?? '*'])
            ->first();

        $perDay = $rule ? (float) $rule->fine_per_day : 1.00;
        $grace  = $rule ? (int) $rule->grace_period_days : 0;
        $cap    = $rule && $rule->fine_cap !== null ? (float) $rule->fine_cap : null;
        $fineDays = max(0, $daysOverdue - $grace);
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
     * Choose the highest-severity active template whose trigger_days_overdue is
     * satisfied by the current overdue count.
     */
    protected function selectTemplate(array $templates, int $daysOverdue): ?object
    {
        foreach (self::TIER_ORDER as $type) {
            $tpl = $templates[$type] ?? null;
            if ($tpl && $daysOverdue >= (int) $tpl->trigger_days_overdue) {
                return $tpl;
            }
        }
        return null;
    }

    /** @return array<string,object> keyed by notice_type */
    protected function activeTemplates(): array
    {
        $rows = DB::table('library_notice_template')
            ->where('channel', 'email')
            ->where('is_active', 1)
            ->whereIn('notice_type', self::TIER_ORDER)
            ->get();

        $byType = [];
        foreach ($rows as $r) {
            $byType[$r->notice_type] = $r;
        }
        return $byType;
    }

    protected function alreadySent(int $checkoutId, string $noticeType): bool
    {
        if (!Schema::hasTable('library_overdue_notice_log')) {
            return false;
        }
        return DB::table('library_overdue_notice_log')
            ->where('checkout_id', $checkoutId)
            ->where('notice_type', $noticeType)
            ->where('status', 'sent')
            ->exists();
    }

    protected function logNotice(object $row, object $tpl, int $daysOverdue, string $status, ?string $error, ?string $recipient): void
    {
        if (!Schema::hasTable('library_overdue_notice_log')) {
            return;
        }
        try {
            DB::table('library_overdue_notice_log')->insert([
                'checkout_id'   => $row->checkout_id,
                'patron_id'     => $row->patron_id,
                'notice_type'   => $tpl->notice_type,
                'channel'       => 'email',
                'recipient'     => $recipient,
                'days_overdue'  => $daysOverdue,
                'status'        => $status,
                'error_message' => $error,
                'sent_at'       => $status === 'sent' ? now() : null,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[library] notice-log write failed', ['error' => $e->getMessage()]);
        }
    }
}
