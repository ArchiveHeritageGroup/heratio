# Disaster Recovery Plan

Operator runbook covering full-host loss, database corruption, single-record loss, single-file loss, and off-site provider failure for a Heratio production instance. Shipped with issue #671 Phase 4.

This plan assumes Phase 2-4 are in place: encrypted backups are running on schedule, the off-site replicator is configured against a non-local driver, the binary-log archiver is running hourly, and the operator has tested PITR at least once in a sandbox.

## Recovery objectives

| Scenario               | RTO target          | RPO target               |
|------------------------|---------------------|--------------------------|
| Single record (one IO) | 15 minutes          | last full backup (~24h)  |
| Single file (digital)  | 30 minutes          | last off-site replication|
| Single database        | 4 hours             | 1 hour (binlog archive)  |
| Full host              | 24 hours            | 1 hour (binlog archive)  |
| Off-site provider loss | 48 hours            | same as host scenario    |

RTO = recovery time objective (how long from declared incident to user-visible recovery). RPO = recovery point objective (how much data loss is acceptable). The RPO of 1 hour for PITR-eligible scenarios is bounded by the `backup:archive-binlogs` schedule; tighten the cron cadence if a smaller window is required.

## Pre-flight: confirm the safety net is live

Before any incident, verify the following on a quarterly cadence:

```
php artisan backup:replicate --driver=<your-driver>
php artisan backup:verify-integrity
php artisan backup:archive-binlogs
mysql -e "SELECT COUNT(*) FROM heratio.ahg_backup_run WHERE log_bin_enabled = 1;"
mysql -e "SELECT MAX(archived_at) FROM heratio.ahg_backup_binlog;"
```

A row count of zero on `ahg_backup_run` with `log_bin_enabled = 1` means PITR is currently impossible. Fix the MySQL config (see preconditions in `backup-phase-4-pitr-granular.md`) and trigger a fresh full backup before declaring DR readiness.

## Scenario 1: Single-record loss (one information_object)

Indicator: a user reports that one archival description has gone (404, blank fields, or wrong content).

Steps:
1. Identify the IO id from the URL or the slug catch-all route.
2. List recent backups: `ls -lt /mnt/nas/heratio/backups/*.sql.gz | head -5`.
3. Pick the most recent backup that pre-dates the corruption.
4. Restore: `php artisan backup:restore-io <id> /path/to/backup.sql.gz`.
5. Spot-check the IO in the UI.
6. If FK violations surface (related digital_object or relation rows), file a follow-up ticket - granular restore intentionally does not chase the dependency graph.

Target time: 15 minutes from report to restored IO.

## Scenario 2: Single-file loss (digital_object on disk)

Indicator: an IO page renders but its TIFF/JP2/PDF returns 404.

Steps:
1. Resolve the file's absolute path from `digital_object.path` joined to `config('heratio.uploads_path')`.
2. Pull the most recent off-site copy of the uploads tarball: `aws s3 cp s3://<bucket>/<prefix>/uploads_<date>.tar.gz /tmp/`.
3. Decrypt if encrypted: `gpg --decrypt --output uploads.tar.gz uploads.tar.gz.gpg`.
4. Extract only the missing file: `tar -xzf uploads.tar.gz <relative-path>` into a staging dir.
5. Verify hash against the IO's stored checksum if one exists; copy into the live uploads path with `chown www-data:www-data`.
6. Touch the IIIF cache to force re-tile.

Target time: 30 minutes.

## Scenario 3: Database corruption

Indicator: the DB is reachable but data is wrong (mass-truncated rows, bad migration, accidental schema drop).

Steps:
1. Stop writes: put the site in maintenance mode (`php artisan down`).
2. Identify a target time just before the corruption (read the audit-trail timestamps).
3. Verify a usable backup exists: `php artisan tinker --execute='echo \DB::table("ahg_backup_run")->where("dumped_at","<=","<target>")->where("log_bin_enabled",1)->orderByDesc("dumped_at")->first()->backup_filename;'`.
4. Dry-run PITR: `php artisan backup:pitr "<target>" --dry-run`.
5. Execute PITR: `php artisan backup:pitr "<target>"`.
6. Reindex Elasticsearch: `php artisan ahg:es-reindex --drop`.
7. Bring the site back up: `php artisan up`.
8. Email affected users (see Scenario 6 for the communication template).

