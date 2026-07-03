<?php

/**
 * PersistArticlesCommand - CLI entry point for the article persistence pair that
 * brackets the nightly demo reset.
 *
 *   php artisan ahg:articles-persist capture   # ~01:50, before the 02:00 reset
 *   php artisan ahg:articles-persist apply     # ~02:10, after the reset
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgArticles\Console;

use AhgArticles\Services\ArticlePersistenceService;
use Illuminate\Console\Command;

class PersistArticlesCommand extends Command
{
    protected $signature = 'ahg:articles-persist {action : capture|apply}';

    protected $description = 'Preserve protected articles and every article read count across the nightly demo DB reset';

    public function handle(ArticlePersistenceService $service): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, ['capture', 'apply'], true)) {
            $this->error("Unknown action '{$action}'. Use: capture | apply");

            return self::INVALID;
        }

        $result = $action === 'capture' ? $service->capture() : $service->apply();
        $this->info('ahg:articles-persist '.$action.' => '.json_encode($result));

        return self::SUCCESS;
    }
}
