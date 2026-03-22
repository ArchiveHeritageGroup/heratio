#!/usr/bin/env php
<?php
/**
 * Full Control Audit: Heratio vs AtoM
 * Counts every control (buttons, links, form fields, headings, badges, text labels, tables)
 * in every Heratio blade view and its AtoM equivalent.
 */

$heratioBase = '/usr/share/nginx/heratio/packages';
$atomBase = '/usr/share/nginx/archive';

// ── Helpers ──────────────────────────────────────────────────────────────

function countControls(string $content): array {
    $c = [
        'buttons'      => 0,
        'links'        => 0,
        'inputs'       => 0,
        'selects'      => 0,
        'textareas'    => 0,
        'checkboxes'   => 0,
        'radios'       => 0,
        'headings'     => 0,
        'badges'       => 0,
        'tables'       => 0,
        'table_cols'   => 0,
        'labels'       => 0,
        'icons'        => 0,
        'images'       => 0,
        'forms'        => 0,
        'btn_classes'  => [],
        'link_hrefs'   => [],
        'badge_types'  => [],
        'field_badges' => ['required' => 0, 'recommended' => 0, 'optional' => 0],
    ];

    // Buttons: <button, type="submit", type="button", .btn classes
    $c['buttons'] = preg_match_all('/<button\b/i', $content)
                  + preg_match_all('/\btype=["\']submit["\']/i', $content)
                  - preg_match_all('/<button[^>]*type=["\']submit["\']/i', $content); // avoid double-count

    // Links: <a href=
    $c['links'] = preg_match_all('/<a\s[^>]*href\s*=/i', $content);

    // Extract link hrefs
    if (preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']*?)["\']/i', $content, $m)) {
        foreach ($m[1] as $href) {
            $href = trim($href);
            if ($href && $href !== '#') {
                // Normalize blade/php expressions
                $c['link_hrefs'][] = $href;
            }
        }
    }

    // Extract button classes (exclude atom-btn-* prefixed classes)
    if (preg_match_all('/<(?:button|a)\s[^>]*class\s*=\s*["\']([^"\']*btn[^"\']*?)["\']/i', $content, $m)) {
        foreach ($m[1] as $cls) {
            if (preg_match_all('/(?<![a-z-])(btn[-\w]*)\b/', $cls, $bm)) {
                foreach ($bm[1] as $b) $c['btn_classes'][] = $b;
            }
        }
    }

    // Form fields
    $c['inputs'] = preg_match_all('/<input\b(?![^>]*type=["\'](?:checkbox|radio|hidden|submit|button)["\'])/i', $content);
    $c['selects'] = preg_match_all('/<select\b/i', $content);
    $c['textareas'] = preg_match_all('/<textarea\b/i', $content);
    $c['checkboxes'] = preg_match_all('/<input[^>]*type=["\']checkbox["\']/i', $content);
    $c['radios'] = preg_match_all('/<input[^>]*type=["\']radio["\']/i', $content);
    $hidden = preg_match_all('/<input[^>]*type=["\']hidden["\']/i', $content);

    // Headings
    $c['headings'] = preg_match_all('/<h[1-6]\b/i', $content);

    // Badges
    $c['badges'] = preg_match_all('/\bbadge\b/i', $content);
    if (preg_match_all('/badge\s+(bg-\w+)/i', $content, $bm)) {
        $c['badge_types'] = array_count_values($bm[1]);
    }

    // Field badges: Required/Recommended/Optional
    $c['field_badges']['required'] = preg_match_all('/Required<\/span>/i', $content);
    $c['field_badges']['recommended'] = preg_match_all('/Recommended<\/span>/i', $content);
    $c['field_badges']['optional'] = preg_match_all('/Optional<\/span>/i', $content);

    // Tables
    $c['tables'] = preg_match_all('/<table\b/i', $content);
    $c['table_cols'] = preg_match_all('/<th\b/i', $content);

    // Labels
    $c['labels'] = preg_match_all('/<label\b/i', $content);

    // Icons (FontAwesome, Bootstrap Icons)
    $c['icons'] = preg_match_all('/<i\s+class\s*=\s*["\'][^"\']*\b(?:fa[srlb]?|bi)\s/i', $content);

    // Images
    $c['images'] = preg_match_all('/<img\b/i', $content);

    // Forms
    $c['forms'] = preg_match_all('/<form\b/i', $content);

    // Total controls
    $c['total_fields'] = $c['inputs'] + $c['selects'] + $c['textareas'] + $c['checkboxes'] + $c['radios'];
    $c['total_controls'] = $c['buttons'] + $c['links'] + $c['total_fields'] + $c['headings'] + $c['labels'] + $c['badges'] + $c['icons'];

    // Detect layout
    $c['layout'] = 'unknown';
    if (preg_match("/extends\(['\"]theme::layouts\.([\w]+)['\"]\)/", $content, $lm)) {
        $c['layout'] = $lm[1];
    }

    // Detect sidebar
    $c['sidebar'] = 'none';
    if (preg_match('/col-md-3.*col-md-9/s', $content)) $c['sidebar'] = 'left';
    if (preg_match('/col-md-9.*col-md-3/s', $content)) $c['sidebar'] = 'right';
    if (preg_match('/sidebar|context-menu|_contextMenu/i', $content)) {
        if ($c['sidebar'] === 'none') $c['sidebar'] = 'detected';
    }

    $c['btn_classes'] = array_count_values($c['btn_classes']);

    return $c;
}

