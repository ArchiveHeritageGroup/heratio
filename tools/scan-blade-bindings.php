<?php

/**
 * scan-blade-bindings.php - find Blade bindings whose query never produces them.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

// Issue #1478. A view reads `$row->some_property ?? fallback`, the query behind
// that row never produces `some_property`, and the fallback therefore fires on
// every row, every time. Nothing errors - the screen renders, looks populated,
// and reports a value that is not the data.
//
// The `??` is what lets it survive. Without it these would be "Undefined
// property" warnings and would have been fixed years ago; with it, the view
// degrades into a plausible-looking lie. Three were found by accident in two
// days (#1477 loan rules, the checkout preview, and the overdue list, which had
// shown a blank patron / 0 days / 0.00 fine on every row for its whole life),
// which is what prompted this scan.
//
// WHAT COUNTS AS A FINDING. Not a single unresolvable property, but a whole
// `??` chain in which NO alternative resolves. That distinction is the useful
// one and was learned from the data: `$s->area_sqm ?? $s->floor_area` WORKS,
// because the real column is named first and the stale alternative never runs.
// Only `$s->ceiling_height ?? $s->height`, where the column is `height_m` and
// both alternatives are wrong, renders a permanent blank.
//
// Those defensive two-name chains are the fingerprint of a column rename that
// was only half finished - and are exactly what hid the unfinished half.
//
// THIS IS A HEURISTIC, NOT A DEFECT LIST. "Producible" is judged generously: a
// property counts as real if it names a column anywhere in the schema, or if
// the identifier appears in any non-Blade source in the repo. That deliberately
// lets some real bugs through rather than crying wolf. It can still produce
// false positives - a property built dynamically (`$row->{$col}`) or supplied
// by something outside the scanned tree. Every hit needs eyes on the actual
// query before it is called a bug. Treat the output as a work queue.
//
// Usage:
//   php tools/scan-blade-bindings.php              # report findings
//   php tools/scan-blade-bindings.php --baseline   # record current state
//   php tools/scan-blade-bindings.php --check      # CI: fail on anything NEW
//
// Repo-only: no database, no Laravel bootstrap, no network. Deterministic on any
// checkout, which is what makes the baseline comparable between a developer's
// machine and CI.
//
// --check is the point of the tool. The existing population is large enough
// that fixing it is a project; the guard stops it growing in the meantime, and
// the baseline may only ever shrink.

$root = dirname(__DIR__);
$baselineFile = $root . '/tools/blade-bindings-baseline.txt';

$args = array_slice($argv, 1);
$mode = 'report';
foreach ($args as $a) {
    if ($a === '--baseline') { $mode = 'baseline'; }
    elseif ($a === '--check') { $mode = 'check'; }
    elseif ($a === '--help' || $a === '-h') {
        fwrite(STDERR, "usage: php tools/scan-blade-bindings.php [--baseline|--check]\n");
        exit(0);
    } else {
        fwrite(STDERR, "unknown option: $a\n");
        exit(2);
    }
}

/** Walk a tree, yielding files whose name passes $accept. */
function walk(string $dir, callable $accept): Generator
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static fn ($f) => ! $f->isDir() || ! in_array($f->getFilename(), ['node_modules', 'vendor', '.git'], true)
        )
    );
    foreach ($it as $file) {
        if ($file->isFile() && $accept($file->getFilename())) {
            yield $file->getPathname();
        }
    }
}

echo "scanning...\n";

// Identifiers from every non-Blade source. Generous on purpose: if a name is
// written anywhere in PHP, SQL or JS, something plausibly produces it.
//
// NO DATABASE IS CONSULTED, and none is needed. An earlier cut queried
// information_schema for every column name; running it both ways over this repo
// gave an identical 131 findings across 73 files, because a column created by a
// migration is named as a PHP string (`$table->string('foo')`) and one created
// by an install.sql is named in the DDL - so the sweep below already has them.
// Dropping the query removes a real failure mode as well as a dependency: the
// CI database is built from database/core/*.sql alone and carries far fewer
// tables than a full install, so a baseline recorded against a developer's
// database would have reported phantom regressions in CI.
$identifiers = [];
foreach (['packages', 'app', 'database'] as $base) {
    foreach (walk($root . '/' . $base, static fn ($n) =>
        preg_match('/\.(php|sql|js)$/', $n) && ! str_ends_with($n, '.blade.php')) as $file) {
        if (preg_match_all('/\w+/', (string) file_get_contents($file), $m)) {
            foreach ($m[0] as $word) {
                $identifiers[strtolower($word)] = true;
            }
        }
    }
}

