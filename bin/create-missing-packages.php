#!/usr/bin/env php
<?php
/**
 * Create blade views for all unmapped AtoM plugin packages.
 * Reads AtoM templates, creates matching Heratio blades with theme CSS.
 */

$atomBase = '/usr/share/nginx/archive/atom-ahg-plugins';
$heratioBase = '/usr/share/nginx/heratio/packages';

// Plugin → package mapping
$plugins = [
    'ahgRegistryPlugin' => ['pkg' => 'ahg-registry', 'modules' => ['registry']],
    'ahgPrivacyPlugin' => ['pkg' => 'ahg-privacy', 'modules' => ['privacy', 'privacyAdmin', 'informationobject']],
    'ahgSpectrumPlugin' => ['pkg' => 'ahg-spectrum', 'modules' => ['spectrum', 'spectrumReports']],
    'ahgMarketplacePlugin' => ['pkg' => 'ahg-marketplace', 'modules' => ['marketplace']],
    'ahgICIPPlugin' => ['pkg' => 'ahg-icip', 'modules' => ['icip']],
    'ahgVendorPlugin' => ['pkg' => 'ahg-vendor', 'modules' => ['vendor', 'contract']],
    'ahgCDPAPlugin' => ['pkg' => 'ahg-cdpa', 'modules' => ['cdpa']],
    'ahgNAZPlugin' => ['pkg' => 'ahg-naz', 'modules' => ['naz']],
    'ahgNMMZPlugin' => ['pkg' => 'ahg-nmmz', 'modules' => ['nmmz']],
    'ahgExhibitionPlugin' => ['pkg' => 'ahg-exhibition', 'modules' => ['exhibition']],
    'ahgIPSASPlugin' => ['pkg' => 'ahg-ipsas', 'modules' => ['ipsas']],
    'ahgSemanticSearchPlugin' => ['pkg' => 'ahg-semantic-search', 'modules' => ['semanticSearchAdmin', 'searchEnhancement']],
    'ahgConditionPlugin' => ['pkg' => 'ahg-condition', 'modules' => ['condition']],
    'ahgStatisticsPlugin' => ['pkg' => 'ahg-statistics', 'modules' => ['statistics']],
    'ahgMultiTenantPlugin' => ['pkg' => 'ahg-multi-tenant', 'modules' => ['tenantAdmin', 'tenantBranding', 'tenantError', 'tenantSwitcher', 'tenantUsers']],
    'ahgLandingPagePlugin' => ['pkg' => 'ahg-landing-page', 'modules' => ['landingPageBuilder']],
    'ahgFormsPlugin' => ['pkg' => 'ahg-forms', 'modules' => ['forms']],
    'ahgIngestPlugin' => ['pkg' => 'ahg-ingest', 'modules' => ['ingest']],
    'ahgMetadataExportPlugin' => ['pkg' => 'ahg-metadata-export', 'modules' => ['metadataExport', 'linkedData']],
    'ahgGISPlugin' => ['pkg' => 'ahg-gis', 'modules' => ['gis']],
    'ahgTranslationPlugin' => ['pkg' => 'ahg-translation', 'modules' => ['translation']],
    'ahgLabelPlugin' => ['pkg' => 'ahg-label', 'modules' => ['label']],
    'ahgGraphQLPlugin' => ['pkg' => 'ahg-graphql', 'modules' => ['graphql']],
    'ahgDiscoveryPlugin' => ['pkg' => 'ahg-discovery', 'modules' => ['discovery']],
    'ahgDcManagePlugin' => ['pkg' => 'ahg-dc-manage', 'modules' => ['dcManage']],
    'ahgDacsManagePlugin' => ['pkg' => 'ahg-dacs-manage', 'modules' => ['dacsManage']],
    'ahgModsManagePlugin' => ['pkg' => 'ahg-mods-manage', 'modules' => ['modsManage']],
    'ahgRadManagePlugin' => ['pkg' => 'ahg-rad-manage', 'modules' => ['radManage']],
    'ahgAPIPlugin' => ['pkg' => 'ahg-api-plugin', 'modules' => ['api', 'apiv2']],
];

$totalCreated = 0;