function findAtomEquivalent(string $package, string $view): array {
    global $atomBase;

    // Map Heratio packages to AtoM module paths (verified against actual directory structure)
    $mapping = [
        'ahg-actor-manage' => [
            'atom-ahg-plugins/ahgActorManagePlugin/modules/actorManage/templates',
            'atom-ahg-plugins/ahgActorManagePlugin/modules/sfIsaarPlugin/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/actor/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsaarPlugin/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/actor/templates',
            'apps/qubit/modules/actor/templates',
        ],
        'ahg-information-object-manage' => [
            'atom-ahg-plugins/ahgInformationObjectManagePlugin/modules/ioManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/informationobject/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsadPlugin/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfAHGPlugin/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/informationobject/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/object/templates',
            'apps/qubit/modules/informationobject/templates',
            'apps/qubit/modules/object/templates',
        ],
        'ahg-repository-manage' => [
            'atom-ahg-plugins/ahgRepositoryManagePlugin/modules/repositoryManage/templates',
            'atom-ahg-plugins/ahgRepositoryManagePlugin/modules/sfIsdiahPlugin/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/repository/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsdiahPlugin/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/repository/templates',
            'apps/qubit/modules/repository/templates',
        ],
        'ahg-donor-manage' => [
            'atom-ahg-plugins/ahgDonorManagePlugin/modules/donorManage/templates',
            'atom-ahg-plugins/ahgDonorManagePlugin/modules/donor/templates',
            'atom-ahg-plugins/ahgDonorAgreementPlugin/modules/donorAgreement/templates',
        ],
        'ahg-rights-holder-manage' => [
            'atom-ahg-plugins/ahgRightsHolderManagePlugin/modules/rightsHolderManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/rightsholder/templates',
            'atom-ahg-plugins/ahgRightsPlugin/modules/rights/templates',
            'atom-ahg-plugins/ahgRightsPlugin/modules/rightsAdmin/templates',
            'atom-ahg-plugins/ahgExtendedRightsPlugin/modules/extendedRights/templates',
            'atom-ahg-plugins/ahgExtendedRightsPlugin/modules/embargo/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/extendedRights/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/right/templates',
            'apps/qubit/modules/rightsholder/templates',
            'apps/qubit/modules/right/templates',
        ],
        'ahg-storage-manage' => [
            'atom-ahg-plugins/ahgStorageManagePlugin/modules/storageManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/physicalobject/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/physicalobject/templates',
            'apps/qubit/modules/physicalobject/templates',
        ],
        'ahg-accession-manage' => [
            'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionIntake/templates',
            'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionManage/templates',
            'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionAppraisal/templates',
            'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionContainer/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/accession/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/deaccession/templates',
        ],
        'ahg-term-taxonomy' => [
            'atom-ahg-plugins/ahgTermTaxonomyPlugin/modules/termTaxonomy/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/term/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/taxonomy/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/term/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/taxonomy/templates',
            'apps/qubit/modules/term/templates',
            'apps/qubit/modules/taxonomy/templates',
        ],
        'ahg-function-manage' => [
            'atom-ahg-plugins/ahgFunctionManagePlugin/modules/functionManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/function/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsdfPlugin/templates',
            'apps/qubit/modules/function/templates',
        ],
        'ahg-user-manage' => [
            'atom-ahg-plugins/ahgUserManagePlugin/modules/userManage/templates',
            'atom-ahg-plugins/ahgUserRegistrationPlugin/modules/userRegistration/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/user/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/user/templates',
            'apps/qubit/modules/user/templates',
        ],
        'ahg-settings' => [
            'atom-ahg-plugins/ahgSettingsPlugin/modules/ahgSettings/templates',
            'atom-ahg-plugins/ahgSettingsPlugin/modules/ahgDropdown/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/settings/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfPluginAdminPlugin/templates',
            'apps/qubit/modules/settings/templates',
        ],
        'ahg-jobs-manage' => [
            'atom-ahg-plugins/ahgJobsManagePlugin/modules/jobsManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/jobs/templates',
            'apps/qubit/modules/jobs/templates',
        ],
        'ahg-search' => [
            'atom-ahg-plugins/ahgSearchPlugin/modules/ahgSearch/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/search/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/search/templates',
            'apps/qubit/modules/search/templates',
        ],
        'ahg-static-page' => [
            'atom-ahg-plugins/ahgStaticPagePlugin/modules/staticPageManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/staticpage/templates',
            'apps/qubit/modules/staticpage/templates',
        ],
        'ahg-menu-manage' => [
            'atom-ahg-plugins/ahgMenuManagePlugin/modules/menuManage/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/menu/templates',
            'apps/qubit/modules/menu/templates',
        ],
        'ahg-reports' => [
            'atom-ahg-plugins/ahgReportsPlugin/modules/reports/templates',
            'atom-ahg-plugins/ahgReportBuilderPlugin/modules/reportBuilder/templates',
        ],
        'ahg-cart' => [
            'atom-ahg-plugins/ahgCartPlugin/modules/cart/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/clipboard/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/clipboard/templates',
            'apps/qubit/modules/clipboard/templates',
        ],
        'ahg-favorites' => [
            'atom-ahg-plugins/ahgFavoritesPlugin/modules/favorites/templates',
        ],
        'ahg-acl' => [
            'atom-ahg-plugins/ahgSecurityClearancePlugin/modules/securityClearance/templates',
            'atom-ahg-plugins/ahgSecurityClearancePlugin/modules/security/templates',
            'atom-ahg-plugins/ahgSecurityClearancePlugin/modules/accessFilter/templates',
            'atom-ahg-plugins/ahgSecurityClearancePlugin/modules/securityAudit/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/aclGroup/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/aclGroup/templates',
        ],
        'ahg-audit-trail' => [
            'atom-ahg-plugins/ahgAuditTrailPlugin/modules/auditTrail/templates',
        ],
        'ahg-backup' => [
            'atom-ahg-plugins/ahgBackupPlugin/modules/backup/templates',
        ],
        'ahg-data-migration' => [
            'atom-ahg-plugins/ahgDataMigrationPlugin/modules/dataMigration/templates',
            'atom-ahg-plugins/ahgDataMigrationPlugin/modules/preservica/templates',
        ],
        'ahg-display' => [
            'atom-ahg-plugins/ahgDisplayPlugin/modules/display/templates',
            'atom-ahg-plugins/ahgDisplayPlugin/modules/digitalobject/templates',
            'atom-ahg-plugins/ahgDisplayPlugin/modules/displaySearch/templates',
            'atom-ahg-plugins/ahgDisplayPlugin/modules/treeview/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/digitalobject/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/digitalobject/templates',
            'apps/qubit/modules/digitalobject/templates',
        ],
        'ahg-dropdown-manage' => [
            'atom-ahg-plugins/ahgSettingsPlugin/modules/ahgDropdown/templates',
        ],
        'ahg-feedback' => [
            'atom-ahg-plugins/ahgFeedbackPlugin/modules/feedback/templates',
        ],
        'ahg-iiif-collection' => [
            'atom-ahg-plugins/ahgIiifPlugin/modules/iiifCollection/templates',
            'atom-ahg-plugins/ahgIiifPlugin/modules/iiif/templates',
            'atom-ahg-plugins/ahgIiifPlugin/modules/iiifAuth/templates',
            'atom-ahg-plugins/ahgIiifPlugin/modules/mediaSettings/templates',
        ],
        'ahg-request-publish' => [
            'atom-ahg-plugins/ahgRequestToPublishPlugin/modules/requestToPublish/templates',
            'atom-ahg-plugins/ahgRequestToPublishPlugin/modules/requesttopublish/templates',
        ],
        'ahg-workflow' => [
            'atom-ahg-plugins/ahgWorkflowPlugin/modules/workflow/templates',
        ],
        'ahg-gallery' => [
            'atom-ahg-plugins/ahgGalleryPlugin/modules/gallery/templates',
            'atom-ahg-plugins/ahgGalleryPlugin/modules/galleryReports/templates',
        ],
        'ahg-museum' => [
            'atom-ahg-plugins/ahgMuseumPlugin/modules/museum/templates',
            'atom-ahg-plugins/ahgMuseumPlugin/modules/cco/templates',
            'atom-ahg-plugins/ahgMuseumPlugin/modules/cidoc/templates',
            'atom-ahg-plugins/ahgMuseumPlugin/modules/dashboard/templates',
            'atom-ahg-plugins/ahgMuseumPlugin/modules/museumReports/templates',
        ],
        'ahg-library' => [
            'atom-ahg-plugins/ahgLibraryPlugin/modules/library/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/circulation/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/acquisition/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/patron/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/serial/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/ill/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/isbn/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/opac/templates',
            'atom-ahg-plugins/ahgLibraryPlugin/modules/libraryReports/templates',
        ],
        'ahg-dam' => [
            'atom-ahg-plugins/ahgDAMPlugin/modules/dam/templates',
            'atom-ahg-plugins/ahgDAMPlugin/modules/damReports/templates',
        ],
        'ahg-heritage-manage' => [
            'atom-ahg-plugins/ahgHeritagePlugin/modules/heritage/templates',
            'atom-ahg-plugins/ahgHeritageAccountingPlugin/modules/heritageAccounting/templates',
            'atom-ahg-plugins/ahgHeritageAccountingPlugin/modules/heritageAdmin/templates',
            'atom-ahg-plugins/ahgHeritageAccountingPlugin/modules/heritageReport/templates',
            'atom-ahg-plugins/ahgHeritageAccountingPlugin/modules/grapCompliance/templates',
        ],
        'ahg-research' => [
            'atom-ahg-plugins/ahgResearchPlugin/modules/research/templates',
            'atom-ahg-plugins/ahgResearchPlugin/modules/audit/templates',
        ],
        'ahg-researcher-manage' => [
            'atom-ahg-plugins/ahgResearcherPlugin/modules/researcher/templates',
        ],
        'ahg-dedupe' => [
            'atom-ahg-plugins/ahgDedupePlugin/modules/dedupe/templates',
            'atom-ahg-plugins/ahgAuthorityPlugin/modules/authorityDedup/templates',
        ],
        'ahg-doi-manage' => [
            'atom-ahg-plugins/ahgDoiPlugin/modules/doi/templates',
        ],
        'ahg-loan' => [
            'atom-ahg-plugins/ahgLoanPlugin/modules/loan/templates',
        ],
        'ahg-preservation' => [
            'atom-ahg-plugins/ahgPreservationPlugin/modules/preservation/templates',
            'atom-ahg-plugins/ahgPreservationPlugin/modules/tiffpdfmerge/templates',
        ],
        'ahg-ai-services' => [
            'atom-ahg-plugins/ahgAIPlugin/modules/ai/templates',
            'atom-ahg-plugins/ahgAiConditionPlugin/modules/aiCondition/templates',
        ],
        'ahg-3d-model' => [
            'atom-ahg-plugins/ahg3DModelPlugin/modules/model3d/templates',
            'atom-ahg-plugins/ahg3DModelPlugin/modules/model3dSettings/templates',
        ],
        'ahg-ftp-upload' => [
            'atom-ahg-plugins/ahgFtpPlugin/modules/ftpUpload/templates',
        ],
        'ahg-help' => [
            'atom-ahg-plugins/ahgHelpPlugin/modules/help/templates',
        ],
        'ahg-integrity' => [
            'atom-ahg-plugins/ahgIntegrityPlugin/modules/integrity/templates',
        ],
        'ahg-metadata-extraction' => [
            'atom-ahg-plugins/ahgMetadataExtractionPlugin/modules/metadataExtraction/templates',
        ],
        'ahg-portable-export' => [
            'atom-ahg-plugins/ahgPortableExportPlugin/modules/portableExport/templates',
        ],
        'ahg-pdf-tools' => [
            'atom-ahg-plugins/ahgPreservationPlugin/modules/tiffpdfmerge/templates',
        ],
        'ahg-ric' => [
            'atom-ahg-plugins/ahgRicExplorerPlugin/modules/ricDashboard/templates',
            'atom-ahg-plugins/ahgRicExplorerPlugin/modules/ricExplorer/templates',
            'atom-ahg-plugins/ahgRicExplorerPlugin/modules/ricSemanticSearch/templates',
        ],
        'ahg-media-processing' => [
            'atom-ahg-plugins/ahgIiifPlugin/modules/mediaSettings/templates',
            'atom-ahg-plugins/ahgIiifPlugin/modules/threeDReports/templates',
        ],
        'ahg-theme-b5' => [
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/default/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/admin/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/browse/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/default/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/admin/templates',
            'apps/qubit/modules/default/templates',
            'apps/qubit/modules/admin/templates',
        ],
        'ahg-core' => [
            'atom-ahg-plugins/ahgCorePlugin/modules/clipboard/templates',
            'atom-ahg-plugins/ahgCorePlugin/modules/contactinformation/templates',
            'atom-ahg-plugins/ahgThemeB5Plugin/modules/contactinformation/templates',
            'apps/qubit/modules/contactinformation/templates',
            'apps/qubit/modules/clipboard/templates',
        ],
    ];

    // View name mapping: Heratio blade → AtoM template naming
    $viewMap = [
        'browse' => ['browseSuccess', 'listSuccess', 'indexSuccess'],
        'show' => ['indexSuccess', 'showSuccess', 'viewSuccess'],
        'edit' => ['editSuccess', 'updateSuccess'],
        'delete' => ['deleteSuccess'],
        'create' => ['createSuccess', 'addSuccess', 'newSuccess'],
        'print' => ['printSuccess', 'printPreviewSuccess'],
    ];

    $viewBasename = pathinfo($view, PATHINFO_FILENAME);
    $viewBasename = str_replace('.blade', '', $viewBasename);

    $results = [];
    $pkg = $package;

    if (!isset($mapping[$pkg])) return $results;

    foreach ($mapping[$pkg] as $atomDir) {
        $fullDir = "$atomBase/$atomDir";
        if (!is_dir($fullDir)) continue;

        // Try mapped names
        $candidates = $viewMap[$viewBasename] ?? [$viewBasename . 'Success', $viewBasename];
        foreach ($candidates as $candidate) {
            $file = "$fullDir/{$candidate}.php";
            if (file_exists($file)) {
                $results[] = $file;
            }
        }

        // Also try direct match
        $direct = "$fullDir/{$viewBasename}.php";
        if (file_exists($direct) && !in_array($direct, $results)) {
            $results[] = $direct;
        }

        // Try with underscore prefix (partials)
        if (strpos($viewBasename, '_') === 0) {
            $partial = "$fullDir/{$viewBasename}.php";
            if (file_exists($partial) && !in_array($partial, $results)) {
                $results[] = $partial;
            }
        }
    }

    return $results;
}

