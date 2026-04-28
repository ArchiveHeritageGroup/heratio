<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WebhookRetryCommand extends Command
{
    protected $signature = 'ahg:webhook-retry
        {--limit=50 : Max delivery rows to retry per run}
        {--max-attempts=5 : Cap retries; failed rows past this are marked permanently_failed}';

    protected $description = 'Retry failed ahg_webhook_delivery rows whose backoff window has elapsed';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));

        $rows = DB::table('ahg_webhook_delivery as d')
            ->join('ahg_webhook as w', 'w.id', '=', 'd.webhook_id')
            ->where('d.status', 'failed')
            ->where('d.attempts', '<', $maxAttempts)
            ->where(function ($q) {
                $q->whereNull('d.next_retry_at')->orWhere('d.next_retry_at', '<=', now());
            })
            ->where('w.is_active', 1)
            ->select('d.id', 'd.webhook_id', 'd.payload', 'd.attempts', 'w.url', 'w.secret')
            ->limit($limit)
            ->get();
        $this->info("retrying {$rows->count()} delivery rows (max_attempts={$maxAttempts})");

        $ok = 0; $fail = 0; $perm = 0;
        foreach ($rows as $r) {
            $attempts = (int) $r->attempts + 1;
            $signature = $r->secret ? hash_hmac('sha256', (string) $r->payload, (string) $r->secret) : null;
            try {
                $resp = Http::timeout(15)
                    ->withHeaders(array_filter([
                        'Content-Type'   => 'application/json',
                        'X-AHG-Signature' => $signature,
                    ]))
                    ->withBody((string) $r->payload, 'application/json')
                    ->send('POST', (string) $r->url);
                if ($resp->successful()) {
                    DB::table('ahg_webhook_delivery')->where('id', $r->id)->update([
                        'status'          => 'delivered',
                        'attempts'        => $attempts,
                        'last_response'   => $resp->status(),
                        'delivered_at'    => now(),
                    ]);
                    $ok++;
                } else {
                    throw new \RuntimeException('HTTP ' . $resp->status());
                }
            } catch (\Throwable $e) {
                $next = now()->addMinutes(5 * $attempts);
                $newStatus = $attempts >= $maxAttempts ? 'permanently_failed' : 'failed';
                DB::table('ahg_webhook_delivery')->where('id', $r->id)->update([
                    'status'        => $newStatus,
                    'attempts'      => $attempts,
                    'last_error'    => $e->getMessage(),
                    'next_retry_at' => $newStatus === 'failed' ? $next : null,
                ]);
                $newStatus === 'failed' ? $fail++ : $perm++;
            }
        }
        $this->info("delivered={$ok} retry-later={$fail} permanently_failed={$perm}");
        return self::SUCCESS;
    }
}
