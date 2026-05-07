<?php

namespace AhgSpectrum\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AhgSpectrumServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'spectrum');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgSpectrum\Commands\SpectrumSeedWorkflowConfigs::class,
                // #91 phase 2: spectrum reminder commands
                \AhgSpectrum\Console\Commands\SpectrumValuationReminderCommand::class,
                \AhgSpectrum\Console\Commands\SpectrumConditionCheckReminderCommand::class,
            ]);

            // #91 phase 2: schedule the two reminder commands daily. Each
            // command guards on its own setting threshold (0 = disabled), so
            // an operator can silence the schedule by zeroing the relevant
            // setting in /admin/ahgSettings/spectrum without needing to
            // touch the schedule.
            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('ahg:spectrum-valuation-reminder')->dailyAt('06:30')->withoutOverlapping(60);
                $schedule->command('ahg:spectrum-condition-check-reminder')->dailyAt('06:45')->withoutOverlapping(60);
            });
        }
    }
}
