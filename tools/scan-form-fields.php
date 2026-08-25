<?php

/**
 * scan-form-fields.php - find form fields that nothing consumes.
 *
 * The sibling of tools/scan-blade-bindings.php, and deliberately the other half
 * of it. That one finds a view READING something its query never produces
 * (#1478). This one finds a form WRITING something no PHP ever reads (#1481):
 * an <input> whose name appears in no validator, no ->input() call, no insert
 * and no update. Somebody types into it, presses Save, and the value goes
 * nowhere. Nothing errors at any point.
 *
 * #1481 records why a separate tool is needed: the binding scanner detects
 * `$row->prop ??` and cannot see an unwired <input> unless that field is also
 * displayed with a fallback. The exhibition storyline form contributed ONE
 * finding to the #1478 baseline while actually having four broken fields and no
 * POST route at all. So #1478's count was a floor, not a total.
 *
 * WHAT IT SKIPS, AND WHY THAT MATTERS MORE THAN WHAT IT FINDS
 *
 *  - GET forms. A filter or search form has no writer by design.
 *  - Any package whose PHP saves through a GENERIC request loop -
 *    $request->all(), ->except(), ->merge(), ->collect(), or a foreach over
 *    ->input(). Those consume whatever arrives, so a field name proves nothing
 *    and judging them would be false positives by construction. 28 of the
 *    packages here do this; ahg-semantic-search alone would have contributed 9
 *    phantom findings. A tool that cries wolf gets switched off, and then
 *    catches nothing at all.
 *  - Array names (foo[]) and _token/_method.
 *
 * Usage:
 *   php tools/scan-form-fields.php              # report
 *   php tools/scan-form-fields.php --baseline   # record current state
 *   php tools/scan-form-fields.php --check      # CI: fail on anything new
 *
 * As with its sibling, the number is a work queue and not a defect tally. Each
 * entry needs tracing to the controller that receives the POST. Verified by
 * hand on report-spatial: the form posts top_level_only and require_coordinates
 * while the handler reads topLevelOnly and requireCoordinates, so both toggles
 * are discarded on every submission.
 */

$root = dirname(__DIR__);
$baselineFile = $root.'/tools/form-fields-baseline.txt';
$mode = $argv[1] ?? '';

function walk(string $dir, callable $keep): \Generator
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        $p = $f->getPathname();
        if (str_contains($p, '/node_modules/') || str_contains($p, '/vendor/')
            || str_contains($p, '/.git/') || str_contains($p, '/storage/')) {
            continue;
        }
        if ($keep($p)) {
            yield $p;
        }
    }
}

echo "scanning...\n";

// Every identifier in non-Blade source. Same generous test as the sibling
// scanner: if the name appears anywhere in any PHP/SQL/JS, treat it as consumed.
$identifiers = [];
foreach (walk($root, fn ($p) => (bool) preg_match('/\.(php|sql|js)$/', $p) && ! str_ends_with($p, '.blade.php')) as $file) {
    if (preg_match_all('/\w+/', (string) file_get_contents($file), $m)) {
        foreach ($m[0] as $w) {
            $identifiers[$w] = true;
        }
    }
}

// Packages that save through a generic request loop cannot be judged by field name.
$genericPackages = [];
foreach (walk($root.'/packages', fn ($p) => str_ends_with($p, '.php') && ! str_ends_with($p, '.blade.php')) as $file) {
    $src = (string) file_get_contents($file);
    if (preg_match('/\$request->all\(\)|\$request->except\(|\$request->merge\(|\$request->collect\(|foreach\s*\(\s*\$request->input\(/', $src)) {
        $parts = explode('/', str_replace($root.'/packages/', '', $file));
        $genericPackages[$parts[0]] = true;
    }
}

$findings = [];
$formViews = 0;
foreach (walk($root.'/packages', fn ($p) => str_ends_with($p, '.blade.php')) as $file) {
    $rel = ltrim(str_replace($root, '', $file), '/');
    $pkg = explode('/', str_replace('packages/', '', $rel))[0];
    if (isset($genericPackages[$pkg])) {
        continue;
    }

    $src = (string) file_get_contents($file);
    // Blade comments are documentation, not markup - the same false-positive
    // class the sibling scanner had to learn about.
    $src = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $src);

    if (! preg_match('/<form[^>]*method\s*=\s*["\']?(post|POST)/', $src)) {
        continue;
    }
    $formViews++;

    if (! preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname\s*=\s*"([^"{}\[\]]+)"/i', $src, $m)) {
        continue;
    }

    foreach (array_unique($m[1]) as $name) {
        if ($name === '_token' || $name === '_method') {
            continue;
        }
        if (! isset($identifiers[$name])) {
            $findings[] = $rel.'  '.$name;
        }
    }
}

sort($findings);
$files = count(array_unique(array_map(fn ($f) => explode('  ', $f)[0], $findings)));
printf("%d POST form view(s) scanned, %d non-blade identifiers\n", $formViews, count($identifiers));
printf("%d field(s) nothing consumes, in %d file(s)\n\n", count($findings), $files);

if ($mode === '--baseline') {
    file_put_contents($baselineFile, implode("\n", $findings)."\n");
    echo "baseline written: tools/form-fields-baseline.txt\n";
    exit(0);
}

if ($mode === '--check') {
    $base = is_file($baselineFile)
        ? array_filter(array_map('trim', file($baselineFile)))
        : [];
    $new = array_diff($findings, $base);
    $gone = array_diff($base, $findings);

    foreach ($gone as $g) {
        echo "  fixed: $g\n";
    }
    if ($gone !== []) {
        echo "\n".count($gone)." finding(s) gone - regenerate the baseline with --baseline so it stays shrink-only.\n\n";
    }
    if ($new !== []) {
        echo "NEW unconsumed form field(s) - a form is collecting something no PHP reads:\n";
        foreach ($new as $n) {
            echo "  $n\n";
        }
        echo "\nSee #1481. Wire the field to its controller, or remove it from the form -\n";
        echo "a control that discards what is typed into it is worse than an absent one.\n";
        exit(1);
    }
    echo "no new unconsumed form fields.\n";
    exit(0);
}

foreach ($findings as $f) {
    echo "  $f\n";
}