Target time: 4 hours including reindex.

## Scenario 4: Full-host loss

Indicator: the host is unreachable and not coming back, or a hardware/datacentre incident has been declared.

Steps:
1. Provision a fresh host with the same Linux + PHP + MySQL versions documented in `CLAUDE.md`.
2. Install Heratio dependencies: clone the repo, `composer install`, `npm ci && npm run build`.
3. Restore the most recent off-site full backup. Pull it from S3/rsync/whatever the configured driver is, decrypt with the operator's GPG passphrase.
4. Restore the binlog archive from the same off-site location into `<heratio.backups_path>/binlogs/`.
5. Apply the dump: `gunzip -c database_*.sql.gz | mysql heratio`.
6. Run PITR forward to the target time: `php artisan backup:pitr "<target>" --skip-full`.
7. Restore uploads + plugins + framework tarballs the same way.
8. Confirm `config/heratio.php` storage paths point at the new host's mount points; update `.env` and clear cache.
9. Recreate Elasticsearch indices: `php artisan ahg:es-reindex --drop`.
10. Restore the php-fpm `ProtectSystem=full` drop-in per `reference_heratio_phpfpm_dropin.md`.
11. Issue a fresh TLS cert and update DNS to the new host's IP.

Target time: 24 hours.

## Scenario 5: Off-site provider failure

Indicator: the configured off-site driver is failing - bucket revoked, rsync target offline, region down.

Strategy: dual-driver rotation. Configure a second driver alongside the primary BEFORE the incident; switch over by changing `BACKUP_OFFSITE_DRIVER` in the env and re-running `backup:replicate`. Phase 3's ledger remembers each driver separately, so files pushed to provider A do not get re-pushed to provider B unless you run with `--force`.

A monthly drill: `BACKUP_OFFSITE_DRIVER=rsync php artisan backup:replicate --force`, then verify the rsync copy is restorable with a sandbox import.

## Scenario 6: User communication

Heratio Phase 2 ships an email transport. Use it directly:

```
$users = \DB::table('users')->whereNotNull('email')->get();
foreach ($users as $u) {
    Mail::raw(
        "Heratio is currently in maintenance following a backup restore at <time>. ".
        "Service is expected to resume by <eta>. We apologise for the interruption.",
        fn ($m) => $m->to($u->email)->subject('[Heratio] Maintenance in progress')
    );
}
```

Also drop a Workbench notification (see `dispatchWorkbenchNotification` in `BackupController.php` for the JSON shape) so on-call operators get an audible alert.

## After-action review

Within seven days of any DR scenario being exercised in anger:

1. File a GitHub issue capturing the timeline, the cause, the recovery steps actually taken, and the gap between target RTO/RPO and observed RTO/RPO.
2. Update this document if any step in the runbook turned out to be wrong or incomplete.
3. If a script needs an enhancement (e.g. PITR didn't handle a new edge case), open a follow-up ticket against the umbrella issue (#671).

## Quarterly DR drill checklist

- [ ] Restore the latest full backup into a sandbox database; confirm row counts match `information_object`, `actor`, `digital_object`.
- [ ] Run `backup:pitr "<one hour ago>" --dry-run` and confirm the planned plan is sensible.
- [ ] Execute granular-restore against a sample IO id in the sandbox.
- [ ] Pull a single binlog file from off-site, decrypt, run `mysqlbinlog --read-from-remote-server=0 <file>` to confirm readability.
- [ ] Rotate to the secondary off-site driver and verify a successful push.
- [ ] Update the "last drill date" stored in `ahg_setting` (group=backup, key=last_dr_drill).
