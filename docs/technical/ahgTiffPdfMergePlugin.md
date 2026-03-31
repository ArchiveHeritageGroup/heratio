# ahgTiffPdfMergePlugin - Technical Documentation

**Version:** 1.0.0
**Category:** Preservation
**Dependencies:** atom-framework, ahgCorePlugin
**Status:** DEPRECATED - Merged into ahgPreservationPlugin

---

## Deprecation Notice

This plugin has been merged into **ahgPreservationPlugin**. The TIFF/PDF merge functionality is now part of the comprehensive preservation suite. No migration is needed - the database tables and routes remain the same.

To migrate:
1. Enable `ahgPreservationPlugin` instead of `ahgTiffPdfMergePlugin`
2. Disable the old plugin: `php bin/atom extension:disable ahgTiffPdfMergePlugin`
3. Remove symlink: `rm /usr/share/nginx/archive/plugins/ahgTiffPdfMergePlugin`

---

## Overview

The TIFF/PDF Merge feature enables users to batch upload multiple image files (TIFF, JPEG, PNG, BMP, GIF) and merge them into a single PDF or PDF/A document for archival purposes. The output can optionally be attached to an archival description as a digital object.

---

## Architecture

```
+-------------------------------------------------------------+
|                    TIFF/PDF Merge System                    |
+-------------------------------------------------------------+
|                                                             |
|  +-------------------------------------------------------+  |
|  |                  Web Interface Layer                  |  |
|  |  * Drag-and-drop file upload                         |  |
|  |  * Sortable file ordering                            |  |
|  |  * Real-time upload progress                         |  |
|  |  * Job management dashboard                          |  |
|  +-------------------------------------------------------+  |
|                           |                                 |
|                           v                                 |
|  +-------------------------------------------------------+  |
|  |              tiffpdfmergeActions Controller           |  |
|  |  * HTTP request handling                             |  |
|  |  * Authentication/authorization                      |  |
|  |  * JSON API responses                                |  |
|  +-------------------------------------------------------+  |
|                           |                                 |
|                           v                                 |
|  +-------------------------------------------------------+  |
|  |               TiffPdfMergeService                     |  |
|  |  * Job lifecycle management                          |  |
|  |  * File validation and storage                       |  |
|  |  * PDF generation coordination                       |  |
|  +-------------------------------------------------------+  |
|                           |                                 |
|        +------------------+------------------+               |
|        |                                     |               |
|        v                                     v               |
|  +------------------+            +----------------------+   |
|  | TiffPdfMergeJob  |            | TiffPdfMergeRepository|  |
|  | (Background)     |            | (Data Access)         |  |
|  | * ImageMagick    |            | * Laravel Query       |  |
|  | * Ghostscript    |            |   Builder             |  |
|  | * PDF/A creation |            | * CRUD operations     |  |
|  +------------------+            +----------------------+   |
|        |                                     |               |
|        v                                     v               |
|  +-------------------------------------------------------+  |
|  |                    Database Layer                     |  |
|  |  * tiff_pdf_merge_job                                |  |
|  |  * tiff_pdf_merge_file                               |  |
|  |  * tiff_pdf_settings                                 |  |
|  +-------------------------------------------------------+  |
|                                                             |
+-------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+-----------------------------------+
|       tiff_pdf_merge_job          |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
|                                   |
| -- Ownership --                   |
| FK user_id INT                    |
|    job_name VARCHAR(255)          |
| FK information_object_id INT      |
|                                   |
| -- Status --                      |
|    status ENUM                    |
|    error_message TEXT             |
|                                   |
| -- PDF Settings --                |
|    pdf_standard VARCHAR(20)       |
|    compression_quality INT        |
|    page_size VARCHAR(20)          |
|    orientation VARCHAR(20)        |
|    dpi INT                        |
|                                   |
| -- Options --                     |
|    preserve_originals TINYINT     |
|    attach_to_record TINYINT       |
|    options JSON                   |
|                                   |
| -- Output --                      |
|    output_filename VARCHAR(255)   |
|    output_path VARCHAR(1024)      |
| FK output_digital_object_id INT   |
|                                   |
| -- Counts --                      |
|    total_files INT                |
|    processed_files INT            |
|                                   |
| -- Timestamps --                  |
|    created_at DATETIME            |
|    updated_at DATETIME            |
|    completed_at DATETIME          |
|    notes TEXT                     |
+-----------------------------------+
         | 1
         |
         | M
         v
+-----------------------------------+
|       tiff_pdf_merge_file         |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
| FK merge_job_id BIGINT            |
|                                   |
| -- File Info --                   |
|    original_filename VARCHAR(255) |
|    stored_filename VARCHAR(255)   |
|    file_path VARCHAR(1024)        |
|    file_size BIGINT               |
|    mime_type VARCHAR(100)         |
|                                   |
| -- Image Properties --            |
|    width INT                      |
|    height INT                     |
|    bit_depth INT                  |
|    color_space VARCHAR(50)        |
|                                   |
| -- Ordering --                    |
|    page_order INT                 |
|    status VARCHAR(50)             |
|                                   |
| -- Integrity --                   |
|    checksum_md5 VARCHAR(32)       |
|    metadata JSON                  |
|                                   |
| -- Timestamps --                  |
|    created_at DATETIME            |
+-----------------------------------+

+-----------------------------------+
|       tiff_pdf_settings           |
+-----------------------------------+
| PK id BIGINT UNSIGNED             |
|    setting_key VARCHAR(100)       |
|    setting_value TEXT             |
|    setting_type VARCHAR(20)       |
|    description TEXT               |
|    created_at DATETIME            |
|    updated_at DATETIME            |
+-----------------------------------+
```

