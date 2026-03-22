#!/usr/bin/env php
<?php
/**
 * Pass 3: Fix remaining multiline labels that span across lines.
 * Uses regex with DOTALL to match <label...>...\n...</label> patterns.
 */

$base = '/usr/share/nginx/heratio/packages';
$stats = ['files_changed' => 0, 'badges_added' => 0];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
);

$bladeFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/\.blade\.php$/', $file->getFilename())) {
        $bladeFiles[] = $file->getPathname();
    }
}
sort($bladeFiles);

foreach ($bladeFiles as $path) {
    $content = file_get_contents($path);
    $original = $content;
    $badgesAdded = 0;

    // Match multiline <label class="form-label">...\n...</label> without a badge
    $content = preg_replace_callback(
        '/<label\b([^>]*class="[^"]*form-label[^"]*"[^>]*)>(.*?)<\/label>/s',
        function ($m) use (&$badgesAdded) {
            $attrs = $m[1];
            $inner = $m[2];

            // Skip if already has badge
            if (strpos($inner, 'badge bg-') !== false) return $m[0];
            if (strpos($inner, 'badge-required') !== false) return $m[0];
            // Skip form-check-label
            if (strpos($attrs, 'form-check-label') !== false) return $m[0];

            // Determine badge type
            $hasRequired = strpos($inner, 'text-danger') !== false
                        || preg_match('/\*\s*$/', trim(strip_tags($inner)))
                        || preg_match('/\*<\/span>/', $inner);

            if ($hasRequired) {
                $badge = '<span class="badge bg-danger ms-1">Required</span>';
            } else {
                $badge = '<span class="badge bg-secondary ms-1">Optional</span>';
            }

            $badgesAdded++;
            // Insert badge before </label>
            return '<label' . $attrs . '>' . rtrim($inner) . ' ' . $badge . '</label>';
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        $stats['files_changed']++;
        $stats['badges_added'] += $badgesAdded;
        $rel = str_replace($base . '/', '', $path);
        echo "  FIXED: $rel (+$badgesAdded badges)\n";
    }
}

echo "\n=== PASS 3 SUMMARY ===\n";
echo "Files changed:  {$stats['files_changed']}\n";
echo "Badges added:   {$stats['badges_added']}\n";
