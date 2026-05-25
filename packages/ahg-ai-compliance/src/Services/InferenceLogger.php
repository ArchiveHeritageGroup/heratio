<?php
/**
 * Heratio - high-level logger used by ahg-ai-services call sites.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Services;

use AhgInferenceReceipts\Receipt;
use AhgInferenceReceipts\ReceiptChain;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

final class InferenceLogger
{
    public function __construct(private ReceiptChain $chain)
    {
    }

    /**
     * Log one inference call. Fingerprints input/output via SHA-256, captures
     * request_id from the observability middleware (#677), user + tenant from
     * Auth. Returns the persisted Receipt for callers that want to surface the
     * id (e.g. include it in API responses for downstream tracing).
     *
     * Failure to log MUST NOT break the inference call. We swallow + log to the
     * regular application log so an operator notices the chain stopped growing.
     *
     * @param array<string,mixed> $extra extra payload columns (latency_ms, tokens_in/out)
     */
    public function log(
        string $service,
        string $modelId,
        ?string $modelVersion,
        string $inputBody,
        string $outputBody,
        array $extra = [],
    ): ?Receipt {
        try {
            $payload = array_merge([
                'service'            => $service,
                'model_id'           => $modelId,
                'model_version'      => $modelVersion,
                'input_fingerprint'  => hash('sha256', $inputBody),
                'output_fingerprint' => hash('sha256', $outputBody),
                'request_id'         => $this->requestId(),
                'user_id'            => Auth::id(),
                'tenant_id'          => $this->tenantId(),
            ], $extra);

            return $this->chain->append($payload);
        } catch (Throwable $e) {
            Log::warning('ai-compliance: inference logger append failed', [
                'service' => $service,
                'model'   => $modelId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function requestId(): ?string
    {
        if (function_exists('app') && app()->bound('request')) {
            $req = app('request');
            if ($req !== null) {
                $rid = $req->header('X-Request-Id') ?: $req->attributes->get('request_id');
                return $rid !== null ? (string) $rid : null;
            }
        }
        return null;
    }

    private function tenantId(): ?int
    {
        if (function_exists('config')) {
            $explicit = config('ahg.tenant_id');
            if ($explicit !== null) {
                return (int) $explicit;
            }
        }
        if (Auth::check()) {
            $user = Auth::user();
            $tid = $user->tenant_id ?? null;
            return $tid === null ? null : (int) $tid;
        }
        return null;
    }
}
