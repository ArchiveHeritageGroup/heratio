<?php
/**
 * Heratio overlay install — column-delta sync.
 *
 * Compares every table that exists in BOTH the reference (heratio) DB and the target DB,
 * and ALTER-TABLE-ADDs any column present in reference but missing in target. Idempotent —
 * skips columns that already exist; never DROPs or modifies existing columns.
 *
 * Usage:
 *   php database/tools/sync-columns.php --reference=heratio --target=dam [--apply]
 *
 * Flags:
 *   --reference  Source DB to compare against. Default: heratio
 *   --target     Target DB to mutate. Required.
 *   --apply      Actually run the ALTER statements. Without it, prints DDL only (dry-run).
 *   --host       MySQL host. Default: localhost
 *   --user       MySQL user. Default: root
 *   --pass       MySQL password. Read from MYSQL_PWD env if not given.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

$opts = getopt('', ['reference::', 'target:', 'apply', 'host::', 'user::', 'pass::', 'socket::']);
if (! isset($opts['target'])) {
    fwrite(STDERR, "Usage: php sync-columns.php --target=<db> [--reference=heratio] [--apply]\n");
    exit(1);
}
$reference = $opts['reference'] ?? 'heratio';
$target    = $opts['target'];
$apply     = isset($opts['apply']);
$host      = $opts['host'] ?? 'localhost';
$user      = $opts['user'] ?? 'root';
$pass      = $opts['pass'] ?? getenv('MYSQL_PWD') ?: '';
$socket    = $opts['socket'] ?? getenv('MYSQL_SOCKET') ?: '';

if ($reference === $target) {
    fwrite(STDERR, "Reference and target are the same DB — nothing to sync.\n");
    exit(1);
}

// Prefer unix socket when host=localhost and no explicit password, matching the CLI mysql client.
if ($host === 'localhost' && $pass === '' && $socket === '') {
    foreach (['/var/run/mysqld/mysqld.sock', '/run/mysqld/mysqld.sock', '/tmp/mysql.sock'] as $candidate) {
        if (file_exists($candidate)) {
            $socket = $candidate;
            break;
        }
    }
}

$dsn = $socket
    ? "mysql:unix_socket={$socket};charset=utf8mb4"
    : "mysql:host={$host};charset=utf8mb4";

$pdo = new PDO($dsn, $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = "
    SELECT h.table_name, h.column_name, h.column_type, h.is_nullable,
           h.column_default, h.extra, h.column_comment, h.ordinal_position,
           (SELECT column_name FROM information_schema.columns
              WHERE table_schema = ? AND table_name = h.table_name
                AND ordinal_position = h.ordinal_position - 1) AS prev_col
    FROM information_schema.columns h
    LEFT JOIN information_schema.columns t
      ON t.table_schema = ?
     AND t.table_name   = h.table_name
     AND t.column_name  = h.column_name
    WHERE h.table_schema = ?
      AND h.table_name IN (SELECT table_name FROM information_schema.tables WHERE table_schema = ?)
      AND t.column_name IS NULL
    ORDER BY h.table_name, h.ordinal_position";
$st = $pdo->prepare($sql);
$st->execute([$reference, $target, $reference, $target]);
$rows = array_map(
    fn($r) => (object) array_change_key_case((array) $r, CASE_LOWER),
    $st->fetchAll(PDO::FETCH_OBJ)
);

if (! $rows) {
    echo "No missing columns. Target `{$target}` is up-to-date with reference `{$reference}`.\n";
    exit(0);
}

$pdo->exec("USE `{$target}`");
if ($apply) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
}

$added = 0; $skipped = 0; $errors = [];
foreach ($rows as $r) {
    $type = $r->column_type;
    $null = ($r->is_nullable === 'YES') ? 'NULL' : 'NOT NULL';
    $default = '';
    if ($r->column_default !== null) {
        if (preg_match('/^(CURRENT_TIMESTAMP|NULL|-?\d+(\.\d+)?)$/i', $r->column_default)) {
            $default = "DEFAULT {$r->column_default}";
        } else {
            $default = "DEFAULT " . $pdo->quote($r->column_default);
        }
    } elseif ($r->is_nullable === 'YES') {
        $default = "DEFAULT NULL";
    }
    $extra = '';
    if (stripos($r->extra, 'auto_increment') !== false) {
        $extra = 'AUTO_INCREMENT';
    }
    if (stripos($r->extra, 'on update CURRENT_TIMESTAMP') !== false) {
        $extra = trim($extra . ' ON UPDATE CURRENT_TIMESTAMP');
    }
    $after = $r->prev_col ? "AFTER `{$r->prev_col}`" : "FIRST";
    $alter = "ALTER TABLE `{$r->table_name}` ADD COLUMN `{$r->column_name}` {$type} {$null} {$default} {$extra} {$after}";

    if (! $apply) {
        echo $alter . ";\n";
        continue;
    }
    try {
        $pdo->exec($alter);
        $added++;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), '1060') !== false) {
            $skipped++;
        } else {
            $errors[] = "{$r->table_name}.{$r->column_name}: " . substr($e->getMessage(), 0, 120);
        }
    }
}

if ($apply) {
    echo "Reference: {$reference}\nTarget:    {$target}\nAdded:     {$added}\nSkipped:   {$skipped}\nErrors:    " . count($errors) . "\n";
    foreach (array_slice($errors, 0, 20) as $e) {
        echo "  ERR: {$e}\n";
    }
    exit(count($errors) > 0 ? 2 : 0);
} else {
    echo "\n-- Dry run only. " . count($rows) . " column(s) would be added. Re-run with --apply to execute.\n";
}
