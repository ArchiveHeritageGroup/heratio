#!/usr/bin/env php
<?php
/**
 * Phase 4: Find AtoM templates with NO Heratio blade equivalent.
 * Lists every AtoM template file and checks if a matching Heratio view exists.
 */

$atomBase = '/usr/share/nginx/archive';
$heratioBase = '/usr/share/nginx/heratio/packages';

// Collect all Heratio blade view basenames per package
$heratioViews = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($heratioBase, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!preg_match('/\.blade\.php$/', $f->getFilename())) continue;
    $rel = str_replace($heratioBase . '/', '', $f->getPathname());
    $pkg = explode('/', $rel)[0];
    $viewName = str_replace('.blade.php', '', $f->getFilename());
    $heratioViews[$pkg][] = strtolower($viewName);
}

// AtoM plugin → Heratio package mapping
$pluginMap = [
    'ahgActorManagePlugin' => 'ahg-actor-manage',
    'ahgInformationObjectManagePlugin' => 'ahg-information-object-manage',
    'ahgRepositoryManagePlugin' => 'ahg-repository-manage',
    'ahgAccessionManagePlugin' => 'ahg-accession-manage',
    'ahgDonorManagePlugin' => 'ahg-donor-manage',
    'ahgRightsHolderManagePlugin' => 'ahg-rights-holder-manage',
    'ahgStorageManagePlugin' => 'ahg-storage-manage',
    'ahgTermTaxonomyPlugin' => 'ahg-term-taxonomy',
    'ahgFunctionManagePlugin' => 'ahg-function-manage',
    'ahgUserManagePlugin' => 'ahg-user-manage',
    'ahgSettingsPlugin' => 'ahg-settings',
    'ahgJobsManagePlugin' => 'ahg-jobs-manage',
    'ahgSearchPlugin' => 'ahg-search',
    'ahgStaticPagePlugin' => 'ahg-static-page',
    'ahgMenuManagePlugin' => 'ahg-menu-manage',
    'ahgReportsPlugin' => 'ahg-reports',
    'ahgCartPlugin' => 'ahg-cart',
    'ahgFavoritesPlugin' => 'ahg-favorites',
    'ahgFeedbackPlugin' => 'ahg-feedback',
    'ahgAuditTrailPlugin' => 'ahg-audit-trail',
    'ahgBackupPlugin' => 'ahg-backup',
    'ahgDataMigrationPlugin' => 'ahg-data-migration',
    'ahgDisplayPlugin' => 'ahg-display',
    'ahgIiifPlugin' => 'ahg-iiif-collection',
    'ahgRequestToPublishPlugin' => 'ahg-request-publish',
    'ahgWorkflowPlugin' => 'ahg-workflow',
    'ahgGalleryPlugin' => 'ahg-gallery',
    'ahgMuseumPlugin' => 'ahg-museum',
    'ahgLibraryPlugin' => 'ahg-library',
    'ahgDAMPlugin' => 'ahg-dam',
    'ahgResearchPlugin' => 'ahg-research',
    'ahgResearcherPlugin' => 'ahg-researcher-manage',
    'ahgDedupePlugin' => 'ahg-dedupe',
    'ahgDoiPlugin' => 'ahg-doi-manage',
    'ahgLoanPlugin' => 'ahg-loan',
    'ahgPreservationPlugin' => 'ahg-preservation',
    'ahgAIPlugin' => 'ahg-ai-services',
    'ahgAiConditionPlugin' => 'ahg-ai-services',
    'ahg3DModelPlugin' => 'ahg-3d-model',
    'ahgFtpPlugin' => 'ahg-ftp-upload',
    'ahgHelpPlugin' => 'ahg-help',
    'ahgIntegrityPlugin' => 'ahg-integrity',
    'ahgMetadataExtractionPlugin' => 'ahg-metadata-extraction',
    'ahgPortableExportPlugin' => 'ahg-portable-export',
    'ahgRicExplorerPlugin' => 'ahg-ric',
    'ahgSecurityClearancePlugin' => 'ahg-acl',
    'ahgHeritagePlugin' => 'ahg-heritage-manage',
    'ahgHeritageAccountingPlugin' => 'ahg-heritage-manage',
    'ahgThemeB5Plugin' => 'ahg-theme-b5',
    'ahgCorePlugin' => 'ahg-core',
    'ahgRightsPlugin' => 'ahg-rights-holder-manage',
    'ahgExtendedRightsPlugin' => 'ahg-rights-holder-manage',
    'ahgDonorAgreementPlugin' => 'ahg-donor-manage',
    'ahgUserRegistrationPlugin' => 'ahg-user-manage',
    'ahgReportBuilderPlugin' => 'ahg-reports',
    'ahgAuthorityPlugin' => 'ahg-dedupe',
    'ahgPrivacyPlugin' => 'ahg-privacy',
    'ahgSpectrumPlugin' => 'ahg-spectrum',
    'ahgMarketplacePlugin' => 'ahg-marketplace',
    'ahgICIPPlugin' => 'ahg-icip',
    'ahgVendorPlugin' => 'ahg-vendor',
    'ahgCDPAPlugin' => 'ahg-cdpa',
    'ahgNAZPlugin' => 'ahg-naz',
    'ahgNMMZPlugin' => 'ahg-nmmz',
    'ahgExhibitionPlugin' => 'ahg-exhibition',
    'ahgIPSASPlugin' => 'ahg-ipsas',
    'ahgSemanticSearchPlugin' => 'ahg-semantic-search',
    'ahgConditionPlugin' => 'ahg-condition',
    'ahgStatisticsPlugin' => 'ahg-statistics',
    'ahgMultiTenantPlugin' => 'ahg-multi-tenant',
    'ahgLandingPagePlugin' => 'ahg-landing-page',
    'ahgFormsPlugin' => 'ahg-forms',
    'ahgIngestPlugin' => 'ahg-ingest',
    'ahgExportPlugin' => 'ahg-export',
    'ahgAccessRequestPlugin' => 'ahg-access-request',
    'ahgFederationPlugin' => 'ahg-federation',
    'ahgProvenancePlugin' => 'ahg-provenance',
    'ahgCustomFieldsPlugin' => 'ahg-custom-fields',
    'ahgMetadataExportPlugin' => 'ahg-metadata-export',
    'ahgGISPlugin' => 'ahg-gis',
    'ahgTranslationPlugin' => 'ahg-translation',
    'ahgLabelPlugin' => 'ahg-label',
    'ahgGraphQLPlugin' => 'ahg-graphql',
    'ahgDiscoveryPlugin' => 'ahg-discovery',
    'ahgDcManagePlugin' => 'ahg-dc-manage',
    'ahgDacsManagePlugin' => 'ahg-dacs-manage',
    'ahgModsManagePlugin' => 'ahg-mods-manage',
    'ahgRadManagePlugin' => 'ahg-rad-manage',
    'ahgAPIPlugin' => 'ahg-api-plugin',
    'ahgRegistryPlugin' => 'ahg-registry',
    'ahgContactPlugin' => 'ahg-actor-manage',
];

