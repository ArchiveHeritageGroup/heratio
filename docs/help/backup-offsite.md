# Off-site Backup Replication

Heratio can copy your local backup archives to an off-site destination on a daily schedule. This protects your data against site loss (fire, hardware failure, theft, ransomware on the local box). It is independent of the in-app Backup dashboard - that dashboard creates the local archives, this feature ships them off the host.

## What gets replicated

Every file in your backup directory (the path shown on `/admin/backup` -> Settings, defaulting to `storage/backups`) that matches `*.gz`, `*.tar.gz`, `*.sql.gz`, or `*.zip`. Already-replicated files are skipped automatically so re-running the command is cheap.

## Choosing a destination

You pick one of three drivers via the `BACKUP_OFFSITE_DRIVER` environment variable:

- **s3** - any S3-compatible object store (AWS S3, Wasabi, Backblaze B2, MinIO, DigitalOcean Spaces). This is the recommended choice for most installs.
- **rsync** - copy over SSH to another server you control. Use this when you already have a backup host and prefer not to put data in a third-party object store.
- **localfs** - copy to another directory on the same host. Useful for testing the pipeline before pointing at real off-site storage. Provides ZERO disaster-recovery value on its own.

See `docs/reference/backup-phase-3-offsite-integrity.md` for the full environment-variable reference for each driver.

## Encryption

If you set the AHG setting `backup_encryption_passphrase` (group: backup), Heratio will GPG-encrypt every archive with AES256 before pushing it. Keep the passphrase somewhere safe (and OFF the Heratio host) - if you lose it, the off-site copies become unrecoverable. Without a passphrase the off-site copy is shipped unencrypted and the daily command emits a warning.

The local archive in `storage/backups/` is never encrypted, so restoring from the local copy still works through the Backup dashboard as usual.

## Running it

By hand:

```bash
php artisan backup:replicate
```

The scheduler automatically runs it daily at 03:15 if you have Laravel's scheduler wired into cron (`* * * * * cd /usr/share/nginx/heratio && php artisan schedule:run`).

## Verifying integrity

Off-site copies are useless if they have silently corrupted. Heratio re-verifies the SHA-256 of every replicated file on a daily schedule (04:00). To run it on demand:

```bash
php artisan backup:verify-integrity
```

Add `--all` to re-check files already marked verified (defence in depth). Add `--from=2026-05-01` to limit the sweep to recent uploads.

Any file that fails verification appears in the application log (`storage/logs/laravel-<date>.log`) and is marked `status='failed'` in the `ahg_backup_replication` table. Operators are expected to react: re-replicate the file with `php artisan backup:replicate --force`, then verify again.

## Where to see history

The ledger lives in the `ahg_backup_replication` table. A future Backup dashboard tab will surface this in the UI; for now, query it directly:

```sql
SELECT local_path, driver, status, replicated_at, verified_at
FROM ahg_backup_replication
ORDER BY replicated_at DESC
LIMIT 50;
```

## Troubleshooting

- "S3OffsiteDriver requires the aws/aws-sdk-php package" - run `composer require aws/aws-sdk-php` in the Heratio root.
- "the `gpg` binary is not on PATH" - install gnupg (`apt install gnupg`) or clear `backup_encryption_passphrase` if you do not want encryption.
- rsync exits non-zero with `Permission denied (publickey)` - check `BACKUP_OFFSITE_RSYNC_SSH_KEY` points at the right private key and the matching public key is in the remote user's `~/.ssh/authorized_keys`.
- All pushes go to `status='failed'` with a SHA-256 mismatch - check that nothing else (a sync tool, the cloud provider's own dedup) is rewriting the object after upload.

## Not in this release

- Point-in-time recovery (PITR) from binary logs.
- Granular restore (single table, single file).
- A formal Disaster Recovery plan document.

Those three items are tracked under issue #671 Phase 4.
