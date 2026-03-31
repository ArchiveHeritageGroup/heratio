# M0: Security Hardening — Technical Documentation

**Version:** 1.0.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd
**Framework Version:** 2.8.2
**Issue:** #198

---

## 1. Overview

Milestone 0 (M0) addresses three critical security vulnerabilities identified during a security audit of the Heratio framework:

1. **Unsafe PHP `unserialize()` calls** — 14 instances across 8 files with no class restriction, enabling potential PHP Object Injection (POI) attacks
2. **PHP serialization for data storage** — Getty vocabulary cache and semantic search embeddings used `serialize()`/`unserialize()`, exposing stored data to deserialization attacks
3. **API file upload with no validation** — path traversal via `type` parameter, no MIME validation, no extension allowlist, no file size limits

## 2. Vulnerability Details

### 2.1 PHP Object Injection via `unserialize()`

**Risk:** Critical
**CWE:** CWE-502 (Deserialization of Untrusted Data)

PHP's `unserialize()` without `['allowed_classes' => false]` can instantiate arbitrary objects. If an attacker controls the serialized string (e.g., via database injection or cookie manipulation), they can trigger magic methods (`__wakeup`, `__destruct`) on any class in the autoloader, potentially achieving Remote Code Execution (RCE).

**Affected Files (14 instances):**

| # | File | Line(s) | Context |
|---|------|---------|---------|
| 1 | `ahgUserManagePlugin/lib/Services/UserCrudService.php` | 427 | ACL permission constants |
| 2 | `ahgSettingsPlugin/.../pluginsAction.class.php` | 145 | Plugin list from `setting_i18n` |
| 3 | `ahgThemeB5Plugin/.../themesAction.class.php` | 48 | Enabled plugins (Propel) |
| 4 | `ahgThemeB5Plugin/.../themesAction.class.php` | 58 | Enabled plugins (Laravel QB) |
| 5 | `ahgThemeB5Plugin/.../themesAction.class.php` | 110 | Plugin settings on save |
| 6 | `ahgInformationObjectManagePlugin/.../InformationObjectCrudService.php` | 1100 | Serialized property array |
| 7 | `ahgSettingsPlugin/.../inventoryAction.class.php` | 64 | Inventory level settings |
| 8 | `ahgSettingsPlugin/.../oaiAction.class.php` | 37 | OAI plugin enabled check |
| 9–11 | `ahgMuseumPlugin/.../GettyCacheService.php` | 65, 204, 242 | Cache read fallback |
| 12–14 | `ahgSemanticSearchPlugin/.../EmbeddingService.php` | 258, 366, 423 | Embedding read fallback |

### 2.2 PHP Serialization for Data Storage

**Risk:** Medium
**CWE:** CWE-502

Two subsystems used `serialize()`/`unserialize()` for data persistence:

- **Getty Cache** (`GettyCacheService.php`): File-based cache for Getty vocabulary API responses stored as PHP serialized data
- **Semantic Search Embeddings** (`EmbeddingService.php`): Vector embeddings stored as PHP serialized arrays in the `ahg_thesaurus_embedding` database table

PHP serialization is unnecessary for these data types (arrays/scalars only) and introduces deserialization risk. JSON is a safer, more portable, and more compact alternative.

### 2.3 API File Upload Vulnerabilities

**Risk:** Critical
**CWE:** CWE-22 (Path Traversal), CWE-434 (Unrestricted File Upload)

The `apiv2FileUploadAction` had multiple vulnerabilities:

| Vulnerability | Detail |
|---------------|--------|
| **Path traversal** | `type` parameter passed directly to directory path: `$uploadDir = sf_upload_dir . '/' . $type . '/' . date(...)`. Attacker could send `type=../../../etc` to write files anywhere |
| **No MIME validation** | Client-supplied `Content-Type` trusted without server-side verification via magic bytes |
| **No extension allowlist** | Any file extension accepted, including `.php`, `.sh`, `.exe` |
| **No size limit** | No maximum file size enforced |
| **Base64 no pre-check** | Base64 content decoded without estimating size first, enabling memory exhaustion |

## 3. Fixes Applied

### 3.1 `unserialize()` Hardening

All 14 instances now include `['allowed_classes' => false]`:

```php
// Before (vulnerable)
$data = unserialize($input);

// After (safe — only arrays/scalars deserialized, no objects)
$data = unserialize($input, ['allowed_classes' => false]);
```

This ensures PHP will never instantiate objects during deserialization, eliminating the POI attack vector entirely.

### 3.2 Serialization Format Migration (PHP → JSON)

**Getty Cache (`GettyCacheService.php`):**

- **Write path:** `serialize($data)` → `json_encode($data, JSON_UNESCAPED_UNICODE)`
- **Read path:** `json_decode()` first, fallback to `@unserialize(['allowed_classes' => false])` for legacy cache files
- Methods updated: `get()`, `set()`, `getStats()`, `prune()`
- Legacy cache files are read correctly and will be replaced with JSON on next write

**Semantic Search Embeddings (`EmbeddingService.php`):**

- **Write path:** `serialize($embedding)` → `json_encode($embedding)`
- **Read path:** `json_decode()` first, fallback to safe `@unserialize()` for legacy data
- Methods updated: `storeEmbedding()`, `getTermEmbedding()`, `findSimilarTerms()`, `findRelatedTerms()`
- Added null/type guards to skip corrupt embeddings gracefully

**Backward compatibility:** Both subsystems use a try-JSON-first, fallback-to-safe-unserialize pattern. Existing data reads correctly; new writes use JSON. Over time, all data migrates to JSON organically.

