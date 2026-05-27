<?php

/**
 * DbEmbeddedMetadataPiiGate - default PII gate over `ahg_pii_finding_embedded`.
 *
 * Walks `digital_object` rows for the IO behind the OCFL object id, asks the
 * #751 findings table whether any pending / escalated `gps_coordinate`
 * finding exists, and strips GPS-shaped EXIF fields when it does. The
 * preservation policy is "we record the rest of the EXIF block, but we do
 * NOT permanently inscribe a GPS coordinate the privacy team has flagged
 * for review" - once the finding is resolved (cleared or accepted) the
 * next OCFL version will carry the coordinate again.
 *
 * GPS-shaped keys (case-insensitive prefix match): `GPS*`, `gps*`,
 * `Geolocation*`, `Location*`. The conservative bias is intentional - a
 * false-positive redaction is harmless to fixity (it just omits a field);
 * a false-negative would inscribe PII into an immutable inventory.
 *
 * The table-absent path returns the input unchanged and logs once per
 * call (matches the ahg-ai-services pattern).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Metadata;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class DbEmbeddedMetadataPiiGate implements EmbeddedMetadataPiiGate
{
    /**
     * Field-name prefixes considered GPS-shaped. Matched
     * case-insensitively against the EXIF block keys.
     */
    private const GPS_PREFIXES = ['gps', 'geolocation', 'location'];

    public function redact(string $ocflObjectId, array $block): ?array
    {
        try {
            if (! Schema::hasTable('ahg_pii_finding_embedded')) {
                Log::warning(
                    'ahg-ocfl PII gate: ahg_pii_finding_embedded missing, '
                    .'proceeding without privacy gate (issue #751 not yet shipped)'
                );
                return $block;
            }

            $ioId = $this->resolveIoId($ocflObjectId);
            if ($ioId === null) {
                return $block;
            }

            $doIds = DB::table('digital_object')->where('object_id', $ioId)->pluck('id')->all();
            if ($doIds === []) {
                return $block;
            }

            $hasPending = DB::table('ahg_pii_finding_embedded')
                ->whereIn('digital_object_id', $doIds)
                ->where('pii_type', 'gps_coordinate')
                ->whereIn('resolution_status', ['pending', 'escalated'])
                ->exists();

            if (! $hasPending) {
                return $block;
            }

            return self::stripGpsFromBlock($block);
        } catch (\Throwable $e) {
            // Fail open + audit trail - never break OCFL write on
            // privacy-side outage.
            try {
                Log::warning('ahg-ocfl PII gate probe failed: '.$e->getMessage());
            } catch (\Throwable) {
                // Logging itself is best-effort.
            }
            return $block;
        }
    }

    /**
     * Remove GPS-shaped keys from the EXIF sub-block. Pure function -
     * exposed as static so tests can exercise the redaction without
     * touching the database.
     *
     * @param array<string,mixed> $block
     * @return array<string,mixed>|null
     */
    public static function stripGpsFromBlock(array $block): ?array
    {
        if (! isset($block['exif']) || ! is_array($block['exif'])) {
            return $block;
        }
        $exif = $block['exif'];
        foreach (array_keys($exif) as $k) {
            $lc = strtolower((string) $k);
            foreach (self::GPS_PREFIXES as $prefix) {
                if (str_starts_with($lc, $prefix)) {
                    unset($exif[$k]);
                    break;
                }
            }
        }
        if ($exif === []) {
            unset($block['exif']);
        } else {
            $block['exif'] = $exif;
        }
        // If we redacted ALL three sub-blocks the caller will drop the
        // extension; signal by returning null.
        $hasAny = isset($block['exif']) || isset($block['iptc']) || isset($block['xmp']);
        return $hasAny ? $block : null;
    }

    /**
     * Reverse the `urn:heratio:io:{id}` form via the upsert map or by
     * parsing the URN. Same fallback chain as DbEmbeddedMetadataSource.
     */
    private function resolveIoId(string $ocflObjectId): ?int
    {
        try {
            $row = DB::table('ahg_ocfl_object_map')
                ->where('ocfl_object_id', $ocflObjectId)
                ->first();
            if ($row && isset($row->information_object_id)) {
                return (int) $row->information_object_id;
            }
        } catch (\Throwable) {
            // Map absent - fall through to URN parse.
        }
        if (preg_match('/^urn:heratio:io:(\d+)$/i', $ocflObjectId, $m)) {
            return (int) $m[1];
        }
        return null;
    }
}
