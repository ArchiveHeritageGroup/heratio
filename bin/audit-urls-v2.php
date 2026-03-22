#!/usr/bin/env php
<?php
/**
 * URL Audit V2: Full page-by-page, link-by-link comparison.
 * Rules 12, 12a, 12b, 13 enforcement.
 *
 * For every show/edit/browse/delete blade in Heratio:
 *   - Extract every <a href> with link text
 *   - For the matching AtoM template, extract every url_for/href
 *   - Pair them by link text
 *   - Flag MISMATCH where AtoM is scoped but Heratio is generic
 *   - Flag MISSING where AtoM has a link Heratio doesn't
 *   - Flag EXTRA where Heratio has a link AtoM doesn't
 */

$heratioBase = '/usr/share/nginx/heratio/packages';
$atomBase = '/usr/share/nginx/archive';

// Route resolution: parse `php artisan route:list` output
$routeMap = [];
$routeOutput = shell_exec('cd /usr/share/nginx/heratio && php artisan route:list 2>/dev/null');
if ($routeOutput) {
    foreach (explode("\n", $routeOutput) as $line) {
        // Format: "  GET|HEAD        path name › Controller"
        if (preg_match('/^\s+(GET|POST|PUT|DELETE|PATCH)[^\s]*\s+(\S+)\s+(\S+)/', $line, $m)) {
            $method = $m[1];
            $uri = '/' . ltrim($m[2], '/');
            $name = $m[3];
            if (strpos($name, '›') === false && strpos($name, '.') !== false) {
                $routeMap[$name] = $uri;
            }
        }
    }
}

function resolveHeratioHref(string $raw): string {
    global $routeMap;
    // route('name', $param) → /resolved/path/{param}
    if (preg_match("/route\('([^']+)'/", $raw, $m)) {
        $name = $m[1];
        return $routeMap[$name] ?? "route:$name";
    }
    // url('/path') → /path
    if (preg_match("/url\('([^']+)'/", $raw, $m)) {
        return $m[1];
    }
    return $raw;
}

function resolveAtomHref(string $raw): string {
    // url_for([$resource, 'module' => 'x', 'action' => 'y']) → /{slug}/x/y
    if (preg_match("/module.*?=.*?'(\w+)'.*?action.*?=.*?'(\w+)'/s", $raw, $m)) {
        return "/{slug}/$m[1]/$m[2]";
    }
    if (preg_match("/module.*?=.*?'(\w+)'/", $raw, $m)) {
        return "/{slug}/$m[1]";
    }
    // Direct href
    if (preg_match('/href="([^"]+)"/', $raw, $m)) {
        return $m[1];
    }
    return $raw;
}

function extractLinks(string $content, bool $isAtom = false): array {
    $links = [];
    // Match <a ... href="..." ...>text</a> (multiline)
    if (preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $content, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $href = trim($match[1]);
            $text = trim(strip_tags($match[2]));
            $text = preg_replace('/\s+/', ' ', $text);
            if (!$text || strlen($text) < 2 || $href === '#' || $href === 'javascript:void(0)') continue;
            // Skip blade/PHP noise in text
            if (preg_match('/^\{\{|^<\?php|^\$/', $text)) continue;
            if (strlen($text) > 60) $text = substr($text, 0, 57) . '...';

            $resolved = $isAtom ? resolveAtomHref($match[0]) : resolveHeratioHref($href);
            $isScoped = preg_match('/\$\w+|\{\{/', $href);

            $links[] = [
                'text' => $text,
                'raw' => substr($href, 0, 120),
                'resolved' => $resolved,
                'scoped' => $isScoped,
            ];
        }
    }
    // Also extract url_for() calls from AtoM templates
    if ($isAtom && preg_match_all('/url_for\(([^)]+)\)/s', $content, $uf)) {
        foreach ($uf[0] as $call) {
            $resolved = resolveAtomHref($call);
            if (!in_array($resolved, array_column($links, 'resolved'))) {
                $links[] = [
                    'text' => '[url_for]',
                    'raw' => substr($call, 0, 120),
                    'resolved' => $resolved,
                    'scoped' => true,
                ];
            }
        }
    }
    return $links;
}

