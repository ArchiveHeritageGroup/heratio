<?php

/**
 * OCFL configuration.
 *
 * Override via env() - never edit this file in deployment.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

return [
    // Laravel filesystem disk that backs the OCFL storage root.
    // Operator wires this disk in config/filesystems.php (local / S3 / Wasabi).
    'disk' => env('OCFL_DISK', 'ocfl'),

    // Whether to auto-run `ocfl:init` on service-provider boot if the
    // storage root namaste declaration is missing. Off by default - real
    // deployments should run the command explicitly.
    'auto_init' => env('OCFL_AUTO_INIT', false),

    // Default digest algorithm for new objects. OCFL v1.1 §6.1 allows
    // sha512 (recommended) or sha256.
    'digest_algorithm' => env('OCFL_DIGEST_ALGORITHM', 'sha512'),

    // Object-root layout: 'flat-id' (default, suitable for < ~10k objects),
    // 'pairtree' (two-char pairs), or 'hashed-n-tuple' (sha256-based 3x3).
    'storage_layout' => env('OCFL_STORAGE_LAYOUT', 'flat-id'),

    // Where `ocfl:export` writes tarballs. Resolved relative to base_path()
    // unless an absolute path is supplied.
    'export_path' => env('OCFL_EXPORT_PATH', 'storage/ocfl-exports'),

    // Captured into the inventory.json "user" block when no auth context
    // is available (e.g. artisan, queue worker).
    'cli_user_name'    => env('OCFL_CLI_USER_NAME', 'cli'),
    'cli_user_address' => env('OCFL_CLI_USER_ADDRESS', null),
];
