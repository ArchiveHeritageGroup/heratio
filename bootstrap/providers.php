<?php

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    AhgArticles\Providers\AhgArticlesServiceProvider::class,
    AhgMarketing\Providers\AhgMarketingServiceProvider::class,
    // #1483 pattern. Guarded so an instance can opt OUT of the archaeology module by
    // removing its PSR-4 entry from that instance's composer.json - the same
    // lever #1483 uses below. Archaeology is a site/context/finds catalogue;
    // an instance with no excavation record carries the surface for nothing.
    // Where the autoload entry is present this registers exactly as before.
    class_exists(AhgArchaeology\Providers\AhgArchaeologyServiceProvider::class)
        ? AhgArchaeology\Providers\AhgArchaeologyServiceProvider::class
        : null,
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
