<?php

/**
 * AhgNarssaServiceProvider - Service for Heratio
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

namespace AhgNarssa\Providers;

use AhgNarssa\Console\TransferPackageCommand;
use AhgNarssa\Services\TransferPackageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgNarssaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TransferPackageService::class, fn () => new TransferPackageService());
    }

    public function boot(): void
    {
        try {
            if (!Schema::hasTable('narssa_transfer')) {
                $sql = file_get_contents(__DIR__ . '/../../database/install.sql');
                if ($sql !== false) {
                    DB::unprepared($sql);
                }
            }
        } catch (\Throwable $e) {
            // installation may be cold; auto-seed is best-effort
        }

        if (file_exists(__DIR__ . '/../../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                TransferPackageCommand::class,
            ]);
        }
    }
}
