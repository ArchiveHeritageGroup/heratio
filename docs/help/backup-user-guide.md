> Heratio Help Center article. Category: Administration / Backup.

# Backup and Restore

Heratio ships a built-in backup and restore module for administrators. From a single dashboard you can create on-demand backups of the database, uploaded files, installed packages, and the application framework, then download, restore, or delete those archives. Beyond the dashboard, a set of console commands adds off-site replication, integrity verification, point-in-time recovery (PITR), and granular single-record restores. Backups are stored as compressed archives in a configurable directory, old archives are pruned automatically by retention rules, and operators can be alerted by email and in-app notification on every completed or failed run.

## Overview

The backup module is administrator-only. Every route sits behind the `admin` middleware and lives under the `/admin/backup` and `/admin/restore` paths. The dashboard works with four backup components:

- **Database** - a `mysqldump` of the full Heratio database, compressed with gzip into a `.sql.gz` file. The dump includes routines, triggers, and events and is taken with a single transaction so the site stays online.
- **Uploads** - a `tar.gz` of the uploads directory (digital objects and media).
- **Packages** - a `tar.gz` of the installed packages directory.
- **Framework** - a `tar.gz` of the application code, excluding vendor libraries, build artefacts, logs, version control, and packages.

Each archive is named with its component and a timestamp (for example `database_heratio_2026-05-25_020000.sql.gz`), so the module can recognise what each file contains even after a restart. Backup metadata such as binary-log coordinates and off-site replication status is tracked in dedicated tables for the recovery commands.

## Key features

- One-click creation of database, uploads, packages, and framework backups, individually or together.
- A dashboard listing of every backup file with its type, components, size, and date.
- Download any backup archive to your workstation.
- Restore any component back into the running system from a selected archive.
- Delete archives you no longer need.
- Automatic retention enforcement: archives older than the retention window, or beyond the maximum count, are removed after each run.
- Email and in-app notifications on success, success-with-warnings, and failure.
- Off-site replication to S3-compatible storage, an rsync-over-SSH target, or a local filesystem path (the local driver is for testing only).
- Optional symmetric encryption of off-site copies, gated on a configured passphrase.
- Integrity verification of off-site copies by checksum comparison.
- Point-in-time recovery using a full backup plus replayed binary logs.
- Granular restore of a single archival description or a single table from a full backup.

## How to use

### Open the dashboard

1. Sign in as an administrator.
2. Navigate to **Admin -> Backup** (route `backup.index`, URL `/admin/backup`).
3. The dashboard shows the current backup directory, a connection check for the database, total archive count and size, and a table of existing backups.

### Create a backup

1. On the dashboard, click **Create Backup**.
2. In the dialog, tick the components you want: Database, Uploads, Packages (Plugins), or Framework. At least one component is required.
3. Confirm to start. The run executes immediately and reports the files created with their sizes.
4. When the run finishes, retention rules are applied automatically and notifications are sent if enabled.

### Download, restore, or delete a backup

From the backup table on the dashboard, each row offers actions:

- **Download** (route `backup.download`) streams the archive to your browser.
- **Restore** (route `backup.restore`, URL `/admin/restore` or `/admin/backup/restore`) opens the restore page pre-selected to that archive. Choose the components to restore and confirm. Database restores import the `.sql.gz` over the live database; uploads, packages, and framework restores extract the `tar.gz` back into place.
- **Delete** (route `backup.destroy`) removes the archive file.

Restores are destructive operations that overwrite live data. Always confirm you have selected the correct archive before proceeding, and test on a non-production copy where possible.

### Off-site replication and verification (console)

These are command-line operations, suitable for scheduled jobs:

1. Push local archives off-site: `php artisan backup:replicate`. Add `--driver=s3` (or `rsync`, `localfs`) to override the configured driver, `--force` to re-push everything, or `--no-encryption` to skip encryption for one run.
2. Re-check off-site copies: `php artisan backup:verify-integrity`. Add `--all` to re-check already-verified rows, `--from=YYYY-MM-DD` to limit by date, or `--driver=` to restrict to one driver.

If a scheduler is enabled, replication runs daily, verification runs daily after it, and binary-log archiving runs hourly.

### Point-in-time and granular recovery (console)

These commands are destructive and should be tested in a sandbox first:

1. Archive binary logs for PITR: `php artisan backup:archive-binlogs` (optionally `--dest=`).
2. Restore to a moment in time: `php artisan backup:pitr "2026-05-25 14:30:00"`. Add `--dry-run` to print the plan only, `--skip-full` to replay logs onto an already-restored database, or `--binlog-dir=` to point at a log archive. PITR requires binary logging enabled in ROW format at backup time.
3. Restore one archival description: `php artisan backup:restore-io <id> <path-to.sql.gz>`.
4. Restore one table or a filtered subset: `php artisan backup:restore-table <table> <path-to.sql.gz> --where="id=42"`.

## Configuration

Open **Admin -> Backup -> Settings** (route `backup.settings`, URL `/admin/backup/settings`) to manage the dashboard options. Settings are stored as application settings in the `backup` group:

| Setting | Purpose | Default |
|---|---|---|
| `backup_path` | Directory where archives are written | The configured backups path |
| `backup_max_backups` | Maximum number of archives to keep | 10 |
| `backup_retention_days` | Age after which archives are pruned | 30 |
| `backup_notification_email` | Address for backup result emails | (falls back to the system from-address) |
| `backup_notify_workbench_username` | Recipient for the in-app notification | admin |
| `backup_notify_on_success` | Send a notification on success | enabled |
| `backup_notify_on_failure` | Send a notification on failure | enabled |
| `backup_encryption_passphrase` | Enables symmetric encryption of off-site copies | (unset) |

Off-site replication is configured separately in `config/backup.php`, with operators supplying credentials through `BACKUP_OFFSITE_*` environment variables rather than committing them. The driver is chosen with `BACKUP_OFFSITE_DRIVER` (`s3`, `rsync`, or `localfs`). The S3 driver reads bucket, region, endpoint, key, secret, and prefix variables; the rsync driver reads host, user, port, remote path, and SSH key variables. The S3 driver also requires the AWS SDK to be installed, and encryption requires the `gpg` binary on the host. The `localfs` driver only copies to another path on the same machine and provides no disaster-recovery protection, so it is intended for testing.

## References

- Source package: `packages/ahg-backup/`
- GitHub issue: [#549](https://github.com/ArchiveHeritageGroup/heratio/issues/549)
- Dashboard routes: `packages/ahg-backup/routes/web.php`
- Console commands: `packages/ahg-backup/src/Console/Commands/`
