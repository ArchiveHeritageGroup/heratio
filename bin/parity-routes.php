#!/usr/bin/env php
<?php
/**
 * Heratio Parity Routes — AtoM vs Heratio Route Comparison
 *
 * Reads all AtoM routing.yml files (base Symfony + AHG plugins) and compares
 * them against Heratio's artisan route:list output.
 *
 * Usage:
 *   php bin/parity-routes.php [OPTIONS]
 *
 * Options:
 *   --output FILE      Write HTML report (default: /tmp/parity-routes-report.html)
 *   --json             Output JSON instead of terminal text
 *   --plugin PATTERN   Only check routes from matching plugins
 *   --missing-only     Only show routes missing from Heratio
 *   --help             Show usage
 */

// ── Configuration ────────────────────────────────────────────────────────────

$atomBase         = '/usr/share/nginx/archive';
$atomPluginsDir   = $atomBase . '/atom-ahg-plugins';
$atomBaseRouting  = $atomBase . '/apps/qubit/config/routing.yml';
$heratioRoot      = '/usr/share/nginx/heratio';
$outputFile       = '/tmp/parity-routes-report.html';
$jsonOutput       = false;
$pluginFilter     = '';
$missingOnly      = false;

// ── Parse CLI arguments ──────────────────────────────────────────────────────

$args = array_slice($argv, 1);
for ($i = 0; $i < count($args); $i++) {
    switch ($args[$i]) {
        case '--output':
            $outputFile = $args[++$i] ?? $outputFile;
            break;
        case '--json':
            $jsonOutput = true;
            break;
        case '--plugin':
            $pluginFilter = $args[++$i] ?? '';
            break;
        case '--missing-only':
            $missingOnly = true;
            break;
        case '--help':
            echo <<<HELP
Heratio Parity Routes — AtoM vs Heratio Route Comparison

Usage: php bin/parity-routes.php [OPTIONS]

Options:
  --output FILE      Write HTML report (default: /tmp/parity-routes-report.html)
  --json             Output JSON instead of terminal text
  --plugin PATTERN   Only check routes from matching plugins
  --missing-only     Only show routes missing from Heratio
  --help             Show this help

HELP;
            exit(0);
    }
}

// ── Colors (terminal) ────────────────────────────────────────────────────────

define('RED',    "\033[0;31m");
define('GREEN',  "\033[0;32m");
define('YELLOW', "\033[1;33m");
define('BLUE',   "\033[0;34m");
define('CYAN',   "\033[0;36m");
define('BOLD',   "\033[1m");
define('DIM',    "\033[2m");
define('NC',     "\033[0m");

// ── Step 1: Get Heratio routes ──────────────────────────────────────────────

echo BLUE . "Loading Heratio routes..." . NC . "\n";

$routeJson = shell_exec("php {$heratioRoot}/artisan route:list --json 2>/dev/null");
$heratioRoutes = json_decode($routeJson, true) ?: [];

// Build lookup: normalized URI => route info
$heratioLookup = [];
$heratioUris = [];
foreach ($heratioRoutes as $route) {
    $method = $route['method'] ?? '';
    $uri = ltrim($route['uri'] ?? '', '/');
    $name = $route['name'] ?? '';
    $action = $route['action'] ?? '';

    // Normalize: replace {param} with :param for comparison
    $normalized = preg_replace('/\{([^}]+)\}/', ':$1', $uri);
    $heratioLookup[$normalized] = [
        'method' => $method,
        'uri'    => $uri,
        'name'   => $name,
        'action' => $action,
    ];
    $heratioUris[] = $normalized;
}

echo GREEN . "  Found " . count($heratioRoutes) . " Heratio routes" . NC . "\n";

// ── Step 2: Parse AtoM routing.yml files ────────────────────────────────────

echo BLUE . "Loading AtoM routes..." . NC . "\n";

/**
 * Parse a Symfony 1.x routing.yml and extract route definitions.
 * Returns array of [name => [url, module, action, params]]
 */
