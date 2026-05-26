# Backup Phase 3 - Off-site Replication and Integrity Verification

Issue #671 Phase 3 closes two of the six items called out on the umbrella backup issue: off-site replication and integrity verification. PITR, granular restore, and the written DR plan remain for Phase 4. Encryption ships here as the wire-level companion to off-site push - it is not the whole encryption-at-rest story.

## What this phase delivers

- A driver abstraction for off-site replication (`OffsiteDriverInterface`) with three concrete implementations: `S3OffsiteDriver`, `RsyncOffsiteDriver`, `LocalFsOffsiteDriver`.
- Two artisan commands: `backup:replicate` (push) and `backup:verify-integrity` (re-hash). Both are daily-cron candidates and are wired into the Laravel scheduler at 03:15 and 04:00 respectively.
- A new ledger table `ahg_backup_replication` tracking every push attempt - what was pushed, when, by which driver, the recorded SHA-256, and the last verification timestamp.
- Optional symmetric GPG (AES256) encryption applied to the outbound payload, keyed on the `backup_encryption_passphrase` ahg_setting (group=backup). With no passphrase set the file is pushed as-is and the command emits a loud warning.

## Driver selection

The active driver comes from `config('backup.offsite.driver')`, which the package's own `config/backup.php` defaults to `localfs` so a stock install never accidentally tries to reach the network. Operators override via env:

```
BACKUP_OFFSITE_DRIVER=s3
BACKUP_OFFSITE_S3_BUCKET=heratio-backups-prod
BACKUP_OFFSITE_S3_REGION=eu-west-1
BACKUP_OFFSITE_S3_KEY=...
BACKUP_OFFSITE_S3_SECRET=...
BACKUP_OFFSITE_S3_ENDPOINT=https://s3.eu-central-2.wasabisys.com   # Wasabi / B2 / MinIO / DO Spaces
BACKUP_OFFSITE_S3_PATH_STYLE=true                                   # most non-AWS providers want this
```

For rsync:

```
BACKUP_OFFSITE_DRIVER=rsync
BACKUP_OFFSITE_RSYNC_HOST=offsite.example.org
BACKUP_OFFSITE_RSYNC_USER=heratio-backup
BACKUP_OFFSITE_RSYNC_PATH=/srv/heratio-backups/$(hostname -s)
BACKUP_OFFSITE_RSYNC_SSH_KEY=/root/.ssh/heratio_offsite_ed25519
```

For testing only:

```
BACKUP_OFFSITE_DRIVER=localfs
BACKUP_OFFSITE_LOCALFS_PATH=/var/lib/heratio-backups-offsite
```

The localfs driver always prints `Using localfs off-site driver - this is TEST ONLY and provides no DR protection.` on every run as a reminder.

## Host dependencies

- `gpg` (gnupg) must be on PATH when `backup_encryption_passphrase` is set. The command refuses to fall back to unencrypted in that case - it errors out so the operator notices that the gpg binary is missing rather than silently shipping plaintext to S3.
- `aws/aws-sdk-php` (composer) is required ONLY when the s3 driver is selected. Heratio does not pull the SDK by default - it's ~30MB of vendor and 99% of installs use rsync or do not replicate at all. `composer require aws/aws-sdk-php` lands it when needed. The driver throws a clear error if it is selected without the SDK installed.
- `rsync` and `ssh` binaries are required for the rsync driver.

## The replication ledger

`ahg_backup_replication` has one row per (local_path, driver) pair. The `backup:replicate` command skips any row whose status is `replicated` or `verified` unless `--force` is passed. Schema:

```
id              int PK
local_path      varchar(500)
remote_path     varchar(500)
driver          varchar(32)        -- s3 | rsync | localfs
size_bytes      bigint
sha256          char(64)
encrypted       tinyint(1)         -- was GPG applied before push?
replicated_at   datetime
verified_at     datetime NULL
status          varchar(24)        -- replicated | verified | failed
error           text NULL
UNIQUE (local_path, driver)
```

`backup:verify-integrity` reads this table, calls `driver->verify(remote_path, sha256)`, and updates status + verified_at + error in place. `--all` re-checks even rows already marked `verified`; `--from=YYYY-MM-DD` constrains by replicated_at.

## Verification semantics per driver

- **S3** prefers `headObject` metadata (`x-amz-meta-sha256` we set on push). Falls back to pulling the object to a temp file and rehashing when the metadata is gone (provider stripped it, or someone overwrote the object outside Heratio).
- **rsync** invokes `sha256sum` on the remote host over SSH. Falls back to pulling the file and hashing locally if the remote `sha256sum` is missing.
- **localfs** rehashes the file in place.

The fallback paths matter: without them the command would silently mark genuinely intact backups as failed any time a metadata-stripping CDN sat in front of S3.

## Encryption flow

When `backup_encryption_passphrase` is set, `OffsiteReplicator::encryptIfConfigured()` writes the passphrase to a 0600 tempfile and invokes:

```
gpg --batch --yes --symmetric --cipher-algo AES256 --passphrase-file <tmp> -o <local>.gpg <local>
```

The push then targets `<local>.gpg`. The tempfile is unlinked after the gpg run, and the `.gpg` file is unlinked after the driver finishes. The SHA-256 in the ledger is the hash of the encrypted payload, which is what `verify-integrity` re-checks against the remote.

This is wire-level encryption. The local copy in `storage/backups/` is NOT encrypted - that's deliberate, because the backup UI's download/restore paths assume plain tar.gz. Encryption-at-rest for the local copy lives in Phase 4 alongside the encryption-key rotation story.

## What is explicitly out of scope for Phase 3

- PITR (point-in-time recovery from binlog).
- Granular restore (single-table or single-file extraction).
- A written DR plan document.

Those three are tracked under #671 Phase 4. This doc and the matching `/help` article should be updated when Phase 4 lands so operators see one coherent flow rather than two half-documented ones.
