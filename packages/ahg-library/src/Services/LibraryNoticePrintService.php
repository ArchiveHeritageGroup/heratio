<?php

/**
 * LibraryNoticePrintService - queues rendered print notices and groups them
 * into print runs delivered as a PDF (#1474 item 2).
 *
 * Log status follows what Heratio actually knows: 'queued' while waiting and
 * 'batched' once in a run. It never becomes 'sent', because Heratio cannot see
 * whether the paper was handed over or posted.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Dompdf\Dompdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LibraryNoticePrintService
{
    /** ponytail: one Dompdf render per request; larger queues take several runs. */
    public const MAX_RUN_SIZE = 500;

    /** ahg:library-overdue-check moves past-due loans from active to overdue; both are still out. */
    public const OPEN_LOAN_STATUSES = ['active', 'overdue'];

    /** @return bool false when this checkout + tier is already queued */
    public function enqueue(object $row, string $noticeType, array $rendered): bool
    {
        return DB::table('library_notice_print_item')->insertOrIgnore([
            'checkout_id'    => $row->checkout_id,
            'patron_id'      => $row->patron_id,
            'notice_type'    => $noticeType,
            'recipient_name' => mb_substr(trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')), 0, 255),
            'postal_address' => $row->address ?? null,
            'subject'        => mb_substr($rendered['subject'], 0, 255),
            'body'           => $rendered['body'],
            'created_at'     => now(),
        ]) > 0;
    }

    public function attachLog(int $checkoutId, string $noticeType, int $logId): void
    {
        DB::table('library_notice_print_item')
            ->where('checkout_id', $checkoutId)
            ->where('notice_type', $noticeType)
            ->update(['log_id' => $logId]);
    }

    public function queuedCount(): int
    {
        return DB::table('library_notice_print_item')->whereNull('batch_id')->count();
    }

    /**
     * Group queued notices into a run. A notice whose loan has ended or is no
     * longer past due is withdrawn, so nobody is chased for a book they already
     * brought back. Where a loan has several tiers waiting, only the most severe
     * is printed, so one patron never gets conflicting letters for one book.
     *
     * @return array{batch_id:?int, included:int, withdrawn:int, superseded:int}
     */
    public function createRun(?int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $items = DB::table('library_notice_print_item as i')
                ->leftJoin('library_checkout as c', 'i.checkout_id', '=', 'c.id')
                ->whereNull('i.batch_id')
                ->orderBy('i.id')
                ->limit(self::MAX_RUN_SIZE)
                ->lockForUpdate()
                ->get(['i.id', 'i.log_id', 'i.checkout_id', 'i.notice_type', 'c.status as loan_status', 'c.due_date', 'c.return_date']);

            $today = Carbon::today()->toDateString();
            [$live, $stale] = $items->partition(
                fn ($i) => in_array($i->loan_status, self::OPEN_LOAN_STATUSES, true)
                    && $i->return_date === null
                    && substr((string) $i->due_date, 0, 10) < $today
            );

            $this->withdraw($stale, 'withdrawn: loan returned or no longer overdue before the print run');

            // Looks beyond this run's limit: the higher tier may be queued later or already printed.
            $topRank = [];
            foreach ($live->pluck('checkout_id')->unique()->chunk(200) as $ids) {
                $tiers = DB::table('library_notice_print_item')->whereIn('checkout_id', $ids->all())->get(['checkout_id', 'notice_type']);
                foreach ($tiers as $t) {
                    $topRank[$t->checkout_id] = min($topRank[$t->checkout_id] ?? PHP_INT_MAX, $this->tierRank($t->notice_type));
                }
            }
            [$live, $superseded] = $live->partition(fn ($i) => $this->tierRank($i->notice_type) === $topRank[$i->checkout_id]);

            $this->withdraw($superseded, 'withdrawn: superseded by a later notice for this loan');

            $counts = ['withdrawn' => $stale->count(), 'superseded' => $superseded->count()];
            if ($live->isEmpty()) {
                return ['batch_id' => null, 'included' => 0] + $counts;
            }

            $batchId = DB::table('library_notice_print_batch')->insertGetId([
                'item_count' => $live->count(), 'created_by' => $userId, 'created_at' => now(),
            ]);
            foreach ($live->chunk(200) as $chunk) {
                DB::table('library_notice_print_item')->whereIn('id', $chunk->pluck('id')->all())->update(['batch_id' => $batchId]);
                DB::table('library_overdue_notice_log')->whereIn('id', $chunk->pluck('log_id')->filter()->all())
                    ->update(['status' => 'batched']);
            }

            return ['batch_id' => (int) $batchId, 'included' => $live->count()] + $counts;
        });
    }

    private function withdraw(\Illuminate\Support\Collection $items, string $reason): void
    {
        foreach ($items->chunk(200) as $chunk) {
            DB::table('library_overdue_notice_log')->whereIn('id', $chunk->pluck('log_id')->filter()->all())
                ->update(['status' => 'skipped', 'error_message' => $reason]);
            DB::table('library_notice_print_item')->whereIn('id', $chunk->pluck('id')->all())->delete();
        }
    }

    /** Lower is more severe. */
    private function tierRank(string $noticeType): int
    {
        $rank = array_search($noticeType, LibraryOverdueNoticeService::TIER_ORDER, true);
        return $rank === false ? count(LibraryOverdueNoticeService::TIER_ORDER) : $rank;
    }

    /** Rendered from the stored notices, so nothing with patron data sits on disk. */
    public function renderPdf(int $batchId): string
    {
        $items = DB::table('library_notice_print_item')->where('batch_id', $batchId)->orderBy('id')->get();

        $dompdf = new Dompdf(['isRemoteEnabled' => false, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'DejaVu Sans']);
        $dompdf->loadHtml(view('ahg-library::notices.print-run-pdf', ['items' => $items])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
