# Backup Phase 4 - Point-in-Time Recovery and Granular Restore

Issue #671 Phase 4 closes the next three items on the umbrella backup issue: point-in-time recovery (PITR), granular restore, and the documented disaster-recovery plan. Phase 5+ (cross-region warm standby and automated DR drills) remains outstanding and the umbrella issue stays open.

## What this phase delivers

- A new service `AhgBackup\Services\BinaryLogArchiver` that records MySQL binary-log coordinates at dump time and archives rotated binlogs into the backups directory on an hourly schedule.
- A new service `AhgBackup\Services\GranularRestoreService` that extracts a single information_object (or any single table, optionally filtered) from a full mysqldump backup and applies it as `INSERT ... ON DUPLICATE KEY UPDATE` inside a transaction.
- Four new artisan commands: `backup:archive-binlogs` (hourly cron candidate), `backup:pitr {target-time}`, `backup:restore-io {ioId} {backup}`, and `backup:restore-table {table} {backup} --where=...`.
- Two new ledger tables: `ahg_backup_run` (one row per full backup, capturing binlog file + position + GTID set) and `ahg_backup_binlog` (one row per archived binlog file).
- A `BackupController::create()` hook that calls `BinaryLogArchiver::recordDumpCoordinates()` immediately after every successful database dump so PITR always has a checkpoint to fall back on.
- Operator runbook: `docs/reference/disaster-recovery-plan.md`.

## Operator preconditions

PITR is built on top of MySQL's binary log. The following must be set on the MySQL server BEFORE the first dump that you want to PITR back to. This package does NOT enable these from PHP - they require root on the MySQL host and a server restart.

```
[mysqld]
log_bin = ON
binlog_format = ROW
binlog_row_image = FULL
expire_logs_days = 7
sync_binlog = 1
```

Without `log_bin = ON` the dump still succeeds, the dump-coordinate row still gets written to `ahg_backup_run`, but `log_bin_enabled` is `0` and the PITR command refuses to use that row. The granular-restore commands work regardless of binary logging.

`binlog_format = STATEMENT` or `MIXED` is recorded in the ledger with a warning; replay reliability for non-trivial workloads is only guaranteed with ROW.

## How PITR works end to end

1. **Capture (continuous, automatic).** Every time `BackupController::create()` runs a database mysqldump, `BinaryLogArchiver::recordDumpCoordinates()` reads `SHOW MASTER STATUS` (or `SHOW BINARY LOG STATUS` on MySQL 8.4+) and writes the `File`, `Position`, and `Executed_Gtid_Set` to `ahg_backup_run`. The row is keyed on the dump filename so re-running over the same dump is idempotent.
2. **Archive (hourly, automatic).** `backup:archive-binlogs` runs once an hour from the Laravel scheduler. It issues `FLUSH BINARY LOGS` (closes the current log, opens a fresh one), then copies every now-closed binlog file out of MySQL's `datadir` into `<heratio.backups_path>/binlogs/` and records one row per file in `ahg_backup_binlog`. The active log is never archived because it can still grow mid-copy. The off-site replicator (Phase 3) sweeps the binlog files into the configured driver on its next pass.
3. **Restore (manual, on demand).** Operator runs `php artisan backup:pitr "2026-05-25 14:30:00"`. The command finds the most recent `ahg_backup_run` row with `dumped_at <= target` and `log_bin_enabled = 1`, restores that full backup with `gunzip | mysql`, then pipes every archived binlog from the captured `binlog_file` forward through `mysqlbinlog --stop-datetime=<target>` into the live server. The first replayed file uses `--start-position=<captured pos>` so events that ran before the dump finished are skipped.

The recovery-point objective (RPO) is bounded by the binlog archive cadence. Hourly archiving means at most one hour of activity can be lost between the last archived log and a catastrophic loss of MySQL's datadir. Operators that need a tighter RPO can change the schedule frequency in `AhgBackupServiceProvider::boot()` or run the command from system cron at higher frequency.

### Sample timeline

