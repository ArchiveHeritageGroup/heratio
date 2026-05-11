<?php

/**
 * AccessService — validate a share-link token and log the access.
 * Mirror of the AtoM-side service.
 *
 * @phase D
 */

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

class AccessService
{
    public const AUDIT_ACTION_ACCESSED = 'share_link_accessed';

    public function __construct(
        private readonly TokenService $tokenService,
    ) {
    }

    public function evaluate(string $token, ?string $ip, ?string $userAgent): AccessResult
    {
        $row = $this->tokenService->lookup($token);
        if ($row === null) {
            return AccessResult::notFound();
        }

        $now = time();

        if ($row->revoked_at !== null) {
            $this->logAccess($row->id, $ip, $userAgent, 'denied_revoked');
            $this->writeAudit($row, 'denied_revoked', $ip);
            return AccessResult::deny($row, 'denied_revoked', 'This share link has been revoked.');
        }
        if (strtotime((string) $row->expires_at) <= $now) {
            $this->logAccess($row->id, $ip, $userAgent, 'denied_expired');
            $this->writeAudit($row, 'denied_expired', $ip);
            return AccessResult::deny($row, 'denied_expired', 'This share link has expired.');
        }
        if ($row->max_access !== null && (int) $row->access_count >= (int) $row->max_access) {
            $this->logAccess($row->id, $ip, $userAgent, 'denied_quota');
            $this->writeAudit($row, 'denied_quota', $ip);
            return AccessResult::deny($row, 'denied_quota', 'This share link has reached its maximum access count.');
        }

        DB::transaction(function () use ($row, $ip, $userAgent) {
            $this->logAccess($row->id, $ip, $userAgent, 'view');
            DB::table('information_object_share_token')
                ->where('id', $row->id)
                ->increment('access_count');
        });
        $this->writeAudit($row, 'view', $ip);

        $row->access_count = ((int) $row->access_count) + 1;

        return AccessResult::allow($row, 'view');
    }

    private function logAccess(int $tokenId, ?string $ip, ?string $userAgent, string $action): void
    {
        try {
            DB::table('information_object_share_access')->insert([
                'token_id'    => $tokenId,
                'accessed_at' => now(),
                'ip_address'  => $ip,
                'user_agent'  => $userAgent !== null ? substr($userAgent, 0, 500) : null,
                'action'      => $action,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('ahg-share-link logAccess failed', ['error' => $e->getMessage()]);
        }
    }

    private function writeAudit(object $tokenRow, string $accessAction, ?string $ip): void
    {
        try {
            $metadata = [
                'token_id'           => (int) $tokenRow->id,
                'access_action'      => $accessAction,
                'parent_entity_type' => 'information_object',
                'parent_entity_id'   => (int) $tokenRow->information_object_id,
                'recipient_email'    => $tokenRow->recipient_email,
            ];
            DB::table('ahg_audit_log')->insert([
                'uuid'           => $this->generateUuid(),
                'user_id'        => null,
                'username'       => null,
                'action'         => self::AUDIT_ACTION_ACCESSED,
                'entity_type'    => 'information_object_share_token',
                'entity_id'      => (int) $tokenRow->information_object_id,
                'module'         => 'share_link',
                'action_name'    => $accessAction,
                'request_method' => 'GET',
                'ip_address'     => $ip,
                'metadata'       => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status'         => str_starts_with($accessAction, 'denied_') ? 'failure' : 'success',
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('ahg-share-link writeAudit failed', ['error' => $e->getMessage()]);
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