/** getFooBarAttribute-style accessors resolve to snake_case columns. */
$snake = static fn (string $s): string => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $s));

$producible = static function (string $prop) use ($identifiers, $snake): bool {
    return isset($identifiers[strtolower($prop)]) || isset($identifiers[$snake($prop)]);
};

$findings = [];
$bladeCount = 0;
foreach (walk($root . '/packages', static fn ($n) => str_ends_with($n, '.blade.php')) as $file) {
    $bladeCount++;
    $src = (string) file_get_contents($file);
    $rel = ltrim(str_replace($root, '', $file), '/');

    // One or more `$var->prop ??` in sequence - the whole alternative chain.
    if (! preg_match_all('/((?:\$[A-Za-z_]\w*->\w+\s*\?\?\s*)+)/', $src, $chains)) {
        continue;
    }

    // A property the view ASSIGNS to itself is produced by the view, not by the
    // query - `$row->checklist_name = ...` a few lines above the read. Those are
    // false positives, and reporting them trains people to ignore the tool.
    preg_match_all('/->(\w+)\s*=(?!=)/', $src, $assigned);
    $localAssigned = array_flip($assigned[1] ?? []);

    foreach ($chains[1] as $chain) {
        preg_match_all('/\$[A-Za-z_]\w*->(\w+)/', $chain, $props);
        if (empty($props[1])) {
            continue;
        }
        foreach ($props[1] as $prop) {
            if ($producible($prop) || isset($localAssigned[$prop])) {
                continue 2;   // one live alternative rescues the whole chain
            }
        }
        $findings[] = $rel . "\t" . implode(' ?? ', $props[1]);
    }
}

$findings = array_values(array_unique($findings));
sort($findings);

printf("%d blade files scanned, %d non-blade identifiers\n",
    $bladeCount, count($identifiers));
printf("%d always-fallback chain(s) in %d file(s)\n\n",
    count($findings), count(array_unique(array_map(static fn ($f) => explode("\t", $f)[0], $findings))));

if ($mode === 'baseline') {
    file_put_contents($baselineFile, implode("\n", $findings) . "\n");
    echo "baseline written: tools/blade-bindings-baseline.txt\n";
    exit(0);
}

if ($mode === 'check') {
    if (! is_readable($baselineFile)) {
        fwrite(STDERR, "ABORT: no baseline. Run --baseline first.\n");
        exit(2);
    }
    $baseline = array_filter(array_map('trim', file($baselineFile)));
    $new = array_diff($findings, $baseline);
    $fixed = array_diff($baseline, $findings);

    foreach ($fixed as $f) {
        echo "  fixed: " . str_replace("\t", '  ', $f) . "\n";
    }
    if (! empty($fixed)) {
        printf("\n%d finding(s) gone - regenerate the baseline with --baseline so it stays shrink-only.\n\n", count($fixed));
    }
    if (! empty($new)) {
        fwrite(STDERR, "NEW always-fallback binding(s) - a view is reading something its query does not produce:\n");
        foreach ($new as $f) {
            fwrite(STDERR, '  ' . str_replace("\t", '  ', $f) . "\n");
        }
        fwrite(STDERR, "\nSee #1478. Bind the real column, or drop the column from the view if the\n");
        fwrite(STDERR, "concept does not exist - do not invent a fallback value.\n");
        exit(1);
    }
    echo "no new always-fallback bindings.\n";
    exit(0);
}

foreach ($findings as $f) {
    echo '  ' . str_replace("\t", '  ', $f) . "\n";
}
