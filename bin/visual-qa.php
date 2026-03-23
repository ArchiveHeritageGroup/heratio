#!/usr/bin/env php
<?php
/**
 * Phase 13: Visual QA — compare AtoM Heratio (psis) vs Heratio page by page.
 *
 * Fetches both pages, extracts key structural elements (headings, links, sections,
 * buttons, forms), and reports differences.
 *
 * Usage: php bin/visual-qa.php [url_path]
 *        php bin/visual-qa.php /plaas-welgelegen
 *        php bin/visual-qa.php --all
 */

$atomBase = 'https://psis.theahg.co.za/index.php';
$heratioBase = 'https://heratio.theahg.co.za';

// Pages to compare (AtoM path => Heratio path)
$pages = [
    // Homepage
    '/' => '/',
    // IO show
    '/plaas-welgelegen' => '/plaas-welgelegen',
    // IO browse
    '/informationobject/browse' => '/informationobject/browse',
    // Actor browse
    '/actor/browse' => '/actor/browse',
    // Actor show
    '/pieterse-family' => '/pieterse-family',
    // Repository browse
    '/repository/browse' => '/repository/browse',
    // Repository show
    '/pieterse-archival-institution' => '/pieterse-archival-institution',
    // Function browse
    '/function/browse' => '/function/browse',
    // Term browse
    '/taxonomy/index/id/35' => '/taxonomy/index/id/35',
    // Search
    '/search' => '/search',
    // Settings (admin)
    '/admin/settings' => '/admin/settings',
    // User browse (admin)
    '/admin/users' => '/admin/users',
    // Static page
    '/staticpage/list/' => '/staticpage/list/',
    // Accession browse (admin)
    '/accession/browse' => '/accession/browse',
    // Storage browse
    '/physicalobject/browse' => '/physicalobject/browse',
    // Jobs (admin)
    '/jobs/browse' => '/jobs/browse',
];

function fetchPage($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Heratio-QA/1.0',
    ]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['html' => $html ?: '', 'code' => $code];
}

function extractStructure($html) {
    if (empty($html)) return ['headings' => [], 'links' => [], 'buttons' => [], 'sections' => [], 'forms' => []];

    $doc = new DOMDocument();
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR);
    $xpath = new DOMXPath($doc);

    // Headings
    $headings = [];
    foreach (['h1', 'h2', 'h3', 'h4', 'h5'] as $tag) {
        foreach ($xpath->query("//{$tag}") as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($text && strlen($text) < 200) $headings[] = $text;
        }
    }

    // Links (named routes / hrefs)
    $links = [];
    foreach ($xpath->query('//a[@href]') as $node) {
        $href = $node->getAttribute('href');
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
        if ($href && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')
            && !str_starts_with($href, 'mailto:') && !str_starts_with($href, 'tel:')
            && strlen($text) < 100) {
            // Normalize URL
            $href = preg_replace('/^https?:\/\/[^\/]+/', '', $href);
            $href = preg_replace('/\/index\.php/', '', $href);
            $links[] = ['text' => $text, 'href' => $href];
        }
    }

    // Buttons
    $buttons = [];
    foreach ($xpath->query('//button|//input[@type="submit"]') as $node) {
        $text = trim($node->textContent ?: $node->getAttribute('value'));
        if ($text) $buttons[] = $text;
    }

    // Sections (cards, accordions)
    $sections = [];
    foreach ($xpath->query('//*[contains(@class,"card-header") or contains(@class,"accordion-header") or contains(@class,"atom-section-header")]') as $node) {
        $text = trim(preg_replace('/\s+/', ' ', $node->textContent));
        if ($text && strlen($text) < 150) $sections[] = $text;
    }

    // Forms
    $forms = [];
    foreach ($xpath->query('//form') as $node) {
        $action = $node->getAttribute('action');
        $method = strtoupper($node->getAttribute('method') ?: 'GET');
        $forms[] = "{$method} {$action}";
    }

    return compact('headings', 'links', 'buttons', 'sections', 'forms');
}

