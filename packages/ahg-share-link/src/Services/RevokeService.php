<?php

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

/**
 * RevokeService — revoke an existing share-link token.
 *
 * Guards (in order):
 *   1. user_id present (anonymous can never revoke)
 *   2. ACL — token issued_by == user OR user has share_link.revoke_others
 *   3. Token must exist (404 reported by caller via TokenNotFound)
 *
 * Idempotent: revoking an already-revoked token returns the existing row
 * without writing a duplicate audit entry.
 *
 * Writes to ahg_audit_log with action='share_link_revoked'.
 *
 * @phase G
 */
class RevokeService
{
    public const AUDIT_ACTION_REVOKED = 'share_link_revoked';

    /**
     * @return array{revoked: bool, was_already_revoked: bool, token_row: object}
     */
    public function revoke(int $userId, int $tokenId, ?string $reason = null): array
    {
        if ($userId <= 0) {
            throw new NotAuthenticatedException('Cannot revoke a share link without an authenticated user');
        }
        if ($tokenId <= 0) {
            throw new InvalidRequestException('token_id is required');
        }

        $row = DB::table('information_object_share_token')->where('id', $tokenId)->first();
        if (!$row) {
            throw new InvalidRequestException("share-link token #{$tokenId} not found");
        }

        $acl = new AclCheck();
        $isOwner = (int) $row->issued_by === $userId;
        if (!$isOwner) {
            if (!$acl->canUserDo($userId, AclCheck::ACTION_REVOKE_OTHERS)) {
                throw new PermissionDeniedException("You do not have permission to revoke another user's share link");
            }
        }

        // Idempotent: short-circuit if already revoked.
        if (!empty($row->revoked_at)) {
            return ['revoked' => false, 'was_already_revoked' => true, 'token_row' => $row];
        }

        $now = date('Y-m-d H:i:s');
        DB::table('information_object_share_token')
            ->where('id', $tokenId)
            ->update(['revoked_at' => $now, 'updated_at' => $now]);

        $row = DB::table('information_object_share_token')->where('id', $tokenId)->first();
        $this->writeAuditEntry($userId, $row, $reason);

        return ['revoked' => true, 'was_already_revoked' => false, 'token_row' => $row];
    }

    private function writeAuditEntry(int $userId, object $row, ?string $reason): void
    {
        try {
            $userRow = DB::table('user')->where('id', $userId)->first();
            $entityTitle = DB::table('information_object_i18n')
                ->where('id', $row->information_object_id)
                ->orderBy('culture')
                ->value('title');

            $metadata = [
                'token_id'           => (int) $row->id,
                'parent_entity_type' => 'information_object',
                'parent_entity_id'   => (int) $row->information_object_id,
                'recipient_email'    => $row->recipient_email,
                'expires_at'         => $row->expires_at,
                'access_count'       => (int) $row->access_count,
                'was_owner'          => (int) $row->issued_by === $userId,
                'reason'             => $reason,
            ];

            DB::table('ahg_audit_log')->insert([
                'uuid'           => $this->generateUuid(),
                'user_id'        => $userId,
                'username'       => $userRow->username ?? null,
                'user_email'     => $userRow->email ?? null,
                'action'         => self::AUDIT_ACTION_REVOKED,
                'entity_type'    => 'information_object_share_token',
                'entity_id'      => (int) $row->information_object_id,
                'entity_title'   => $entityTitle,
                'module'         => 'share_link',
                'action_name'    => 'revoke',
                'request_method' => 'POST',
                'metadata'       => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status'         => 'success',
                'created_at'     => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('ahgTimeLimitedShareLinkPlugin revoke audit failed: ' . $e->getMessage());
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
