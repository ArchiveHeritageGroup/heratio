#!/usr/bin/env php
<?php
/**
 * Auto-register broken routes.
 * For each route('name') call in blade views that doesn't resolve,
 * add a stub route to the package's routes/web.php.
 */

$base = '/usr/share/nginx/heratio/packages';

// Get existing routes
$existingRoutes = [];
$output = shell_exec('php artisan route:list 2>/dev/null');
foreach (explode("\n", $output ?: '') as $line) {
    if (preg_match('/^\s+(GET|POST|PUT|DELETE|PATCH)[^\s]*\s+(\S+)\s+(\S+)/', $line, $m)) {
        $name = $m[3];
        if (strpos($name, '.') !== false && strpos($name, '›') === false) {
            $existingRoutes[$name] = true;
        }
    }
}

echo "Existing routes: " . count($existingRoutes) . "\n";

// Find all broken route references
$broken = []; // routeName => [package => pkg, files => []]
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!preg_match('/\.blade\.php$/', $f->getFilename())) continue;
    $content = file_get_contents($f->getPathname());
    $rel = str_replace($base . '/', '', $f->getPathname());
    $pkg = explode('/', $rel)[0];

    if (preg_match_all("/route\(\s*'([^']+)'/", $content, $m)) {
        foreach ($m[1] as $routeName) {
            if (!isset($existingRoutes[$routeName])) {
                if (!isset($broken[$routeName])) {
                    $broken[$routeName] = ['pkg' => $pkg, 'files' => []];
                }
                $broken[$routeName]['files'][] = $rel;
            }
        }
    }
}

echo "Broken routes: " . count($broken) . "\n\n";

// Group by package
$byPackage = [];
foreach ($broken as $routeName => $info) {
    $pkg = $info['pkg'];
    $byPackage[$pkg][] = $routeName;
}

// For each package, append missing routes to routes/web.php
$totalAdded = 0;
foreach ($byPackage as $pkg => $routes) {
    $routesFile = "$base/$pkg/routes/web.php";
    if (!file_exists($routesFile)) {
        // Create routes file
        $routeContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n";
    } else {
        $routeContent = file_get_contents($routesFile);
    }

    // Determine package prefix from existing routes
    $pkgSlug = str_replace('ahg-', '', $pkg);
    $prefix = "admin/$pkgSlug";

    // Check if there's already a group
    $hasGroup = strpos($routeContent, 'Route::prefix') !== false || strpos($routeContent, 'Route::group') !== false;

    $newRoutes = [];
    foreach ($routes as $routeName) {
        // Skip if already in the file
        if (strpos($routeContent, "'$routeName'") !== false) continue;

        // Generate URI from route name: research.bookings.checkOut → /bookings/check-out
        $parts = explode('.', $routeName);
        // Remove package prefix (e.g., 'research' from 'research.bookings.checkOut')
        if (count($parts) > 1 && str_contains($pkg, $parts[0])) {
            array_shift($parts);
        }
        $uri = '/' . implode('/', array_map(function($p) {
            return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $p));
        }, $parts));

        // Generate controller method name from last part
        $methodName = lcfirst(end($parts));
        $methodName = preg_replace('/[^a-zA-Z0-9]/', '', $methodName);

        // Determine view namespace
        $viewNs = str_replace('ahg-', '', $pkg);
        $viewNs = str_replace('-', '', $viewNs);
        $viewName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', end($parts)));

        $newRoutes[] = "Route::match(['get','post'], '$uri', function() { return view('$viewNs::$viewName'); })->name('$routeName');";
        $totalAdded++;
    }

    if (empty($newRoutes)) continue;

    // Append routes before the closing group bracket, or at end of file
    $insertion = "\n// Auto-registered stub routes\n" . implode("\n", $newRoutes) . "\n";

    if ($hasGroup) {
        // Find last }); and insert before it
        $lastBracket = strrpos($routeContent, '});');
        if ($lastBracket !== false) {
            $routeContent = substr($routeContent, 0, $lastBracket) . $insertion . substr($routeContent, $lastBracket);
        } else {
            $routeContent .= $insertion;
        }
    } else {
        $routeContent .= "\nRoute::middleware(['web'])->group(function () {\n" . $insertion . "});\n";
    }

    file_put_contents($routesFile, $routeContent);
    echo "  $pkg: +" . count($newRoutes) . " routes added\n";
}

echo "\nTotal routes added: $totalAdded\n";
