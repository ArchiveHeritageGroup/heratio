# GLAM Metadata Export

## A Guide for Archivists, Librarians, and Collection Managers

---

## What is GLAM Metadata Export?

The GLAM Metadata Export plugin allows you to export your archival descriptions to international metadata standards used by:

- **Archives** - EAD3, RIC-O
- **Libraries** - MARC21, BIBFRAME
- **Museums** - LIDO
- **Visual Resources** - VRA Core 4
- **Media Collections** - PBCore, EBUCore
- **Digital Preservation** - PREMIS

---

## Export Formats at a Glance

| Format | Sector | Output | Best For |
|--------|--------|--------|----------|
| **EAD3** | Archives | XML | Finding aids, ArchivesSpace, ArchivesHub |
| **RIC-O** | Archives | JSON-LD | Linked data, semantic web |
| **LIDO** | Museums | XML | Europeana, museum aggregators |
| **MARC21** | Libraries | XML | Library catalogs (Koha, Alma) |
| **BIBFRAME** | Libraries | JSON-LD | Library of Congress linked data |
| **VRA Core 4** | Visual | XML | Art/photography collections |
| **PBCore** | Media | XML | Public broadcasting, video archives |
| **EBUCore** | Media | XML | European broadcasters |
| **PREMIS** | Preservation | XML | Digital preservation systems |

---

## Using the Web Interface

### Single Record Export

```
┌──────────────────────────┐
│  View any record         │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│  Click "Export" button   │
│  (top right area)        │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│  Select format:          │
│  • EAD3                  │
│  • RIC-O (JSON-LD)       │
│  • LIDO                  │
│  • MARC21                │
│  • PREMIS                │
│  • (more...)             │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│  Configure options:      │
│  ☑ Include children      │
│  ☑ Include digital obj   │
│  ☐ Include drafts        │
└───────────┬──────────────┘
            │
            ▼
┌──────────────────────────┐
│  Download file           │
└──────────────────────────┘
```

### Steps

1. Navigate to any archival description
2. Look for the **Export** dropdown or button
3. Select your desired format
4. Configure export options
5. Click **Export** or **Download**

---

## Using the Command Line

The CLI is ideal for bulk exports and automation.

### Basic Commands

```bash
# List all available formats
php symfony metadata:export --list

# Export single record to EAD3
php symfony metadata:export --format=ead3 --slug=my-fonds

# Export to all formats at once
php symfony metadata:export --format=all --slug=my-fonds --output=/exports/
```

### Export Options

| Option | Description |
|--------|-------------|
| `--format=FORMAT` | Export format (ead3, rico, lido, marc21, etc.) |
| `--slug=SLUG` | Record slug to export |
| `--repository=ID` | Export all records from a repository |
| `--output=PATH` | Output directory (default: /tmp) |
| `--include-children` | Include child records |
| `--include-digital-objects` | Include digital object metadata |
| `--include-drafts` | Include unpublished records |

### Examples

**Export a finding aid to EAD3:**
```bash
php symfony metadata:export --format=ead3 --slug=smith-family-papers --output=/exports/
```

**Export entire repository to RIC-O linked data:**
```bash
php symfony metadata:export --format=rico --repository=5 --include-children --output=/exports/rico/
```

**Export museum objects to LIDO:**
```bash
php symfony metadata:export --format=lido --repository=3 --include-digital-objects --output=/exports/lido/
```

**Export all formats for a record:**
```bash
php symfony metadata:export --format=all --slug=my-record --output=/exports/multi-format/
```

---

## Understanding the Formats

### For Archives

#### EAD3 (Encoded Archival Description 3)

The latest version of the standard finding aid format.

```xml
<ead xmlns="http://ead3.archivists.org/schema/">
  <control>
    <recordid>F001</recordid>
  </control>
  <archdesc level="fonds">
    <did>
      <unittitle>Smith Papers</unittitle>
      <unitdate>1920-1950</unitdate>
    </did>
    <scopecontent>
      <p>Personal papers of John Smith...</p>
    </scopecontent>
  </archdesc>
</ead>
```

**Use for:** ArchivesSpace, ArchivesHub, Library of Congress

#### RIC-O (Records in Contexts - Ontology)

Linked data format from the International Council on Archives.

```json
{
  "@context": "https://www.ica.org/standards/RiC/ontology#",
  "@type": "rico:RecordSet",
  "rico:identifier": "F001",
  "rico:title": "Smith Papers",
  "rico:hasOrHadCreator": {
    "@type": "rico:Person",
    "rico:name": "John Smith"
  }
}
```

**Use for:** Semantic web, linked data publishing, knowledge graphs

---

### For Libraries

#### MARC21

Standard library catalog format.

