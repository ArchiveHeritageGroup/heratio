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

];