### Job Status Flow

```
pending --> queued --> processing --> completed
                |                        |
                +-----> failed <---------+
```

| Status | Description |
|--------|-------------|
| pending | Job created, accepting file uploads |
| queued | Files uploaded, waiting for background worker |
| processing | ImageMagick/Ghostscript conversion in progress |
| completed | PDF created successfully |
| failed | Error occurred during processing |

---

## Routes

### Web Routes

| Route | URL | Method | Description |
|-------|-----|--------|-------------|
| tiffpdfmerge | /tiff-pdf-merge | GET | Main upload interface |
| tiffpdfmerge_with_object | /tiff-pdf-merge/:informationObject | GET | Upload with record context |
| tiffpdfmerge_browse | /tiff-pdf-merge/jobs | GET | Job management dashboard |
| tiffpdfmerge_view | /tiff-pdf-merge/job/:job_id/view | GET | Single job detail view |

### API Routes

| Route | URL | Method | Description |
|-------|-----|--------|-------------|
| tiffpdfmerge_create | /tiff-pdf-merge/create | POST | Create new merge job |
| tiffpdfmerge_upload | /tiff-pdf-merge/upload | POST | Upload file(s) to job |
| tiffpdfmerge_reorder | /tiff-pdf-merge/reorder | POST | Reorder files in job |
| tiffpdfmerge_remove_file | /tiff-pdf-merge/remove-file | POST | Remove file from job |
| tiffpdfmerge_get_job | /tiff-pdf-merge/job/:job_id | GET | Get job details + files |
| tiffpdfmerge_process | /tiff-pdf-merge/process | POST | Queue job for processing |
| tiffpdfmerge_download | /tiff-pdf-merge/download/:job_id | GET | Download output PDF |
| tiffpdfmerge_delete | /tiff-pdf-merge/delete | POST | Delete job and files |

---

## Service Methods

### TiffPdfMergeService

```php
namespace AtomFramework\Services;

class TiffPdfMergeService
{
    // Job Management
    public function createJob(int $userId, string $jobName, ?int $informationObjectId = null, array $options = []): int
    public function processJob(int $jobId): array
    public function getOutputPath(int $jobId): ?string
    public function cleanupJob(int $jobId): bool

    // File Management
    public function uploadFile(int $jobId, array $uploadedFile): array

    // Conversion Methods (protected)
    protected function convertToPdf($files, string $outputPath, object $job): array
    protected function convertToPdfA($files, string $outputPath, object $job): array
    protected function createPdfaDefinition(string $level): string

    // Digital Object Integration
    protected function attachToRecord(int $informationObjectId, string $filePath, string $filename, int $userId): ?int
    protected function generateDerivatives(int $digitalObjectId, string $masterPath): void

    // Utilities
    protected function validateFile(array $file): array
    protected function getImageInfo(string $filePath): array
    protected function getJobDirectory(int $jobId): string
    protected function sanitizeFilename(string $filename): string
}
```