```xml
<record>
  <leader>00000npc a2200000 u 4500</leader>
  <controlfield tag="001">F001</controlfield>
  <datafield tag="245" ind1="1" ind2="0">
    <subfield code="a">Smith Papers</subfield>
  </datafield>
  <datafield tag="520">
    <subfield code="a">Personal papers of John Smith...</subfield>
  </datafield>
</record>
```

**Use for:** Koha, Evergreen, Alma, WorldCat

#### BIBFRAME

Library of Congress linked data format.

```json
{
  "@context": "http://id.loc.gov/ontologies/bibframe/",
  "@type": "bf:Work",
  "bf:title": {
    "@type": "bf:Title",
    "bf:mainTitle": "Smith Papers"
  }
}
```

**Use for:** Library linked data, id.loc.gov

---

### For Museums

#### LIDO (Lightweight Information Describing Objects)

Standard for museum object metadata.

```xml
<lido:lido>
  <lido:lidoRecID>OBJ-001</lido:lidoRecID>
  <lido:descriptiveMetadata>
    <lido:objectIdentificationWrap>
      <lido:titleWrap>
        <lido:titleSet>
          <lido:appellationValue>Portrait of a Lady</lido:appellationValue>
        </lido:titleSet>
      </lido:titleWrap>
    </lido:objectIdentificationWrap>
  </lido:descriptiveMetadata>
</lido:lido>
```

**Use for:** Europeana, museum aggregators, CollectiveAccess

---

### For Visual Resources

#### VRA Core 4

Visual Resources Association standard.

```xml
<vra:vra>
  <vra:work>
    <vra:titleSet>
      <vra:title>Photograph of Main Street</vra:title>
    </vra:titleSet>
    <vra:dateSet>
      <vra:date type="creation">1945</vra:date>
    </vra:dateSet>
  </vra:work>
  <vra:image>
    <vra:measurementsSet>
      <vra:measurements>1024x768 pixels</vra:measurements>
    </vra:measurementsSet>
  </vra:image>
</vra:vra>
```

**Use for:** Art libraries, image repositories

---

### For Media Collections

#### PBCore (Public Broadcasting Core)

Metadata for audiovisual content.

```xml
<pbcoreDescriptionDocument>
  <pbcoreIdentifier>VID-001</pbcoreIdentifier>
  <pbcoreTitle>Interview with John Smith</pbcoreTitle>
  <pbcoreDescription>Oral history interview...</pbcoreDescription>
</pbcoreDescriptionDocument>
```

**Use for:** PBS, public broadcasters, video archives

#### EBUCore

European Broadcasting Union standard.

**Use for:** European broadcasters, media archives

---

### For Digital Preservation

#### PREMIS

Preservation Metadata Implementation Strategies.

```xml
<premis:premis>
  <premis:object xsi:type="premis:file">
    <premis:objectIdentifier>
      <premis:objectIdentifierType>local</premis:objectIdentifierType>
      <premis:objectIdentifierValue>DO-001</premis:objectIdentifierValue>
    </premis:objectIdentifier>
    <premis:objectCharacteristics>
      <premis:format>
        <premis:formatDesignation>
          <premis:formatName>image/tiff</premis:formatName>
        </premis:formatDesignation>
      </premis:format>
    </premis:objectCharacteristics>
  </premis:object>
  <premis:event>
    <premis:eventType>ingestion</premis:eventType>
  </premis:event>
</premis:premis>
```

**Use for:** Archivematica, Preservica, Rosetta, digital preservation workflows

---

## DOI Integration

### What is a DOI?

A **Digital Object Identifier (DOI)** is a persistent identifier used to uniquely identify digital objects. DOIs are widely used in academic publishing and increasingly in cultural heritage to provide permanent, citable links to archival records.

Example DOI: `10.12345/archive.2025.001`
Resolves to: `https://doi.org/10.12345/archive.2025.001`

### DOI in Exports

When you export records, any existing DOIs are automatically included in the appropriate metadata element for each format:

| Format | DOI Location in Export |
|--------|----------------------|
| **EAD3** | `<otherrecordid localtype="doi">` |
| **RIC-O** | `rico:Identifier` with type DOI |
| **LIDO** | `<objectPublishedID lido:type="doi">` |
| **MARC21** | Field `024` with `$2=doi` |
| **BIBFRAME** | `bf:identifiedBy` → `bf:Doi` |
| **VRA Core** | `<refid type="doi">` |
| **PBCore** | `<pbcoreIdentifier source="DOI">` |
| **EBUCore** | `<identifier typeLabel="DOI">` |
| **PREMIS** | `<objectIdentifierType>DOI` |

### Example: DOI in EAD3