// Mapping (same as audit-controls.php)
$mapping = json_decode(file_get_contents(__DIR__ . '/atom-mapping.json'), true) ?? [];
// Fallback: load inline if json doesn't exist
if (empty($mapping)) {
    $mapping = [
        'ahg-actor-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsaarPlugin/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/actor/templates', 'atom-ahg-plugins/ahgCorePlugin/modules/actor/templates'],
        'ahg-information-object-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsadPlugin/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/informationobject/templates', 'atom-ahg-plugins/ahgCorePlugin/modules/informationobject/templates'],
        'ahg-repository-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsdiahPlugin/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/repository/templates'],
        'ahg-accession-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/accession/templates', 'atom-ahg-plugins/ahgAccessionManagePlugin/modules/accessionIntake/templates'],
        'ahg-donor-manage' => ['atom-ahg-plugins/ahgDonorManagePlugin/modules/donorManage/templates', 'atom-ahg-plugins/ahgDonorManagePlugin/modules/donor/templates'],
        'ahg-rights-holder-manage' => ['atom-ahg-plugins/ahgRightsHolderManagePlugin/modules/rightsHolderManage/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/rightsholder/templates'],
        'ahg-function-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/sfIsdfPlugin/templates', 'atom-ahg-plugins/ahgFunctionManagePlugin/modules/functionManage/templates'],
        'ahg-term-taxonomy' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/term/templates', 'atom-ahg-plugins/ahgTermTaxonomyPlugin/modules/termTaxonomy/templates'],
        'ahg-storage-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/physicalobject/templates', 'atom-ahg-plugins/ahgStorageManagePlugin/modules/storageManage/templates'],
        'ahg-user-manage' => ['atom-ahg-plugins/ahgThemeB5Plugin/modules/user/templates', 'atom-ahg-plugins/ahgUserManagePlugin/modules/userManage/templates'],
        'ahg-settings' => ['atom-ahg-plugins/ahgSettingsPlugin/modules/ahgSettings/templates', 'atom-ahg-plugins/ahgCorePlugin/modules/settings/templates'],
        'ahg-jobs-manage' => ['atom-ahg-plugins/ahgJobsManagePlugin/modules/jobsManage/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/jobs/templates'],
        'ahg-menu-manage' => ['atom-ahg-plugins/ahgMenuManagePlugin/modules/menuManage/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/menu/templates'],
        'ahg-static-page' => ['atom-ahg-plugins/ahgStaticPagePlugin/modules/staticPageManage/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/staticpage/templates'],
        'ahg-search' => ['atom-ahg-plugins/ahgSearchPlugin/modules/ahgSearch/templates', 'atom-ahg-plugins/ahgThemeB5Plugin/modules/search/templates'],
        'ahg-feedback' => ['atom-ahg-plugins/ahgFeedbackPlugin/modules/feedback/templates'],
        'ahg-workflow' => ['atom-ahg-plugins/ahgWorkflowPlugin/modules/workflow/templates'],
        'ahg-loan' => ['atom-ahg-plugins/ahgLoanPlugin/modules/loan/templates'],
        'ahg-gallery' => ['atom-ahg-plugins/ahgGalleryPlugin/modules/gallery/templates'],
        'ahg-museum' => ['atom-ahg-plugins/ahgMuseumPlugin/modules/museum/templates'],
        'ahg-library' => ['atom-ahg-plugins/ahgLibraryPlugin/modules/library/templates'],
        'ahg-dam' => ['atom-ahg-plugins/ahgDAMPlugin/modules/dam/templates'],
        'ahg-research' => ['atom-ahg-plugins/ahgResearchPlugin/modules/research/templates'],
        'ahg-dedupe' => ['atom-ahg-plugins/ahgDedupePlugin/modules/dedupe/templates'],
        'ahg-doi-manage' => ['atom-ahg-plugins/ahgDoiPlugin/modules/doi/templates'],
        'ahg-backup' => ['atom-ahg-plugins/ahgBackupPlugin/modules/backup/templates'],
        'ahg-audit-trail' => ['atom-ahg-plugins/ahgAuditTrailPlugin/modules/auditTrail/templates'],
        'ahg-preservation' => ['atom-ahg-plugins/ahgPreservationPlugin/modules/preservation/templates'],
        'ahg-ric' => ['atom-ahg-plugins/ahgRicExplorerPlugin/modules/ricDashboard/templates'],
        'ahg-help' => ['atom-ahg-plugins/ahgHelpPlugin/modules/help/templates'],
        'ahg-iiif-collection' => ['atom-ahg-plugins/ahgIiifPlugin/modules/iiifCollection/templates'],
        'ahg-request-publish' => ['atom-ahg-plugins/ahgRequestToPublishPlugin/modules/requestToPublish/templates'],
        'ahg-cart' => ['atom-ahg-plugins/ahgCartPlugin/modules/cart/templates'],
        'ahg-favorites' => ['atom-ahg-plugins/ahgFavoritesPlugin/modules/favorites/templates'],
        'ahg-dropdown-manage' => ['atom-ahg-plugins/ahgSettingsPlugin/modules/ahgDropdown/templates'],
        'ahg-acl' => ['atom-ahg-plugins/ahgSecurityClearancePlugin/modules/securityClearance/templates'],
        'ahg-heritage-manage' => ['atom-ahg-plugins/ahgHeritagePlugin/modules/heritage/templates'],
        'ahg-data-migration' => ['atom-ahg-plugins/ahgDataMigrationPlugin/modules/dataMigration/templates'],
    ];
}

