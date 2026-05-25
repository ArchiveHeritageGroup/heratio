<?php
/**
 * Heratio - serves the AI inference signing public key to external verifiers.
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class PublicKeyController
{
    public function show(): JsonResponse
    {
        $rows = DB::table('ai_inference_key')
            ->orderByDesc('created_at')
            ->get(['kid', 'public_key', 'alg', 'active', 'rotated_at', 'created_at']);

        $keys = [];
        foreach ($rows as $row) {
            $pubBytes = (string) $row->public_key;
            $keys[] = [
                'kid'        => (string) $row->kid,
                'alg'        => (string) $row->alg,
                'active'     => (bool) $row->active,
                'public_key' => [
                    'hex'        => bin2hex($pubBytes),
                    'base64'     => base64_encode($pubBytes),
                    'base64url'  => rtrim(strtr(base64_encode($pubBytes), '+/', '-_'), '='),
                ],
                'jwk' => [
                    'kty' => 'OKP',
                    'crv' => 'Ed25519',
                    'kid' => (string) $row->kid,
                    'x'   => rtrim(strtr(base64_encode($pubBytes), '+/', '-_'), '='),
                ],
                'rotated_at' => $row->rotated_at,
                'created_at' => $row->created_at,
            ];
        }

        return response()->json([
            'issuer'  => url('/'),
            'purpose' => 'EU AI Act Article 12 record-keeping. Public keys for verifying tamper-evident inference receipts.',
            'spec'    => 'https://github.com/ArchiveHeritageGroup/heratio/blob/main/packages/ahg-inference-receipts/README.md',
            'keys'    => $keys,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
