> Heratio Help Center article. Category: Reference.

# Importing Digital Objects via CSV (Command Line)

## Overview

Heratio supports importing digital objects alongside archival descriptions using the CSV import command. Digital objects can be referenced as **external URLs** or **local server file paths** in your CSV file.

## The Import Command

Heratio imports archival descriptions (with digital objects) from CSV using the
`ahg:csv-import` artisan command:

```bash
php artisan ahg:csv-import /path/to/your/import.csv --source-name="My Import"
```

Run it as the web user so it does not leave root-owned cache/log files:

```bash
sudo -u www-data php artisan ahg:csv-import /path/to/your/import.csv --source-name="My Import"
```

## CSV Columns for Digital Objects

| CSV Column | Use Case |
|---|---|
| `digitalObjectURI` | External URL — Heratio downloads the file from a remote server |
| `digitalObjectPath` | Local file path on the **server** filesystem |

**Important:** Only **one digital object per row** can be imported via CSV. If both `digitalObjectURI` and `digitalObjectPath` are provided for the same row, `digitalObjectURI` takes priority.

## Where Must Digital Objects Be Located?

### External URLs (`digitalObjectURI`)

Digital objects can be hosted on any publicly accessible web server. Heratio will download the file automatically (with up to 3 retry attempts). If the download fails, the archival description is still created — just without the digital object.

### Local Paths (`digitalObjectPath`)

**Server filesystem only.** The path must point to a file on the same server where Heratio is installed, and it must be readable by the web server process (typically `www-data` on Ubuntu/Debian).

You **cannot** use paths from your local desktop or laptop. Files must first be transferred to the server.

### Recommended Server Directory for Import Files

Create a dedicated staging directory on the server:

```bash
sudo mkdir -p /usr/share/nginx/heratio/uploads/imports
sudo chown www-data:www-data /usr/share/nginx/heratio/uploads/imports
```

## Transferring Files to the Server (Linux)

Since `digitalObjectPath` requires server-side files, you need to upload them first. Common methods:

```bash
# SCP — simple copy from local machine to server
scp -r /local/path/to/images/ user@server:/usr/share/nginx/heratio/uploads/imports/

# Rsync — preferred for large batches (resumable, shows progress)
rsync -avz --progress /local/path/to/images/ user@server:/usr/share/nginx/heratio/uploads/imports/

# SFTP — interactive session
sftp user@server
put -r /local/path/to/images/ /usr/share/nginx/heratio/uploads/imports/
```

After uploading, set correct ownership and permissions on the server:

```bash
sudo chown -R www-data:www-data /usr/share/nginx/heratio/uploads/imports/
sudo chmod -R 644 /usr/share/nginx/heratio/uploads/imports/*
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
1,ACC-001,"Photograph of City Hall",Item,/usr/share/nginx/heratio/uploads/imports/cityhall.jpg,Published
2,ACC-002,"Map of Region",Item,/usr/share/nginx/heratio/uploads/imports/region_1950.tiff,Published
3,ACC-003,"Interview Recording",Item,/usr/share/nginx/heratio/uploads/imports/interview.mp3,Published
```

## Validate Before Importing

Run a validation-only pass first to catch malformed rows and missing required
columns before anything is written:

```bash
php artisan sector:archives-csv-import /path/to/your/import.csv --validate-only
```

This checks the CSV structure and required columns without creating records.
Before the real import, confirm each local `digitalObjectPath` you reference
actually exists on the server (for example with `ls`) - a missing local file
leaves the description created but without its digital object.

## Useful CLI Options

| Option | Purpose |
|---|---|
| `--source-name="label"` | Tag the import batch for easy identification later |
| `--update=overwrite` | Update strategy for existing records (values: `match`, `overwrite`, `skip`; default `skip`) |
| `--skip-matched` | Skip rows whose record already exists |
| `--limit=N` | Import at most N records |
| `--default-legacy-parent-id=ID` | Parent ID for orphan rows with no matched parent |
| `--index` | Rebuild the search index after the import |

## Complete Import Workflow (Linux)

```bash
# 1. Upload files to server (from your local machine)
rsync -avz --progress ./images/ user@server:/usr/share/nginx/heratio/uploads/imports/

# 2. On the server — set correct permissions
sudo chown -R www-data:www-data /usr/share/nginx/heratio/uploads/imports/

# 3. Validate the CSV (no records written)
php artisan sector:archives-csv-import /home/user/import.csv --validate-only

# 4. Run the import
php artisan ahg:csv-import /home/user/import.csv \
  --source-name="Batch Import March 2026"

# 5. Rebuild search index after import
php artisan ahg:search-populate
```

## Key Notes

- Only **one digital object per row** — CSV cannot attach multiple files to a single description
- External URLs are downloaded by Heratio with automatic retry (up to 3 attempts)
- If a URL download fails, the description record is still created — just without the digital object attached
- Supported file types include images (JPEG, TIFF, PNG), PDFs, audio (MP3, WAV), video (MP4), and 3D models
- After importing, run `php artisan ahg:search-populate` to ensure new records appear in search results
