<?php

/**
 * Issue #675 Phase 3 - register Blade directives for locale-aware formatting.
 *
 * Pairs each global helper in app/Helpers/i18n.php with a @ahg* Blade
 * directive so views can write @ahgDate($row->created_at) instead of
 * {{ ahg_date($row->created_at) }}. Functionally identical - the directive
 * form is just easier on the eye in dense ISAD/ISDIAH templates.
 *
 * This provider would normally live alongside the existing
 * AhgTranslationServiceProvider (packages/ahg-translation/src/Providers/),
 * but that package is currently in the .locked-paths manifest. Hosting the
 * directives in app/Providers/ keeps Phase 3 inside the unlocked surface and
 * also makes the formatting layer available even when the translation
 * package is unloaded (e.g. a stripped-down API-only deployment).
 *
 * Registered via bootstrap/providers.php.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class I18nFormattingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Each directive forwards to the global helper of the same name. The
        // helper handles ext-intl fallback, empty/null inputs, and string vs
        // DateTime coercion so the directives stay trivial.
        Blade::directive('ahgDate', function ($expression) {
            return "<?php echo e(ahg_date($expression)); ?>";
        });

        Blade::directive('ahgDateTime', function ($expression) {
            return "<?php echo e(ahg_datetime($expression)); ?>";
        });

        Blade::directive('ahgTime', function ($expression) {
            return "<?php echo e(ahg_time($expression)); ?>";
        });

        Blade::directive('ahgNumber', function ($expression) {
            return "<?php echo e(ahg_number($expression)); ?>";
        });

        Blade::directive('ahgCurrency', function ($expression) {
            return "<?php echo e(ahg_currency($expression)); ?>";
        });

        Blade::directive('ahgPercent', function ($expression) {
            return "<?php echo e(ahg_percent($expression)); ?>";
        });
    }
}
