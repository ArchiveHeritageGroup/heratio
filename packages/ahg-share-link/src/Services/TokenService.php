<?php

/**
 * TokenService — generates and parses time-limited share-link tokens.
 *
 * Mirror of the AtoM-side service. Byte-equivalent token generation for the
 * same input + same hmac_secret — useful if a deployment ever wants to migrate
 * tokens between surfaces.
 *
 * @phase B
 */

namespace AhgShareLink\Services;

use Illuminate\Support\Facades\DB;

class TokenService
{
    private const SECRET_SETTING_KEY = 'share_link.hmac_secret';
    private const HMAC_ALGO = 'sha256';

    public function generate(int $informationObjectId, \DateTimeInterface $expiresAt, ?string $recipientEmail = null): string
    {
        $nonce = bin2hex(random_bytes(16));
        $input = sprintf(
            '%d|%d|%s|%s',
            $informationObjectId,
            $expiresAt->getTimestamp(),
            (string) $recipientEmail,
            $nonce,
        );
        $digest = hash_hmac(self::HMAC_ALGO, $input, $this->getSecret(), true);
        return $this->base64urlEncode($digest);
    }

    public function extractFromUrl(string $url): ?string
    {
        if (preg_match('#/share/([A-Za-z0-9_\-]{32,64})\b#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#^[A-Za-z0-9_\-]{32,64}$#', trim($url))) {
            return trim($url);
        }
        return null;
    }

    public function lookup(string $token): ?object
    {
        $row = DB::table('information_object_share_token')->where('token', $token)->first();
        return $row ?: null;
    }

    private function getSecret(): string
    {
        $row = DB::table('ahg_settings')->where('setting_key', self::SECRET_SETTING_KEY)->first();
        if ($row && is_string($row->setting_value) && $row->setting_value !== '') {
            return $row->setting_value;
        }
        $secret = bin2hex(random_bytes(32));
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_key' => self::SECRET_SETTING_KEY],
            [
                'setting_value' => $secret,
                'setting_type'  => 'string',
                'setting_group' => 'share_link',
                'description'   => 'Auto-generated HMAC secret used by the TokenService. Rotate via secret-rotation runbook (future enhancement).',
                'is_sensitive'  => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        );
        return $secret;
    }

    private function base64urlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