function parseRoutingYml(string $filePath): array
{
    if (!file_exists($filePath)) {
        return [];
    }

    $content = file_get_contents($filePath);
    $routes = [];
    $currentRoute = null;
    $currentData = [];
    $inParam = false;
    $indent = 0;

    foreach (explode("\n", $content) as $line) {
        // Skip empty lines and comments
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }

        // Detect route name (no leading whitespace, ends with colon)
        if (preg_match('/^([a-zA-Z0-9_\/;]+):\s*$/', $line, $m)) {
            // Save previous route
            if ($currentRoute !== null) {
                $routes[$currentRoute] = $currentData;
            }
            $currentRoute = $m[1];
            $currentData = ['url' => '', 'module' => '', 'action' => '', 'class' => ''];
            $inParam = false;
            continue;
        }

        if ($currentRoute === null) {
            continue;
        }

        // Parse url
        if (preg_match('/^\s+url:\s*(.+)/', $line, $m)) {
            $currentData['url'] = trim($m[1]);
            continue;
        }

        // Parse class
        if (preg_match('/^\s+class:\s*(.+)/', $line, $m)) {
            $currentData['class'] = trim($m[1]);
            continue;
        }

        // Parse param (inline YAML hash)
        if (preg_match('/^\s+param:\s*\{(.+)\}/', $line, $m)) {
            $params = parseInlineYamlHash($m[1]);
            $currentData['module'] = $params['module'] ?? '';
            $currentData['action'] = $params['action'] ?? '';
            continue;
        }

        // Parse param block start
        if (preg_match('/^\s+param:\s*$/', $line)) {
            $inParam = true;
            continue;
        }

        // Parse param block entries
        if ($inParam && preg_match('/^\s+(module|action):\s*(.+)/', $line, $m)) {
            $val = trim($m[2]);
            // Handle pattern syntax: { pattern: '...' }
            if (preg_match('/^\{/', $val)) {
                // It's a constraint, not a simple value
                $currentData[$m[1]] = $val;
            } else {
                $currentData[$m[1]] = $val;
            }
            continue;
        }

        // Requirements or other nested blocks end param
        if (preg_match('/^\s+requirements:/', $line)) {
            $inParam = false;
            continue;
        }
    }

    // Save last route
    if ($currentRoute !== null) {
        $routes[$currentRoute] = $currentData;
    }

    return $routes;
}

/**
 * Parse inline YAML hash like: module: donorManage, action: browse
 */
function parseInlineYamlHash(string $str): array
{
    $result = [];
    // Split by comma, handle nested braces
    $pairs = preg_split('/,\s*(?![^{]*\})/', $str);
    foreach ($pairs as $pair) {
        $pair = trim($pair);
        if (preg_match('/^(\w+):\s*(.+)$/', $pair, $m)) {
            $result[$m[1]] = trim($m[2]);
        }
    }
    return $result;
}

/**
 * Normalize an AtoM URL pattern into a comparable form.
 * - Remove leading /
 * - Keep :param placeholders as-is
 */
function normalizeAtomUrl(string $url): string
{
    $url = ltrim(trim($url), '/');
    return $url;
}

// Parse base AtoM routing
$atomRoutes = [];

// Base Symfony routing
$baseRoutes = parseRoutingYml($atomBaseRouting);
foreach ($baseRoutes as $name => $data) {
    $atomRoutes[$name] = array_merge($data, ['source' => 'base']);
}

// AHG plugin routing files
$pluginDirs = glob($atomPluginsDir . '/*/config/routing.yml');
foreach ($pluginDirs as $routingFile) {
    // Extract plugin name
    preg_match('#/atom-ahg-plugins/([^/]+)/#', $routingFile, $m);
    $pluginName = $m[1] ?? 'unknown';

    // Apply plugin filter
    if ($pluginFilter && stripos($pluginName, $pluginFilter) === false) {
        continue;
    }

    $pluginRoutes = parseRoutingYml($routingFile);
    foreach ($pluginRoutes as $name => $data) {
        $atomRoutes[$name] = array_merge($data, ['source' => $pluginName]);
    }
}

// Also scan for programmatically-defined routes (documented in comments)
$programmaticRoutes = extractProgrammaticRoutes($atomPluginsDir);
foreach ($programmaticRoutes as $name => $data) {
    if ($pluginFilter && isset($data['source']) && stripos($data['source'], $pluginFilter) === false) {
        continue;
    }
    if (!isset($atomRoutes[$name])) {
        $atomRoutes[$name] = $data;
    }
}

echo GREEN . "  Found " . count($atomRoutes) . " AtoM routes" . NC . "\n";