### TiffPdfMergeRepository

```php
namespace AtomFramework\Repositories;

class TiffPdfMergeRepository
{
    // Settings
    public function getSettings(): array
    public function getSetting(string $key, mixed $default = null): mixed

    // Job CRUD
    public function createJob(array $data): int
    public function getJob(int $jobId): ?object
    public function getJobs(array $filters = [], int $limit = 50, int $offset = 0): Collection
    public function updateJobStatus(int $jobId, string $status, ?string $error = null): bool
    public function updateJobOutput(int $jobId, array $data): bool
    public function deleteJob(int $jobId): bool

    // File CRUD
    public function addFile(int $jobId, array $fileData): int
    public function getJobFiles(int $jobId): Collection
    public function updateFileOrder(int $jobId, array $fileOrder): bool
    public function updateFileStatus(int $fileId, string $status): bool
    public function deleteFile(int $fileId): ?int

    // Queries
    public function getPendingJobs(int $userId, int $limit = 10): Collection
    public function getStatistics(?int $userId = null): array
    public function getMaxPageOrder(int $jobId): int
}
```

---

## Background Job Processing

### TiffPdfMergeJob Class

The `TiffPdfMergeJob` class handles asynchronous PDF generation using ImageMagick and Ghostscript.

```php
namespace AtomFramework\Jobs;

class TiffPdfMergeJob
{
    public function __construct(int $mergeJobId)
    public function handle(): bool

    protected function convertToPdf($files, $outputPath, $job): array
    protected function convertToPdfA($files, $outputPath, $job): array
    protected function attachToRecord($informationObjectId, $filePath, $filename): ?int
    protected function generateDerivatives($digitalObjectId, $masterPath, $slugValue): void
}
```

### CLI Command

```php
namespace AtomFramework\Commands;

class TiffPdfProcessCommand
{
    public function processPending(int $limit = 10): array
    public function processJob(int $jobId): array
    public function cleanup(int $hoursOld = 24): int
    public function stats(): array
}
```

---

## PDF Standards Supported

| Standard | Description | Use Case |
|----------|-------------|----------|
| pdf | Standard PDF 1.4+ | General distribution |
| pdfa-1b | PDF/A-1b (ISO 19005-1) | Long-term archival (basic) |
| pdfa-2b | PDF/A-2b (ISO 19005-2) | Long-term archival (recommended) |
| pdfa-3b | PDF/A-3b (ISO 19005-3) | Archival with embedded files |

### Conversion Pipeline

**Standard PDF:**
```
Input Images --> ImageMagick convert --> PDF Output
```

**PDF/A:**
```
Input Images --> ImageMagick convert --> Temp PDF --> Ghostscript --> PDF/A Output
```

---

## Supported Input Formats

| Format | Extensions | Notes |
|--------|------------|-------|
| TIFF | .tif, .tiff | Multi-page supported |
| JPEG | .jpg, .jpeg | Lossy compression |
| PNG | .png | Lossless with transparency |
| BMP | .bmp | Windows bitmap |
| GIF | .gif | Single frame only |
| WebP | .webp | Modern web format |

---

## Configuration Settings

| Setting Key | Type | Default | Description |
|-------------|------|---------|-------------|
| imagemagick_path | string | /usr/bin/convert | Path to ImageMagick convert binary |
| ghostscript_path | string | /usr/bin/gs | Path to Ghostscript binary |
| temp_directory | string | /tmp/tiff-pdf-merge | Temporary file storage |
| max_file_size_mb | integer | 500 | Maximum upload size per file |
| default_pdf_standard | string | pdfa-2b | Default output format |
| default_quality | integer | 85 | Default JPEG quality (1-100) |
| default_dpi | integer | 300 | Default output resolution |
| allowed_extensions | json | ["tif","tiff","jpg",...] | Allowed input formats |

---

## Digital Object Integration

When a job has `attach_to_record` enabled and an `information_object_id`, the completed PDF is:

