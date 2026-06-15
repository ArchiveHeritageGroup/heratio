> Heratio Help Center article. Category: Plugin Reference.

# Media Processing - User Guide

## Generate Thumbnails and Reference Images, and Apply Watermarks

The Media Processing module keeps your image derivatives in good order. It
generates the thumbnail and reference copies that the catalogue displays from your
master files, lets you regenerate them one at a time or in batches, and manages
watermarks that can be applied to images on view or download.

It also reports which system tools are available, so you can see at a glance
whether the host has everything needed to process images, video, and documents.

---

## Overview

For each master digital object the module can produce two derivatives:

- A **thumbnail** - a small square image (150 by 150 pixels, centre-cropped),
  used in lists and grids.
- A **reference image** - a larger, aspect-correct copy (up to 480 pixels by
  default, configurable), used on record pages.

Derivatives are generated with ImageMagick. The dashboard shows how many masters
have each derivative and which are missing, so you can fill the gaps. A separate
watermark screen manages the watermarks that protect images when they are viewed
or downloaded.

---

## Key features

- Dashboard showing derivative coverage: total masters, how many have thumbnails,
  how many have reference images, and how many are missing each.
- Regenerate derivatives for a single object, or batch-regenerate across many.
- Tool-availability check for ffmpeg, ffprobe, mediainfo, exiftool, whisper,
  ImageMagick (convert), and pdfinfo.
- Watermark management: upload custom watermarks, choose position and opacity,
  and set when watermarks apply.
- Watermark configuration syncs to the IIIF image server cache so deep-zoom views
  stay consistent.

---

## How to use

The module lives under the **Media Processing** admin area
(**`/admin/media-processing`**) and requires an administrator login.

### Review coverage and tools

Open **`/admin/media-processing`**. The dashboard shows derivative coverage with
progress bars, lists recent derivatives and masters that are still missing
derivatives, reports which system tools are installed, and shows the media
processor and derivative settings.

### Regenerate derivatives

- **One object:** use the regenerate action on a single master to rebuild its
  thumbnail and reference image.
- **Batch:** use the batch action to regenerate across many masters at once. You
  choose the type (all, thumbnail only, or reference only) and a limit (default
  100). The screen reports how many succeeded and how many failed.

### Manage watermarks

Go to **`/admin/media-processing/watermark`**. From here you can:

- Upload a custom watermark image (PNG, JPEG, or GIF, up to 5 MB), giving it a
  name, a position, and an opacity.
- Delete a custom watermark you no longer need.
- Set the global watermark behaviour: whether watermarking is on by default,
  which default watermark type or custom watermark to use, whether to apply
  watermarks on view, on download, or both, whether a security classification can
  override the default, and a minimum image size below which watermarks are
  skipped.

Watermarks can be positioned in any of nine compass positions (top-left through
bottom-right) or tiled across the image.

---

## Configuration

### Derivative dimensions

- Thumbnails default to 150 by 150 pixels, centre-cropped to a square.
- Reference images default to a maximum of 480 pixels on the longest side,
  preserving the aspect ratio and never enlarging the original. The
  reference-image maximum width can be changed in the derivative settings.

### Photo upload processing

When photos are uploaded, the module can validate and re-encode them. The
controlling settings include the maximum upload size (5 MB by default), the
allowed file types (JPEG, PNG, GIF, TIFF by default), JPEG quality and PNG
compression, auto-orientation and EXIF stripping, whether small / medium / large
thumbnails are created, whether a watermark is applied, and whether EXIF metadata
(date, camera, photographer, dimensions) is extracted.

### Watermark settings

Watermark behaviour - default on/off, default type, apply-on-view, apply-on-
download, security override, and minimum size - is set on the watermark screen and
stored centrally. Changes there are also pushed to the IIIF image server cache.

---

## Notes

- Image processing depends on ImageMagick being installed on the host;
  watermark and derivative generation will not run without it. The dashboard's
  tool check tells you what is present.
- Supported photo upload formats are JPEG, PNG, GIF, and TIFF by default; this
  list is itself a setting.

---

## References

- Source package: `packages/ahg-media-processing/`
- GitHub issue: [GH #595](https://github.com/ArchiveHeritageGroup/heratio/issues/595)