/**
 * Extract routes documented in comments of routing.yml files
 * (for plugins that define routes programmatically)
 */
function extractProgrammaticRoutes(string $pluginsDir): array
{
    $routes = [];
    $files = glob($pluginsDir . '/*/config/routing.yml');

    foreach ($files as $file) {
        $content = file_get_contents($file);
        preg_match('#/atom-ahg-plugins/([^/]+)/#', $file, $m);
        $pluginName = $m[1] ?? 'unknown';

        // Look for comment-documented routes: # GET /path/to/thing - Description
        if (preg_match_all('/^#\s*(GET|POST|PUT|DELETE)\s+(\S+)\s+-?\s*(.*)$/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $method = $match[1];
                $url = ltrim($match[2], '/');
                $desc = trim($match[3]);
                $routeName = 'prog_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($url));
                $routes[$routeName] = [
                    'url'    => '/' . $url,
                    'module' => $desc,
                    'action' => $method,
                    'class'  => 'programmatic',
                    'source' => $pluginName . ' (programmatic)',
                ];
            }
        }
    }

    return $routes;
}

// ── Step 3: Compare routes ──────────────────────────────────────────────────

echo BLUE . "\nComparing routes..." . NC . "\n\n";

$results = [
    'matched'    => [],
    'missing'    => [],
    'extra'      => [],
    'dynamic'    => [], // AtoM routes with :slug/:module patterns (hard to match)
];

// Known URL mapping: AtoM URL pattern => Heratio URI pattern
$urlMappings = [
    'user/list'         => 'user/browse',
    'physicalobject'    => 'physicalobject',
];

foreach ($atomRoutes as $routeName => $data) {
    $atomUrl = normalizeAtomUrl($data['url'] ?? '');

    // Skip catch-all/dynamic Symfony routes (/:slug, /:module/:action, etc.)
    if (in_array($atomUrl, [':slug', ':slug/:module/:action', ':module', ':module/:action',
        ':slug;:template', ':slug;:module', ':module/:action/id/:id',
        ':module/copy', ':module/add', ':slug/edit', ':slug/:action'])) {
        $results['dynamic'][] = [
            'atom_name'   => $routeName,
            'atom_url'    => $atomUrl,
            'module'      => $data['module'] ?? '',
            'action'      => $data['action'] ?? '',
            'source'      => $data['source'] ?? '',
        ];
        continue;
    }

    // Normalize AtoM URL for comparison
    // Replace :slug, :id etc. with Heratio-style :param
    $compareUrl = $atomUrl;

    // Check if Heratio has a matching route
    $found = false;
    $matchedHeratio = null;

    // Direct match
    if (isset($heratioLookup[$compareUrl])) {
        $found = true;
        $matchedHeratio = $heratioLookup[$compareUrl];
    }

    // Try with URL mappings
    if (!$found) {
        foreach ($urlMappings as $atomPattern => $heratioPattern) {
            $mapped = str_replace($atomPattern, $heratioPattern, $compareUrl);
            if ($mapped !== $compareUrl && isset($heratioLookup[$mapped])) {
                $found = true;
                $matchedHeratio = $heratioLookup[$mapped];
                break;
            }
        }
    }

    // Try fuzzy match: strip leading entity prefix and check
    if (!$found) {
        // Try matching by checking if any Heratio route contains the same path
        foreach ($heratioUris as $hUri) {
            // Normalize both for comparison (replace :param with *)
            $atomNorm = preg_replace('/:[\w]+/', '*', $compareUrl);
            $herNorm = preg_replace('/:[\w]+/', '*', $hUri);
            if ($atomNorm === $herNorm) {
                $found = true;
                $matchedHeratio = $heratioLookup[$hUri];
                break;
            }
        }
    }

    if ($found) {
        $results['matched'][] = [
            'atom_name'   => $routeName,
            'atom_url'    => $atomUrl,
            'heratio_uri' => $matchedHeratio['uri'] ?? '',
            'heratio_name'=> $matchedHeratio['name'] ?? '',
            'source'      => $data['source'] ?? '',
        ];
    } else {
        $results['missing'][] = [
            'atom_name'   => $routeName,
            'atom_url'    => $atomUrl,
            'module'      => $data['module'] ?? '',
            'action'      => $data['action'] ?? '',
            'source'      => $data['source'] ?? '',
        ];
    }
}

