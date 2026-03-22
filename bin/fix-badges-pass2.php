#!/usr/bin/env php
<?php
/**
 * Pass 2: Fix remaining labels without badges.
 * Catches: <label class="form-label"> patterns across multiple lines,
 * labels without form-label class, and accordion-button labels.
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

    // Pattern 1: <label ...class="form-label"...>Text</label> (single line, no badge yet)
    $content = preg_replace_callback(
        '/<label\b([^>]*class="[^"]*form-label[^"]*"[^>]*)>([^<]*(?:<span class="text-danger">\*<\/span>[^<]*)?)<\/label>/',
        function ($m) use (&$badgesAdded, $path) {
            // Skip if already has badge
            if (strpos($m[0], 'badge bg-') !== false) return $m[0];
            // Skip form-check-label
            if (strpos($m[1], 'form-check-label') !== false) return $m[0];

            $hasRequired = strpos($m[0], 'text-danger') !== false || strpos($m[0], '*</span>') !== false;

            // Look ahead in the file for 'required' attribute near this label
            $pos = strpos(file_get_contents($path), $m[0]);

            if ($hasRequired) {
                $badge = '<span class="badge bg-danger ms-1">Required</span>';
            } else {
                $badge = '<span class="badge bg-secondary ms-1">Optional</span>';
            }

            $badgesAdded++;
            return '<label' . $m[1] . '>' . $m[2] . ' ' . $badge . '</label>';
        },
        $content
    );

    // Pattern 2: <label class="form-label">Text\n (multiline - text on same line as label open, no close)
    // These have the badge-less label text followed by more content
    $lines = explode("\n", $content);
    $newLines = [];
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];

        // Match: <label ...form-label...>SomeText (no </label> on this line, no badge)
        if (preg_match('/<label\b[^>]*form-label[^>]*>[^<]+$/', $line)
            && !preg_match('/badge\s+bg-/', $line)
            && !preg_match('/form-check-label/', $line)
            && !preg_match('/<\/label>/', $line)) {

            // Check if the next line or two have </label>
            $nextChunk = implode("\n", array_slice($lines, $i + 1, 5));
            $hasRequired = (bool)preg_match('/\brequired\b/', $nextChunk);

            if (preg_match('/text-danger.*\*/', $line)) {
                $hasRequired = true;
            }

            $badge = $hasRequired
                ? '<span class="badge bg-danger ms-1">Required</span>'
                : '<span class="badge bg-secondary ms-1">Optional</span>';

            // Add badge at end of label text on this line
            $line = rtrim($line) . ' ' . $badge;
            $badgesAdded++;
        }

        $newLines[] = $line;
    }
    $content = implode("\n", $newLines);

    // Pattern 3: Settings pages — labels inside <td> or <div> that use form-label
    // Already covered by pattern 1 & 2

    // Pattern 4: Labels with only class="form-label" (no other classes, already covered)

    if ($content !== $original) {
        file_put_contents($path, $content);
        $stats['files_changed']++;
        $stats['badges_added'] += $badgesAdded;
        $rel = str_replace($base . '/', '', $path);
        echo "  FIXED: $rel (+$badgesAdded badges)\n";
    }
}

echo "\n=== PASS 2 SUMMARY ===\n";
echo "Files changed:  {$stats['files_changed']}\n";
echo "Badges added:   {$stats['badges_added']}\n";
