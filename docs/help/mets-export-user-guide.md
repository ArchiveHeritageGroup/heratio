> Heratio Help Center article. Category: Technical / Integration.

# METS export per record

## Overview

Every published record can be exported as a **METS** document. METS (the Metadata Encoding and Transmission Standard, maintained by the Library of Congress) is the standard archival-interchange wrapper: one XML file that bundles a record's descriptive metadata, a list of its files, and a map of how those files fit together. Archives use it to exchange records with one another and to ingest material into preservation and repository systems.

This is open data: no API key, read-only, published records only, and CORS-open, so any interchange or ingest tool on any site can fetch it.

---

## The METS URL

**GET /mets/{idOrSlug}.xml**

`{idOrSlug}` is the record's slug (the same identifier used in its public page address) or its numeric record id.

Example:

```
https://<your-heratio>/mets/a-photograph-collection.xml
```

The response is `application/xml` (METS 1.12).

---

## What is in the document

A METS document has four parts, all present here:

| METS section | Contents |
|---|---|
| `metsHdr` | The creation date of the export and a creator **agent** naming the holding repository (or the platform when there is no repository) |
| `dmdSec` | Descriptive metadata, wrapped as simple **Dublin Core** (the same `oai_dc` shape served by the "Cite this" `.dc.xml` download and the OAI-PMH endpoint): title, creator(s), date, description, publisher (the holding repository), identifier (the reference code and the record URL), and type |
| `fileSec` | A file inventory: one `mets:file` per digital object, carrying its MIME type, byte size, and **checksum + checksum type** (when stored) for fixity, with a `mets:FLocat` link to the file URL. An image also gets a second `FLocat` pointing at the IIIF Image API service |
| `structMap` | A physical structure map: a record-level division (linked to the descriptive metadata) that points at each file in order |

An honest mapping: a field that is absent is simply omitted, never invented.

---

## Records with no files

A published record that has no digital objects still returns a **valid** METS document, with an empty file group. The descriptive metadata and the structure map are still present. The endpoint never errors over a record that happens to have no attached files.

---

## Checksums and fixity

When a digital object has a stored checksum, the `mets:file` carries `CHECKSUM` plus a `CHECKSUMTYPE` from the METS controlled vocabulary (for example `SHA-256`, `MD5`, `SHA-1`). A receiving preservation system can use these to verify the file is intact after transfer.

---

## Behaviour and edge cases

- An **unknown**, **unpublished**, or the synthetic **root** record returns a clean `404` XML document, never an error page and never a draft leak.
- Only **published** records are exposed (the same publication gate as the rest of the public API).
- Every URL inside the document (the file links, the IIIF service, the record URL) follows the host the request arrived on, so a fresh install on its own domain emits its own URLs with nothing hardcoded.
- Every value is XML-escaped, so a record title can never break the document.

---

## Related surfaces

- **IIIF Presentation manifest** - `/iiif-presentation/{idOrSlug}/manifest.json` to open the record's images in a IIIF viewer.
- **Cite this record** - `/cite/{idOrSlug}` for bibliographic citation formats (including the same Dublin Core as `.dc.xml`).
- **OAI-PMH** - `/api/oai` to harvest the whole published corpus as Dublin Core.
- **Open data protocol** - `/open-data/protocol` lists every open-data surface, including this METS export, so an agent can discover them all from one document.
