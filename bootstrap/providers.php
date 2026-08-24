<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    AhgArticles\Providers\AhgArticlesServiceProvider::class,
    AhgMarketing\Providers\AhgMarketingServiceProvider::class,
    AhgArchaeology\Providers\AhgArchaeologyServiceProvider::class,
    // #1483. Guarded: the PSR-4 entry that autoloads this class lives in
    // composer.json, which bin/release deliberately excludes (it carries
    // dev-only glue). class_exists() returns false rather than throwing where
    // the autoload entry has not been applied, so the plugin is simply absent
    // there instead of taking the site down.
    class_exists(AhgHarrisMatrix\Providers\AhgHarrisMatrixServiceProvider::class)
        ? AhgHarrisMatrix\Providers\AhgHarrisMatrixServiceProvider::class
        : null,
    AhgArtworkRequest\Providers\AhgArtworkRequestServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\I18nFormattingServiceProvider::class,
]));
