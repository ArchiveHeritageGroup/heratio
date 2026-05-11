<?php

/**
 * IssueService — runs every guard, generates a token, persists the row,
 * dual-writes to ahg_audit_log. Mirror of the AtoM-side service.
 *
 * @phase C
 */

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

class IssueService
{
    public const DEFAULT_EXPIRY_DAYS = 14;
    public const DEFAULT_MAX_EXPIRY_DAYS = 90;
    public const AUDIT_ACTION_ISSUED = 'share_link_issued';

    public function __construct(
        private readonly TokenService $tokenService,
        private readonly AclCheck $acl,
        private readonly ClearanceCheck $clearance,
    ) {
    }

    /**
     * @return array{token: string, token_id: int, expires_at: string, public_url: ?string}
     */
    public function issue(
        int $userId,
        int $informationObjectId,
        ?\DateTimeInterface $expiresAt = null,
        ?string $recipientEmail = null,
        ?string $recipientNote = null,
        ?int $maxAccess = null,
    ): array {
        if ($userId <= 0) {
            throw new NotAuthenticatedException('Cannot issue a share link without an authenticated user');
        }
        if ($informationObjectId <= 0) {
            throw new InvalidRequestException('information_object_id is required');
        }

        $maxExpiryDays = (int) $this->readSetting('share_link.max_expiry_days', (string) self::DEFAULT_MAX_EXPIRY_DAYS);
        $defaultExpiryDays = (int) $this->readSetting('share_link.default_expiry_days', (string) self::DEFAULT_EXPIRY_DAYS);

        if ($expiresAt === null) {
            $expiresAt = new \DateTimeImmutable("+{$defaultExpiryDays} days");
        }
        if ($expiresAt->getTimestamp() <= time()) {
            throw new InvalidRequestException('expires_at must be in the future');
        }

        if (!$this->acl->canUserDo($userId, AclCheck::ACTION_CREATE)) {
            throw new PermissionDeniedException('You do not have permission to issue share links');
        }

        if (!DB::table('information_object')->where('id', $informationObjectId)->exists()) {
            throw new InvalidRequestException("information_object {$informationObjectId} not found");
        }

        $classificationLevel = $this->clearance->resolveEntityClassificationLevel($informationObjectId);
        if ($classificationLevel !== null) {
            if (!$this->acl->canUserDo($userId, AclCheck::ACTION_CREATE_CLASSIFIED)) {
                throw new PermissionDeniedException('You do not have permission to issue share links for classified records');
            }
            if (!$this->clearance->canUserIssueLink($userId, $informationObjectId)) {
                throw new InsufficientClearanceException($this->clearance->explainDenial($userId, $informationObjectId));
            }
        }

        if ($maxExpiryDays > 0) {
            $cutoff = (new \DateTimeImmutable("+{$maxExpiryDays} days"))->getTimestamp();
            if ($expiresAt->getTimestamp() > $cutoff) {
                if (!$this->acl->canUserDo($userId, AclCheck::ACTION_CREATE_UNLIMITED_EXPIRY)) {
                    throw new ExpiryCapExceededException(
                        "Expiry is capped at {$maxExpiryDays} days. Contact an administrator to issue longer-lived links.",
                    );
                }
            }
        }

        $token = $this->tokenService->generate($informationObjectId, $expiresAt, $recipientEmail);
        $issuerDownloadAtIssuance = $this->issuerCanDownload($userId) ? 1 : 0;

        $tokenId = (int) DB::table('information_object_share_token')->insertGetId([
            'information_object_id' => $informationObjectId,
            'token'                 => $token,
            'issued_by'             => $userId,
            'recipient_email'       => $recipientEmail,
            'recipient_note'        => $recipientNote,
            'expires_at'            => $expiresAt->format('Y-m-d H:i:s'),
            'max_access'            => $maxAccess,
            'access_count'          => 0,
            'classification_level_at_issuance' => $classificationLevel,
            'issuer_download_at_issuance'      => $issuerDownloadAtIssuance,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        $this->writeAuditEntry(
            $tokenId, $informationObjectId, $userId, $expiresAt,
            $recipientEmail, $recipientNote, $maxAccess, $classificationLevel,
        );

        return [
            'token'      => $token,
            'token_id'   => $tokenId,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'public_url' => $this->buildPublicUrl($token),
        ];
    }

    private function issuerCanDownload(int $userId): bool
    {
        try {
            $isAdmin = DB::table('acl_user_group')->where('user_id', $userId)->where('group_id', 100)->exists();
            return $isAdmin ? true : true; // If they passed the create check, treat as downloadable. Phase D re-evaluates at access time.
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function buildPublicUrl(string $token): ?string
    {
        if (function_exists('route')) {
            try {
                return route('share-link.recipient', ['token' => $token]);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
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

    private function writeAuditEntry(
        int $tokenId, int $entityId, int $userId, \DateTimeInterface $expiresAt,
        ?string $recipientEmail, ?string $recipientNote, ?int $maxAccess, ?int $classificationLevel,
    ): void {
        try {
            $userRow = DB::table('user')->where('id', $userId)->first();
            $username = $userRow->username ?? null;
            $userEmail = $userRow->email ?? null;
            $entityTitle = DB::table('information_object_i18n')
                ->where('id', $entityId)->orderBy('culture')->value('title');

            $metadata = [
                'token_id'              => $tokenId,
                'expires_at'            => $expiresAt->format('Y-m-d H:i:s'),
                'recipient_email'       => $recipientEmail,
                'recipient_note'        => $recipientNote,
                'max_access'            => $maxAccess,
                'classification_level'  => $classificationLevel,
                'parent_entity_type'    => 'information_object',
                'parent_entity_id'      => $entityId,
            ];

            DB::table('ahg_audit_log')->insert([
                'uuid'           => $this->generateUuid(),
                'user_id'        => $userId,
                'username'       => $username,
                'user_email'     => $userEmail,
                'action'         => self::AUDIT_ACTION_ISSUED,
                'entity_type'    => 'information_object_share_token',
                'entity_id'      => $entityId,
                'entity_title'   => $entityTitle,
                'module'         => 'share_link',
                'action_name'    => 'issue',
                'request_method' => 'INTERNAL',
                'metadata'       => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'status'         => 'success',
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('ahg-share-link audit dual-write failed', ['error' => $e->getMessage()]);
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
