# DOI Management - User Guide

## What is a DOI?

A **Digital Object Identifier (DOI)** is a persistent, unique identifier for digital content. DOIs are globally recognized and ensure that your archival records can be reliably cited and found, even if URLs change.

Example DOI: `10.12345/archive.2024.00001`

When someone visits `https://doi.org/10.12345/archive.2024.00001`, they are automatically redirected to your record.

---

## Why Use DOIs?

| Benefit | Description |
|---------|-------------|
| **Permanent Links** | URLs change, DOIs don't - citations remain valid forever |
| **Citability** | Researchers can properly cite your records in publications |
| **Discoverability** | DOIs are indexed by DataCite, Google Scholar, and academic databases |
| **Metrics** | Track how often your records are accessed via DOI resolution |
| **Standards Compliance** | Required by many funding agencies and journals |

---

## DOI States

```
+------------------------------------------------------------------+
|                        DOI LIFECYCLE                              |
|                                                                   |
|   +---------+      +------------+      +-----------+             |
|   |  DRAFT  | ---> | REGISTERED | ---> | FINDABLE  |             |
|   +---------+      +------------+      +-----------+             |
|       |                 |                    |                    |
|       |                 |                    |                    |
|   Reserved          DOI exists           DOI resolves            |
|   in DataCite       but hidden           and is public           |
|                                                                   |
|                          |                                        |
|                          v                                        |
|                    +-----------+                                  |
|                    |  DELETED  |  (Tombstone page shown)          |
|                    +-----------+                                  |
+------------------------------------------------------------------+
```

| State | Resolves? | In DataCite Search? | Use Case |
|-------|-----------|---------------------|----------|
| **Draft** | No | No | Reserve a DOI before publishing |
| **Registered** | Yes | No | DOI works but not publicly indexed |
| **Findable** | Yes | Yes | Fully public and discoverable |
| **Deleted** | Tombstone | No | Record removed, shows explanation |

---

## DOI Dashboard

Navigate to: **Admin -> DOI Management**

The dashboard shows:
- Total DOIs minted
- DOIs by status (Findable, Registered, Draft, Failed)
- Queue pending count
- Recently minted DOIs

### Quick Actions

| Button | Action |
|--------|--------|
| **Batch Mint** | Mint DOIs for multiple records at once |
| **Bulk Sync** | Update all DOI metadata in DataCite |
| **Export** | Download DOI list as CSV or JSON |
| **Configuration** | DataCite API settings |

---

## Minting a DOI

### Method 1: From Record Page

1. Navigate to any archival record
2. Look for the DOI badge in the header area
3. If no DOI exists, click **"Mint DOI"**
4. Confirm the action
5. DOI is minted and displayed

```
+------------------------------------------------------------+
|  Record: Annual Report 1952                                |
|                                                            |
|  +---------------------------------------------+           |
|  | DOI: 10.12345/archive.2024.00001           |           |
|  |    Status: Findable                         |           |
|  |    [View on DataCite] [Update Metadata]     |           |
|  +---------------------------------------------+           |
|                                                            |
|  OR (if no DOI):                                           |
|                                                            |
|  +---------------------------------------------+           |
|  | [Mint DOI]  (Admin only)                   |           |
|  +---------------------------------------------+           |
+------------------------------------------------------------+
```

### Method 2: Batch Minting

1. Go to **Admin -> DOI Management -> Batch Mint**
2. Select records by:
   - Repository
   - Level of description
   - Publication status
   - Date range
3. Preview selected records
4. Click **"Mint DOIs"**
5. Records are queued for minting

### Method 3: Auto-Mint (If Configured)

When enabled, DOIs are automatically minted when:
- A record is published
- Record meets configured criteria (level, repository, etc.)

---

## Managing DOIs

### View DOI Details

1. Go to **Admin -> DOI Management -> Browse**
2. Click on any DOI to see:
   - Full metadata
   - Resolution status
   - Minting history
   - DataCite sync status

### Update DOI Metadata

When record metadata changes, the DOI metadata in DataCite should be updated:

1. **Automatic:** Bulk Sync updates all DOIs
2. **Manual:** Click "Update" on individual DOI view

```bash
# CLI: Sync all DOI metadata
php symfony doi:sync --all

# CLI: Sync specific DOI
php symfony doi:sync --id=123
```

### Verify DOI Resolution

1. Go to DOI view page
2. Click **"Verify Resolution"**
3. System checks if DOI resolves correctly

---

## Deactivating DOIs

When a record is deleted or should no longer be accessible:

### Via Web Interface

1. Go to **Admin -> DOI Management -> Browse**
2. Find the DOI
3. Click **"Deactivate"**
4. Enter reason for deactivation
5. Confirm

### Via CLI