// Module → Heratio package cross-reference (for theme/core module overrides)
// When ahgThemeB5Plugin or ahgCorePlugin has a module override like
// modules/actor/templates/browseSuccess.php, it should map to ahg-actor-manage,
// NOT to ahg-theme-b5 or ahg-core.
$modulePackageMap = [
    'accession'          => 'ahg-accession-manage',
    'actor'              => 'ahg-actor-manage',
    'repository'         => 'ahg-repository-manage',
    'informationobject'  => 'ahg-information-object-manage',
    'physicalobject'     => 'ahg-storage-manage',
    'function'           => 'ahg-function-manage',
    'rightsholder'       => 'ahg-rights-holder-manage',
    'term'               => 'ahg-term-taxonomy',
    'taxonomy'           => 'ahg-term-taxonomy',
    'user'               => 'ahg-user-manage',
    'aclGroup'           => 'ahg-acl',
    'jobs'               => 'ahg-jobs-manage',
    'search'             => 'ahg-search',
    'staticpage'         => 'ahg-static-page',
    'menu'               => 'ahg-menu-manage',
    'settings'           => 'ahg-settings',
    'digitalobject'      => 'ahg-information-object-manage',
    'default'            => 'ahg-core',
    'object'             => 'ahg-core',
    'contactinformation' => 'ahg-core',
    'right'              => 'ahg-rights-holder-manage',
    'deaccession'        => 'ahg-accession-manage',
    'clipboard'          => 'ahg-cart',
    'admin'              => 'ahg-core',
    'browse'             => 'ahg-core',
    'event'              => 'ahg-core',
    'relation'           => 'ahg-core',
    'tts'                => 'ahg-core',
    'ahgVoice'           => 'ahg-core',
    'import'             => 'ahg-data-migration',
    'savedSearch'        => 'ahg-search',
    'extendedRights'     => 'ahg-rights-holder-manage',
    'sfIsadPlugin'       => 'ahg-information-object-manage',
    'sfIsaarPlugin'      => 'ahg-actor-manage',
    'sfIsdiahPlugin'     => 'ahg-repository-manage',
    'sfIsdfPlugin'       => 'ahg-function-manage',
    'sfDcPlugin'         => 'ahg-information-object-manage',
    'sfRadPlugin'        => 'ahg-information-object-manage',
    'sfModsPlugin'       => 'ahg-information-object-manage',
    'sfAHGPlugin'        => 'ahg-display',
    'sfPluginAdminPlugin' => 'ahg-settings',
    'sfSkosPlugin'       => 'ahg-term-taxonomy',
    'sfTranslatePlugin'  => 'ahg-core',
    'arDacsPlugin'       => 'ahg-information-object-manage',
    'arStorageService'   => 'ahg-storage-manage',
    'arStorageServiceSettings' => 'ahg-settings',
    'heritage.bak'       => 'ahg-heritage-manage',
];

