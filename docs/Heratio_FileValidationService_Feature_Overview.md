# Heratio - FileValidationService Feature Overview

**Version:** 1.0.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd
**Component:** atom-framework / FileValidationService

---

## What It Does

The FileValidationService provides centralized, secure file validation for the entire Heratio platform. It ensures that all file uploads - whether through the REST API, data ingest pipeline, or any plugin - are validated against a consistent set of security rules before being accepted into the system.

## Key Features

- **Extension Allowlist** - Only files with approved extensions (48 by default) are accepted. Dangerous file types such as `.php`, `.exe`, `.sh`, and `.bat` are rejected. The allowlist is configurable per deployment via the AHG Settings panel.

- **MIME Type Verification** - Every uploaded file is inspected using `finfo` (libmagic) to detect its true content type from magic bytes, regardless of what the client claims. A shell script renamed to `.jpg` will be detected and rejected.

- **MIME Cross-Check** - When a client provides a claimed MIME type, the service compares it against the detected MIME. Significant mismatches (e.g., claiming `image/jpeg` but detected as `text/x-shellscript`) are flagged as errors.

- **File Size Enforcement** - Configurable maximum file size (default 100 MB). For base64-encoded uploads, size is estimated before decoding to prevent memory exhaustion attacks.

- **Filename Sanitization** - Removes path traversal sequences (`../`), null bytes, hidden file prefixes (leading dots), and all characters outside a safe set (alphanumeric, dash, underscore, dot).

- **Base64 Pre-Validation** - For API consumers sending base64-encoded files, the service estimates decoded size from the encoded string length before allocating memory for decoding.

- **Settings Integration** - Extension allowlist and size limits are configurable through the AHG Settings panel (`Admin > AHG Settings`), allowing each deployment to customize validation rules without code changes.

## Supported File Types (Default)

| Category | Extensions |
|----------|-----------|
| Images | jpg, jpeg, png, gif, tif, tiff, bmp, webp, svg |
| Documents | pdf, doc, docx, xls, xlsx, ppt, pptx, odt, ods, odp, rtf, txt, csv |
| Audio | mp3, wav, ogg, flac, aac, m4a |
| Video | mp4, avi, mov, mkv, webm, wmv |
| Archives | zip, tar, gz, tgz |
| 3D Models | obj, gltf, glb, stl, fbx |
| Archival | xml, ead, json, marc, mrc |

## Standards and Compliance

- **OWASP Top 10** - Addresses A04:2021 (Insecure Design) and A08:2021 (Software and Data Integrity Failures)
- **CWE-434** - Unrestricted Upload of File with Dangerous Type
- **CWE-22** - Path Traversal prevention via filename sanitization
- **POPIA / GDPR** - Supports data protection requirements by preventing unauthorized file injection

## Technical Requirements

- PHP 8.1 or higher
- `fileinfo` PHP extension (standard in PHP 8.x)
- Heratio Framework v2.8.2+

## Configuration

| Setting | Location | Default |
|---------|----------|---------|
| Allowed extensions | `Admin > AHG Settings > file_allowed_extensions` | 48 built-in types |
| Max upload size | `Admin > AHG Settings > file_max_upload_mb` | 100 MB |
