<?php

namespace AhgPdfTools\Providers;

use AhgPdfTools\Services\PdfTextExtractService;
use AhgPdfTools\Services\TiffPdfMergeService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AhgPdfToolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PdfTextExtractService::class, function () {
            return new PdfTextExtractService();
        });

        $this->app->singleton(TiffPdfMergeService::class, function () {
            return new TiffPdfMergeService();
        });
    }

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-pdf-tools');
    }
}