// ── Main scan ────────────────────────────────────────────────────────────

$allResults = [];
$totals = ['heratio_controls' => 0, 'atom_controls' => 0, 'delta' => 0, 'files' => 0, 'missing_atom' => 0];

// Find all Heratio blade files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($heratioBase, RecursiveDirectoryIterator::SKIP_DOTS)
);

$bladeFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/\.blade\.php$/', $file->getFilename())) {
        $bladeFiles[] = $file->getPathname();
    }
}
sort($bladeFiles);

foreach ($bladeFiles as $bladePath) {
    // Extract package name
    $rel = str_replace($heratioBase . '/', '', $bladePath);
    $parts = explode('/', $rel);
    $package = $parts[0];
    $viewFile = basename($bladePath);

    $heratioContent = file_get_contents($bladePath);
    $hControls = countControls($heratioContent);

    // Find AtoM equivalent
    $atomFiles = findAtomEquivalent($package, $viewFile);
    $aControls = null;
    $atomPath = '';

    if (!empty($atomFiles)) {
        // Merge controls from all matching AtoM files
        $atomContent = '';
        foreach ($atomFiles as $af) {
            $atomContent .= file_get_contents($af) . "\n";
        }
        $aControls = countControls($atomContent);
        $atomPath = implode(', ', array_map(function($f) use ($atomBase) {
            return str_replace($atomBase . '/', '', $f);
        }, $atomFiles));
    }

    $row = [
        'package'    => $package,
        'view'       => str_replace('.blade.php', '', $viewFile),
        'heratio'    => $bladePath,
        'atom_files' => $atomPath,
        'layout'     => $hControls['layout'],
        'sidebar'    => $hControls['sidebar'],
        'h_buttons'  => $hControls['buttons'],
        'h_links'    => $hControls['links'],
        'h_fields'   => $hControls['total_fields'],
        'h_headings' => $hControls['headings'],
        'h_labels'   => $hControls['labels'],
        'h_badges'   => $hControls['badges'],
        'h_icons'    => $hControls['icons'],
        'h_tables'   => $hControls['tables'],
        'h_th'       => $hControls['table_cols'],
        'h_total'    => $hControls['total_controls'],
        'h_btn_cls'  => $hControls['btn_classes'],
        'h_hrefs'    => $hControls['link_hrefs'],
        'h_fbadges'  => $hControls['field_badges'],
        'a_buttons'  => $aControls ? $aControls['buttons'] : '—',
        'a_links'    => $aControls ? $aControls['links'] : '—',
        'a_fields'   => $aControls ? $aControls['total_fields'] : '—',
        'a_headings' => $aControls ? $aControls['headings'] : '—',
        'a_labels'   => $aControls ? $aControls['labels'] : '—',
        'a_badges'   => $aControls ? $aControls['badges'] : '—',
        'a_icons'    => $aControls ? $aControls['icons'] : '—',
        'a_tables'   => $aControls ? $aControls['tables'] : '—',
        'a_th'       => $aControls ? $aControls['table_cols'] : '—',
        'a_total'    => $aControls ? $aControls['total_controls'] : '—',
        'a_btn_cls'  => $aControls ? $aControls['btn_classes'] : [],
        'a_hrefs'    => $aControls ? $aControls['link_hrefs'] : [],
        'a_fbadges'  => $aControls ? $aControls['field_badges'] : ['required' => 0, 'recommended' => 0, 'optional' => 0],
        'delta'      => $aControls ? ($aControls['total_controls'] - $hControls['total_controls']) : '?',
        'has_atom'   => !empty($atomFiles),
    ];

    $allResults[] = $row;
    $totals['files']++;
    $totals['heratio_controls'] += $hControls['total_controls'];
    if ($aControls) {
        $totals['atom_controls'] += $aControls['total_controls'];
        $totals['delta'] += ($aControls['total_controls'] - $hControls['total_controls']);
    } else {
        $totals['missing_atom']++;
    }
}

