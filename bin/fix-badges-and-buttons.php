#!/usr/bin/env php
<?php
/**
 * Fix ALL missing field badges and bad button classes across all Heratio views.
 *
 * Badge logic:
 * - If next input/select/textarea has 'required' attribute → Required (bg-danger)
 * - If field name is in RECOMMENDED list → Recommended (bg-warning)
 * - Everything else → Optional (bg-secondary)
 *
 * Button fix:
 * - btn-light → atom-btn-white
 * - btn-dark → atom-btn-white
 */

$base = '/usr/share/nginx/heratio/packages';

// Fields that are typically "Recommended" in AtoM (not required but important)
$recommendedFields = [
    'authorized_form_of_name', 'dates_of_existence', 'description_identifier',
    'institution_identifier', 'repository', 'level_of_description', 'extent_and_medium',
    'scope_and_content', 'archival_history', 'acquisition', 'arrangement',
    'conditions_governing_access', 'conditions_governing_reproduction',
    'language_of_material', 'script_of_material', 'finding_aids',
    'related_units_of_description', 'publication_note', 'sources',
    'rules_or_conventions', 'dates_of_creation_revision_deletion',
    'language_of_description', 'script_of_description',
    'type_of_entity', 'places', 'legal_status', 'functions',
    'mandates', 'internal_structures', 'general_context',
    'description_status', 'level_of_detail', 'maintenance_notes',
    'collecting_area', 'holdings', 'geocultural_context',
    'type', 'classification', 'dates_of_existence',
];

$stats = ['files_changed' => 0, 'badges_added' => 0, 'buttons_fixed' => 0, 'files_scanned' => 0];
$changedFiles = [];

// Find all blade files
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
    $stats['files_scanned']++;
    $content = file_get_contents($path);
    $original = $content;
    $fileChanged = false;
    $badgesAdded = 0;

    // ── Fix bad button classes ──
    $btnReplacements = [
        'btn-light' => 'atom-btn-white',
        'btn-dark' => 'atom-btn-white',
    ];
    foreach ($btnReplacements as $bad => $good) {
        // Only replace standalone btn-light/btn-dark (not btn-outline-light etc)
        $pattern = '/\bclass="([^"]*)\b' . preg_quote($bad, '/') . '\b([^"]*)"/';
        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                '/\b' . preg_quote($bad, '/') . '\b/',
                $good,
                $content
            );
            $stats['buttons_fixed']++;
            $fileChanged = true;
        }
    }

    // ── Fix missing field badges on labels ──
    // Match <label ...>text</label> or <label ...>text with no closing badge
    // Strategy: find all <label> tags, check if they already have a badge, if not add one

    // Split content into lines for processing
    $lines = explode("\n", $content);
    $newLines = [];

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];

        // Check if this line has a <label with class="form-label"> but NO badge
        if (preg_match('/<label\b[^>]*class="[^"]*form-label[^"]*"[^>]*>/', $line)
            && !preg_match('/badge\s+bg-/', $line)
            && !preg_match('/form-check-label/', $line)) {

            // Determine badge type by looking at the next few lines for 'required' attribute
            $nextChunk = implode("\n", array_slice($lines, $i, 8));

            // Check if field is required
            $isRequired = preg_match('/\brequired\b/', $nextChunk)
                       && !preg_match('/\brequired\b.*\brequired\b/s', $nextChunk); // avoid matching two different fields

            // Also check for <span class="text-danger">*</span> on this label line
            if (preg_match('/text-danger[^>]*>\*/', $line)) {
                $isRequired = true;
            }

            // Extract field name from the label or nearby input
            $fieldName = '';
            if (preg_match('/\bname="(?:contacts\[\w+\]\[)?(\w+)/', $nextChunk, $m)) {
                $fieldName = $m[1];
            } elseif (preg_match('/\bfor="(\w+)"/', $line, $m)) {
                $fieldName = $m[1];
            }

            $isRecommended = in_array($fieldName, $recommendedFields);

            // Determine badge
            if ($isRequired) {
                $badge = '<span class="badge bg-danger ms-1">Required</span>';
            } elseif ($isRecommended) {
                $badge = '<span class="badge bg-warning ms-1">Recommended</span>';
            } else {
                $badge = '<span class="badge bg-secondary ms-1">Optional</span>';
            }

            // Insert badge before </label> on this line, or before the closing >text pattern
            if (preg_match('/<\/label>/', $line)) {
                // Badge goes before </label>
                $line = preg_replace('/<\/label>/', ' ' . $badge . '</label>', $line, 1);
                $badgesAdded++;
            } elseif (preg_match('/(>)([^<]+)$/', $line, $m)) {
                // Label text is at end of line, no closing tag yet - add badge after label text
                // Look for closing </label> on next line
                $labelText = trim($m[2]);
                if ($labelText && !empty($labelText)) {
                    // Check if next line has </label>
                    $nextLine = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
                    if ($nextLine === '</label>' || preg_match('/^\s*<\/label>/', $lines[$i + 1] ?? '')) {
                        // Add badge to this line after label text
                        $line = rtrim($line) . ' ' . $badge;
                        $badgesAdded++;
                    } else {
                        // Single-line label with text after >
                        // Find the text content after the last > and before end of line
                        $line = preg_replace('/(>[^<]+)(<\s*\/label>|$)/', '$1 ' . $badge . '$2', $line, 1);
                        if (strpos($line, $badge) !== false) {
                            $badgesAdded++;
                        }
                    }
                }
            }
        }

        $newLines[] = $line;
    }

    $content = implode("\n", $newLines);

    if ($content !== $original) {
        file_put_contents($path, $content);
        $stats['files_changed']++;
        $stats['badges_added'] += $badgesAdded;
        $rel = str_replace($base . '/', '', $path);
        $changedFiles[] = ['file' => $rel, 'badges' => $badgesAdded];
        echo "  FIXED: $rel (+$badgesAdded badges)\n";
    }
}

echo "\n";
echo "=== SUMMARY ===\n";
echo "Files scanned:  {$stats['files_scanned']}\n";
echo "Files changed:  {$stats['files_changed']}\n";
echo "Badges added:   {$stats['badges_added']}\n";
echo "Buttons fixed:  {$stats['buttons_fixed']}\n";
echo "\nChanged files:\n";
foreach ($changedFiles as $cf) {
    echo "  {$cf['file']} (+{$cf['badges']} badges)\n";
}
