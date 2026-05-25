<?php

/**
 * ModelManifestTest - Unit test for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgProvenanceAi\Tests\Unit;

use AhgProvenanceAi\Services\InferenceService;
use PHPUnit\Framework\TestCase;

/**
 * heratio#135 - the model-manifest composer. Pure: composeModelManifest()
 * does no DB work, so this needs no Laravel bootstrap.
 */
class ModelManifestTest extends TestCase
{
    public function test_minimal_manifest_is_non_empty_when_no_config(): void
    {
        $m = InferenceService::composeModelManifest('qwen3:8b', 'unknown', 'LLM', null);

        $this->assertSame('qwen3:8b', $m['model_name']);
        $this->assertSame('unknown', $m['model_version']);
        $this->assertSame('LLM', $m['service_name']);
        $this->assertSame('qwen3:8b@unknown', $m['model_id']);
        $this->assertNotEmpty($m, 'manifest is always non-empty when model metadata is available');
    }

    public function test_curated_config_fields_are_kept(): void
    {
        $config = [
            'publisher' => 'Alibaba',
            'artifact_hash' => 'abc123',
            'declared_capabilities' => ['chat', 'summarize'],
        ];
        $m = InferenceService::composeModelManifest('qwen3:8b', '8.0', 'LLM', $config);

        $this->assertSame('Alibaba', $m['publisher']);
        $this->assertSame('abc123', $m['artifact_hash']);
        $this->assertSame(['chat', 'summarize'], $m['declared_capabilities']);
    }

    public function test_live_identity_overlays_stale_config(): void
    {
        // A stale version/service in the config manifest must be overridden
        // by what was actually used at inference time.
        $config = ['model_version' => 'STALE', 'service_name' => 'STALE'];
        $m = InferenceService::composeModelManifest('spaCy en_core_web_sm', '3.7.1', 'NER', $config);

        $this->assertSame('3.7.1', $m['model_version']);
        $this->assertSame('NER', $m['service_name']);
    }

    public function test_operator_model_id_is_preserved(): void
    {
        $m = InferenceService::composeModelManifest('m', 'v', 'NER', ['model_id' => 'curated:xyz']);

        $this->assertSame('curated:xyz', $m['model_id']);
    }
}
