<?php

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

/**
 * PruneService — retention pruning for share-link tables.
 *
 * Two independent sweeps:
 *
 *   1. token_retain_days        — delete `information_object_share_token`
 *                                  rows where expires_at < now - N days
 *                                  OR revoked_at < now - N days.
 *                                  CASCADE removes their access rows.
 *
 *   2. access_log_retain_days   — delete `information_object_share_access`
 *                                  rows where accessed_at < now - M days,
 *                                  regardless of parent token state. Lets
 *                                  the access log self-trim while keeping
 *                                  the issued tokens visible for audit.
 *
 * Defaults (when ahg_settings is not populated):
 *   share_link.token_retain_days       = 365
 *   share_link.access_log_retain_days  = 180
 *
 * A summary row is written to ahg_audit_log with
 *   action='share_link_prune'.
 *
 * Each run is idempotent — a no-op run is safe.
 *
 * @phase H
 */
class PruneService
{
    public const DEFAULT_TOKEN_RETAIN_DAYS = 365;
    public const DEFAULT_ACCESS_LOG_RETAIN_DAYS = 180;

    public const AUDIT_ACTION_PRUNE = 'share_link_prune';

    /**
     * Run both sweeps. Returns counts so the CLI task / cron can report.
     *
     * @return array{
     *   tokens_deleted: int,
     *   access_rows_deleted: int,
     *   token_retain_days: int,
     *   access_log_retain_days: int,
     *   dry_run: bool,
     * }
     */
    public function prune(bool $dryRun = false): array
    {
        $tokenDays = (int) $this->readSetting('share_link.token_retain_days', (string) self::DEFAULT_TOKEN_RETAIN_DAYS);
        $accessDays = (int) $this->readSetting('share_link.access_log_retain_days', (string) self::DEFAULT_ACCESS_LOG_RETAIN_DAYS);

        if ($tokenDays < 1) {
            $tokenDays = self::DEFAULT_TOKEN_RETAIN_DAYS;
        }
        if ($accessDays < 1) {
            $accessDays = self::DEFAULT_ACCESS_LOG_RETAIN_DAYS;
        }

        $tokenCutoff = (new \DateTimeImmutable("-{$tokenDays} days"))->format('Y-m-d H:i:s');
        $accessCutoff = (new \DateTimeImmutable("-{$accessDays} days"))->format('Y-m-d H:i:s');

        $tokensToDelete = DB::table('information_object_share_token')
            ->where(function ($q) use ($tokenCutoff) {
                $q->where(function ($qq) use ($tokenCutoff) {
                    $qq->whereNotNull('expires_at')->where('expires_at', '<', $tokenCutoff);
                })->orWhere(function ($qq) use ($tokenCutoff) {
                    $qq->whereNotNull('revoked_at')->where('revoked_at', '<', $tokenCutoff);
                });
            });

        $accessToDelete = DB::table('information_object_share_access')
            ->where('accessed_at', '<', $accessCutoff);

        if ($dryRun) {
            $tokensDeleted = (int) $tokensToDelete->count();
            $accessDeleted = (int) $accessToDelete->count();
        } else {
            $tokensDeleted = (int) $tokensToDelete->delete();
            $accessDeleted = (int) $accessToDelete->delete();
        }

        $summary = [
            'tokens_deleted'         => $tokensDeleted,
            'access_rows_deleted'    => $accessDeleted,
            'token_retain_days'      => $tokenDays,
            'access_log_retain_days' => $accessDays,
            'dry_run'                => $dryRun,
        ];

        if (!$dryRun && ($tokensDeleted > 0 || $accessDeleted > 0)) {
            $this->writeAudit($summary);
        }

        return $summary;
    }

    private function readSetting(string $key, string $default): string
    {
        try {
            $v = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            return is_string($v) && $v !== '' ? $v : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function writeAudit(array $summary): void
    {
        try {
            DB::table('ahg_audit_log')->insert([
                'uuid'           => $this->generateUuid(),
                'user_id'        => null,
                'username'       => 'cron',
                'action'         => self::AUDIT_ACTION_PRUNE,
                'entity_type'    => 'information_object_share_token',
                'entity_id'      => null,
                'module'         => 'share_link',
                'action_name'    => 'prune',
                'request_method' => 'CLI',
                'metadata'       => json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status'         => 'success',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('ahgTimeLimitedShareLinkPlugin prune audit failed: ' . $e->getMessage());
        }
    }

    private function generateUuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
