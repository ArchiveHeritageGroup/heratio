<?php
/**
 * NullFaceDetector - default placeholder face detector. Always returns
 * zero faces. Wired in via the service provider so any caller depending
 * on FaceDetectorInterface gets a working object on stock installs.
 *
 * Issue #667 Phase 1.
 *
 * To switch on real detection, an operator (or a follow-up phase) binds a
 * real implementation to the container under the same interface, e.g.
 *
 *   $this->app->bind(FaceDetectorInterface::class, GatewayFaceDetector::class);
 *
 * The Null implementation still goes through the QuotaService gate, so
 * operators can rehearse the quota workflow before the real detector is
 * shipped.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

declare(strict_types=1);

namespace AhgAiServices\Services;

use AhgAiServices\Contracts\FaceDetectorInterface;

final class NullFaceDetector implements FaceDetectorInterface
{
    public function __construct(private QuotaService $quotaService, private CostService $costService)
    {
    }

    public function detect(string $imagePath): array
    {
        // Gate the call through the quota system so face_detect usage
        // shows up in the operator dashboard even with the Null backend.
        $this->quotaService->consume('face_detect');
        $this->costService->record('face_detect', 'null-face-detector', [
            'tokens_in'   => 0,
            'tokens_out'  => 0,
            'duration_ms' => 0,
        ]);

        return [
            'faces'       => [],
            'model'       => 'null-face-detector',
            'duration_ms' => 0,
        ];
    }

    public function health(): bool
    {
        return true;
    }
}
