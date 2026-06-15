> Heratio Help Center article. Category: User Guide / Digital Objects.

# Edit Digital Object

The digital object editor manages the files attached to an archival description: the master file you upload, its automatically generated reference and thumbnail derivatives, and any external media you link instead of uploading. From here you can replace the master, regenerate or replace derivatives, change the media type, and delete individual representations or the whole object.

---

## Overview

Each archival description can carry one master digital object plus derivatives generated from it:

- **Master** is the file you upload (or the external URL you link). It is the authoritative source copy.
- **Reference** is a web-optimised derivative used for on-screen display.
- **Thumbnail** is a small preview image.

When you upload an image, Heratio generates the reference and thumbnail automatically. For other file types it creates generic placeholder derivatives, which you can replace with your own. When you link an external URL instead of uploading, no derivatives are created and the URL is stored directly.

---

## Key features

| Feature | Description |
|---------|-------------|
| Upload a master | Attach a file (up to 100 MB) to an archival description. |
| Link external media | Point at an HTTP, HTTPS, or FTP URL instead of uploading; recognised hosts such as video platforms render inline. |
| Automatic derivatives | Reference and thumbnail images are generated automatically for image masters. |
| Replace the master | Swap the master file in place; size, type, and checksum update automatically. |
| Replace derivatives | Upload your own reference or thumbnail image. |
| Per-representation delete | Remove just the reference or thumbnail without touching the master. |
| Batch and folder upload | Drag-and-drop many files at once, or upload a folder and mirror its structure as child records. |
| Media metadata | Audio and video metadata is extracted on upload; image EXIF can be applied to the record. |
| IIIF viewing | Image masters can be viewed through the deep-zoom IIIF viewer. |

---

## How to use

### Reach the editor

1. Open the archival description and switch to its edit page.
2. Expand the **Digital object** section.
3. If the record already has a master, use the **Edit digital object** action to open the editor directly.
4. If the record has no digital object yet, you are taken to the add page at `/{record}/object/addDigitalObject`.

### Upload a master file

1. In the digital object section, choose the upload option and select a file (maximum 100 MB).
2. Save. Heratio stores the master, generates reference and thumbnail derivatives for images, and extracts media metadata where it can.

### Link an external URL instead

1. In the same section, switch to the link option.
2. Enter the external address. It must begin with `http://`, `https://`, or `ftp://`, and you can give it a display name.
3. Save. No derivatives are generated for linked media; the address is stored as the object's location.

### Edit an existing digital object

On the editor (route `/digitalobject/{id}`) you can:

- **Change the media type** (Audio, Image, Text, Video, or Other).
- **Replace the master file** by choosing a new file. The new file is moved into place and its size, type, and checksum are updated.
- **Replace the reference or thumbnail** by uploading your own image for either.
- **Save** to persist the changes.

### Delete representations or the whole object

- To remove only one derivative, use the per-representation delete for the reference or thumbnail. The master is left untouched and you return to the editor.
- To remove the whole object, delete the master. This cascades to all derivatives. The deleted row is snapshotted for the audit trail first.

### Upload many files at once

- Use the multi-file (drag-and-drop) uploader to create a child record per file, with a configurable title pattern.
- Use the bulk folder upload (route `/{record}/object/addDigitalObject/bulk`, up to 512 MB per file) to mirror a folder structure: folders become child records and each file becomes an item-level child with its own master. The repository storage quota is checked for the whole batch before anything is created.

---

## Configuration

- **Media types** are drawn from a controlled list (Audio, Image, Text, Video, Other) rather than hard-coded values.
- **Storage quota:** uploads are checked against the repository storage quota; bulk uploads are all-or-nothing if the batch would exceed the quota.
- **IIIF viewing** uses the deep-zoom image server for image masters; viewer behaviour is controlled by the IIIF viewer settings.
- **Derivative encryption:** when derivative encryption is enabled, the master is encrypted in place after upload and decrypted on the fly when streamed to a viewer. With encryption off, files stream directly.
- **Media metadata:** audio and video metadata (duration, codec, tags) is extracted on upload, and image EXIF can be applied to the record's fields when metadata extraction is enabled. Extraction failures never block the upload.

---

## References

- Source: `packages/ahg-information-object-manage/` (digital object controller and service)
- Issue: [GH #553](https://github.com/ArchiveHeritageGroup/heratio/issues/553)