// Find Heratio routes with no AtoM equivalent
$atomUrls = [];
foreach ($atomRoutes as $data) {
    $atomUrls[] = normalizeAtomUrl($data['url'] ?? '');
}

foreach ($heratioLookup as $normalized => $hRoute) {
    $foundInAtom = false;

    // Direct match
    if (in_array($normalized, $atomUrls)) {
        $foundInAtom = true;
    }

    // Fuzzy match
    if (!$foundInAtom) {
        $hNorm = preg_replace('/:[\w]+/', '*', $normalized);
        foreach ($atomUrls as $aUrl) {
            $aNorm = preg_replace('/:[\w]+/', '*', $aUrl);
            if ($hNorm === $aNorm) {
                $foundInAtom = true;
                break;
            }
        }
    }

    // Check dynamic catch-all routes that could match
    if (!$foundInAtom) {
        // AtoM's /:slug and /:module/:action can match almost anything
        // Don't report these as "extra" - they are likely matched by catch-all
        $parts = explode('/', $normalized);
        if (count($parts) <= 2) {
            // Could be matched by AtoM's catch-all /:slug or /:module/:action
            $foundInAtom = true;
        }
    }

    if (!$foundInAtom) {
        $results['extra'][] = [
            'heratio_uri'  => $hRoute['uri'],
            'heratio_name' => $hRoute['name'],
            'heratio_action' => $hRoute['action'],
        ];
    }
}

// ── Step 4: Output results ──────────────────────────────────────────────────

if ($jsonOutput) {
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
    exit(0);
}

// Terminal output
$totalAtom = count($atomRoutes);
$totalHeratio = count($heratioRoutes);
$matchedCount = count($results['matched']);
$missingCount = count($results['missing']);
$extraCount = count($results['extra']);
$dynamicCount = count($results['dynamic']);

echo BOLD . CYAN . "=== Route Comparison Summary ===" . NC . "\n";
echo DIM . str_repeat('─', 64) . NC . "\n";
echo "  AtoM routes (explicit):     " . BOLD . $totalAtom . NC . "\n";
echo "  Heratio routes:             " . BOLD . $totalHeratio . NC . "\n";
echo "  " . GREEN . "Matched:                    " . $matchedCount . NC . "\n";
echo "  " . RED . "Missing from Heratio:       " . $missingCount . NC . "\n";
echo "  " . YELLOW . "Extra in Heratio:           " . $extraCount . NC . "\n";
echo "  " . DIM . "Dynamic/catch-all (skipped):" . $dynamicCount . NC . "\n";
echo DIM . str_repeat('─', 64) . NC . "\n\n";

// Missing routes (grouped by source plugin)
if ($missingCount > 0) {
    echo BOLD . RED . "Missing from Heratio:" . NC . "\n";
    echo DIM . str_repeat('─', 64) . NC . "\n";

    // Group by source
    $bySource = [];
    foreach ($results['missing'] as $r) {
        $src = $r['source'] ?? 'unknown';
        $bySource[$src][] = $r;
    }
    ksort($bySource);

    foreach ($bySource as $source => $routes) {
        echo "\n  " . BOLD . YELLOW . $source . NC . " (" . count($routes) . " routes)\n";
        foreach ($routes as $r) {
            printf("    " . RED . "%-45s" . NC . " " . DIM . "%-20s %s" . NC . "\n",
                $r['atom_url'], $r['module'], $r['action']);
        }
    }
    echo "\n";
}

// Matched routes (only in verbose / not missing-only mode)
if (!$missingOnly && $matchedCount > 0) {
    echo BOLD . GREEN . "Matched routes:" . NC . "\n";
    echo DIM . str_repeat('─', 64) . NC . "\n";

    foreach ($results['matched'] as $r) {
        printf("  " . GREEN . "%-40s" . NC . " => " . DIM . "%-40s" . NC . "\n",
            $r['atom_url'], $r['heratio_uri']);
    }
    echo "\n";
}

// Extra Heratio routes
if (!$missingOnly && $extraCount > 0) {
    echo BOLD . YELLOW . "Extra in Heratio (no AtoM equivalent):" . NC . "\n";
    echo DIM . str_repeat('─', 64) . NC . "\n";

    foreach ($results['extra'] as $r) {
        printf("  " . YELLOW . "%-45s" . NC . " " . DIM . "%s" . NC . "\n",
            '/' . $r['heratio_uri'], $r['heratio_name']);
    }
    echo "\n";
}

