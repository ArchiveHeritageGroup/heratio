<?php

/**
 * EmbeddedMetadataPiiGate - defensive privacy gate applied to the
 * `ahg-embedded-metadata` extension block before it is written into the
 * OCFL inventory.
 *
 * Why a separate gate (not done inside DbEmbeddedMetadataSource)? The
 * source resolver is concerned with "what metadata exists for this
 * object". The privacy decision "may we preserve it as-is, or must
 * something be redacted" is a different concern that lives in the
 * ahg-privacy package (#751). We surface it as an interface so a
 * test can stub a known answer without booting Laravel + MySQL.
 *
 * The default DB-backed implementation reads `ahg_pii_finding_embedded`
 * (#751 schema) and removes GPS-shaped EXIF fields when a pending
 * finding is recorded against the source. When the table itself is
 * absent (#751 not yet shipped, or stripped test DB), the gate
 * fails open with a Log::warning so the OCFL write is never blocked
 * by an upstream-package outage - same "fail open with audit trail"
 * pattern used by EmbeddedMetadataContextService in ahg-ai-services.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Metadata;

interface EmbeddedMetadataPiiGate
{
    /**
     * Inspect (and possibly mutate) the extension block for an OCFL
     * object id. Implementations MUST NOT throw - return the input
     * unchanged + log instead so the OCFL write survives privacy-side
     * outages.
     *
     * Return null to suppress the block entirely (rare; used only when
     * EVERY meaningful field is redacted-out). Return an empty array
     * with the same meaning. Otherwise return the redacted block.
     *
     * @param array<string,mixed> $block
     * @return array<string,mixed>|null
     */
    public function redact(string $ocflObjectId, array $block): ?array;
}