```bash
# Deactivate by DOI record ID
php symfony doi:deactivate --id=123 --reason="Record deleted"

# Deactivate by object ID
php symfony doi:deactivate --object-id=456 --reason="Duplicate record"

# List all deactivated DOIs
php symfony doi:deactivate --list-deleted
```

### What Happens?

- DOI state changes to "deleted" in DataCite
- DOI still resolves but shows a tombstone page
- Record remains in your database for audit
- Can be reactivated if needed

---

## Queue Management

DOI operations are processed via a queue to avoid API rate limits:

1. Go to **Admin -> DOI Management -> Queue**
2. View pending, processing, completed, and failed jobs
3. Retry failed jobs
4. Cancel pending jobs

### Queue Statuses

| Status | Meaning |
|--------|---------|
| **Pending** | Waiting to be processed |
| **Processing** | Currently being minted |
| **Completed** | Successfully minted |
| **Failed** | Error occurred (can retry) |

---

## Export DOI Data

### Via Web Interface

1. Go to DOI Dashboard
2. Click **Export** dropdown
3. Choose CSV or JSON format
4. File downloads automatically

### Export Columns

| Column | Description |
|--------|-------------|
| DOI | The full DOI string |
| Title | Record title |
| Status | Current DOI state |
| Minted Date | When DOI was created |
| URL | Target URL |
| Repository | Source repository |

---

## Reports

Navigate to: **Admin -> DOI Management -> Reports**

Available reports:
- DOIs minted per month
- DOIs by repository
- DOIs by status
- Failed minting attempts
- Queue processing times

---

## Configuration

Navigate to: **Admin -> DOI Management -> Configuration**

### Required Settings

| Setting | Description |
|---------|-------------|
| **DataCite Repository ID** | Your DataCite repository identifier |
| **DataCite Password** | API password from DataCite Fabrica |
| **DOI Prefix** | Your assigned prefix (e.g., 10.12345) |
| **Environment** | Test or Production |

### Optional Settings

| Setting | Description |
|---------|-------------|
| **Auto-Mint** | Automatically mint DOIs on publish |
| **Default State** | Initial DOI state (draft, registered, findable) |
| **URL Template** | Pattern for target URLs |
| **Shoulder** | Sub-prefix for organization |

### Test Connection

Click **"Test Connection"** to verify your DataCite credentials before saving.

---

## CLI Commands

| Command | Description |
|---------|-------------|
| `php symfony doi:mint --id=X` | Mint DOI for record X |
| `php symfony doi:mint --all --limit=100` | Batch mint with limit |
| `php symfony doi:sync --all` | Sync all DOI metadata |
| `php symfony doi:sync --status=findable` | Sync only findable DOIs |
| `php symfony doi:deactivate --id=X` | Deactivate DOI |
| `php symfony doi:deactivate --reactivate --id=X` | Reactivate DOI |

---

## Cron Jobs

Set up automatic DOI processing:

```bash
# Process DOI queue every 5 minutes
*/5 * * * * cd /usr/share/nginx/atom && php symfony doi:process-queue >> /var/log/atom/doi.log 2>&1

# Weekly metadata sync (Sundays at 4 AM)
0 4 * * 0 cd /usr/share/nginx/atom && php symfony doi:sync --all --limit=500 >> /var/log/atom/doi-sync.log 2>&1
```

---

## Troubleshooting

### DOI Not Resolving

1. Check DOI status is "findable" or "registered"
2. Verify target URL is accessible
3. Allow 24 hours for DataCite propagation
4. Use "Verify Resolution" to check

### Minting Failed

1. Check queue for error message
2. Verify DataCite credentials
3. Check required metadata is present (title, creator, year)
4. Ensure API quota not exceeded

### Metadata Not Updating

1. Run `php symfony doi:sync --id=X` for specific DOI
2. Check DataCite API response
3. Verify record has all required fields

---

## Best Practices

1. **Test First**: Use DataCite test environment before production
2. **Batch Wisely**: Don't mint DOIs for unpublished records
3. **Monitor Queue**: Check failed jobs regularly
4. **Sync Regularly**: Keep DOI metadata current with records
5. **Document**: Add DOI policy to your repository guidelines

---

## Integration with Other Systems

The DOI is available via:
- **API**: `GET /api/doi/status/{recordId}`
- **OAI-PMH**: Included in Dublin Core exports
- **EAD3**: Added to `<unitid>` element
- **Record Display**: Badge shown on public pages

---

## Need Help?

- **DataCite Support**: https://support.datacite.org
- **Test Environment**: https://doi.test.datacite.org
- **System Administrator**: For API credential issues

---

*Last updated: February 2026*
*Part of the AHG Extensions for AtoM 2.10*
