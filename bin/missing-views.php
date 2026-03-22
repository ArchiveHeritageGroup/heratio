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
];

// No-package plugins (need new Heratio packages)
$noPackage = [];
$missing = [];
$matched = 0;
$total = 0;

// Scan all AtoM plugin templates
$atomPluginDir = "$atomBase/atom-ahg-plugins";
foreach (glob("$atomPluginDir/*/modules/*/templates/*.php") as $tpl) {
    $total++;
    $rel = str_replace("$atomPluginDir/", '', $tpl);
    $parts = explode('/', $rel);
    $plugin = $parts[0];
    $module = $parts[2];
    $file = basename($tpl, '.php');

    // Normalize template name
    $normalized = strtolower(preg_replace('/Success$/', '', $file));
    $normalized = preg_replace('/^_/', '', $normalized);

    $heratioPkg = $pluginMap[$plugin] ?? null;

    if (!$heratioPkg) {
        $noPackage[$plugin][] = "$module/$file";
        continue;
    }

    $views = $heratioViews[$heratioPkg] ?? [];
    $found = in_array($normalized, $views)
          || in_array(str_replace('_', '-', $normalized), $views)
          || in_array(preg_replace('/([a-z])([A-Z])/', '$1-$2', $normalized), $views);

    if ($found) {
        $matched++;
    } else {
        $missing[$heratioPkg][] = "$module/$file.php";
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
