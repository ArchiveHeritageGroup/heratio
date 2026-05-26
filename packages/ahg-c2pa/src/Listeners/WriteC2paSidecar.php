<?php
/**
 * Heratio - listener that catches AiOutputProduced and emits a signed
 * C2PA manifest (sidecar JSON, optionally embedded JPEG), persisting a
 * row in ahg_c2pa_manifest and linking the resulting hash into the
 * EU AI Act Article 12 inference receipt chain.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Listeners;

use AhgC2pa\Events\AiOutputProduced;
use AhgC2pa\Services\C2paService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class WriteC2paSidecar
{
    public function __construct(private C2paService $c2pa)
    {
    }

    public function handle(AiOutputProduced $event): void
    {
        try {
            $manifest = $this->c2pa->manifestForAiSuggestion(
                informationObjectId: $event->informationObjectId,
                action: $event->action,
                modelId: $event->modelId,
                modelVersion: $event->modelVersion,
                output: $event->output,
                assetPath: $event->artefactPath,
            );

            $signed = $this->c2pa->signManifest($manifest);

            $sidecarPath = null;
            if ($event->artefactPath !== null && is_readable($event->artefactPath)) {
                if (preg_match('/\.jpe?g$/i', $event->artefactPath)) {
                    $sidecarPath = $this->c2pa->embedInJpeg($event->artefactPath, $signed);
                } else {
                    $sidecarPath = $this->c2pa->sidecar($signed, $event->artefactPath);
                }
            }

            $rowId = $this->c2pa->persist(
                signedManifest: $signed,
                informationObjectId: $event->informationObjectId,
                action: $event->action,
                modelId: $event->modelId,
                modelVersion: $event->modelVersion,
                sidecarPath: $sidecarPath,
            );

            // Best-effort: link the C2PA manifest hash into the Article 12
            // chain. We resolve InferenceLogger lazily so this listener
            // works in installs that don't have ahg-ai-compliance booted.
            $this->logToInferenceChain($event, $signed, $rowId);
        } catch (Throwable $e) {
            Log::warning('c2pa: WriteC2paSidecar failed', [
                'io'    => $event->informationObjectId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string,mixed> $signed
     */
    private function logToInferenceChain(AiOutputProduced $event, array $signed, ?int $rowId): void
    {
        if (!class_exists(\AhgAiCompliance\Services\InferenceLogger::class)) {
            return;
        }
        if (!function_exists('app')) {
            return;
        }
        try {
            /** @var \AhgAiCompliance\Services\InferenceLogger $logger */
            $logger = app(\AhgAiCompliance\Services\InferenceLogger::class);

            $claimDigest = hash(
                'sha256',
                \AhgInferenceReceipts\JcsEncoder::encode($signed['claim'] ?? []),
            );

            $logger->log(
                service: 'c2pa',
                modelId: $event->modelId,
                modelVersion: $event->modelVersion,
                inputBody: (string) $event->informationObjectId,
                outputBody: $event->output,
                extra: [
                    'c2pa_action'         => $event->action,
                    'c2pa_claim_digest'   => $claimDigest,
                    'c2pa_kid'            => $signed['claim_signature']['kid'] ?? null,
                    'c2pa_manifest_id'    => $rowId,
                ],
            );
        } catch (Throwable $e) {
            // Article 12 chain logging is best-effort - never break the
            // manifest emission because the chain hiccupped.
            Log::info('c2pa: inference-chain link skipped', ['error' => $e->getMessage()]);
        }
    }
}
