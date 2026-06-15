> Heratio Help Center article. Category: Import/Export / Digital Objects.

# Multi-File Upload (Import digital objects)

Multi-file upload is a drag-and-drop batch uploader for attaching many digital files to an archival description in one pass. From any information object you open **More -> Import digital objects**, drop a set of files into the uploader, and the platform creates one child description per file, attaches each uploaded file as that child's master digital object, and then takes you to a review page where you can adjust each title. It is the quickest way to bring a folder of scans or images into the hierarchy under an existing description. For structured bulk imports that need metadata mapping, validation, and CSV support, use the Data Ingest tool instead.

## Overview

When you import multiple digital objects against an information object, each file becomes a new **child** description nested beneath the current record. The uploader applies a shared title pattern and a shared level of description to every child it creates, then lets you fine-tune the individual titles afterwards.

The flow has three stages:

1. **Configure and select** - set the title pattern and level of description, then drag in or browse for files.
2. **Upload** - files are sent to the server and held in a temporary area.
3. **Import and review** - on submit, the platform creates the child descriptions and digital objects, then redirects you to a review page to edit each title.

This feature lives on the archival description, not in a standalone importer, so the new children always land in the correct place in the hierarchy.

## Key features

- **Drag-and-drop uploader** - drop files into the pane, paste, or use the browse link to open your file explorer.
- **Batch child creation** - one child information object is created per uploaded file, parented to the current record.
- **Title pattern with auto-numbering** - the title you enter is applied to every child, with the `%dd%` placeholder replaced by an incrementing two-digit number (for example `image 01`, `image 02`). Per-file titles can override the pattern.
- **Shared level of description** - pick a single level (from the Levels of description taxonomy) applied to all new children.
- **Master digital object per child** - each uploaded file is stored and registered as the child's master digital object, with name, path, byte size, and detected MIME type recorded.
- **Draft publication status** - new children are created in Draft status so they are not publicly visible until you publish them.
- **Post-upload review** - after import you are taken to a review page to individually rename each new description.
- **Upload-size awareness** - the uploader reads the server's file-size and total-post-size limits and surfaces them, warning you before you exceed them.
- **Signpost to Data Ingest** - the page links to the Data Ingest tool for imports that need metadata mapping, validation, or CSV.

## How to use

1. Open the archival description (information object) you want the files to sit under.
2. In the actions bar, open the **More** menu and choose **Import digital objects** (route name `io.multiFileUpload`, URL `/informationobject/{slug}/multiFileUpload`).
3. On the **Import multiple digital objects** page, set the defaults that will apply to every file:
   - **Title** - defaults to `image %dd%`. The `%dd%` placeholder is replaced with an incrementing two-digit number. Change the wording but keep `%dd%` if you want auto-numbering.
   - **Level of description** - choose a level from the dropdown (optional; leave as the dash for none).
4. Add your files by dragging and dropping them into the upload pane, pasting, or clicking the browse link.
5. The uploader shows progress per file. If any file fails, you can **Retry** the failed files, or proceed with the successfully uploaded ones.
6. Click **Upload** to submit. The platform then, for each uploaded file:
   - creates a child information object under the current record,
   - applies the title (pattern with `%dd%` substituted, or a per-file title if you set one),
   - applies the chosen level of description,
   - generates a slug and sets the child to **Draft** publication status,
   - moves the uploaded file into permanent storage and registers it as the child's master digital object (recording name, path, byte size, and MIME type).
7. You are redirected to the **review** page listing the newly created descriptions, where you can edit each title individually before publishing.
8. To abandon without importing, click **Cancel** to return to the description.

### Title pattern examples

| Title field | Result for 3 files |
|-------------|--------------------|
| `image %dd%` (default) | `image 01`, `image 02`, `image 03` |
| `Letter page %dd%` | `Letter page 01`, `Letter page 02`, `Letter page 03` |
| `Front cover` (no `%dd%`) | `Front cover`, `Front cover`, `Front cover` |

If you set a per-file title in the uploader, that title is used for that file instead of the pattern.

## Configuration

There are no per-user settings for this feature; behaviour is driven by the server environment and the description you are working on.

- **Menu path.** The entry is **More -> Import digital objects** on the archival description actions bar.
- **Parent context.** New children are always created beneath the description you launched the uploader from. Pick the correct parent before starting.
- **Publication status.** New children are created as **Draft**. They will not appear to the public until you publish them.
- **Levels of description.** The level dropdown is populated from the Levels of description taxonomy; manage its values in the Dropdown / taxonomy administration if a level you need is missing.
- **Upload limits.** Maximum single-file size and maximum total upload size come from the server's PHP `upload_max_filesize` and `post_max_size` settings. The uploader displays the effective limits and warns when a file or the batch total would exceed them. If you need to raise the limits, an administrator must adjust the server PHP configuration.
- **Storage location.** Uploaded files are moved into the configured uploads path for the installation (`heratio.uploads_path`), under a per-object directory. Files are first held in a temporary upload area and then moved into permanent storage when you submit.
- **When to use Data Ingest instead.** Multi-file upload is for quickly attaching files as child descriptions with minimal metadata. For imports that require column-to-field mapping, validation, preview, or CSV-driven metadata, use the **Data Ingest** tool (linked from the page).

## References

Source packages and files used to document this article (verified against current code):

- `packages/ahg-information-object-manage/src/Controllers/DigitalObjectController.php` - the `multiFileUpload` method: reads PHP upload limits, loads the levels-of-description list, and on POST creates one child information object plus master digital object per file, sets Draft status, and redirects to the review page. Includes the `phpSizeToBytes` helper.
- `packages/ahg-information-object-manage/routes/web.php` - the `io.multiFileUpload` route (`/informationobject/{slug}/multiFileUpload`, GET and POST) and the `io.digitalobject.upload` AJAX endpoint used to receive the files.
- `packages/ahg-information-object-manage/resources/views/digitalobject/multi-file-upload.blade.php` - the drag-and-drop upload page: title pattern, level-of-description dropdown, upload limits, Upload / Cancel actions, and the link to Data Ingest.
- `packages/ahg-information-object-manage/resources/views/_actions-bar.blade.php` - the **More -> Import digital objects** menu entry.
- `packages/ahg-information-object-manage/resources/views/multi-file-update.blade.php` - the post-upload review page where each new child description can be retitled.
- `packages/ahg-ingest/` - the Data Ingest tool referenced as the alternative for structured, metadata-mapped bulk imports.
