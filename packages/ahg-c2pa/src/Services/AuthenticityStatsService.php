<?php
/**
 * Heratio - C2PA authenticity coverage statistics (issue #1209, north star).
 *
 * Read-only aggregation over the content-credentials layer that backs the
 * public "Authenticity" front door. It answers, at an institution level, the
 * questions the per-record /verify pages answer for a single object: how much
 * of our digitised material carries verifiable content credentials, how much
 * is cryptographically signed, and under whose key. Never writes; every table
 * is schema-guarded so it returns an honest "not enabled" shape on installs
 * without the c2pa layer (or before anything has been signed).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Aggregates the ahg_c2pa_provenance / ahg_c2pa_manifest layer (with
 * digital_object as the denominator) into a single coverage snapshot for the
 * public authenticity dashboard. Distinct from ProvenanceRecordService, which
 * owns per-record write + verify; this service is purely a read-only reporter.
 */
final class AuthenticityStatsService
{
    /** digital_object.usage_id for a master file (taxonomy 47), mirrors the backfill command. */
    private const USAGE_MASTER = 140;

    /**
     * Whole-institution coverage snapshot for the public authenticity page.
     *
     * @return array{
     *     enabled: bool,
     *     reason: ?string,
     *     can_sign: bool,
     *     capability_summary: string,
     *     total_masters: int,
     *     signed_records: int,
     *     unsigned_records: int,
     *     records_total: int,
     *     records_with_credentials: int,
     *     records_signed: int,
     *     covered_masters: int,
     *     coverage_pct: float,
     *     verifiable_pct: float,
     *     manifests_total: int,
     *     last_signed_at: ?string,
     *     issuers: list<array{kid: string, count: int}>
     * }
     */
    public function snapshot(): array
    {
        $base = [
            'enabled'                  => false,
            'reason'                   => null,
            'can_sign'                 => $this->canSign(),
            'capability_summary'       => $this->capabilitySummary(),
            'total_masters'            => 0,
            'signed_records'           => 0,
            'unsigned_records'         => 0,
            'records_total'            => 0,
            'records_with_credentials' => 0,
            'records_signed'           => 0,
            'covered_masters'          => 0,
            'coverage_pct'             => 0.0,
            'verifiable_pct'           => 0.0,
            'manifests_total'          => 0,
            'last_signed_at'           => null,
            'issuers'                  => [],
        ];

        // The provenance table is the heart of the feature. Without it the
        // c2pa layer is simply not installed on this host.
        if (!Schema::hasTable('ahg_c2pa_provenance')) {
            $base['reason'] = 'not-installed';

            return $base;
        }

        try {
            $base['total_masters'] = $this->masterCount();

            $prov = DB::table('ahg_c2pa_provenance');
            $base['records_total']    = (int) (clone $prov)->count();
            $base['signed_records']   = (int) (clone $prov)->whereNotNull('manifest_id')->count();
            $base['records_signed']   = $base['signed_records'];
            $base['unsigned_records'] = max(0, $base['records_total'] - $base['signed_records']);

            // Distinct information objects that carry any content credentials,
            // and the subset whose credentials are signed.
            $base['records_with_credentials'] = (int) (clone $prov)
                ->distinct()
                ->count('information_object_id');

            // Distinct master digital objects covered by a signed record - the
            // numerator for "% of our masters that are verifiable".
            $base['covered_masters'] = (int) (clone $prov)
                ->whereNotNull('digital_object_id')
                ->whereNotNull('manifest_id')
                ->distinct()
                ->count('digital_object_id');

            $base['last_signed_at'] = $this->lastSignedAt();

            if ($base['total_masters'] > 0) {
                $base['coverage_pct']   = round($base['covered_masters'] / $base['total_masters'] * 100, 1);
                $base['verifiable_pct'] = $base['coverage_pct'];
            }
        } catch (Throwable) {
            // DB unreachable / unexpected schema: fall back to the honest
            // "not enabled" surface rather than a 500.
            $base['reason'] = 'unavailable';

            return $base;
        }

        // Manifest-level detail (issuer / signing identity). Optional table.
        if (Schema::hasTable('ahg_c2pa_manifest')) {
            try {
                $base['manifests_total'] = (int) DB::table('ahg_c2pa_manifest')->count();
                $base['issuers']         = $this->issuers();
            } catch (Throwable) {
                // leave manifest detail at its defaults
            }
        }

        // "Enabled" means the layer is installed AND something is signed; an
        // installed-but-empty layer renders the graceful "ready, nothing
        // signed yet" state, not the dashboard.
        $base['enabled'] = $base['signed_records'] > 0;
        if (!$base['enabled'] && $base['reason'] === null) {
            $base['reason'] = $base['records_total'] > 0 ? 'unsigned-only' : 'no-records';
        }

        return $base;
    }

    /**
     * Count master digital objects (the denominator). Mirrors the master
     * selection in ahg:c2pa-provenance-backfill: parentless rows whose usage is
     * "master" (140) or unset.
     */
    private function masterCount(): int
    {
        if (!Schema::hasTable('digital_object')) {
            return 0;
        }

        return (int) DB::table('digital_object')
            ->whereNull('parent_id')
            ->where(function ($w) {
                $w->where('usage_id', self::USAGE_MASTER)->orWhereNull('usage_id');
            })
            ->count();
    }

    /**
     * Most recent signing timestamp across the signed provenance records.
     */
    private function lastSignedAt(): ?string
    {
        $row = DB::table('ahg_c2pa_provenance')
            ->whereNotNull('manifest_id')
            ->orderByDesc('updated_at')
            ->first(['updated_at', 'created_at']);

        if ($row === null) {
            return null;
        }

        $ts = $row->updated_at ?? $row->created_at ?? null;

        return is_string($ts) && $ts !== '' ? $ts : null;
    }

    /**
     * Signing-key identities (kid) that have issued content credentials, with
     * how many manifests each signed. The kid resolves through ai_inference_key
     * to the institution's Ed25519 public key (see ProvenanceRecordService).
     *
     * @return list<array{kid: string, count: int}>
     */
    private function issuers(): array
    {
        $rows = DB::table('ahg_c2pa_manifest')
            ->select('kid', DB::raw('COUNT(*) as c'))
            ->whereNotNull('kid')
            ->where('kid', '<>', '')
            ->groupBy('kid')
            ->orderByDesc('c')
            ->limit(10)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = ['kid' => (string) $r->kid, 'count' => (int) $r->c];
        }

        return $out;
    }

    /**
     * Whether this host can cryptographically sign at all (ext-sodium). Kept
     * here (and not via ProvenanceRecordService) so the read-only stats path
     * has no dependency on the write/sign service.
     */
    private function canSign(): bool
    {
        return extension_loaded('sodium') || function_exists('sodium_crypto_sign');
    }

    private function capabilitySummary(): string
    {
        return $this->canSign()
            ? 'Content credentials are sealed with an Ed25519 signature and re-checked live on every view.'
            : 'Signing is unavailable on this host (ext-sodium missing); provenance can be recorded but not cryptographically sealed.';
    }
}
