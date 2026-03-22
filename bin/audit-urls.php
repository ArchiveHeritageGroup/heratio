#!/usr/bin/env php
<?php
/**
 * URL Audit: Check ALL href= values in ALL Heratio blade views.
 * Flags links that should be record-scoped but appear generic.
 *
 * A "scoped" link includes a record identifier (slug, ID) in the URL.
 * A "generic" link goes to a global page without record context.
 *
 * AtoM uses url_for([$resource, 'action' => '...']) which is always scoped.
 * Heratio should use route('entity.action', $slug) which is also scoped.
 *
 * This script flags cases where a link TEXT implies record context
 * (e.g., "Reports", "Browse as list", "Inventory") but the URL is generic.
 */

$base = '/usr/share/nginx/heratio/packages';

// Links that MUST be scoped when on a show/view page
// (they make no sense without a record context)
$mustBeScopedLinks = [
    'Reports' => 'Should link to per-record reports, not global dashboard',
    'Browse as list' => 'Should scope to current collection',
    'Browse digital objects' => 'Should scope to current collection',
    'Inventory' => 'Should be per-record inventory',
    'Calculate dates' => 'Should be per-record action',
    'Rename' => 'Should link to rename action for this record',
    'Manage rights' => 'Should manage rights for this record',
    'Box label' => 'Should generate label for this record',
    'File list' => 'Should generate file list for this record',
    'Item list' => 'Should generate item list for this record',
    'Storage locations' => 'Should show storage for this record',
];

// Patterns that indicate a link IS scoped (includes a variable)
$scopedPatterns = [
    '/\$\w+->slug/',
    '/\$\w+->id/',
    '/\$\w+\[.slug.\]/',
    '/\$\w+\[.id.\]/',
    '/\$slug/',
    '/\$id/',
    '/\{\{.*\$/',  // Any blade variable in the URL
];

$stats = ['total_links' => 0, 'scoped' => 0, 'generic' => 0, 'mismatches' => 0, 'files' => 0];
$issues = [];

// Scan all blade files
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($it as $file) {
    if (!preg_match('/\.blade\.php$/', $file->getFilename())) continue;

    $path = $file->getPathname();
    $rel = str_replace($base . '/', '', $path);
    $content = file_get_contents($path);

    // Only check show pages for must-be-scoped links
    $isShowPage = preg_match('/\bshow\b/', $file->getFilename())
               || preg_match('/\bindexSuccess\b/', $file->getFilename());

    $stats['files']++;

    // Extract all <a href="...">text</a> pairs
    if (preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/si', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $href = trim($m[1]);
            $text = trim(strip_tags($m[2]));
            $stats['total_links']++;

            if (!$text || strlen($text) < 2) continue;
            if ($href === '#' || $href === '') continue;

            // Check if link is scoped (contains a variable)
            $isScoped = false;
            foreach ($scopedPatterns as $pat) {
                if (preg_match($pat, $href)) {
                    $isScoped = true;
                    break;
                }
            }

            if ($isScoped) {
                $stats['scoped']++;
            } else {
                $stats['generic']++;

                // Check if this link text matches a must-be-scoped pattern
                foreach ($mustBeScopedLinks as $linkText => $reason) {
                    if (stripos($text, $linkText) !== false) {
                        $stats['mismatches']++;
                        $issues[] = [
                            'file' => $rel,
                            'text' => $text,
                            'href' => substr($href, 0, 100),
                            'reason' => $reason,
                            'line' => 0, // Could extract but expensive
                        ];
                        break;
                    }
                }
            }
        }
    }

    // Also check form actions for potential issues
    if (preg_match_all('/action\s*=\s*["\']([^"\']+)["\']/i', $content, $fMatches)) {
        foreach ($fMatches[1] as $action) {
            $stats['total_links']++;
            $isScoped = false;
            foreach ($scopedPatterns as $pat) {
                if (preg_match($pat, $action)) { $isScoped = true; break; }
            }
            if ($isScoped) $stats['scoped']++;
            else $stats['generic']++;
        }
    }
}

// Output
echo "# URL AUDIT: All Heratio Blade Views\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# Files scanned: {$stats['files']}\n";
echo "# Total links/actions: {$stats['total_links']}\n";
echo "# Scoped (include record var): {$stats['scoped']}\n";
echo "# Generic (no record var): {$stats['generic']}\n";
echo "# MISMATCHES (should be scoped): {$stats['mismatches']}\n\n";

if (empty($issues)) {
    echo "All context-sensitive links are properly scoped. ✓\n";
} else {
    echo "## MISMATCHES — Links that should be record-scoped but are generic\n\n";
    echo str_pad('File', 55) . str_pad('Link Text', 25) . str_pad('Reason', 50) . "\n";
    echo str_repeat('─', 130) . "\n";
    foreach ($issues as $iss) {
        echo str_pad($iss['file'], 55)
           . str_pad($iss['text'], 25)
           . str_pad($iss['reason'], 50)
           . "\n";
        echo "  href: {$iss['href']}\n\n";
    }
}

echo "\n## GENERIC LINK SUMMARY (top patterns)\n\n";

// Collect all generic hrefs for analysis
$genericHrefs = [];
$it2 = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($it2 as $file) {
    if (!preg_match('/\.blade\.php$/', $file->getFilename())) continue;
    $content = file_get_contents($file->getPathname());
    if (preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $content, $m)) {
        foreach ($m[1] as $href) {
            $isScoped = false;
            foreach ($scopedPatterns as $pat) {
                if (preg_match($pat, $href)) { $isScoped = true; break; }
            }
            if (!$isScoped && $href !== '#' && $href !== '') {
                // Normalize route calls
                $normalized = preg_replace('/\{\{.*?\}\}/', '...', $href);
                $genericHrefs[$normalized] = ($genericHrefs[$normalized] ?? 0) + 1;
            }
        }
    }
}
arsort($genericHrefs);
$top = array_slice($genericHrefs, 0, 30, true);
foreach ($top as $href => $count) {
    echo str_pad($count . 'x', 6) . $href . "\n";
}
