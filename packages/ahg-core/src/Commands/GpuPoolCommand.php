<?php

/**
 * GpuPoolCommand - Heratio
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

namespace AhgCore\Commands;

use AhgCore\Services\AhgGpuPoolService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operator-side CLI for the AhgGpuPoolService registry. Lets ops add,
 * list, health-check, enable, and disable GPU endpoints without touching
 * SQL. Common workflows:
 *
 *   php artisan ahg:gpu-pool list
 *   php artisan ahg:gpu-pool add gpu-115 http://192.168.0.115:11434 --vram=20 --models=qwen2.5:13b,llama3:13b
 *   php artisan ahg:gpu-pool health
 *   php artisan ahg:gpu-pool enable gpu-115
 *   php artisan ahg:gpu-pool disable legacy-voice
 */
class GpuPoolCommand extends Command
{
    protected $signature = 'ahg:gpu-pool
                            {action : list|add|health|enable|disable|remove}
                            {name? : endpoint name (required for add/enable/disable/remove)}
                            {url? : Ollama base URL (required for add)}
                            {--vram=8 : GPU vRAM in GB (default 8 for RTX 3070-class)}
                            {--priority=100 : lower wins under priority strategy}
                            {--models= : CSV of supported model names}
                            {--notes= : free-form notes}';
    protected $description = 'Manage the AhgGpuPoolService endpoint registry (list / add / health / enable / disable / remove).';

    public function handle(): int
    {
        $action = $this->argument('action');
        AhgGpuPoolService::ensureTable();

        return match ($action) {
            'list'    => $this->doList(),
            'add'     => $this->doAdd(),
            'health'  => $this->doHealth(),
            'enable'  => $this->doToggle(true),
            'disable' => $this->doToggle(false),
            'remove'  => $this->doRemove(),
            default   => $this->bail("unknown action '{$action}' - use list|add|health|enable|disable|remove"),
        };
    }

    private function doList(): int
    {
        $rows = DB::table(AhgGpuPoolService::TABLE)->orderBy('priority')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->line('(pool empty - run `php artisan ahg:gpu-pool add <name> <url>` to register an endpoint)');
            return self::SUCCESS;
        }
        $this->table(
            ['name', 'url', 'vram_gb', 'priority', 'active', 'last_health', 'models'],
            $rows->map(fn ($r) => [
                $r->name,
                $r->url,
                (string) $r->vram_gb,
                (string) $r->priority,
                $r->is_active ? 'yes' : 'no',
                $r->last_healthcheck_status ?? '-',
                substr((string) ($r->models_supported ?? ''), 0, 40),
            ])->all(),
        );
        return self::SUCCESS;
    }

    private function doAdd(): int
    {
        $name = (string) $this->argument('name');
        $url  = (string) $this->argument('url');
        if ($name === '' || $url === '') {
            return $this->bail('add: name + url are required');
        }
        AhgGpuPoolService::registerEndpoint(
            $name, $url,
            (string) $this->option('models'),
            (int) $this->option('priority'),
            (int) $this->option('vram'),
            $this->option('notes')
        );
        $this->info("[gpu-pool] registered '$name' -> $url (vram={$this->option('vram')}GB, priority={$this->option('priority')})");
        return self::SUCCESS;
    }

    private function doHealth(): int
    {
        $result = AhgGpuPoolService::health();
        $this->line(sprintf(
            '[gpu-pool] health: %d up, %d down (of %d active)',
            $result['up'], $result['down'], $result['total']
        ));
        return $result['down'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function doToggle(bool $enable): int
    {
        $name = (string) $this->argument('name');
        if ($name === '') return $this->bail('enable/disable: name is required');
        $affected = DB::table(AhgGpuPoolService::TABLE)
            ->where('name', $name)
            ->update(['is_active' => $enable ? 1 : 0, 'updated_at' => now()]);
        if ($affected === 0) return $this->bail("'$name' not found in pool");
        $this->info('[gpu-pool] ' . ($enable ? 'enabled' : 'disabled') . " '$name'");
        return self::SUCCESS;
    }

    private function doRemove(): int
    {
        $name = (string) $this->argument('name');
        if ($name === '') return $this->bail('remove: name is required');
        $affected = DB::table(AhgGpuPoolService::TABLE)->where('name', $name)->delete();
        if ($affected === 0) return $this->bail("'$name' not found in pool");
        $this->info("[gpu-pool] removed '$name'");
        return self::SUCCESS;
    }

    private function bail(string $msg): int
    {
        $this->error($msg);
        return self::FAILURE;
    }
}
