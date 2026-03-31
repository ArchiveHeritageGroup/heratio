# Exporting Your Data

## A Guide for Archivists and Collection Managers

---

## Why Export Data?

You might need to export data from your system to:
- Share records with other institutions
- Create backups
- Generate reports
- Migrate to another system
- Publish online

---

## Export Formats Explained

| Format | Best For | Who Uses It |
|--------|----------|-------------|
| **CSV** | Spreadsheets, simple lists | Everyone - opens in Excel |
| **EAD** | Archival finding aids | Archives, libraries |
| **Dublin Core** | Web publishing, sharing | Digital repositories |
| **PDF** | Printing, sharing | General use |
| **JSON** | Developers, APIs | Technical users |

---

## Quick Export: Single Records

### Exporting One Record

```
┌──────────────────┐
│  View any record │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Click "Export"  │
│  button          │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Choose format:  │
│  • EAD           │
│  • Dublin Core   │
│  • PDF           │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Download file   │
└──────────────────┘
```

### Steps

1. Navigate to any archival description
2. Look for the **Export** button (usually top right)
3. Select your format
4. File downloads automatically

---

## Export Dashboard

### Accessing the Export Dashboard

Navigate to `/export` to access the export dashboard with format options.

```
┌──────────────────────────────────────────────────────────────┐
│                    EXPORT DASHBOARD                           │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   ┌────────────────┐   ┌────────────────┐                   │
│   │  CSV Export    │   │  EAD Export    │                   │
│   │  Spreadsheet   │   │  Finding Aid   │                   │
│   └────────────────┘   └────────────────┘                   │
│                                                              │
│   Export archival descriptions in standard formats           │
│   with filtering by repository, level, and scope.           │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### CSV Export

Export archival descriptions as ISAD-G compliant CSV:

1. Go to **Export** → **CSV Export**
2. Select **Repository** (optional) to filter records
3. Choose **Level of Description** (optional)
4. Enter **Parent Record Slug** to export a specific branch
5. Check **Include descendants** for hierarchical export
6. Click **Export CSV**

### EAD Export

Export finding aids in EAD 2002 XML format:

1. Go to **Export** → **EAD Export**
2. Select a **top-level record** (fonds or collection)
3. Check **Include all descendants** for complete hierarchy
4. Click **Export EAD XML**

---

## Bulk Export: Multiple Records

### Using the Clipboard

The clipboard lets you collect records and export them together.

```
┌──────────────────┐
│  Browse records  │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Click "Add to   │
│  Clipboard" on   │
│  each record     │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Go to Clipboard │
│  (top menu)      │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Review your     │
│  selections      │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Click "Export"  │
│  Choose format   │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│  Download file   │
└──────────────────┘
```

### Steps

1. Search or browse for records
2. Click the clipboard icon on each record you want
3. Click **Clipboard** in the top menu
4. Review your selection
5. Click **Export All**
6. Choose format (CSV, EAD, Dublin Core)
7. Download

---

## Sector-Specific Exports

### Archives

| Format | Description |
|--------|-------------|
| **EAD 2002** | Standard finding aid format |
| **EAD3** | Newer finding aid format |
| **CSV (ISAD-G)** | Spreadsheet with archival fields |

### Museum Objects

| Format | Description |
|--------|-------------|
| **CSV (Spectrum)** | Museum standard fields |
| **CSV (CCO)** | Art cataloguing fields |
| **Dublin Core** | Simple metadata |

### Library Items

| Format | Description |
|--------|-------------|
| **MARC** | Library catalogue format |
| **CSV (RDA)** | Bibliographic spreadsheet |
| **BibTeX** | For citations |

### Digital Assets

| Format | Description |
|--------|-------------|
| **CSV with paths** | Includes file locations |
| **Dublin Core** | Standard digital metadata |
| **IPTC** | Photo metadata |

---

## Understanding Export Files

### CSV Files

Opens in Excel or Google Sheets. Each row is one record.

```
┌────────────────────────────────────────────────────────────┐
│  A           │  B        │  C                │  D          │
├──────────────┼───────────┼───────────────────┼─────────────┤
│  Identifier  │  Title    │  Date             │  Creator    │
├──────────────┼───────────┼───────────────────┼─────────────┤
│  F001        │  Smith    │  1920-1950        │  John Smith │
│              │  Papers   │                   │             │
├──────────────┼───────────┼───────────────────┼─────────────┤
│  F001/S1     │  Corres-  │  1920-1935        │             │
│              │  pondence │                   │             │
└──────────────┴───────────┴───────────────────┴─────────────┘
```

### EAD Files

XML format for archival finding aids. Opens in text editors or specialised software.

```xml
<ead>
  <archdesc>
    <did>
      <unitid>F001</unitid>
      <unittitle>Smith Papers</unittitle>
      <unitdate>1920-1950</unitdate>
    </did>
  </archdesc>
</ead>
```

### Dublin Core Files

Simple XML format for sharing metadata.

```xml
<record>
  <dc:identifier>F001</dc:identifier>
  <dc:title>Smith Papers</dc:title>
  <dc:date>1920-1950</dc:date>
</record>
```

---

## Export Settings

### What Gets Exported

| Setting | Meaning |
|---------|---------|
| **Include children** | Export sub-records too |
| **Include digital objects** | Include file links |
| **Include drafts** | Include unpublished records |

### Field Selection

Some exports let you choose which fields to include:

```
┌─────────────────────────────────────────┐
│        SELECT FIELDS TO EXPORT          │
├─────────────────────────────────────────┤
│  ☑ Identifier                           │
│  ☑ Title                                │
│  ☑ Date                                 │
│  ☑ Creator                              │
│  ☐ Physical description (skip)          │
│  ☑ Scope and content                    │
│  ☐ Access conditions (skip)             │
└─────────────────────────────────────────┘
```

---

## Tips for Good Exports

**Before exporting:**
- Know what format the recipient needs
- Check if they need digital objects included
- Consider file size for large exports

**For sharing with other archives:**
- Use EAD format
- Include hierarchy information
- Test with a small export first

**For spreadsheet analysis:**
- Use CSV format
- Choose only the fields you need
- Large exports may need splitting

**For web publishing:**
- Use Dublin Core
- Include public records only
- Check access restrictions

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Export is empty | Check your clipboard has items |
| File won't open | Try a different program |
| Missing fields | Check export settings |
| Export too slow | Try smaller batches |
| Characters look wrong | Ensure UTF-8 encoding |

---

*For technical support, contact your system administrator.*
