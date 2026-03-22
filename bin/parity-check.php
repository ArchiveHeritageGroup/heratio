#!/usr/bin/env php
<?php
/**
 * Phase 3: Field-by-field parity check for edit/create forms.
 * Compares name= attributes between AtoM templates and Heratio blades.
 */

$heratioBase = '/usr/share/nginx/heratio/packages';
$atomBase = '/usr/share/nginx/archive';

// Include the mapping without executing the full audit
// We extract findAtomEquivalent by loading just that function
eval('namespace ParityCheck; ' . preg_replace(
    ['/^<\?php/', '/echo\s/', '/\$allResults/', '/\$totals/'],
    ['', '//echo ', '//$allResults', '//$totals'],
    ''
));

// Inline the mapping from audit-controls.php
$mapping = [
    'ahg-actor-manage' => [
        'atom-ahg-plugins/ahgActorManagePlugin/modules/actorManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/actor/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsaarPlugin/templates',
    ],
    'ahg-information-object-manage' => [
        'atom-ahg-plugins/ahgInformationObjectManagePlugin/modules/ioManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/informationobject/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsadPlugin/templates',
        'atom-ahg-plugins/ahgCorePlugin/modules/informationobject/templates',
    ],
    'ahg-repository-manage' => [
        'atom-ahg-plugins/ahgRepositoryManagePlugin/modules/repositoryManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/repository/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsdiahPlugin/templates',
    ],
    'ahg-accession-manage' => [
        'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionIntake/templates',
        'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/accession/templates',
    ],
    'ahg-donor-manage' => [
        'atom-ahg-plugins/ahgDonorManagePlugin/modules/donorManage/templates',
        'atom-ahg-plugins/ahgDonorManagePlugin/modules/donor/templates',
    ],
    'ahg-function-manage' => [
        'atom-ahg-plugins/ahgFunctionManagePlugin/modules/functionManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/function/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsdfPlugin/templates',
    ],
    'ahg-user-manage' => [
        'atom-ahg-plugins/ahgUserManagePlugin/modules/userManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/user/templates',
    ],
    'ahg-term-taxonomy' => [
        'atom-ahg-plugins/ahgTermTaxonomyPlugin/modules/termTaxonomy/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/term/templates',
    ],
    'ahg-storage-manage' => [
        'atom-ahg-plugins/ahgStorageManagePlugin/modules/storageManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/physicalobject/templates',
    ],
    'ahg-rights-holder-manage' => [
        'atom-ahg-plugins/ahgRightsHolderManagePlugin/modules/rightsHolderManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/rightsholder/templates',
    ],
    'ahg-settings' => [
        'atom-ahg-plugins/ahgSettingsPlugin/modules/ahgSettings/templates',
        'atom-ahg-plugins/ahgCorePlugin/modules/settings/templates',
    ],
    'ahg-static-page' => [
        'atom-ahg-plugins/ahgStaticPagePlugin/modules/staticPageManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/staticpage/templates',
    ],
    'ahg-menu-manage' => [
        'atom-ahg-plugins/ahgMenuManagePlugin/modules/menuManage/templates',
        'atom-ahg-plugins/ahgThemeB5Plugin/modules/menu/templates',
    ],
    'ahg-feedback' => [
        'atom-ahg-plugins/ahgFeedbackPlugin/modules/feedback/templates',
    ],
    'ahg-dropdown-manage' => [
        'atom-ahg-plugins/ahgSettingsPlugin/modules/ahgDropdown/templates',
    ],
    'ahg-iiif-collection' => [
        'atom-ahg-plugins/ahgIiifPlugin/modules/iiifCollection/templates',
    ],
    'ahg-request-publish' => [
        'atom-ahg-plugins/ahgRequestToPublishPlugin/modules/requestToPublish/templates',
    ],
    'ahg-loan' => [
        'atom-ahg-plugins/ahgLoanPlugin/modules/loan/templates',
    ],
    'ahg-workflow' => [
        'atom-ahg-plugins/ahgWorkflowPlugin/modules/workflow/templates',
    ],
    'ahg-gallery' => [
        'atom-ahg-plugins/ahgGalleryPlugin/modules/gallery/templates',
    ],
    'ahg-museum' => [
        'atom-ahg-plugins/ahgMuseumPlugin/modules/museum/templates',
        'atom-ahg-plugins/ahgMuseumPlugin/modules/cco/templates',
    ],
    'ahg-library' => [
        'atom-ahg-plugins/ahgLibraryPlugin/modules/library/templates',
    ],
    'ahg-dam' => [
        'atom-ahg-plugins/ahgDAMPlugin/modules/dam/templates',
    ],
    'ahg-research' => [
        'atom-ahg-plugins/ahgResearchPlugin/modules/research/templates',
    ],
    'ahg-dedupe' => [
        'atom-ahg-plugins/ahgDedupePlugin/modules/dedupe/templates',
    ],
    'ahg-doi-manage' => [
        'atom-ahg-plugins/ahgDoiPlugin/modules/doi/templates',
    ],
    'ahg-backup' => [
        'atom-ahg-plugins/ahgBackupPlugin/modules/backup/templates',
    ],
    'ahg-audit-trail' => [
        'atom-ahg-plugins/ahgAuditTrailPlugin/modules/auditTrail/templates',
    ],
    'ahg-data-migration' => [
        'atom-ahg-plugins/ahgDataMigrationPlugin/modules/dataMigration/templates',
    ],
    'ahg-preservation' => [
        'atom-ahg-plugins/ahgPreservationPlugin/modules/preservation/templates',
    ],
    'ahg-help' => [
        'atom-ahg-plugins/ahgHelpPlugin/modules/help/templates',
    ],
];