```xml
<ead xmlns="http://ead3.archivists.org/schema/">
  <control>
    <recordid>F001</recordid>
    <otherrecordid localtype="doi">10.12345/archive.2025.001</otherrecordid>
  </control>
  <!-- ... -->
</ead>
```

### Example: DOI in RIC-O

```json
{
  "@type": "rico:RecordSet",
  "rico:identifier": [
    {
      "@type": "rico:Identifier",
      "rico:textualValue": "F001"
    },
    {
      "@type": "rico:Identifier",
      "rico:identifierType": "DOI",
      "rico:textualValue": "10.12345/archive.2025.001"
    }
  ],
  "owl:sameAs": {"@id": "https://doi.org/10.12345/archive.2025.001"}
}
```

### Example: DOI in MARC21

```xml
<datafield tag="024" ind1="7" ind2=" ">
  <subfield code="a">10.12345/archive.2025.001</subfield>
  <subfield code="2">doi</subfield>
</datafield>
```

### DOI Export Options

| Option | Description |
|--------|-------------|
| `--include-doi` | Include DOI in export (default: yes) |
| `--mint-doi` | Mint new DOI for records without one |
| `--doi-state=STATE` | State for new DOIs: draft, registered, findable |
| `--skip-no-doi` | Only export records that have DOIs |

### CLI Examples with DOI

**Export records with existing DOIs:**
```bash
php symfony metadata:export --format=ead3 --repository=5 --output=/exports/
```

**Export only records that have DOIs:**
```bash
php symfony metadata:export --format=marc21 --repository=5 --skip-no-doi --output=/exports/
```

**Mint DOIs during export (requires ahgDoiPlugin):**
```bash
php symfony metadata:export --format=rico --slug=my-fonds --mint-doi --doi-state=findable
```

### Benefits of DOI in Exports

- **Persistent citation** - Recipients can cite your records with a permanent link
- **Interoperability** - DOIs are recognized by academic databases and discovery systems
- **Linked data** - DOIs provide a stable URI for semantic web applications
- **Tracking** - DataCite provides usage statistics for your DOIs

### Requirements

- DOIs are managed via the **ahgDoiPlugin** (DOI Management module)
- DOI minting requires DataCite credentials configured in Admin > DOI Settings
- Existing DOIs are included automatically; no configuration needed

---

## Scheduling Automated Exports

### Cron Job Examples

Set up regular exports for data synchronization:

**Weekly EAD3 export (Sundays at 2am):**
```bash
0 2 * * 0 cd /usr/share/nginx/archive && php symfony metadata:export --format=ead3 --repository=5 --output=/exports/ead3 >> /var/log/atom/ead3-export.log 2>&1
```

**Daily PREMIS export for preservation (4am):**
```bash
0 4 * * * cd /usr/share/nginx/archive && php symfony metadata:export --format=premis --output=/exports/premis >> /var/log/atom/premis-export.log 2>&1
```

**Monthly all-format export (1st of month, 3am):**
```bash
0 3 1 * * cd /usr/share/nginx/archive && php symfony metadata:export --format=all --repository=5 --output=/exports/monthly >> /var/log/atom/monthly-export.log 2>&1
```

---

## Tips for Successful Exports

### Before Exporting

- Know what format your recipient needs
- Check if they need hierarchical (children) data
- Verify access permissions on records
- Test with a single record first

### For Archives (EAD3, RIC-O)

- EAD3 is best for traditional finding aid systems
- RIC-O is best for linked data / semantic web projects
- Include children for complete hierarchies

### For Libraries (MARC21, BIBFRAME)

- MARC21 for traditional ILS systems
- BIBFRAME for modern linked data catalogs
- Check field mappings match your catalog's needs

### For Museums (LIDO)

- Include digital objects for image metadata
- LIDO is required for Europeana submission
- Check object type mappings

### For Preservation (PREMIS)

- Include digital objects for full preservation metadata
- PREMIS captures fixity, events, and rights
- Essential for AIP/SIP package creation

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Export is empty | Check the slug/repository ID is correct |
| Missing fields | Some fields may not map to the target format |
| File won't validate | Check the exported XML with an online validator |
| JSON-LD won't parse | Verify UTF-8 encoding |
| Export too slow | Use smaller batches or schedule overnight |
| Permission denied | Check output directory is writable |

---

## Finding System Info

View available export formats and plugin status:

1. Go to **Admin > AHG Settings > System Info**
2. Scroll to **GLAM Metadata Export Formats** section
3. View all 9 formats with status indicators

View cron job examples:

1. Go to **Admin > AHG Settings > Cron Jobs**
2. Scroll to **Metadata Export** section
3. Copy example commands for scheduling

---

*For technical support, contact your system administrator or The Archive and Heritage Group.*
