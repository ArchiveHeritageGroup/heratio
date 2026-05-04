<?php

/**
 * AhgProvenanceAiServiceProvider - Service provider for Heratio
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

namespace AhgProvenanceAi\Providers;

use AhgProvenanceAi\Services\InferenceService;
use AhgProvenanceAi\Services\OverrideService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the AI provenance package: auto-installs the schema on first request,
 * binds InferenceService as a singleton.
 *
 * The auto-install pattern mirrors AhgDropdownManageServiceProvider's approach
 * (see project memory project_dropdown_phase_1) so a fresh checkout doesn't
 * need a manual mysql import step.
 */
class AhgProvenanceAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InferenceService::class, function ($app) {
            return new InferenceService();
        });
        $this->app->singleton(OverrideService::class, function ($app) {
            return new OverrideService();
        });
    }

    public function boot(): void
    {
        $this->ensureSchema();
    }

    /**
     * Idempotent first-boot installer. Skipped when both tables already exist.
     * Failure is logged but does not throw - provisioning concerns must not
     * crash the request handler.
     *
     * Uses DB::unprepared() so the multi-statement install.sql (with leading
     * SQL comments and the SET FOREIGN_KEY_CHECKS wrapper) executes as one
     * batch, matching the AhgDropdownManageServiceProvider precedent.
     */
    protected function ensureSchema(): void
    {
        try {
            if (Schema::hasTable('ahg_ai_inference') && Schema::hasTable('ahg_ai_override')) {
                return;
            }
            $sql = @file_get_contents(__DIR__ . '/../../database/install.sql');
            if (is_string($sql) && $sql !== '') {
                DB::unprepared($sql);
            }
        } catch (\Throwable $e) {
            \Log::warning('[ahg-provenance-ai] auto-install failed: ' . $e->getMessage());
        }
    }
}