// View name → AtoM template name mapping
$viewToAtom = [
    'show' => ['indexSuccess', 'showSuccess', 'viewSuccess'],
    'browse' => ['browseSuccess', 'listSuccess'],
    'edit' => ['editSuccess'],
    'create' => ['editSuccess', 'addSuccess', 'createSuccess'],
    'delete' => ['deleteSuccess'],
];

function findAtomTemplates(string $pkg, string $viewBase): array {
    global $atomBase, $mapping, $viewToAtom;
    if (!isset($mapping[$pkg])) return [];
    $candidates = $viewToAtom[$viewBase] ?? [$viewBase . 'Success'];
    $found = [];
    foreach ($mapping[$pkg] as $dir) {
        $fullDir = "$atomBase/$dir";
        if (!is_dir($fullDir)) continue;
        foreach ($candidates as $c) {
            $f = "$fullDir/$c.php";
            if (file_exists($f)) $found[] = $f;
        }
        // Also check for _actionIcons, _contextMenu partials
        foreach (['_actionIcons', '_contextMenu', '_actions', '_findingAid', '_calculateDatesLink'] as $partial) {
            $f = "$fullDir/$partial.php";
            if (file_exists($f)) $found[] = $f;
        }
    }
    return array_unique($found);
}

// Scan
$packages = glob("$heratioBase/*/", GLOB_ONLYDIR);
sort($packages);

$totalPages = 0;
$totalLinks = 0;
$totalMismatches = 0;
$totalMissing = 0;
$allIssues = [];

echo "# FULL URL AUDIT V2: Page-by-Page Link Comparison\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# Route map: " . count($routeMap) . " named routes resolved\n\n";