foreach ($plugins as $pluginName => $config) {
    $pkg = $config['pkg'];
    $pkgDir = "$heratioBase/$pkg";
    $viewDir = "$pkgDir/resources/views";

    // Check if views already exist
    $existingViews = 0;
    if (is_dir($viewDir)) {
        $existingViews = count(glob("$viewDir/*.blade.php") + glob("$viewDir/**/*.blade.php"));
    }

    // Ensure directories exist
    @mkdir($viewDir, 0755, true);
    @mkdir("$pkgDir/src/Controllers", 0755, true);
    @mkdir("$pkgDir/src/Providers", 0755, true);
    @mkdir("$pkgDir/routes", 0755, true);

    $viewsCreated = 0;
    $routeEntries = [];
    $controllerMethods = [];

    foreach ($config['modules'] as $module) {
        $tplDir = "$atomBase/$pluginName/modules/$module/templates";
        if (!is_dir($tplDir)) continue;

        foreach (glob("$tplDir/*.php") as $tpl) {
            $filename = basename($tpl, '.php');

            // Skip .blade.php duplicates
            if (str_ends_with($filename, '.blade')) continue;

            // Normalize name
            $rawName = preg_replace('/Success$/', '', $filename);
            $bladeName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $rawName));
            $bladeName = str_replace('_', '-', ltrim($bladeName, '_'));
            $isPartial = str_starts_with($filename, '_');

            // Skip if already exists
            $bladeFile = "$viewDir/$bladeName.blade.php";
            if ($isPartial) {
                $bladeFile = "$viewDir/_$bladeName.blade.php";
            }
            if (file_exists($bladeFile)) continue;

            // Read AtoM template to get title/purpose
            $atomContent = file_get_contents($tpl);
            $title = ucwords(str_replace('-', ' ', $bladeName));

            // Extract h1/title from AtoM template
            if (preg_match("/__\('([^']+)'\)/", $atomContent, $tm)) {
                $title = $tm[1];
            }

            if ($isPartial) {
                // Create partial
                $bladeContent = "{{-- Partial: $bladeName (migrated from $pluginName/$module) --}}\n";
                $bladeContent .= "<div class=\"$bladeName-partial\">\n";
                $bladeContent .= "  {{-- TODO: Port from $tpl --}}\n";
                $bladeContent .= "</div>\n";
            } else {
                // Create full page view
                $bladeContent = "@extends('theme::layouts.1col')\n\n";
                $bladeContent .= "@section('title', '$title')\n\n";
                $bladeContent .= "@section('content')\n";
                $bladeContent .= "<h1>$title</h1>\n\n";

                // Check if it's a form page
                if (preg_match('/renderFormTag|<form/i', $atomContent)) {
                    $bladeContent .= "<form method=\"POST\">\n  @csrf\n\n";
                    $bladeContent .= "  <div class=\"accordion mb-3\">\n";
                    $bladeContent .= "    <div class=\"accordion-item\">\n";
                    $bladeContent .= "      <h2 class=\"accordion-header\">\n";
                    $bladeContent .= "        <button class=\"accordion-button\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#main-collapse\" aria-expanded=\"true\">$title</button>\n";
                    $bladeContent .= "      </h2>\n";
                    $bladeContent .= "      <div id=\"main-collapse\" class=\"accordion-collapse collapse show\">\n";
                    $bladeContent .= "        <div class=\"accordion-body\">\n";

                    // Extract field names
                    if (preg_match_all('/render_field\(\$form->(\w+)/', $atomContent, $fm)) {
                        foreach (array_unique($fm[1]) as $field) {
                            $label = ucwords(str_replace('_', ' ', preg_replace('/([a-z])([A-Z])/', '$1 $2', $field)));
                            $bladeContent .= "          <div class=\"mb-3\">\n";
                            $bladeContent .= "            <label for=\"$field\" class=\"form-label\">$label <span class=\"badge bg-secondary ms-1\">Optional</span></label>\n";
                            $bladeContent .= "            <input type=\"text\" name=\"$field\" id=\"$field\" class=\"form-control\" value=\"{{ old('$field') }}\">\n";
                            $bladeContent .= "          </div>\n";
                        }
                    }

                    $bladeContent .= "        </div>\n      </div>\n    </div>\n  </div>\n\n";
                    $bladeContent .= "  <ul class=\"actions mb-3 nav gap-2\">\n";
                    $bladeContent .= "    <li><a href=\"{{ url()->previous() }}\" class=\"btn atom-btn-outline-light\" role=\"button\">Cancel</a></li>\n";
                    $bladeContent .= "    <li><input class=\"btn atom-btn-outline-success\" type=\"submit\" value=\"Save\"></li>\n";
                    $bladeContent .= "  </ul>\n";
                    $bladeContent .= "</form>\n";
                } elseif (preg_match('/table/i', $atomContent)) {
                    // Table/browse page
                    $bladeContent .= "<div class=\"table-responsive\">\n";
                    $bladeContent .= "  <table class=\"table table-bordered table-striped\">\n";
                    $bladeContent .= "    <thead>\n      <tr style=\"background:var(--ahg-primary);color:#fff\">\n";
                    $bladeContent .= "        <th>#</th><th>Name</th><th>Actions</th>\n";
                    $bladeContent .= "      </tr>\n    </thead>\n    <tbody>\n";
                    $bladeContent .= "      <tr><td colspan=\"3\" class=\"text-muted text-center\">No records found.</td></tr>\n";
                    $bladeContent .= "    </tbody>\n  </table>\n</div>\n";
                } else {
                    // Generic display page
                    $bladeContent .= "<div class=\"card\">\n";
                    $bladeContent .= "  <div class=\"card-header\" style=\"background:var(--ahg-primary);color:#fff\">\n";
                    $bladeContent .= "    <h5 class=\"mb-0\">$title</h5>\n";
                    $bladeContent .= "  </div>\n";
                    $bladeContent .= "  <div class=\"card-body\">\n";
                    $bladeContent .= "    <p class=\"text-muted\">Content for $title.</p>\n";
                    $bladeContent .= "  </div>\n</div>\n";
                }

                $bladeContent .= "@endsection\n";

                // Add route entry
                $routeName = strtolower(str_replace('-', '', $pkg)) . '.' . $bladeName;
                $routeEntries[] = "Route::get('/$bladeName', [\\{$controllerClass}::class, '" . lcfirst(str_replace('-', '', ucwords($bladeName, '-'))) . "'])->name('$routeName');";

                $methodName = lcfirst(str_replace('-', '', ucwords($bladeName, '-')));
                $controllerMethods[] = "    public function $methodName() { return view('{$viewNamespace}::$bladeName'); }";
            }

            file_put_contents($bladeFile, $bladeContent);
            $viewsCreated++;
            $totalCreated++;
        }
    }

    // Create/update routes file
    $pkgSlug = str_replace('ahg-', '', $pkg);
    $viewNamespace = $pkgSlug;
    $controllerClass = 'Ahg' . str_replace('-', '', ucwords($pkgSlug, '-')) . '\\Controllers\\' . str_replace('-', '', ucwords($pkgSlug, '-')) . 'Controller';

    $routesFile = "$pkgDir/routes/web.php";
    if (!file_exists($routesFile) || filesize($routesFile) < 10) {
        $routeContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";
        $routeContent .= "Route::prefix('admin/$pkgSlug')->middleware(['web'])->group(function () {\n";
        foreach ($routeEntries as $r) {
            $routeContent .= "    $r\n";
        }
        $routeContent .= "});\n";
        file_put_contents($routesFile, $routeContent);
    }

    // Create controller if doesn't exist or is empty
    $ctrlName = str_replace('-', '', ucwords($pkgSlug, '-'));
    $ctrlFile = "$pkgDir/src/Controllers/{$ctrlName}Controller.php";
    if (!file_exists($ctrlFile) || filesize($ctrlFile) < 100) {
        $ns = 'Ahg' . $ctrlName;
        $ctrlContent = "<?php\n\nnamespace $ns\\Controllers;\n\nuse App\\Http\\Controllers\\Controller;\n\nclass {$ctrlName}Controller extends Controller\n{\n";
        foreach ($controllerMethods as $m) {
            $ctrlContent .= "$m\n\n";
        }
        $ctrlContent .= "}\n";
        file_put_contents($ctrlFile, $ctrlContent);
    }

    // Create ServiceProvider if doesn't exist
    $spFile = "$pkgDir/src/Providers/Ahg{$ctrlName}ServiceProvider.php";
    if (!file_exists($spFile)) {
        $ns = 'Ahg' . $ctrlName;
        $spContent = "<?php\n\nnamespace $ns\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\n\n";
        $spContent .= "class Ahg{$ctrlName}ServiceProvider extends ServiceProvider\n{\n";
        $spContent .= "    public function boot(): void\n    {\n";
        $spContent .= "        \$this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');\n";
        $spContent .= "        \$this->loadViewsFrom(__DIR__ . '/../../resources/views', '$viewNamespace');\n";
        $spContent .= "    }\n}\n";
        file_put_contents($spFile, $spContent);
    }

    // Create composer.json if doesn't exist
    $composerFile = "$pkgDir/composer.json";
    if (!file_exists($composerFile)) {
        $ns = 'Ahg' . $ctrlName;
        $composerContent = json_encode([
            'name' => "ahg/$pkg",
            'description' => "Heratio $ctrlName package (migrated from $pluginName)",
            'autoload' => ['psr-4' => ["$ns\\" => 'src/']],
            'extra' => ['laravel' => ['providers' => ["$ns\\Providers\\Ahg{$ctrlName}ServiceProvider"]]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($composerFile, $composerContent);
    }

    if ($viewsCreated > 0) {
        echo "  $pkg: +$viewsCreated views created\n";
    }
}

echo "\n=== TOTAL: $totalCreated views created across " . count($plugins) . " packages ===\n";
