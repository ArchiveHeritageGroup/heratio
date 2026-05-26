<?php

/**
 * config/backup.php - off-site replication settings for ahg-backup
 *
 * Issue #671 Phase 3. Merged via AhgBackupServiceProvider::register().
 * Operators override via the matching BACKUP_OFFSITE_* env vars; never
 * commit live credentials to this file.
 */

return [
    /*
    |---------------------------------------------------------------------
    | Off-site replication
    |---------------------------------------------------------------------
    |
    | Which driver `backup:replicate` should use when pushing local
    | backups off-box. One of: s3, rsync, localfs. The `localfs` driver
    | is for testing only - see LocalFsOffsiteDriver docblock.
    |
    */
    'offsite' => [
        'driver' => env('BACKUP_OFFSITE_DRIVER', 'localfs'),

        // S3-compatible (AWS S3, Wasabi, B2, MinIO, DO Spaces, ...).
        // Operator MUST `composer require aws/aws-sdk-php` when selecting
        // this driver - we don't pull the ~30MB SDK as a hard dep.
        's3' => [
            'bucket'                  => env('BACKUP_OFFSITE_S3_BUCKET'),
            'region'                  => env('BACKUP_OFFSITE_S3_REGION', 'us-east-1'),
            'endpoint'                => env('BACKUP_OFFSITE_S3_ENDPOINT'),
            'key'                     => env('BACKUP_OFFSITE_S3_KEY'),
            'secret'                  => env('BACKUP_OFFSITE_S3_SECRET'),
            'prefix'                  => env('BACKUP_OFFSITE_S3_PREFIX', 'heratio-backups'),
            'use_path_style_endpoint' => (bool) env('BACKUP_OFFSITE_S3_PATH_STYLE', false),
        ],

        // rsync over SSH. Requires the `rsync` and `ssh` binaries on
        // PATH. SSH key auth strongly preferred; password auth is not
        // supported by this driver.
        'rsync' => [
            'host'        => env('BACKUP_OFFSITE_RSYNC_HOST'),
            'user'        => env('BACKUP_OFFSITE_RSYNC_USER'),
            'port'        => (int) env('BACKUP_OFFSITE_RSYNC_PORT', 22),
            'remote_path' => env('BACKUP_OFFSITE_RSYNC_PATH'),
            'ssh_key'     => env('BACKUP_OFFSITE_RSYNC_SSH_KEY'),
            'extra_args'  => env('BACKUP_OFFSITE_RSYNC_EXTRA', ''),
        ],

        // Test/sandbox only. Copies the file to another path on the
        // same host. Provides ZERO disaster-recovery value - the whole
        // point of "off-site" is "if the host dies, the backup
        // survives". Keep here for CI + dry-runs.
        'localfs' => [
            'path' => env('BACKUP_OFFSITE_LOCALFS_PATH', storage_path('backups-offsite')),
        ],
    ],
];
