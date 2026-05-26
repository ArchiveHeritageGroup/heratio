<?php

/**
 * GranularRestoreService - restore a single record or table from a full backup
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgBackup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Granular restore (#671 Phase 4).
 *
 * The full backup is a single .sql.gz produced by mysqldump --routines
 * --triggers --events. To restore one row or one table without
 * rewinding the entire DB we:
 *
 *   1. Extract the backup to a temp directory using a per-table
 *      filter (mysqldump emits one stanza per table, each prefixed by
 *      a `-- Table structure for table \`name\`` line - we cut on
 *      those boundaries).
 *   2. For row-level restores, scan the captured INSERT statements
 *      for the matching id and rewrite as `INSERT ... ON DUPLICATE
 *      KEY UPDATE`, so an existing live row is overwritten rather
 *      than producing a duplicate-key error.
 *   3. Apply inside a transaction so a parse failure or constraint
 *      violation leaves the DB untouched.
 *
 * WARNING: granular restore can break referential integrity. The
 * caller is responsible for verifying the resulting state in a dev
 * environment first. See `docs/help/backup-granular-restore.md`.
 */
class GranularRestoreService
{
    /**
     * Restore one information_object row (and its i18n companions)
     * from the supplied .sql.gz full backup.
     *
     * @param int    $ioId        information_object.id
     * @param string $backupPath  Absolute path to .sql.gz dump.
     * @return array{tables: array<string,int>, statements: int}
     */
    public function restoreInformationObject(int $ioId, string $backupPath): array
    {
        $tables = ['information_object', 'information_object_i18n'];
        $totals = ['tables' => [], 'statements' => 0];

        DB::beginTransaction();
        try {
            foreach ($tables as $table) {
                $col = $table === 'information_object_i18n' ? 'id' : 'id';
                $where = "{$col} = {$ioId}";
                $count = $this->applyRows($table, $backupPath, $where);
                $totals['tables'][$table] = $count;
                $totals['statements'] += $count;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new RuntimeException(
                'restoreInformationObject failed (rolled back): '.$e->getMessage(),
                0,
                $e
            );
        }

        Log::info('[ahg-backup] granular restore (information_object)', [
            'io_id'  => $ioId,
            'backup' => basename($backupPath),
            'result' => $totals,
        ]);

        return $totals;
    }

    /**
     * Restore one table (optionally filtered by a WHERE clause) from
     * the supplied backup. The WHERE filter is applied against the
     * extracted INSERT rows in-memory.
     *
     * @param string      $tableName
     * @param string      $backupPath
     * @param string|null $whereClause  e.g. "id = 42" or "lft BETWEEN 10 AND 20"
     * @return array{statements: int}
     */
    public function restoreTable(string $tableName, string $backupPath, ?string $whereClause = null): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            throw new RuntimeException("restoreTable: refusing unsafe table name '{$tableName}'.");
        }

        DB::beginTransaction();
        try {
            $count = $this->applyRows($tableName, $backupPath, $whereClause);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new RuntimeException(
                'restoreTable failed (rolled back): '.$e->getMessage(),
                0,
                $e
            );
        }

        Log::info('[ahg-backup] granular restore (table)', [
            'table'  => $tableName,
            'where'  => $whereClause,
            'backup' => basename($backupPath),
            'rows'   => $count,
        ]);

