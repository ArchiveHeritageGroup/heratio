<?php

/**
 * Marketing routes. All two-segment paths so they beat nothing and are beaten
 * by nothing - they sit outside the locked `/{slug}` single-segment catch-all.
 *
 * @license AGPL-3.0-or-later
 */

use AhgMarketing\Controllers\ComparisonController;
use AhgMarketing\Controllers\MigrationLeadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/compare/atom', [ComparisonController::class, 'atom'])
        ->name('marketing.compare.atom');

    Route::get('/migration/assessment', [MigrationLeadController::class, 'show'])
        ->name('marketing.migration.assessment');

    Route::post('/migration/assessment', [MigrationLeadController::class, 'submit'])
        ->name('marketing.migration.assessment.submit');

    // Self-contained sitemap for the marketing pages (submit alongside /sitemap.xml
    // in Search Console). The ".xml" suffix keeps it clear of the /{slug} catch-all
    // (which only matches [a-z0-9-]+, no dot). URLs are built with url() so they are
    // correct on any host (canonical heratio.org in production).
    Route::get('/sitemap-marketing.xml', function () {
        $urls = [
            ['loc' => url('/compare/atom'),         'priority' => '0.9', 'changefreq' => 'monthly'],
            ['loc' => url('/migration/assessment'), 'priority' => '0.7', 'changefreq' => 'monthly'],
        ];
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $xml .= '  <url><loc>' . htmlspecialchars($u['loc'], ENT_XML1)
                 . '</loc><changefreq>' . $u['changefreq']
                 . '</changefreq><priority>' . $u['priority'] . '</priority></url>' . "\n";
        }
        $xml .= '</urlset>';
        return response($xml, 200)->header('Content-Type', 'application/xml');
    })->name('marketing.sitemap');
});
