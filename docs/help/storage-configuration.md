> Heratio Help Center article. Category: Admin & Settings / Configuration.

# Storage Configuration

## Overview

Heratio uses a centralized storage configuration to manage all file paths for uploads, backups, condition photos, provenance documents, and other assets. All paths are defined in `config/heratio.php` and can be overridden via environment variables in `.env`.

---

## Central Configuration File

The primary configuration file is `config/heratio.php`. This file defines the base storage path and all subdirectory paths used throughout the application:

```php
// config/heratio.php
return [
    'storage_path' => env('HERATIO_STORAGE_PATH', base_path('uploads')),
    'uploads_path' => env('HERATIO_UPLOADS_PATH', base_path('uploads')),
    // ... additional path definitions
];
```

All Heratio packages reference these configuration values rather than hardcoding paths.

---

## Environment Variables

### HERATIO_STORAGE_PATH

The base directory for all Heratio file storage. Every other storage path is relative to this unless explicitly overridden.

| Setting | Description |
|---------|-------------|
| Variable | `HERATIO_STORAGE_PATH` |
| Default | `{app}/uploads` (the `uploads` directory within the Laravel application root) |
| Example | `/mnt/nas/heratio/archive` |

Set this in `.env`:
```
HERATIO_STORAGE_PATH=/mnt/nas/heratio/archive
```

### HERATIO_UPLOADS_PATH

The directory for digital object uploads. This path is compatible with the original upload structure, ensuring that existing digital objects remain accessible without migration.

| Setting | Description |
|---------|-------------|
| Variable | `HERATIO_UPLOADS_PATH` |
| Default | Same as `HERATIO_STORAGE_PATH` |
| Example | `/mnt/nas/heratio/archive/uploads` |

Set this in `.env`:
```
HERATIO_UPLOADS_PATH=/mnt/nas/heratio/archive/uploads
```

---

## Default Local Installation

For a standard local installation where all files are stored on the same server as the application:

```
# .env (local install - defaults are sufficient)
# HERATIO_STORAGE_PATH is not set, defaults to {app}/uploads
```

The default configuration creates the following structure inside the Laravel application directory:

```
/usr/share/nginx/heratio/
  uploads/
    r/
      NNN/
        NNN/
          digitalobject.jpg      # Digital object files
    condition_photos/             # Condition report photographs
    provenance/                   # Provenance supporting documents
    backups/                      # Database and configuration backups
    exports/                      # Generated export files (CSV, EAD, etc.)
    imports/                      # Uploaded import files awaiting processing
    tmp/                          # Temporary processing files
```

---

## NAS / Shared Storage

For production deployments where storage is on a NAS or shared filesystem:

```
# .env (NAS storage)
HERATIO_STORAGE_PATH=/mnt/nas/heratio/archive
HERATIO_UPLOADS_PATH=/mnt/nas/heratio/archive/uploads
```

### Requirements for NAS Storage

- The NAS mount must be available before the application starts
- The web server user (`www-data`) must have read/write permissions on the mount
- Ensure the mount is configured in `/etc/fstab` for automatic mounting at boot
- Network latency to the NAS should be minimal (local network recommended)

### Permissions

Set appropriate ownership and permissions:

```bash
chown -R www-data:www-data /mnt/nas/heratio/archive
chmod -R 775 /mnt/nas/heratio/archive
```

---

## Directory Structure

The full directory structure under the storage path:

| Directory | Purpose |
|-----------|---------|
| `uploads/` | Digital object files (original upload structure) |
| `uploads/r/NNN/NNN/` | Digital objects organized by object ID |
| `backups/` | Database dumps and configuration backups |
| `uploads/condition_photos/` | Photographs attached to condition reports |
| `uploads/provenance/` | Supporting documents for provenance records |
| `exports/` | Generated export files (CSV, EAD, EAC-CPF, etc.) |
| `imports/` | Files uploaded for import processing |
| `tmp/` | Temporary files created during processing (auto-cleaned) |
| `logs/` | Application-specific log files |

---

## Verifying Configuration

To verify your storage configuration is correct:

```bash
# Check current configuration values
php artisan tinker --execute="echo config('heratio.storage_path');"
php artisan tinker --execute="echo config('heratio.uploads_path');"

# Verify directory exists and is writable
ls -la /mnt/nas/heratio/archive/
touch /mnt/nas/heratio/archive/test_write && rm /mnt/nas/heratio/archive/test_write
```

---

## Migration from Previous Installation

When migrating from an existing installation:

1. Copy the entire `uploads/` directory to the new storage path
2. Update `HERATIO_STORAGE_PATH` and `HERATIO_UPLOADS_PATH` in `.env`
3. Verify file permissions
4. Test that digital objects display correctly in the web interface
5. Run `php artisan config:clear` to apply the new configuration

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Digital objects not displaying | Verify `HERATIO_UPLOADS_PATH` points to the correct directory |
| Permission denied errors | Check ownership and permissions on storage directories |
| Condition photos not saving | Ensure `uploads/condition_photos/` exists and is writable |
| NAS mount not available | Check `/etc/fstab` and `mount` output |
| Disk space warnings | Monitor storage usage, consider archiving old exports and backups |

---

*Part of the Heratio AHG Framework*
