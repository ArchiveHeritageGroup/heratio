<?php

/*
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems. Part of Heratio.
 * GNU AGPL v3 or later. See <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Services\Concerns;

/**
 * Resolve the display name of the default AI model (via ahg-ai-services'
 * LlmService), or the generic gateway label. Byte-identical in 5 research
 * copilot-style services. (AnalysisBridgeService has a variant, left alone.)
 */
trait ResolvesAiModel
{
    protected function resolveAiModel(): string
    {
        try {
            if (class_exists(\AhgAiServices\Services\LlmService::class)) {
                $cfg = (new \AhgAiServices\Services\LlmService())->getDefaultConfig();
                $model = trim((string) ($cfg->model ?? ''));
                if ($model !== '') {
                    return mb_substr($model, 0, 120);
                }
            }
        } catch (\Throwable $e) {
            // fall through to label.
        }
        return 'AHG AI gateway';
    }
}