function findAtom(string $pkg, string $viewBase): array {
    global $atomBase, $mapping;
    if (!isset($mapping[$pkg])) return [];

    $viewMap = [
        'edit' => ['editSuccess', 'updateSuccess'],
        'create' => ['createSuccess', 'addSuccess', 'newSuccess', 'editSuccess'],
    ];
    $candidates = $viewMap[$viewBase] ?? [$viewBase . 'Success'];

    $found = [];
    foreach ($mapping[$pkg] as $dir) {
        $fullDir = "$atomBase/$dir";
        if (!is_dir($fullDir)) continue;
        foreach ($candidates as $c) {
            $f = "$fullDir/$c.php";
            if (file_exists($f)) $found[] = $f;
        }
    }
    return $found;
}

function extractFields(string $content): array {
    $fields = [];
    if (preg_match_all('/\bname\s*=\s*["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $name) {
            $name = preg_replace('/\w+\[\d+\]\[(\w+)\]/', '$1', $name);
            $name = preg_replace('/\w+\[(\w+)\]/', '$1', $name);
            if (in_array($name, ['_token','_method','sf_method','next','csrf_token','MAX_FILE_SIZE','topLod','sort'])) continue;
            $fields[] = $name;
        }
    }
    if (preg_match_all('/render_field\(\$form->(\w+)/', $content, $m)) {
        foreach ($m[1] as $f) $fields[] = $f;
    }
    return array_values(array_unique($fields));
}

// Scan all edit/create views
$packages = glob("$heratioBase/*/", GLOB_ONLYDIR);
sort($packages);

$results = [];
$totalMissing = 0;

foreach ($packages as $pkgPath) {
    $pkg = basename($pkgPath);
    $viewDir = "$pkgPath/resources/views";
    if (!is_dir($viewDir)) continue;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('/\.blade\.php$/', $f->getFilename())) continue;
        $viewName = str_replace([$viewDir . '/', '.blade.php'], '', $f->getPathname());
        $viewBase = basename($viewName);
        if (!in_array($viewBase, ['edit', 'create'])) continue;

        $hContent = file_get_contents($f->getPathname());
        $hFields = extractFields($hContent);

        $atomFiles = findAtom($pkg, $viewBase);
        if (empty($atomFiles)) continue; // skip unmapped

        $aContent = '';
        foreach ($atomFiles as $af) $aContent .= file_get_contents($af) . "\n";
        $aFields = extractFields($aContent);

        $missing = array_values(array_diff($aFields, $hFields));
        $extra = array_values(array_diff($hFields, $aFields));

        $results[] = [
            'pkg' => $pkg,
            'view' => $viewName,
            'h' => count($hFields),
            'a' => count($aFields),
            'missing' => $missing,
            'extra' => $extra,
            'atom' => array_map(fn($f) => str_replace($atomBase.'/', '', $f), $atomFiles),
        ];
        $totalMissing += count($missing);
    }
}

echo "# FIELD PARITY: Edit/Create Forms (AtoM vs Heratio)\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# Forms compared: " . count($results) . "\n";
echo "# Total missing fields: $totalMissing\n\n";

echo str_pad('Package', 35) . str_pad('View', 20) . str_pad('H-Fld', 7) . str_pad('A-Fld', 7) . str_pad('Miss', 6) . str_pad('Extra', 6) . "\n";
echo str_repeat('─', 81) . "\n";

foreach ($results as $r) {
    $status = count($r['missing']) === 0 ? '✓' : '✗';
    echo str_pad($r['pkg'], 35) . str_pad($r['view'], 20)
       . str_pad($r['h'], 7) . str_pad($r['a'], 7)
       . str_pad(count($r['missing']), 6) . str_pad(count($r['extra']), 6)
       . " $status\n";

    if (!empty($r['missing'])) {
        echo "  MISSING: " . implode(', ', $r['missing']) . "\n";
    }
    echo "  AtoM: " . implode(', ', $r['atom']) . "\n\n";
}