// ── Step 5: Generate HTML report ────────────────────────────────────────────

echo BLUE . "Generating HTML report: {$outputFile}" . NC . "\n";

$timestamp = date('Y-m-d H:i:s');

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Heratio Route Parity Report</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; color: #212529; padding: 2rem; }
  h1 { color: #2c3e50; margin-bottom: 0.5rem; }
  h2 { color: #2c3e50; margin: 1.5rem 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #dee2e6; }
  h3 { color: #495057; margin: 1rem 0 0.5rem; }
  .meta { color: #6c757d; margin-bottom: 2rem; font-size: 0.9rem; }
  .summary { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; }
  .card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 1.25rem; min-width: 140px; flex: 1; }
  .card .number { font-size: 2rem; font-weight: 700; }
  .card .label { color: #6c757d; font-size: 0.85rem; }
  .card.green .number { color: #198754; }
  .card.yellow .number { color: #ffc107; }
  .card.red .number { color: #dc3545; }
  .card.blue .number { color: #0d6efd; }
  .card.gray .number { color: #6c757d; }
  table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #dee2e6; margin-bottom: 1rem; }
  th { background: #2c3e50; color: #fff; text-align: left; padding: 0.6rem 0.8rem; font-size: 0.82rem; }
  td { padding: 0.45rem 0.8rem; border-bottom: 1px solid #dee2e6; font-size: 0.82rem; }
  tr:hover { background: #f1f3f5; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem; font-weight: 600; }
  .badge-red { background: #f8d7da; color: #842029; }
  .badge-yellow { background: #fff3cd; color: #664d03; }
  .badge-green { background: #d1e7dd; color: #0f5132; }
  .badge-blue { background: #cfe2ff; color: #084298; }
  .badge-gray { background: #e9ecef; color: #495057; }
  .mono { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 0.78rem; }
  .plugin-group { margin-left: 1rem; margin-bottom: 1rem; }
  .filter-bar { margin-bottom: 1rem; }
  .filter-bar input { padding: 0.5rem 1rem; border: 1px solid #dee2e6; border-radius: 4px; width: 300px; }
  .pct-bar { display: inline-block; height: 8px; border-radius: 4px; }
  .pct-green { background: #198754; }
  .pct-red { background: #dc3545; }
</style>
</head>
<body>
<h1>Heratio Route Parity Report</h1>
<p class="meta">Generated: {$timestamp}</p>

<div class="summary">
  <div class="card blue"><div class="number">{$totalAtom}</div><div class="label">AtoM Routes</div></div>
  <div class="card blue"><div class="number">{$totalHeratio}</div><div class="label">Heratio Routes</div></div>
  <div class="card green"><div class="number">{$matchedCount}</div><div class="label">Matched</div></div>
  <div class="card red"><div class="number">{$missingCount}</div><div class="label">Missing</div></div>
  <div class="card yellow"><div class="number">{$extraCount}</div><div class="label">Heratio Extra</div></div>
  <div class="card gray"><div class="number">{$dynamicCount}</div><div class="label">Dynamic (skipped)</div></div>
</div>

HTML;

// Coverage bar
$coveragePct = $totalAtom > 0 ? round(($matchedCount / ($matchedCount + $missingCount)) * 100, 1) : 0;
$html .= "<p style='margin-bottom:2rem;'><strong>Route Coverage:</strong> {$coveragePct}% ";
$html .= "<span class='pct-bar pct-green' style='width:{$coveragePct}px;'></span>";
$html .= "<span class='pct-bar pct-red' style='width:" . (100 - $coveragePct) . "px;'></span></p>\n";

// Missing routes section
if ($missingCount > 0) {
    $html .= "<h2>Missing from Heratio ({$missingCount})</h2>\n";
    $html .= "<div class='filter-bar'><input type='text' id='filterMissing' placeholder='Filter missing routes...' onkeyup=\"filterRows('missingTable','filterMissing')\"></div>\n";
    $html .= "<table id='missingTable'>\n<thead><tr><th>AtoM URL</th><th>Route Name</th><th>Module</th><th>Action</th><th>Source Plugin</th></tr></thead>\n<tbody>\n";

    // Sort by source then URL
    usort($results['missing'], function($a, $b) {
        $c = strcmp($a['source'], $b['source']);
        return $c !== 0 ? $c : strcmp($a['atom_url'], $b['atom_url']);
    });

    foreach ($results['missing'] as $r) {
        $url = htmlspecialchars($r['atom_url']);
        $name = htmlspecialchars($r['atom_name']);
        $module = htmlspecialchars($r['module']);
        $action = htmlspecialchars($r['action']);
        $source = htmlspecialchars($r['source']);
        $html .= "<tr><td class='mono'>/{$url}</td><td>{$name}</td><td>{$module}</td><td>{$action}</td><td><span class='badge badge-blue'>{$source}</span></td></tr>\n";
    }
    $html .= "</tbody></table>\n";
}

// Matched routes section
if ($matchedCount > 0) {
    $html .= "<h2>Matched Routes ({$matchedCount})</h2>\n";
    $html .= "<div class='filter-bar'><input type='text' id='filterMatched' placeholder='Filter matched routes...' onkeyup=\"filterRows('matchedTable','filterMatched')\"></div>\n";
    $html .= "<table id='matchedTable'>\n<thead><tr><th>AtoM URL</th><th>Heratio URI</th><th>AtoM Route</th><th>Heratio Route</th><th>Source</th></tr></thead>\n<tbody>\n";

    foreach ($results['matched'] as $r) {
        $aUrl = htmlspecialchars($r['atom_url']);
        $hUri = htmlspecialchars($r['heratio_uri']);
        $aName = htmlspecialchars($r['atom_name']);
        $hName = htmlspecialchars($r['heratio_name']);
        $source = htmlspecialchars($r['source']);
        $html .= "<tr><td class='mono'>/{$aUrl}</td><td class='mono'>/{$hUri}</td><td>{$aName}</td><td>{$hName}</td><td><span class='badge badge-gray'>{$source}</span></td></tr>\n";
    }
    $html .= "</tbody></table>\n";
}

// Extra Heratio routes section
if ($extraCount > 0) {
    $html .= "<h2>Extra Heratio Routes ({$extraCount})</h2>\n";
    $html .= "<table>\n<thead><tr><th>Heratio URI</th><th>Route Name</th><th>Action</th></tr></thead>\n<tbody>\n";

    foreach ($results['extra'] as $r) {
        $uri = htmlspecialchars($r['heratio_uri']);
        $name = htmlspecialchars($r['heratio_name']);
        $action = htmlspecialchars($r['heratio_action']);
        $html .= "<tr><td class='mono'>/{$uri}</td><td>{$name}</td><td class='mono'>{$action}</td></tr>\n";
    }
    $html .= "</tbody></table>\n";
}

// Dynamic routes section
if ($dynamicCount > 0) {
    $html .= "<h2>AtoM Dynamic/Catch-All Routes ({$dynamicCount})</h2>\n";
    $html .= "<p style='color:#6c757d;margin-bottom:1rem;'>These are Symfony catch-all routes (/:slug, /:module/:action, etc.) that match dynamically. They cannot be directly compared to Heratio routes.</p>\n";
    $html .= "<table>\n<thead><tr><th>AtoM URL Pattern</th><th>Route Name</th><th>Module</th><th>Action</th></tr></thead>\n<tbody>\n";

    foreach ($results['dynamic'] as $r) {
        $url = htmlspecialchars($r['atom_url']);
        $name = htmlspecialchars($r['atom_name']);
        $module = htmlspecialchars($r['module']);
        $action = htmlspecialchars($r['action']);
        $html .= "<tr><td class='mono'>{$url}</td><td>{$name}</td><td>{$module}</td><td>{$action}</td></tr>\n";
    }
    $html .= "</tbody></table>\n";
}

$html .= <<<HTML

<script>
function filterRows(tableId, inputId) {
  var input = document.getElementById(inputId).value.toLowerCase();
  var rows = document.querySelectorAll('#' + tableId + ' tbody tr');
  rows.forEach(function(row) {
    row.style.display = row.textContent.toLowerCase().includes(input) ? '' : 'none';
  });
}
</script>
</body>
</html>
HTML;

file_put_contents($outputFile, $html);
echo GREEN . "HTML report written to: {$outputFile}" . NC . "\n\n";
echo BOLD . "Done." . NC . "\n";
