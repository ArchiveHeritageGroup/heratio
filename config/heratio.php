<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Base Path
    |--------------------------------------------------------------------------
    |
    | Root directory for all Heratio file storage. By default this is local
    | under the application directory (like AtoM's /atom/uploads). For shared
    | or NAS storage, set HERATIO_STORAGE_PATH in .env.
    |
    | Structure:
    |   {storage_path}/
    |   ├── uploads/              ← AtoM-compatible digital object uploads
    |   ├── uploads/condition_photos/
    |   ├── uploads/provenance/
    |   ├── uploads/loans/
    |   ├── uploads/3d/
    |   ├── uploads/watermarks/
    |   └── backups/
    |
    */

    'storage_path' => env('HERATIO_STORAGE_PATH', base_path('uploads')),

    // Uploads path — independently configurable for servers where the subdir
    // name differs (e.g. "archive" on the AHG NAS vs "uploads" on a fresh install).
    'uploads_path' => env('HERATIO_UPLOADS_PATH', env('HERATIO_STORAGE_PATH', base_path('uploads'))),

    // Backups path — independently configurable.
    'backups_path' => env('HERATIO_BACKUPS_PATH', env('HERATIO_STORAGE_PATH', base_path('uploads')) . '/backups'),

    // OAIS package storage — where SIP/AIP/DIP bundles are assembled and
    // exported to. Sessions can override via ingest_session.output_*_path.
    'packages_path' => env('HERATIO_PACKAGES_PATH', env('HERATIO_STORAGE_PATH', base_path('uploads')) . '/packages'),

    /*
    |--------------------------------------------------------------------------
    | Scanner / capture pipeline (ahg-scan)
    |--------------------------------------------------------------------------
    |
    | Watched folders (and the scan API) stage files here while the ingest
    | pipeline processes them. Successful files are moved to archive_path;
    | failures to quarantine_path.
    */
    'scan' => [
        'staging_path' => env('HERATIO_SCAN_STAGING', env('HERATIO_STORAGE_PATH', base_path('uploads')) . '/.scan_staging'),
        'quarantine_path' => env('HERATIO_SCAN_QUARANTINE', env('HERATIO_STORAGE_PATH', base_path('uploads')) . '/.scan_quarantine'),
        'archive_path' => env('HERATIO_SCAN_ARCHIVE', env('HERATIO_STORAGE_PATH', base_path('uploads')) . '/.scan_archived'),
        'min_quiet_seconds' => (int) env('HERATIO_SCAN_MIN_QUIET', 10),
        'max_attempts' => (int) env('HERATIO_SCAN_MAX_ATTEMPTS', 5),
        // Exponential backoff ladder (minutes per attempt); comma-separated.
        // Default: 15 min → 1 h → 4 h → 24 h → 72 h.
        'retry_backoff_minutes' => env('HERATIO_SCAN_RETRY_BACKOFF', '15,60,240,1440,4320'),
    ],
];
