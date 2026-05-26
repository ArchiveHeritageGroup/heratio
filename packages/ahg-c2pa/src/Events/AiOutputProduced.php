<?php
/**
 * Heratio - event dispatched by AI call sites once they have produced output.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Events;

/**
 * Fire-and-forget event. ahg-ai-services dispatches one of these per
 * suggestion / summary / translation / HTR run; the WriteC2paSidecar
 * listener does the manifest work asynchronously.
 *
 * The integration shape (one line in ahg-ai-services, once that package
 * unlocks for unrelated work):
 *
 *   event(new \AhgC2pa\Events\AiOutputProduced(
 *       informationObjectId: $ioId,
 *       action: 'ai-generated',
 *       modelId: $modelId,
 *       modelVersion: $modelVersion,
 *       output: $generatedText,
 *       artefactPath: null,
 *   ));
 */
final class AiOutputProduced
{
    public function __construct(
        public readonly int $informationObjectId,
        public readonly string $action,
        public readonly string $modelId,
        public readonly ?string $modelVersion,
        public readonly string $output,
        public readonly ?string $artefactPath = null,
    ) {
    }
}
