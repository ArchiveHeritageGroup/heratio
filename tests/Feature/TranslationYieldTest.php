<?php

/**
 * TranslationYieldTest - the overnight translation fill yields the GPU only while a
 * priority model is in use (its /api/ps expires_at moves), not while it merely stays
 * loaded: an idle model held the whole run on 16 Oct 2026.
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

namespace Tests\Feature;

use AhgCore\Commands\TranslationLlmFillCommand;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TranslationYieldTest extends TestCase
{
    private function ps(?string $expiry): array
    {
        return ['models' => $expiry === null ? [] : [['name' => 'qwen2.5vl:7b', 'expires_at' => $expiry]]];
    }

    private function inUse(TranslationLlmFillCommand $cmd): bool
    {
        $m = new \ReflectionMethod($cmd, 'priorityModelInUse');
        $m->setAccessible(true);

        return $m->invoke($cmd, ['qwen2.5vl:7b']);
    }

    public function test_a_loaded_but_idle_model_stops_counting_as_in_use(): void
    {
        Http::fake(['*/api/ps' => Http::sequence()
            ->push($this->ps('2026-10-16T22:00:00Z'))
            ->push($this->ps('2026-10-16T22:00:00Z'))
            ->push($this->ps('2026-10-16T22:00:00Z'))]);
        $cmd = app(TranslationLlmFillCommand::class);

        $this->assertTrue($this->inUse($cmd), 'first sight counts as in use');
        $this->assertFalse($this->inUse($cmd), 'unchanged expiry means idle');
        $this->assertFalse($this->inUse($cmd));
    }

    public function test_a_model_serving_requests_counts_as_in_use(): void
    {
        Http::fake(['*/api/ps' => Http::sequence()
            ->push($this->ps('2026-10-16T22:00:00Z'))
            ->push($this->ps('2026-10-16T22:00:31Z'))
            ->push($this->ps(null))
            ->push($this->ps('2026-10-16T22:10:00Z'))]);
        $cmd = app(TranslationLlmFillCommand::class);

        $this->assertTrue($this->inUse($cmd));
        $this->assertTrue($this->inUse($cmd), 'expiry moved: it served a request');
        $this->assertFalse($this->inUse($cmd), 'unloaded');
        $this->assertTrue($this->inUse($cmd), 'loaded again counts as new use');
    }

    public function test_an_unreadable_ps_does_not_hold_the_run(): void
    {
        Http::fake(['*/api/ps' => Http::response('', 502)]);

        $this->assertFalse($this->inUse(app(TranslationLlmFillCommand::class)));
    }
}