// ── Output ───────────────────────────────────────────────────────────────

// Group by package
$byPackage = [];
foreach ($allResults as $r) {
    $byPackage[$r['package']][] = $r;
}
ksort($byPackage);

echo "# FULL CONTROL AUDIT: Heratio vs AtoM\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# Total Heratio views: {$totals['files']}\n";
echo "# Views with AtoM equivalent: " . ($totals['files'] - $totals['missing_atom']) . "\n";
echo "# Views without AtoM equivalent: {$totals['missing_atom']}\n";
echo "# Total Heratio controls: {$totals['heratio_controls']}\n";
echo "# Total AtoM controls (matched): {$totals['atom_controls']}\n";
echo "# Total delta: {$totals['delta']}\n\n";

// Summary table
echo "## SUMMARY BY PACKAGE\n\n";
echo str_pad('Package', 35) . str_pad('Views', 6) . str_pad('H-Ctrl', 8) . str_pad('A-Ctrl', 8) . str_pad('Delta', 8) . str_pad('Layout', 10) . str_pad('Sidebar', 10) . str_pad('AtoM?', 6) . "\n";
echo str_repeat('─', 91) . "\n";

foreach ($byPackage as $pkg => $rows) {
    $hTotal = array_sum(array_column($rows, 'h_total'));
    $aTotal = 0;
    $hasAtom = 0;
    foreach ($rows as $r) {
        if ($r['has_atom']) { $aTotal += $r['a_total']; $hasAtom++; }
    }
    $delta = $hasAtom > 0 ? ($aTotal - $hTotal) : '?';
    $layouts = array_unique(array_column($rows, 'layout'));
    $sidebars = array_unique(array_column($rows, 'sidebar'));

    echo str_pad($pkg, 35)
       . str_pad(count($rows), 6)
       . str_pad($hTotal, 8)
       . str_pad($hasAtom > 0 ? $aTotal : '—', 8)
       . str_pad($delta, 8)
       . str_pad(implode(',', $layouts), 10)
       . str_pad(implode(',', $sidebars), 10)
       . str_pad("$hasAtom/" . count($rows), 6)
       . "\n";
}

