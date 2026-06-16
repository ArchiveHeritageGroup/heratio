<?php

/**
 * gen-base-plugins.php - regenerate the atom_plugin rows for
 * database/seeds/08_base_plugins.sql, all enabled except ahgFederationPlugin.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

// Repo-only generator for database/seeds/08_base_plugins.sql plugin rows.
// Sources: section-9 mapping in docs/standalone-install-plan.md + each package composer.json.
// No database / no production access.

$root = '/usr/share/nginx/heratio';

// 1. package -> canonical AtoM plugin name, from the §9 table "| ahgXxxPlugin | ahg-xxx |"
$pkg2atom = [];
foreach (explode("\n", file_get_contents("$root/docs/standalone-install-plan.md")) as $line) {
    if (preg_match('/^\|\s*(ahg[A-Za-z0-9]+Plugin)\s*\|\s*(ahg-[a-z0-9-]+)\s*\|/', $line, $m)) {
        $pkg2atom[$m[2]] = $m[1];
    }
}

// foundation rows are written by hand in the seed (is_core/is_locked); skip here
$foundation = ['ahgCorePlugin','ahgSettingsPlugin','ahgSecurityClearancePlugin','ahgThemeB5Plugin'];

$rows = []; $order = 200; $derived = [];
foreach (glob("$root/packages/ahg-*", GLOB_ONLYDIR) as $dir) {
    $pkg = basename($dir);
    if (isset($pkg2atom[$pkg])) {
        $name = $pkg2atom[$pkg];
    } else {
        $parts = explode('-', $pkg);                       // ahg-foo-bar
        $name  = 'ahg' . implode('', array_map('ucfirst', array_slice($parts, 1))) . 'Plugin';
        $derived[] = "$pkg -> $name";
    }
    if (in_array($name, $foundation, true)) continue;
    $desc = '';
    if (is_file("$dir/composer.json")) {
        $j = json_decode(file_get_contents("$dir/composer.json"), true);
        $desc = $j['description'] ?? '';
    }
    if ($desc === '') $desc = "AHG plugin: $pkg";
    $desc  = str_replace("'", "''", $desc);
    $class = $name . 'Configuration';
    $enabled = ($name === 'ahgFederationPlugin') ? 0 : 1;
    $status  = $enabled ? 'enabled' : 'disabled';
    $order++;
    $rows[] = sprintf("    ('%s', '%s', '1.0.0', '%s', 'ahg', %d, 0, 0, 0, '%s', %d, NOW(), NOW())",
        $name, $class, $desc, $enabled, $status, $order);
}

fwrite(STDERR, "generated " . count($rows) . " rows; derived-name packages (not in §9): " . count($derived) . "\n");
foreach ($derived as $d) fwrite(STDERR, "  $d\n");

echo "INSERT IGNORE INTO `atom_plugin`\n";
echo "    (`name`, `class_name`, `version`, `description`, `category`, `is_enabled`, `is_core`, `is_locked`, `admin_only`, `status`, `load_order`, `created_at`, `updated_at`)\nVALUES\n";
echo implode(",\n", $rows) . ";\n";
