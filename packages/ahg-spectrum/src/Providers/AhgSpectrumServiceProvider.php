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

        // Share whether Spectrum is enabled so nav/menus can hide ALL Spectrum
        // entry points when the operator switches it off. Every /admin/spectrum/*
        // route is 404'd by EnsureSpectrumEnabled, so an un-gated link would land
        // the user on "page not found". Guarded so a missing/locked ahg_settings
        // table during install never breaks view rendering.
        if (! $this->app->runningInConsole()) {
            try {
                \Illuminate\Support\Facades\View::share(
                    'spectrumEnabled',
                    (new \AhgSpectrum\Services\SpectrumSettings())->isEnabled()
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\View::share('spectrumEnabled', false);
            }
        }

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