echo "\n\n## DETAILED PAGE-BY-PAGE AUDIT\n\n";

foreach ($byPackage as $pkg => $rows) {
    echo "### $pkg\n\n";
    echo str_pad('View', 30) . str_pad('Layout', 8) . str_pad('Side', 8)
       . str_pad('H-Btn', 6) . str_pad('H-Lnk', 6) . str_pad('H-Fld', 6) . str_pad('H-Hdg', 6) . str_pad('H-Lbl', 6) . str_pad('H-Bdg', 6) . str_pad('H-Tot', 7)
       . str_pad('A-Btn', 6) . str_pad('A-Lnk', 6) . str_pad('A-Fld', 6) . str_pad('A-Tot', 7)
       . str_pad('Delta', 7) . "\n";
    echo str_repeat('─', 120) . "\n";

    foreach ($rows as $r) {
        echo str_pad($r['view'], 30)
           . str_pad($r['layout'], 8)
           . str_pad($r['sidebar'], 8)
           . str_pad($r['h_buttons'], 6)
           . str_pad($r['h_links'], 6)
           . str_pad($r['h_fields'], 6)
           . str_pad($r['h_headings'], 6)
           . str_pad($r['h_labels'], 6)
           . str_pad($r['h_badges'], 6)
           . str_pad($r['h_total'], 7)
           . str_pad($r['a_buttons'], 6)
           . str_pad($r['a_links'], 6)
           . str_pad($r['a_fields'], 6)
           . str_pad($r['a_total'], 7)
           . str_pad($r['delta'], 7)
           . "\n";

        // Show button classes
        if (!empty($r['h_btn_cls'])) {
            echo "  BTN classes: " . implode(', ', array_map(fn($c,$n) => "$c($n)", array_keys($r['h_btn_cls']), $r['h_btn_cls'])) . "\n";
        }

        // Show field badges
        $fb = $r['h_fbadges'];
        if ($fb['required'] + $fb['recommended'] + $fb['optional'] > 0) {
            echo "  Field badges: Req={$fb['required']} Rec={$fb['recommended']} Opt={$fb['optional']}\n";
        } elseif ($r['h_labels'] > 2) {
            echo "  ⚠ NO FIELD BADGES on {$r['h_labels']} labels\n";
        }

        // Show link hrefs (abbreviated)
        if (!empty($r['h_hrefs'])) {
            $unique = array_unique($r['h_hrefs']);
            $display = array_slice($unique, 0, 10);
            echo "  URLs: " . implode(' | ', $display);
            if (count($unique) > 10) echo " ... +" . (count($unique) - 10) . " more";
            echo "\n";
        }

        // Show AtoM equivalent
        if ($r['atom_files']) {
            echo "  AtoM: {$r['atom_files']}\n";
        } else {
            echo "  AtoM: ⚠ NO EQUIVALENT FOUND\n";
        }
        echo "\n";
    }
    echo "\n";
}

