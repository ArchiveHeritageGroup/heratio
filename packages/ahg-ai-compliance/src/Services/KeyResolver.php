<?php
/**
 * Heratio - resolves a Ed25519 kid to its public-key bytes via ai_inference_key.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Services;

use Illuminate\Support\Facades\DB;

final class KeyResolver
{
    /** @var array<string,string> in-memory cache keyed by kid */
    private array $cache = [];

    public function publicKey(string $kid): ?string
    {
        if (array_key_exists($kid, $this->cache)) {
            return $this->cache[$kid];
        }

        $row = DB::table('ai_inference_key')
            ->where('kid', $kid)
            ->first();
        if ($row === null) {
            return null;
        }

        $bytes = (string) $row->public_key;
        $this->cache[$kid] = $bytes;
        return $bytes;
    }

    public function activeKid(): ?string
    {
        $row = DB::table('ai_inference_key')
            ->where('active', 1)
            ->orderByDesc('id')
            ->first();
        return $row === null ? null : (string) $row->kid;
    }

    public function register(string $kid, string $publicKeyBytes, bool $active): void
    {
        if ($active) {
            DB::table('ai_inference_key')
                ->where('active', 1)
                ->update(['active' => 0, 'rotated_at' => now()]);
        }

        DB::table('ai_inference_key')->updateOrInsert(
            ['kid' => $kid],
            [
                'public_key' => $publicKeyBytes,
                'alg'        => 'ed25519',
                'active'     => $active ? 1 : 0,
                'created_at' => now(),
            ],
        );

        $this->cache[$kid] = $publicKeyBytes;
    }
}
