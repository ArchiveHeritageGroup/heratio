# Importing Digital Objects via CSV (Command Line)

## Overview

AtoM supports importing digital objects alongside archival descriptions using the CSV import command. Digital objects can be referenced as **external URLs** or **local server file paths** in your CSV file.

## The Import Command

### Standard AtoM (native Symfony CLI)

```bash
php symfony import:csv /path/to/your/import.csv --source-name="My Import"
```

### Heratio (Laravel framework CLI)

```bash
php bin/atom import:csv /path/to/your/import.csv --source-name="My Import"
```

Both commands accept the same options and CSV format. Use whichever matches your installation.

**Official AtoM documentation:** [CLI import/export](https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/cli-import-export/#digital-object-load-task)

**See also:**
- [CSV import](https://www.accesstomemory.org/en/docs/2.10/user-manual/import-export/csv-import/#csv-import)
- [Upload digital objects](https://www.accesstomemory.org/en/docs/2.10/user-manual/import-export/upload-digital-object/#upload-digital-objects)
- [Digital object storage](https://www.accesstomemory.org/en/docs/2.10/user-manual/administer/manage-digital-object-storage/#manage-digital-object-storage)

## CSV Columns for Digital Objects

| CSV Column | Use Case |
|---|---|
| `digitalObjectURI` | External URL — AtoM downloads the file from a remote server |
| `digitalObjectPath` | Local file path on the **server** filesystem |
| `digitalObjectChecksum` | Optional — SHA256/MD5 checksum to skip unchanged files on re-import |

**Important:** Only **one digital object per row** can be imported via CSV. If both `digitalObjectURI` and `digitalObjectPath` are provided for the same row, `digitalObjectURI` takes priority.

## Where Must Digital Objects Be Located?

### External URLs (`digitalObjectURI`)

Digital objects can be hosted on any publicly accessible web server. AtoM will download the file automatically (with up to 3 retry attempts). If the download fails, the archival description is still created — just without the digital object.

### Local Paths (`digitalObjectPath`)

**Server filesystem only.** The path must point to a file on the same server where AtoM is installed, and it must be readable by the web server process (typically `www-data` on Ubuntu/Debian).

You **cannot** use paths from your local desktop or laptop. Files must first be transferred to the server.

### Recommended Server Directory for Import Files

Create a dedicated staging directory on the server:

```bash
sudo mkdir -p /usr/share/nginx/archive/uploads/imports
sudo chown www-data:www-data /usr/share/nginx/archive/uploads/imports
```

## Transferring Files to the Server (Linux)

Since `digitalObjectPath` requires server-side files, you need to upload them first. Common methods:

```bash
# SCP — simple copy from local machine to server
scp -r /local/path/to/images/ user@server:/usr/share/nginx/archive/uploads/imports/

# Rsync — preferred for large batches (resumable, shows progress)
rsync -avz --progress /local/path/to/images/ user@server:/usr/share/nginx/archive/uploads/imports/

# SFTP — interactive session
sftp user@server
put -r /local/path/to/images/ /usr/share/nginx/archive/uploads/imports/
```

After uploading, set correct ownership and permissions on the server:

```bash
sudo chown -R www-data:www-data /usr/share/nginx/archive/uploads/imports/
sudo chmod -R 644 /usr/share/nginx/archive/uploads/imports/*
```

## Sample CSV — External URLs

```csv
legacyId,identifier,title,levelOfDescription,digitalObjectURI,publicationStatus
1,ACC-001,"Photograph of City Hall",Item,https://example.com/images/cityhall.jpg,Published
2,ACC-002,"Map of Region",Item,https://cdn.example.org/maps/region_1950.tiff,Published
3,ACC-003,"Interview Recording",Item,https://media.example.com/audio/interview.mp3,Published
```

## Sample CSV — Local Server Paths

```csv
legacyId,identifier,title,levelOfDescription,digitalObjectPath,publicationStatus
1,ACC-001,"Photograph of City Hall",Item,/usr/share/nginx/archive/uploads/imports/cityhall.jpg,Published
2,ACC-002,"Map of Region",Item,/usr/share/nginx/archive/uploads/imports/region_1950.tiff,Published
3,ACC-003,"Interview Recording",Item,/usr/share/nginx/archive/uploads/imports/interview.mp3,Published
```

## Validate Paths Before Importing

Always validate your file paths before running the actual import to catch missing or mismatched files:

```bash
php bin/atom import:csv-digital-object-paths-check \
  /usr/share/nginx/archive/uploads/imports \
  /path/to/your/import.csv \
  --csv-column-name=digitalObjectPath
```

This reports:
- Files referenced in CSV but **missing** from the directory
- Files in the directory but **not referenced** in the CSV
- Files referenced **multiple times**

## Useful CLI Options

| Option | Purpose |
|---|---|
| `--skip-derivatives` | Skip thumbnail/reference image generation (faster import, but no previews until you regenerate them) |
| `--update="match-and-update"` | Update existing records instead of creating duplicates |
| `--keep-digital-objects` | When updating, keep existing digital objects instead of replacing them |
| `--source-name="label"` | Tag the import batch for easy identification later |

## Complete Import Workflow (Linux)

```bash
# 1. Upload files to server (from your local machine)
rsync -avz --progress ./images/ user@server:/usr/share/nginx/archive/uploads/imports/

# 2. On the server — set correct permissions
sudo chown -R www-data:www-data /usr/share/nginx/archive/uploads/imports/

# 3. Validate paths match the CSV
php bin/atom import:csv-digital-object-paths-check \
  /usr/share/nginx/archive/uploads/imports \
  /home/user/import.csv

# 4. Run the import
php bin/atom import:csv /home/user/import.csv \
  --source-name="Batch Import March 2026"

# 5. Rebuild search index after import
php bin/atom search:populate
```

## Key Notes

- Only **one digital object per row** — CSV cannot attach multiple files to a single description
- External URLs are downloaded by AtoM with automatic retry (up to 3 attempts)
- If a URL download fails, the description record is still created — just without the digital object attached
- The optional `digitalObjectChecksum` column lets AtoM skip re-importing unchanged files during update imports
- Supported file types include images (JPEG, TIFF, PNG), PDFs, audio (MP3, WAV), video (MP4), and 3D models
- After importing, run `php bin/atom search:populate` to ensure new records appear in search results
