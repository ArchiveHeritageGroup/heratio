# Point-in-Time Recovery

Heratio can restore your database to any moment in the recent past, not just the moment of the last full backup. This is called point-in-time recovery, or PITR. Use it when a destructive change (accidental deletion, runaway script, schema mistake) happened mid-day and you don't want to lose all the legitimate work that happened earlier.

## How it works

PITR combines two things:

1. The most recent full backup taken BEFORE the moment you want to recover to.
2. MySQL's binary log, which records every change to the database in commit order.

Heratio archives the binary log every hour (`backup:archive-binlogs`). When you ask for a PITR, the system restores the chosen full backup and then "fast-forwards" the binary log up to the exact second you specified.

## Operator preconditions

PITR only works if your MySQL server has binary logging turned on. Ask your sysadmin to confirm the following in `my.cnf`:

```
[mysqld]
log_bin = ON
binlog_format = ROW
binlog_row_image = FULL
expire_logs_days = 7
```

A server restart is needed if these were not already enabled. **Heratio cannot enable this from the web UI.**

To check whether your install has PITR available:

```
php artisan tinker --execute='echo \DB::table("ahg_backup_run")->where("log_bin_enabled",1)->count();'
```

Any number greater than zero means at least one PITR-eligible backup exists.

## Running a recovery

You will need an SSH session to the Heratio host - PITR is a command-line operation, not a button in the web UI, because it destructively rewrites the database.

```
# 1. See the plan without applying anything
php artisan backup:pitr "2026-05-25 14:30:00" --dry-run

# 2. Apply it for real
php artisan backup:pitr "2026-05-25 14:30:00"
```

The target time is read in the server's local time zone. Pick a moment one or two minutes BEFORE the destructive event - replaying right up to the event itself is rarely safe.

After PITR completes:

1. Reindex Elasticsearch so search results match the restored database: `php artisan ahg:es-reindex --drop`.
2. Clear the application cache: `php artisan cache:clear`.
3. Spot-check the affected records in the UI before re-opening the site to users.

## How far back can I go?

By default, as far back as your retention windows allow:

- Full backups: kept according to `backup_retention_days` (default 30 days) and `backup_max_backups` (default 10) - whichever is more restrictive.
- Binary logs: kept according to MySQL's `expire_logs_days` (default 7 days on most installs).

The shorter of the two is your effective PITR window. Increase `expire_logs_days` if you need a longer window, but bear in mind binlogs accumulate disk space proportional to write volume.

## When NOT to use PITR

- The destructive change was a single record being modified or deleted. Use the narrower **granular restore** workflow instead (see `backup-granular-restore.md`) - it leaves everything else in place.
- You need to recover one digital file (TIFF, JP2, PDF). PITR only touches the database; files are restored from the uploads tarball.
- The corruption is older than your retention window. In that case the most recent off-site copy is the best you can do.

## Troubleshooting

**"No backup run found with dumped_at <= ..."** - There is no full backup before the requested time. Either the time is too far in the past (older than retention) or no PITR-eligible backups exist yet (binary logging may have been turned on after backups started running). Take a fresh full backup and try again.

**"mysqlbinlog binary is not on PATH"** - Install the MySQL client package on the host: `apt install mysql-client`.

**"binlog_format at dump time was 'STATEMENT'"** - The backup was taken while MySQL was using statement-based replication. PITR will run but is less reliable for workloads with non-deterministic SQL (NOW(), UUID(), AUTO_INCREMENT race conditions). Switch the server to ROW format and take a fresh backup as soon as practical.

## Where the moving pieces live

- The binlog archive directory is at `<heratio.backups_path>/binlogs/`.
- The dump-coordinate ledger is the MySQL table `ahg_backup_run`.
- The binlog ledger is `ahg_backup_binlog`.
- Both are populated automatically; you should not need to touch them by hand.