### 3.3 API File Upload Hardening

**Path traversal fix:**
```php
// Before
$type = $request->getParameter('type', 'general');

// After — basename strips directory components, regex strips special chars
$type = basename($request->getParameter('type', 'general'));
$type = preg_replace('/[^a-zA-Z0-9_-]/', '', $type);
```

**MIME validation:** Post-save `finfo_file()` check using magic bytes. If MIME is disallowed, the file is deleted immediately:
```php
$mimeCheck = FileValidationService::validateMime($filepath);
if (!$mimeCheck['valid']) {
    @unlink($filepath);
    return ['error' => true, 'reasons' => $mimeCheck['errors']];
}
```

**Extension allowlist:** 48 safe extensions covering images, documents, audio, video, archives, 3D models, and archival formats. Configurable via `ahg_settings` key `file_allowed_extensions`.

**Size limits:** Default 100 MB, configurable via `ahg_settings` key `file_max_upload_mb`. For base64 uploads, size is estimated before decoding to prevent memory exhaustion.

**Strict base64 decoding:** Uses `base64_decode($str, true)` which returns `false` on invalid input.

### 3.4 FileValidationService (New)

**Location:** `atom-framework/src/Services/FileValidationService.php`
**Namespace:** `AtomExtensions\Services`

Centralized file validation service for use across all plugins:

| Method | Purpose |
|--------|---------|
| `validateUpload(array $file, array $options): array` | Full upload validation: extension + MIME + size |
| `validateMime(string $filePath, ?string $claimedMime): array` | Magic-byte MIME detection via `finfo` |
| `sanitizeFilename(string $filename): string` | Strip path traversal, null bytes, dangerous chars |
| `getAllowedExtensions(): array` | From `ahg_settings` or 48-extension default list |
| `getMaxSize(): int` | From `ahg_settings` or 100 MB default |
| `validateBase64Size(string $base64, ?int $maxSize): array` | Estimate decoded size before decoding |

All methods are `static` for easy use without instantiation.

**Configuration via `ahg_settings`:**

| Setting Key | Type | Default | Description |
|-------------|------|---------|-------------|
| `file_allowed_extensions` | string (comma-separated) | 48 built-in extensions | Custom extension allowlist |
| `file_max_upload_mb` | integer | 100 | Maximum upload size in MB |

## 4. Files Changed

### atom-framework (1 new file)
| File | Change |
|------|--------|
| `src/Services/FileValidationService.php` | **NEW** — Centralized file validation |

### atom-ahg-plugins (8 files modified)
| File | Change |
|------|--------|
| `ahgUserManagePlugin/lib/Services/UserCrudService.php` | `unserialize` hardened |
| `ahgSettingsPlugin/.../pluginsAction.class.php` | `unserialize` hardened |
| `ahgSettingsPlugin/.../inventoryAction.class.php` | `unserialize` hardened |
| `ahgSettingsPlugin/.../oaiAction.class.php` | `unserialize` hardened |
| `ahgThemeB5Plugin/.../themesAction.class.php` | `unserialize` hardened (3 instances) |
| `ahgInformationObjectManagePlugin/.../InformationObjectCrudService.php` | `unserialize` hardened |
| `ahgMuseumPlugin/lib/Services/Getty/GettyCacheService.php` | Converted to JSON + safe fallback |
| `ahgSemanticSearchPlugin/lib/Services/EmbeddingService.php` | Converted to JSON + safe fallback |
| `ahgAPIPlugin/.../fileUploadAction.class.php` | Full security hardening |

## 5. Verification

### Automated Checks

```bash
# Verify no unprotected unserialize remains
grep -rn "unserialize(" atom-ahg-plugins/ --include="*.php" | grep -v "allowed_classes" | grep -v "json_decode"
# Expected: zero results (or multi-line false positives where allowed_classes is on next line)

# PHP syntax validation on all changed files
php -l <file>  # for each file above
```

### Manual Test Cases

| # | Test | Expected Result |
|---|------|-----------------|
| 1 | API upload with `type=../../../etc` | 400 — type sanitized to `etc`, no path traversal |
| 2 | API upload of `shell.php` | 400 — extension `php` not in allowlist |
| 3 | API upload of shell script renamed to `.jpg` | 400 — MIME mismatch (`text/x-shellscript` != `image/jpeg`) |
| 4 | API upload of valid JPEG | 201 — accepted, MIME confirmed `image/jpeg` |
| 5 | API base64 upload exceeding 100 MB | 400 — size limit exceeded (checked before decode) |
| 6 | Getty cache: read old serialized file | Parsed via fallback, returns correct data |
| 7 | Getty cache: write new entry | Written as JSON |
| 8 | Embedding: read old serialized record | Parsed via fallback, returns correct array |
| 9 | Embedding: store new embedding | Stored as JSON |
| 10 | Theme admin: plugin enable/disable | Works correctly with hardened unserialize |

## 6. Security References

- [CWE-502: Deserialization of Untrusted Data](https://cwe.mitre.org/data/definitions/502.html)
- [CWE-22: Path Traversal](https://cwe.mitre.org/data/definitions/22.html)
- [CWE-434: Unrestricted Upload of File with Dangerous Type](https://cwe.mitre.org/data/definitions/434.html)
- [OWASP: Insecure Deserialization](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/16-Testing_for_HTTP_Incoming_Requests)
- [PHP Manual: unserialize — allowed_classes](https://www.php.net/manual/en/function.unserialize.php)