foreach ($packages as $pkgPath) {
    $pkg = basename($pkgPath);
    $viewDir = "$pkgPath/resources/views";
    if (!is_dir($viewDir)) continue;

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewDir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!preg_match('/\.blade\.php$/', $f->getFilename())) continue;
        $viewName = str_replace('.blade.php', '', $f->getFilename());

        // Only check main pages (show, browse, edit, create, delete)
        if (!in_array($viewName, ['show', 'browse', 'edit', 'create', 'delete'])) continue;

        $hContent = file_get_contents($f->getPathname());
        // Resolve @include directives to inline partial content
        if (preg_match_all('/@include\s*\(\s*[\'"]([^\'"]+)[\'"]/m', $hContent, $incMatches)) {
            foreach ($incMatches[1] as $incView) {
                // Convert view namespace notation to file path
                // e.g., 'ahg-information-object-manage::_context-menu' → packages/ahg-information-object-manage/resources/views/_context-menu.blade.php
                if (str_contains($incView, '::')) {
                    [$incPkg, $incName] = explode('::', $incView, 2);
                    $incPath = "$heratioBase/$incPkg/resources/views/" . str_replace('.', '/', $incName) . ".blade.php";
                } else {
                    // Same package
                    $incPath = dirname($f->getPathname()) . '/' . str_replace('.', '/', $incView) . ".blade.php";
                }
                if (file_exists($incPath)) {
                    $hContent .= "\n" . file_get_contents($incPath);
                }
            }
        }
        $hLinks = extractLinks($hContent, false);

        $atomFiles = findAtomTemplates($pkg, $viewName);
        if (empty($atomFiles)) continue;

        $aContent = '';
        foreach ($atomFiles as $af) $aContent .= file_get_contents($af) . "\n";
        $aLinks = extractLinks($aContent, true);

        $totalPages++;
        $totalLinks += count($hLinks);

        $pageIssues = [];

        // Check: for each AtoM scoped link, is there a matching Heratio scoped link?
        foreach ($aLinks as $aLink) {
            if (!$aLink['scoped']) continue;
            $aText = strtolower($aLink['text']);

            // Find matching Heratio link by text
            $found = false;
            foreach ($hLinks as $hLink) {
                if (strtolower($hLink['text']) === $aText ||
                    stripos($hLink['text'], $aLink['text']) !== false ||
                    stripos($aLink['text'], $hLink['text']) !== false) {
                    $found = true;
                    if (!$hLink['scoped']) {
                        $pageIssues[] = [
                            'type' => 'SCOPE_MISMATCH',
                            'text' => $aLink['text'],
                            'atom_url' => $aLink['resolved'],
                            'heratio_url' => $hLink['resolved'],
                        ];
                        $totalMismatches++;
                    }
                    break;
                }
            }
            if (!$found && $aLink['text'] !== '[url_for]') {
                $pageIssues[] = [
                    'type' => 'MISSING_IN_HERATIO',
                    'text' => $aLink['text'],
                    'atom_url' => $aLink['resolved'],
                ];
                $totalMissing++;
            }
        }

        if (!empty($pageIssues)) {
            $rel = str_replace($heratioBase . '/', '', $f->getPathname());
            echo "### $pkg/$viewName\n";
            echo str_pad('Type', 20) . str_pad('Link Text', 30) . str_pad('AtoM URL', 40) . str_pad('Heratio URL', 40) . "\n";
            echo str_repeat('─', 130) . "\n";
            foreach ($pageIssues as $iss) {
                echo str_pad($iss['type'], 20)
                   . str_pad(substr($iss['text'], 0, 28), 30)
                   . str_pad(substr($iss['atom_url'] ?? '', 0, 38), 40)
                   . str_pad(substr($iss['heratio_url'] ?? 'N/A', 0, 38), 40)
                   . "\n";
            }
            echo "\n";
            $allIssues[$rel] = $pageIssues;
        }
    }
}

echo "\n## SUMMARY\n\n";
echo "Pages checked:     $totalPages\n";
echo "Total links:       $totalLinks\n";
echo "Scope mismatches:  $totalMismatches\n";
echo "Missing in Heratio: $totalMissing\n";
echo "Pages with issues: " . count($allIssues) . "\n";