```
03:00  full backup (mysqldump) -> ahg_backup_run row #14:
         backup_filename = database_heratio_2026-05-25_030000.sql.gz
         binlog_file     = mysql-bin.000023
         binlog_pos      = 156
         gtid_executed   = ...
         log_bin_enabled = 1
04:00  backup:archive-binlogs (cron) flushes; copies mysql-bin.000023 to backups/binlogs/
05:00  backup:archive-binlogs flushes; copies mysql-bin.000024
...
14:30  operator deletes a critical IO by accident
15:00  backup:archive-binlogs flushes; copies mysql-bin.000034 (covers up to ~14:59)

Recovery:
  php artisan backup:pitr "2026-05-25 14:29:00" --dry-run
  php artisan backup:pitr "2026-05-25 14:29:00"
```

## Granular restore

Granular restore is the right tool when:

- A single archival description (or a small set) was modified or deleted incorrectly and the rest of the database has moved on since the last full backup. Rolling back the whole DB would lose unrelated edits.
- A single reference table (dropdowns, taxonomy terms) was corrupted by a bad import.

It is NOT the right tool when:

- The wrong-ness is large or unbounded (rolling restore + PITR is safer).
- The affected rows have outbound foreign keys to data added since the backup (you will hit FK violations on apply, or break referential integrity quietly).

### Information-object restore

```
php artisan backup:restore-io 42 /mnt/nas/heratio/backups/database_heratio_2026-05-25_030000.sql.gz
```

This reads both `information_object` and `information_object_i18n` from the dump, filters to `id = 42`, and applies each matching row inside a transaction. Other tables (relations, digital_object, properties) are NOT touched - they likely have IDs that are still valid against the live DB.

### Table restore (full or filtered)

```
php artisan backup:restore-table ahg_dropdown /path/to/backup.sql.gz
php artisan backup:restore-table actor_i18n   /path/to/backup.sql.gz --where="id BETWEEN 100 AND 200"
```

The `--where` clause is evaluated by MySQL itself against each in-memory tuple (we wrap each row as a `SELECT ... AS <cols>` and run `SELECT 1 WHERE <clause>`), so BETWEEN, LIKE, IS NULL and any other MySQL operator works exactly as it would against a live table. The clause is rejected if it contains a semicolon.

## Caveats and limits

- Granular restore reuses the literal value tokens from the dump file unmodified. If the dump was generated against a different character set than the live DB you can in theory hit collation mismatches. mysqldump defaults to `utf8mb4` so this only bites on very old or hand-edited dumps.
- The CREATE TABLE parser only recognises columns that start with a backtick (which is mysqldump's standard output). Hand-edited dumps may need touch-up before granular restore works.
- PITR uses `--database=` on mysqlbinlog to restrict replay to one database. If the original cluster had cross-database writes you may need to re-run the command per database.
- The `--skip-full` PITR flag exists for the case where you've already restored a full backup by hand (e.g. from an off-site copy) and only need the binlog replay step.

## Where the moving pieces live

- `packages/ahg-backup/src/Services/BinaryLogArchiver.php` - coordinate capture + binlog archival.
- `packages/ahg-backup/src/Services/GranularRestoreService.php` - granular restore engine.
- `packages/ahg-backup/src/Console/Commands/ArchiveBinaryLogsCommand.php` - hourly archive.
- `packages/ahg-backup/src/Console/Commands/RestoreToPointInTimeCommand.php` - PITR orchestrator.
- `packages/ahg-backup/src/Console/Commands/RestoreInformationObjectCommand.php` - granular IO restore.
- `packages/ahg-backup/src/Console/Commands/RestoreTableCommand.php` - granular table restore.
- `packages/ahg-backup/database/install.sql` - schema for `ahg_backup_run` and `ahg_backup_binlog`.
- `packages/ahg-backup/src/Providers/AhgBackupServiceProvider.php` - registers commands, schedules the hourly archive, seeds the new tables on first boot.
- `packages/ahg-backup/src/Controllers/BackupController.php` - hooks `recordDumpCoordinates()` into the database-dump path.

## Related issues

- Umbrella: heratio #671 (Backup + restore). Stays open after Phase 4.
- Prerequisites shipped: Phase 2 (v1.73.0, email notifications), Phase 3 (v1.98.0, off-site replication + integrity verification + GPG AES256).
- Remaining (Phase 5+): cross-region warm standby, automated quarterly DR drills.