        return ['statements' => $count];
    }

    /**
     * Core mechanic: extract the stanza for $tableName from the dump,
     * filter INSERT VALUES tuples against $whereClause (when given),
     * and apply each tuple as INSERT ... ON DUPLICATE KEY UPDATE.
     */
    private function applyRows(string $tableName, string $backupPath, ?string $whereClause): int
    {
        $stanza = $this->extractTableStanza($tableName, $backupPath);
        if ($stanza === '') {
            return 0;
        }

        $columns = $this->parseColumnsFromStanza($tableName, $stanza);
        if (empty($columns)) {
            throw new RuntimeException("Could not parse columns for table {$tableName} in backup.");
        }

        $tuples = $this->extractInsertTuples($tableName, $stanza);
        if (empty($tuples)) {
            return 0;
        }

        $filtered = $whereClause === null
            ? $tuples
            : $this->filterTuples($tuples, $columns, $whereClause);

        $applied = 0;
        foreach ($filtered as $tuple) {
            $sql = $this->buildUpsertSql($tableName, $columns, $tuple);
            DB::statement($sql);
            $applied++;
        }
        return $applied;
    }

    /**
     * Read the gzipped dump line-by-line, collect everything from the
     * `-- Table structure for table \`X\`` marker for $tableName up to
     * the next such marker (or EOF).
     */
    private function extractTableStanza(string $tableName, string $backupPath): string
    {
        if (!is_file($backupPath)) {
            throw new RuntimeException("Backup file not found: {$backupPath}");
        }

        $isGz = str_ends_with($backupPath, '.gz');
        $fh = $isGz ? @gzopen($backupPath, 'rb') : @fopen($backupPath, 'rb');
        if (!$fh) {
            throw new RuntimeException("Cannot open backup file: {$backupPath}");
        }

        $startMarker = "-- Table structure for table `{$tableName}`";
        $endMarkerPrefix = "-- Table structure for table `";

        $inside = false;
        $buf = '';
        $reader = $isGz
            ? function () use ($fh) { return gzgets($fh); }
            : function () use ($fh) { return fgets($fh); };

        while (($line = $reader()) !== false) {
            if (!$inside) {
                if (str_contains($line, $startMarker)) {
                    $inside = true;
                    $buf .= $line;
                }
                continue;
            }
            // Stop at next table marker (but not the one we just entered).
            if (str_starts_with(ltrim($line), $endMarkerPrefix)
                && !str_contains($line, "`{$tableName}`")) {
                break;
            }
            $buf .= $line;
        }
        $isGz ? gzclose($fh) : fclose($fh);

        return $buf;
    }

    /**
     * Parse the column list out of the CREATE TABLE block in $stanza.
     */
    private function parseColumnsFromStanza(string $tableName, string $stanza): array
    {
        if (!preg_match(
            '/CREATE TABLE\s+`'.preg_quote($tableName, '/').'`\s*\((.*?)\)\s*ENGINE/s',
            $stanza,
            $m
        )) {
            return [];
        }
        $inner = $m[1];
        $cols = [];
        foreach (preg_split('/,\s*\n/', $inner) ?: [] as $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }
            // Skip keys / indexes / constraints
            $upper = strtoupper($line);
            if (str_starts_with($upper, 'PRIMARY KEY')
                || str_starts_with($upper, 'KEY ')
                || str_starts_with($upper, 'UNIQUE KEY')
                || str_starts_with($upper, 'CONSTRAINT')
                || str_starts_with($upper, 'FULLTEXT')
                || str_starts_with($upper, 'SPATIAL')
                || str_starts_with($upper, 'INDEX ')) {
                continue;
            }
            if (preg_match('/^`([^`]+)`/', $line, $cm)) {
                $cols[] = $cm[1];
            }
        }
        return $cols;
    }

    /**
     * Pull out the values tuples from every INSERT INTO `$tableName`
     * VALUES ... statement in the stanza. Returns each tuple as the
     * raw text inside the outer parens (e.g. "1,'foo',NULL").
     *
     * mysqldump emits multi-row inserts: `INSERT INTO ... VALUES (...),
     * (...), (...);` - we split on the closing-paren-comma-open-paren
     * boundary respecting quoted strings.
     *
     * @return array<int, string>
     */
    private function extractInsertTuples(string $tableName, string $stanza): array
    {
        $tuples = [];
        $pattern = '/INSERT INTO\s+`'.preg_quote($tableName, '/').'`\s*(?:\([^)]*\)\s*)?VALUES\s*(.+?);\s*(?:\n|$)/is';
        if (!preg_match_all($pattern, $stanza, $matches)) {
            return [];
        }
        foreach ($matches[1] as $valuesBlob) {
            foreach ($this->splitTuples($valuesBlob) as $t) {
                $tuples[] = $t;
            }
        }
        return $tuples;
    }

    /**
     * Split a multi-tuple VALUES blob into its constituent tuples.
     * Respects single quotes (with `\'` and `\\` escapes) so commas
     * inside string literals don't split a tuple.
     *
     * @return array<int, string>
     */
    private function splitTuples(string $blob): array
    {
        $out = [];
        $len = strlen($blob);
        $depth = 0;
        $inStr = false;
        $start = 0;
        for ($i = 0; $i < $len; $i++) {
            $ch = $blob[$i];
            if ($inStr) {
                if ($ch === '\\') { $i++; continue; }
                if ($ch === "'") { $inStr = false; }
                continue;
            }
            if ($ch === "'") { $inStr = true; continue; }
            if ($ch === '(') {
                if ($depth === 0) { $start = $i + 1; }
                $depth++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $out[] = substr($blob, $start, $i - $start);
                }
            }
        }
        return $out;
    }

    /**
     * Apply a SQL-style WHERE clause to in-memory tuples by binding
     * the columns into a single-row CTE-equivalent: we wrap each
     * tuple as `SELECT <vals> AS <cols>` and run the user's WHERE
     * via a `SELECT 1 WHERE ...` against MySQL itself. This means
     * we get exact MySQL operator semantics (BETWEEN, LIKE, IS NULL)
     * for free, at the cost of one round-trip per tuple.
     *
     * The WHERE clause is sanitised: bare semicolons forbidden.
     */
    private function filterTuples(array $tuples, array $columns, string $whereClause): array
    {
        if (str_contains($whereClause, ';')) {
            throw new RuntimeException('filterTuples: WHERE clause may not contain `;`.');
        }
        $kept = [];
        foreach ($tuples as $tuple) {
            $vals = $this->splitTupleValues($tuple);
            if (count($vals) !== count($columns)) {
                // mismatched arity - skip rather than risk wrong assignment
                continue;
            }
            $selectExprs = [];
            for ($i = 0; $i < count($columns); $i++) {
                $selectExprs[] = $vals[$i].' AS `'.$columns[$i].'`';
            }
            $probe = 'SELECT 1 AS keep FROM (SELECT '.implode(', ', $selectExprs).') t WHERE ('.$whereClause.')';
            try {
                $row = DB::selectOne($probe);
                if ($row) {
                    $kept[] = $tuple;
                }
            } catch (\Throwable $e) {
                throw new RuntimeException('filterTuples: WHERE evaluation failed: '.$e->getMessage(), 0, $e);
            }
        }
        return $kept;
    }

    /**
     * Split one tuple's raw comma-separated values respecting quoted
     * strings. Returns the literal value tokens with quotes intact
     * so they can be re-emitted into an INSERT statement verbatim.
     *
     * @return array<int, string>
     */
    private function splitTupleValues(string $tuple): array
    {
        $out = [];
        $len = strlen($tuple);
        $inStr = false;
        $buf = '';
        for ($i = 0; $i < $len; $i++) {
            $ch = $tuple[$i];
            if ($inStr) {
                $buf .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $tuple[$i + 1];
                    $i++;
                    continue;
                }
                if ($ch === "'") { $inStr = false; }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                $buf .= $ch;
                continue;
            }
            if ($ch === ',') {
                $out[] = trim($buf);
                $buf = '';
                continue;
            }
            $buf .= $ch;
        }
        if ($buf !== '') {
            $out[] = trim($buf);
        }
        return $out;
    }

    /**
     * Build an INSERT ... ON DUPLICATE KEY UPDATE statement reusing
     * the raw value tokens straight out of the dump.
     */
    private function buildUpsertSql(string $tableName, array $columns, string $tupleBody): string
    {
        $cols = array_map(fn ($c) => "`{$c}`", $columns);
        $assignments = [];
        foreach ($columns as $c) {
            $assignments[] = "`{$c}` = VALUES(`{$c}`)";
        }
        return sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $tableName,
            implode(', ', $cols),
            $tupleBody,
            implode(', ', $assignments)
        );
    }
}