// Plugins that use module overrides — their templates should be checked
// against the module-mapped package first, then the plugin-mapped package
$overridePlugins = ['ahgThemeB5Plugin', 'ahgCorePlugin'];

// No-package plugins (need new Heratio packages)
$noPackage = [];
$missing = [];
$matched = 0;
$total = 0;

// Helper: check if a normalized view name exists in a package's view list
function checkViewExists($rawName, $viewsList) {
    $normalized = strtolower($rawName);
    $normalizedNoUnderscore = preg_replace('/^_/', '', $normalized);
    $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', preg_replace('/^_/', '', $rawName)));
    $isPartial = str_starts_with($rawName, '_');

    // Handle .mod_* variants: convert dots and underscores to hyphens
    // e.g., _userMenu.mod_cas → _user-menu-mod-cas
    $kebabFull = str_replace(['.', '_'], '-', $kebab);
    $normalizedFull = str_replace(['.', '_'], '-', $normalizedNoUnderscore);

    return in_array($normalizedNoUnderscore, $viewsList)
        || in_array(str_replace('_', '-', $normalizedNoUnderscore), $viewsList)
        || in_array($kebab, $viewsList)
        || ($isPartial && in_array('_' . $kebab, $viewsList))
        || in_array($normalized, $viewsList)
        || in_array($kebabFull, $viewsList)
        || in_array($normalizedFull, $viewsList)
        || ($isPartial && in_array('_' . $kebabFull, $viewsList))
        || ($isPartial && in_array('_' . $normalizedFull, $viewsList));
}

// Scan all AtoM plugin templates
$atomPluginDir = "$atomBase/atom-ahg-plugins";
foreach (glob("$atomPluginDir/*/modules/*/templates/*.php") as $tpl) {
    $total++;
    $rel = str_replace("$atomPluginDir/", '', $tpl);
    $parts = explode('/', $rel);
    $plugin = $parts[0];
    $module = $parts[2];
    $file = basename($tpl, '.php');

    // Normalize template name — strip .blade suffix and Success suffix
    $rawName = preg_replace('/\.blade$/', '', $file);
    $rawName = preg_replace('/Success$/', '', $rawName);

    $heratioPkg = $pluginMap[$plugin] ?? null;

    if (!$heratioPkg) {
        $noPackage[$plugin][] = "$module/$file";
        continue;
    }

    // For override plugins (theme-b5, core), check the module-mapped package first
    $found = false;
    if (in_array($plugin, $overridePlugins)) {
        // 1. Check the module-mapped package (e.g., actor → ahg-actor-manage)
        $modulePkg = $modulePackageMap[$module] ?? null;
        if ($modulePkg) {
            $views = $heratioViews[$modulePkg] ?? [];
            if (checkViewExists($rawName, $views)) {
                $found = true;
            }
        }
        // 2. Check the plugin-mapped package (ahg-theme-b5 or ahg-core)
        if (!$found) {
            $views = $heratioViews[$heratioPkg] ?? [];
            if (checkViewExists($rawName, $views)) {
                $found = true;
            }
        }
        // 3. Check ALL packages as final fallback
        if (!$found) {
            foreach ($heratioViews as $pkg => $pViews) {
                if (checkViewExists($rawName, $pViews)) {
                    $found = true;
                    break;
                }
            }
        }
        // Report missing against the module-mapped package (not the plugin package)
        if (!$found) {
            $targetPkg = $modulePkg ?? $heratioPkg;
            $missing[$targetPkg][] = "$module/$file.php";
        }
    } else {
        // Non-override plugin: check only the plugin-mapped package
        $views = $heratioViews[$heratioPkg] ?? [];
        if (checkViewExists($rawName, $views)) {
            $found = true;
        }
        if (!$found) {
            $missing[$heratioPkg][] = "$module/$file.php";
        }
    }

    if ($found) {
        $matched++;
    }
}

// Output
echo "# MISSING VIEWS: AtoM templates without Heratio equivalent\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# Total AtoM templates scanned: $total\n";
echo "# Matched to Heratio: $matched\n";
echo "# Missing in Heratio: " . ($total - $matched - array_sum(array_map('count', $noPackage))) . "\n";
echo "# No Heratio package: " . array_sum(array_map('count', $noPackage)) . "\n\n";

echo "## MISSING VIEWS BY PACKAGE\n\n";
ksort($missing);
foreach ($missing as $pkg => $templates) {
    echo "### $pkg (" . count($templates) . " missing)\n";
    foreach ($templates as $t) echo "  - $t\n";
    echo "\n";
}

echo "\n## AtoM PLUGINS WITHOUT HERATIO PACKAGE\n\n";
ksort($noPackage);
foreach ($noPackage as $plugin => $templates) {
    echo "### $plugin (" . count($templates) . " templates)\n";
    foreach (array_slice($templates, 0, 10) as $t) echo "  - $t\n";
    if (count($templates) > 10) echo "  ... and " . (count($templates) - 10) . " more\n";
    echo "\n";
}
