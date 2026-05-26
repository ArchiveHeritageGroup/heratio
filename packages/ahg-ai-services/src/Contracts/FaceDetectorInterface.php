<?php
/**
 * FaceDetectorInterface - the call shape every face-detect backend must
 * implement.
 *
 * Issue #667 Phase 1.
 *
 * Real backends (e.g. a RetinaFace / YuNet model behind the AHG AI gateway
 * or a self-hosted FastAPI endpoint) implement this and bind themselves to
 * the container key `face-detector` in their own service provider. Heratio
 * ships with NullFaceDetector as the default - it always returns an
 * empty face list - so unconfigured installs never throw.
 *
 * The detector contract intentionally returns plain arrays (not a value
 * object) to keep the wire format identical to the gateway's JSON
 * response: each face is {x, y, w, h, score, attributes?}.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 */

declare(strict_types=1);

namespace AhgAiServices\Contracts;

interface FaceDetectorInterface
{
    /**
     * Detect faces in the image at $imagePath.
     *
     * @return array{
     *     faces: list<array{x:int,y:int,w:int,h:int,score:float,attributes?:array<string,mixed>}>,
     *     model: string,
     *     duration_ms: int
     * }
     */
    public function detect(string $imagePath): array;

    /** Whether the configured backend is reachable. */
    public function health(): bool;
}
