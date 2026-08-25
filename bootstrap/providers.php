<?php

// Per-instance module switch.
//
// A module is turned OFF on one instance by dropping a marker file:
//
//     touch .disabled-modules/archaeology
//
// .disabled-modules/ is tracked (so it exists and is discoverable) but its
// contents are gitignored, so the switch is per-instance and survives the
// `git pull --ff-only` every deploy runs. That is the whole point of it: the
// previous lever was deleting the module's PSR-4 line from that instance's
// composer.json, which left the file permanently modified - and composer.json
// moved upstream 61 times in the last 300 releases, because RELEASE_EXCLUDE is
// overridden whenever a new package is registered. The next such release would
// have aborted that instance's deploy.
//
// Why a file and NOT env(): this is not a config file, and Laravel skips
// LoadEnvironmentVariables entirely when the config is cached, so
// env('AHG_ARCHAEOLOGY_ENABLED') would return null here and the default would
// silently switch the module back ON. A file answers the same either way.
//
// class_exists() stays alongside it and means something different - a package
// whose PSR-4 entry has not been applied on this instance is absent rather
// than fatal (#1483). Off-by-choice and not-installed are separate states.

$moduleOff = static fn (string $module): bool
    => file_exists(__DIR__.'/../.disabled-modules/'.$module);

return array_values(array_filter([
    App\Providers\AppServiceProvider::class,
    AhgArticles\Providers\AhgArticlesServiceProvider::class,
    AhgMarketing\Providers\AhgMarketingServiceProvider::class,

    // Archaeology is a site/context/finds catalogue. A GLAM instance with no
    // excavation record carries the surface for nothing - off on sasa.
    (! $moduleOff('archaeology')
        && class_exists(AhgArchaeology\Providers\AhgArchaeologyServiceProvider::class))
            ? AhgArchaeology\Providers\AhgArchaeologyServiceProvider::class
            : null,

    // #1483. The Harris Matrix is an archaeological ANALYSIS tool that reads
    // ahg-archaeology's tables and mounts its routes UNDER /archaeology, so it
    // follows the base module off. Without this, switching archaeology off and
    // leaving this on leaves a stratigraphic analyser pointed at a module that
    // is meant to be gone. Active on heratio-dev only.
    (! $moduleOff('harris-matrix')
        && ! $moduleOff('archaeology')
        && class_exists(AhgHarrisMatrix\Providers\AhgHarrisMatrixServiceProvider::class))
            ? AhgHarrisMatrix\Providers\AhgHarrisMatrixServiceProvider::class
            : null,

    AhgArtworkRequest\Providers\AhgArtworkRequestServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\I18nFormattingServiceProvider::class,
]));
