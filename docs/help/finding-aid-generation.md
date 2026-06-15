> Heratio Help Center article. Category: Import/Export / Finding Aids.

# Finding Aid Generation

A finding aid is a structured description of a collection that helps researchers understand and navigate its contents. Heratio generates a finding aid from an archival description as EAD XML (and optionally a PDF), or lets you upload a finding aid you produced elsewhere. The four controls are Generate, Upload, Download, and Delete.

---

## Overview

Finding aids attach to an archival description (typically at fonds or collection level). Heratio can build one for you from the catalogue metadata, or hold a file you supply.

- **Generate** builds an EAD 2002 XML finding aid from the description and its children, and optionally a PDF. Generation runs as a background job so large collections do not block the interface.
- **Upload** stores a finding aid you created elsewhere (PDF or RTF).
- **Download** retrieves the current finding aid.
- **Delete** removes the finding aid in all its formats.

---

## Key features

| Feature | Description |
|---------|-------------|
| EAD XML generation | Produces a valid EAD 2002 finding aid covering the description and its nested components. |
| Optional PDF | Produces a PDF alongside the XML when PDF output is enabled and the converter is available. |
| Background processing | Generation runs as a queued job with status tracking; regenerating clears the previous output first. |
| Two detail models | Choose an inventory summary (lighter lower levels) or full details (every level fully expanded). |
| Upload your own | Store an externally produced PDF or RTF finding aid (up to 20 MB). |
| Download and delete | Retrieve or remove the finding aid; delete clears all format variants. |
| Audit trail | Generate, upload, and delete actions are recorded as mutations in the audit log. |

---

## How to use

You reach these controls from the archival description's context menu, in the **Finding aid** section. The Generate, Upload, and Delete actions require you to be signed in with the appropriate permission; Download may be public depending on your settings.

### Generate a finding aid

1. Open the archival description (usually a collection or fonds).
2. In the context menu, choose **Generate finding aid**.
3. Heratio queues a background job that builds the EAD XML (and a PDF if enabled). Any existing finding aid is removed first so you always get a fresh build.
4. When the job finishes, the finding aid is available for download.

### Upload an existing finding aid

1. Choose **Upload finding aid** to open the upload form.
2. Select a PDF or RTF file (maximum 20 MB).
3. Save. Uploading requires create permission.

### Download a finding aid

- Choose **Download finding aid**. Whether anonymous visitors can download depends on the public finding aid setting; staff can always download when a file exists.

### Delete a finding aid

- Choose **Delete finding aid** and confirm. This removes every format (XML, PDF, HTML, RTF). Deletion requires delete permission.

### Research collection finding aids

Within the research module you can also export a finding aid for a research collection as PDF or HTML, or display it in the browser. These exports use the research collection's items rather than the full EAD build above.

---

## Configuration

Finding aid behaviour is controlled in the AHG Settings finding aid section:

- **Finding aids enabled:** toggles generation, the download links, and the related advanced-search filter on or off across the platform.
- **Finding aid format:** chooses whether generation produces a PDF or keeps XML only. PDF output also requires the PDF converter to be installed on the server.
- **Finding aid model:** `inventory-summary` (the default) keeps lower-level components to identifier, title, and scope; `full-details` expands every level with full metadata.
- **Generate from public records:** when set, excludes unpublished or hidden records from the generated finding aid.

Generated files are stored in the application's downloads area and named per record. Generation status is tracked in the job table.

---

## References

- Source: `packages/ahg-information-object-manage/` (finding aid controllers) and the `FindingAidJob` queue job in `app/Jobs/`
- Issue: [GH #553](https://github.com/ArchiveHeritageGroup/heratio/issues/553)