// ── Missing field badges report ──────────────────────────────────────────
echo "\n## MISSING FIELD BADGES REPORT\n\n";
echo str_pad('Package', 35) . str_pad('View', 30) . str_pad('Labels', 8) . str_pad('Req', 5) . str_pad('Rec', 5) . str_pad('Opt', 5) . str_pad('Status', 15) . "\n";
echo str_repeat('─', 103) . "\n";

foreach ($allResults as $r) {
    if ($r['h_labels'] <= 2) continue; // skip files with few labels
    $fb = $r['h_fbadges'];
    $totalBadges = $fb['required'] + $fb['recommended'] + $fb['optional'];
    $status = $totalBadges >= $r['h_labels'] ? 'OK' : ($totalBadges > 0 ? 'PARTIAL' : 'MISSING');
    if ($status === 'OK') continue; // only show problems

    echo str_pad($r['package'], 35)
       . str_pad($r['view'], 30)
       . str_pad($r['h_labels'], 8)
       . str_pad($fb['required'], 5)
       . str_pad($fb['recommended'], 5)
       . str_pad($fb['optional'], 5)
       . str_pad($status, 15)
       . "\n";
}

// ── Button class audit ───────────────────────────────────────────────────
echo "\n\n## BUTTON CLASS AUDIT (non-theme classes)\n\n";
$badBtnFiles = [];
foreach ($allResults as $r) {
    foreach ($r['h_btn_cls'] as $cls => $count) {
        // Flag non-atom-btn classes that should probably be atom-btn-*
        // Exclude btn-primary/btn-success in user-menu (AtoM uses these for Register buttons)
        if (preg_match('/^btn-(primary|secondary|success|danger|warning|info|dark|light)$/', $cls)
            && !($r['view'] === 'user-menu' && in_array($cls, ['btn-primary', 'btn-success']))) {
            $badBtnFiles[] = [
                'package' => $r['package'],
                'view' => $r['view'],
                'class' => $cls,
                'count' => $count,
            ];
        }
    }
}

if (empty($badBtnFiles)) {
    echo "All button classes use atom-btn-* theme. ✓\n";
} else {
    echo str_pad('Package', 35) . str_pad('View', 30) . str_pad('Bad Class', 20) . str_pad('Count', 6) . "\n";
    echo str_repeat('─', 91) . "\n";
    foreach ($badBtnFiles as $bf) {
        echo str_pad($bf['package'], 35) . str_pad($bf['view'], 30) . str_pad($bf['class'], 20) . str_pad($bf['count'], 6) . "\n";
    }
}

echo "\n\n# END OF AUDIT\n";