function compareArrays($atomArr, $heratioArr, $label) {
    $atomSet = array_map('strtolower', array_unique($atomArr));
    $heratioSet = array_map('strtolower', array_unique($heratioArr));

    $missing = array_diff($atomSet, $heratioSet);
    $extra = array_diff($heratioSet, $atomSet);

    $issues = [];
    foreach ($missing as $item) {
        $issues[] = "  MISSING {$label}: {$item}";
    }
    foreach ($extra as $item) {
        $issues[] = "  EXTRA   {$label}: {$item}";
    }
    return $issues;
}

// Parse args
$targetPath = $argv[1] ?? '--all';

echo "# PHASE 13: VISUAL QA — AtoM Heratio vs Heratio\n";
echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
echo "# AtoM: {$atomBase}\n";
echo "# Heratio: {$heratioBase}\n\n";

$pagesToCheck = $pages;
if ($targetPath !== '--all') {
    $pagesToCheck = [$targetPath => $targetPath];
}

$totalPages = 0;
$totalIssues = 0;
$results = [];

foreach ($pagesToCheck as $atomPath => $heratioPath) {
    $totalPages++;
    $atomUrl = $atomBase . $atomPath;
    $heratioUrl = $heratioBase . $heratioPath;

    echo "## Page: {$heratioPath}\n";
    echo "   AtoM:    {$atomUrl}\n";
    echo "   Heratio: {$heratioUrl}\n";

    $atom = fetchPage($atomUrl);
    $heratio = fetchPage($heratioUrl);

    echo "   Status:  AtoM={$atom['code']} Heratio={$heratio['code']}\n";

    if ($atom['code'] >= 400 || $heratio['code'] >= 400) {
        echo "   ⚠ SKIPPED — one or both returned error\n\n";
        continue;
    }

    $atomStruct = extractStructure($atom['html']);
    $heratioStruct = extractStructure($heratio['html']);

    $issues = [];
    $issues = array_merge($issues, compareArrays(
        array_map(fn($h) => $h, $atomStruct['headings']),
        array_map(fn($h) => $h, $heratioStruct['headings']),
        'heading'
    ));
    $issues = array_merge($issues, compareArrays(
        $atomStruct['buttons'],
        $heratioStruct['buttons'],
        'button'
    ));
    $issues = array_merge($issues, compareArrays(
        $atomStruct['sections'],
        $heratioStruct['sections'],
        'section'
    ));

    // Compare link texts (not URLs — URLs will differ)
    $atomLinkTexts = array_filter(array_map(fn($l) => $l['text'], $atomStruct['links']));
    $heratioLinkTexts = array_filter(array_map(fn($l) => $l['text'], $heratioStruct['links']));
    $issues = array_merge($issues, compareArrays($atomLinkTexts, $heratioLinkTexts, 'link'));

    echo "   Counts:  AtoM(h=" . count($atomStruct['headings']) . " l=" . count($atomStruct['links']) . " b=" . count($atomStruct['buttons']) . " s=" . count($atomStruct['sections']) . ")";
    echo " Heratio(h=" . count($heratioStruct['headings']) . " l=" . count($heratioStruct['links']) . " b=" . count($heratioStruct['buttons']) . " s=" . count($heratioStruct['sections']) . ")\n";

    if (empty($issues)) {
        echo "   ✓ No structural differences found\n";
    } else {
        $totalIssues += count($issues);
        echo "   ✗ " . count($issues) . " differences:\n";
        foreach (array_slice($issues, 0, 20) as $issue) {
            echo "   {$issue}\n";
        }
        if (count($issues) > 20) {
            echo "   ... and " . (count($issues) - 20) . " more\n";
        }
    }

    echo "\n";
    $results[$heratioPath] = ['issues' => count($issues), 'atom_code' => $atom['code'], 'heratio_code' => $heratio['code']];
}

echo "## SUMMARY\n\n";
echo "| Page | Issues | AtoM | Heratio |\n";
echo "|---|---|---|---|\n";
foreach ($results as $path => $r) {
    $status = $r['issues'] === 0 ? '✓' : "✗ {$r['issues']}";
    echo "| {$path} | {$status} | {$r['atom_code']} | {$r['heratio_code']} |\n";
}
echo "\nTotal pages: {$totalPages} | Total issues: {$totalIssues}\n";
