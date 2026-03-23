#!/usr/bin/env php
<?php
/**
 * COMPLETE URL Audit: Every href in every blade file.
 * Checks if each route() call resolves to a valid Heratio route.
 */

$base = '/usr/share/nginx/heratio/packages';

// Build route map using JSON output to avoid terminal truncation
$routeMap = [];
$output = shell_exec('php artisan route:list --json 2>/dev/null');
$routes = json_decode($output ?: '[]', true);
if (is_array($routes)) {
    foreach ($routes as $route) {
        $name = $route['name'] ?? null;
        $uri = $route['uri'] ?? '';
        if ($name) {
            $routeMap[$name] = '/' . ltrim($uri, '/');
        }
    }
}
// Fallback: parse text output if JSON failed
if (empty($routeMap)) {
    $output = shell_exec('cd /usr/share/nginx/heratio && COLUMNS=500 php artisan route:list 2>/dev/null');
    foreach (explode("\n", $output ?: '') as $line) {
        if (preg_match('/^\s+(GET|POST|PUT|DELETE|PATCH)[^\s]*\s+(\S+)\s+(\S+)/', $line, $m)) {
            $name = $m[3];
            $uri = '/' . ltrim($m[2], '/');
            if (strpos($name, '.') !== false && strpos($name, '›') === false) {
                $routeMap[$name] = $uri;
            }
        }
    }
}

$stats = ['files' => 0, 'hrefs' => 0, 'route_calls' => 0, 'valid_routes' => 0, 'unknown_routes' => 0, 'url_calls' => 0, 'external' => 0, 'anchor' => 0, 'blade_expr' => 0];
$issues = [];

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!preg_match('/\.blade\.php$/', $file->getFilename())) continue;
    $stats['files']++;
    $rel = str_replace($base . '/', '', $file->getPathname());
    $content = file_get_contents($file->getPathname());
    
    // Find ALL route('name') calls anywhere in the file (not just in href)
    if (preg_match_all("/route\(\s*'([^']+)'/", $content, $rm)) {
        foreach ($rm[1] as $routeName) {
            $stats['route_calls']++;
            if (isset($routeMap[$routeName])) {
                $stats['valid_routes']++;
            } else {
                $stats['unknown_routes']++;
                $issues[] = ['file' => $rel, 'route' => $routeName, 'uri' => 'NOT FOUND'];
            }
        }
    }
    
    // Count href types
    if (preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $content, $hm)) {
        foreach ($hm[1] as $href) {
            $stats['hrefs']++;
            if (strpos($href, '#') === 0 || strpos($href, 'javascript:') === 0) $stats['anchor']++;
            elseif (preg_match('/^https?:\/\//', $href)) $stats['external']++;
            elseif (strpos($href, '{{') !== false) $stats['blade_expr']++;
            elseif (strpos($href, '/') === 0) $stats['url_calls']++;
        }
    }
}

echo "# COMPLETE URL AUDIT: Every route() call in every blade file\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# Named routes in app: " . count($routeMap) . "\n\n";

echo "## SUMMARY\n\n";
echo "| Metric | Count |\n";
echo "|---|---|\n";
echo "| Files scanned | {$stats['files']} |\n";
echo "| Total hrefs found | {$stats['hrefs']} |\n";
echo "| route() calls found | {$stats['route_calls']} |\n";
echo "| Valid routes | {$stats['valid_routes']} |\n";
echo "| **UNKNOWN ROUTES (broken)** | **{$stats['unknown_routes']}** |\n";
echo "| Blade expressions | {$stats['blade_expr']} |\n";
echo "| Static url() paths | {$stats['url_calls']} |\n";
echo "| External URLs | {$stats['external']} |\n";
echo "| Anchors/JS | {$stats['anchor']} |\n\n";

if (empty($issues)) {
    echo "**All route() calls resolve to valid routes. ✓**\n";
} else {
    echo "## BROKEN ROUTES — route() name not in route:list\n\n";
    echo str_pad('File', 60) . str_pad('Route Name', 45) . "Status\n";
    echo str_repeat('─', 115) . "\n";
    // Dedupe
    $seen = [];
    foreach ($issues as $iss) {
        $key = $iss['file'] . ':' . $iss['route'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        echo str_pad($iss['file'], 60) . str_pad("route('" . $iss['route'] . "')", 45) . "NOT FOUND\n";
    }
    echo "\nTotal unique broken routes: " . count($seen) . "\n";
}
