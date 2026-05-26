<?php
/**
 * Heratio - validate a stored C2PA manifest (re-hash assertions, validate
 * claim signature, walk the ingredient chain).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Console\Commands;

use AhgC2pa\Manifest\Assertion;
use AhgC2pa\Manifest\C2paSigner;
use AhgInferenceReceipts\JcsEncoder;
use Illuminate\Console\Command;
use Throwable;

final class C2paVerifyCommand extends Command
{
    protected $signature = 'c2pa:verify
        {manifest-path : Absolute path to a .c2pa.json sidecar (or signed-manifest JSON file)}
        {--public-key= : Override: hex-encoded 32-byte raw Ed25519 public key (skips DB key lookup)}';

    protected $description = 'Verify a C2PA manifest: re-hash assertions, validate claim signature, walk ingredients.';

    public function handle(): int
    {
        $path = (string) $this->argument('manifest-path');
        if (!is_readable($path)) {
            $this->error("c2pa:verify: cannot read {$path}");
            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            $this->error("c2pa:verify: empty manifest at {$path}");
            return self::FAILURE;
        }

        try {
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error('c2pa:verify: invalid JSON: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!is_array($manifest)) {
            $this->error('c2pa:verify: top-level manifest must be an object');
            return self::FAILURE;
        }

        $failures = 0;

        $assertions = $manifest['assertions'] ?? [];
        $claimRefs = $manifest['claim']['assertions'] ?? [];
        if (!is_array($assertions) || !is_array($claimRefs)) {
            $this->error('c2pa:verify: manifest missing assertions / claim.assertions array');
            return self::FAILURE;
        }

        $this->line('-- Re-hashing assertions --');
        foreach ($assertions as $a) {
            if (!is_array($a) || !isset($a['label'], $a['data'])) {
                $this->error('  ! assertion missing label/data');
                $failures++;
                continue;
            }
            $assertion = new Assertion((string) $a['label'], (array) $a['data'], (int) ($a['instance'] ?? 1));
            $hash = $assertion->hashHex();

            $matched = false;
            foreach ($claimRefs as $ref) {
                if (!is_array($ref)) {
                    continue;
                }
                if (($ref['url'] ?? null) === $assertion->uri()) {
                    if (($ref['hash'] ?? null) === $hash) {
                        $matched = true;
                    }
                    break;
                }
            }
            if ($matched) {
                $this->info("  OK  {$assertion->uri()}");
            } else {
                $this->error("  !!  {$assertion->uri()} (hash mismatch or missing in claim)");
                $failures++;
            }
        }

        $this->line('-- Verifying claim signature --');
        $publicKeyOverride = $this->option('public-key');
        $resolver = function (string $kid) use ($publicKeyOverride): ?string {
            if (is_string($publicKeyOverride) && $publicKeyOverride !== '') {
                if (!ctype_xdigit($publicKeyOverride) || strlen($publicKeyOverride) !== 64) {
                    return null;
                }
                return hex2bin($publicKeyOverride) ?: null;
            }
            return $this->resolveKidFromDb($kid);
        };

        $sigOk = false;
        try {
            $sigOk = C2paSigner::verify($manifest, $resolver);
        } catch (Throwable $e) {
            $this->error('  !! signature verify threw: ' . $e->getMessage());
        }
        if ($sigOk) {
            $this->info('  OK  claim_signature verifies under kid=' . ($manifest['claim_signature']['kid'] ?? '?'));
        } else {
            $this->error('  !!  claim_signature did NOT verify');
            $failures++;
        }

        $this->line('-- Ingredient chain --');
        $ingredientCount = 0;
        foreach ($assertions as $a) {
            if (($a['label'] ?? '') === 'c2pa.ingredients') {
                foreach (($a['data']['ingredients'] ?? []) as $ingredient) {
                    $ingredientCount++;
                    $title = $ingredient['title'] ?? '(untitled)';
                    $hash = $ingredient['hash'] ?? '?';
                    $this->line("  - {$title}  sha256={$hash}");
                }
            }
        }
        if ($ingredientCount === 0) {
            $this->line('  (no ingredients declared)');
        }

        if ($failures === 0) {
            $this->info("\nc2pa:verify PASSED");
            return self::SUCCESS;
        }

        $this->error("\nc2pa:verify FAILED ({$failures} problem(s))");
        return self::FAILURE;
    }

    private function resolveKidFromDb(string $kid): ?string
    {
        // 1) Prefer the registry table (populated by ahg-ai-compliance).
        try {
            if (class_exists(\Illuminate\Support\Facades\DB::class)) {
                $row = \Illuminate\Support\Facades\DB::table('ai_inference_key')
                    ->where('kid', $kid)
                    ->first(['public_key']);
                if ($row !== null && is_string($row->public_key) && $row->public_key !== '') {
                    return $row->public_key;
                }
            }
        } catch (Throwable) {
            // table missing or DB down - fall through to filesystem
        }

        // 2) Fall back to the on-disk signing pubkey. Works on installs
        //    that have C2PA without the Article 12 compliance package.
        if (!function_exists('storage_path')) {
            return null;
        }
        $pkPath = storage_path('keys/inference-signing.pk');
        if (!is_readable($pkPath)) {
            return null;
        }
        $raw = @file_get_contents($pkPath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $candidateKid = substr(hash('sha256', $raw), 0, 16);
        return $candidateKid === $kid ? $raw : null;
    }
}