1. Copied to the AtoM uploads directory (`uploads/r/{slug}/`)
2. Registered as a digital object with `usage_id = 142` (Master)
3. Thumbnail derivative generated (100x100px JPEG)
4. Reference derivative generated (480x480px JPEG)

### Usage IDs

| ID | Constant | Purpose |
|----|----------|---------|
| 137 | THUMBNAIL_ID | Thumbnail image |
| 141 | REFERENCE_ID | Reference/preview image |
| 142 | MASTER_ID | Original/master file |

---

## API Response Formats

### Create Job Response
```json
{
    "success": true,
    "job_id": 123
}
```

### Upload Response
```json
{
    "success": true,
    "uploaded": 3,
    "results": [
        {
            "success": true,
            "file_id": 456,
            "filename": "page001.tif",
            "width": 2480,
            "height": 3508,
            "size": 4521984
        }
    ]
}
```

### Get Job Response
```json
{
    "success": true,
    "job": {
        "id": 123,
        "job_name": "Merged Document",
        "status": "pending",
        "pdf_standard": "pdfa-2b",
        "total_files": 3
    },
    "files": [
        {
            "id": 456,
            "original_filename": "page001.tif",
            "page_order": 0,
            "width": 2480,
            "height": 3508
        }
    ]
}
```

### Process Response
```json
{
    "success": true,
    "message": "Job queued for processing. Refresh this page to check status."
}
```

---

## Error Handling

| Error Code | Message | Resolution |
|------------|---------|------------|
| AUTH_REQUIRED | Authentication required | User must log in |
| JOB_NOT_FOUND | Job not found | Invalid job_id |
| INVALID_STATUS | Job already processed or processing | Cannot modify completed jobs |
| NO_FILES | No files to process | Upload files before processing |
| INVALID_TYPE | File type not allowed | Use supported image formats |
| SIZE_EXCEEDED | File exceeds maximum size limit | Reduce file size |
| CONVERT_FAILED | ImageMagick conversion failed | Check ImageMagick installation |
| PDFA_FAILED | Ghostscript PDF/A conversion failed | Falls back to standard PDF |

---

## System Requirements

| Component | Minimum Version | Purpose |
|-----------|-----------------|---------|
| PHP | 8.1+ | Runtime |
| ImageMagick | 6.9+ | Image to PDF conversion |
| Ghostscript | 9.50+ | PDF/A conversion |
| sRGB ICC Profile | - | PDF/A color management |

### ICC Profile Location

For PDF/A compliance, ensure the sRGB ICC profile is installed:
```
/usr/share/color/icc/colord/sRGB.icc
```

Ubuntu/Debian installation:
```bash
apt-get install icc-profiles-free
```

---

## File Storage

### Temporary Files

```
/tmp/tiff-pdf-merge/
  job_123/
    tiff_64a5b3c2d1e4f.tif    # Uploaded source file
    tiff_64a5b3c2d1e5g.jpg    # Uploaded source file
    Merged_Document.pdf        # Output PDF
```

### Permanent Storage (when attached)

```
{sf_upload_dir}/r/{slug}/
  Merged_Document.pdf          # Master PDF
  Merged_Document_thumb.jpg    # Thumbnail derivative
  Merged_Document_ref.jpg      # Reference derivative
```

---

## Security Considerations

1. **Authentication:** All routes require user authentication
2. **File Validation:** MIME type and extension validation before processing
3. **Path Sanitization:** Filenames sanitized to prevent directory traversal
4. **Command Injection:** All shell arguments escaped with `escapeshellarg()`
5. **Job Ownership:** Users can only access their own jobs (browse shows all for admins)

---

## Performance Considerations

| Scenario | Recommendation |
|----------|----------------|
| Large files (>100MB) | Increase PHP upload_max_filesize and post_max_size |
| Many pages (>100) | Process in batches, increase memory_limit |
| High DPI (600+) | Consider lowering for web use, keep high for print |
| Concurrent jobs | Use background worker, limit concurrent ImageMagick processes |

---

## Related Documentation

- User Guide: [pdf-merge-user-guide.md](../pdf-merge-user-guide.md)
- Parent Plugin: [ahgPreservationPlugin Technical Doc](./ahgPreservationPlugin.md)

---

*Part of the AtoM AHG Framework - Digital Preservation Suite*
