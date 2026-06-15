> Heratio Help Center article. Category: Collections Care and Conservation.

# Condition Module

The Condition module records condition checks against archival and museum objects, lets staff attach and annotate condition photographs, surfaces at-risk items on a risk dashboard, and manages reusable condition-assessment templates. It is supplied by the `ahg-condition` package and supports the Spectrum-style condition-checking procedure used elsewhere in Heratio.

---

## Overview

A **condition check** is a dated assessment of an object's physical state. Each check records who carried it out, an overall condition rating, and a set of narrative notes (condition, completeness, hazards, recommended treatment, environment, handling, display, storage, and packing). Photographs can be attached to a check, and each photograph can carry on-image annotations marking specific areas of concern. Checks rated **poor** or **critical** are surfaced on a risk dashboard so conservation attention can be prioritised.

The module is built around four screens:

1. **Condition dashboard** - statistics and recent activity.
2. **Risk assessment dashboard** - at-risk objects (poor and critical).
3. **Photos and annotation** - upload, view, annotate, and delete condition photographs.
4. **Templates** - browse reusable condition-assessment templates.

It is jurisdiction-neutral and applies to any collection type; condition terminology is supplied through site-configurable vocabulary and dropdown tables rather than hardcoded lists.

---

## Key features

| Feature | Description |
|---|---|
| Condition dashboard | Totals for checks, photos, and annotations, plus a recent-checks list and a breakdown by overall condition. |
| Risk assessment | A dedicated view of checks rated `poor` or `critical`, with per-level counts and an optional level filter. |
| Condition check list | A filterable list of all condition checks, joined to object titles. |
| Per-object condition view | A page showing all condition checks recorded against a single record (resolved by slug). |
| Photo capture | Upload condition photographs against a check, with type, caption, and automatic image processing (thumbnails and EXIF capture). |
| On-image annotation | Save and load JSON annotations (marked regions) on each photograph. |
| Photo deletion | Remove a photo, its master file, and its thumbnail siblings. |
| Export report | A printable condition-report view for a single check, including photos and annotation stats. |
| Templates | Browse condition-assessment templates and their sections and fields. |
| Quota enforcement | Photo uploads are checked against the owning repository's storage quota before processing. |
| Audit logging | Creation, completion, photo upload, annotation update, and photo deletion are written to the audit log. |
| AI dropdown seeding | Seeds AI-related dropdown taxonomies on first boot. |

---

## How to use

### Open the condition dashboard

1. Sign in as an administrator.
2. Go to **Condition** at `/admin/condition` (or the equivalent `/condition/admin`).
3. The dashboard shows:
   - **Totals** - total condition checks, total photos, and total annotated photos.
   - **Recent checks** - the 20 most recent checks (by check date), each linked to its object title.
   - **By condition** - a count of checks grouped by overall condition.

### Review at-risk objects

1. Open the **Risk Assessment** dashboard at `/admin/condition/risk`.
2. By default it lists every check rated **poor** or **critical** (up to 200 rows, newest first), with a count badge for each level.
3. Filter to a single level by appending `?level=poor` or `?level=critical`; `?level=all` (the default) shows both.

### Browse and filter condition checks

- Open the list at `/condition/list`.
- Filter by overall condition by appending `?condition=<value>` (for example `?condition=fair`).

### View an object's condition history

- Open `/condition/check/{slug}` for any record. The page resolves the slug to its information object and shows every condition check recorded against it, newest first, with the latest highlighted.

### Work with a single condition check

- Open a check at `/condition/{id}/view` to see its detail, attached photos, and annotation statistics.
- Export a printable report at `/condition/export/{id}`.

### Create a check and add photos

1. Go to `/condition/check/new/photos?object_id=<id>`. The module creates a new condition check for that object (reference `CC-YYYYMMDD-<objectId>`, overall condition `pending`) and redirects you to its photos page.
2. On the photos page (`/condition/check/{id}/photos`), upload one or more photographs. Each upload is processed automatically (resized thumbnails, EXIF metadata extracted) and stored against the check.
3. Provide a **photo type** (before, after, detail, damage, overall, other - default `detail`) and an optional **caption** for each photo.

### Annotate a photo

1. From the photos page, open a photo's annotation screen at `/condition/photo/{id}/annotate`.
2. The image is shown from `/uploads/condition_photos/{filename}`.
3. Mark regions of concern; annotations are saved as JSON to the photo row.
4. Annotations are loaded back through the get-annotation endpoint and saved through the save-annotation endpoint (both AJAX).

### Delete a photo

- Use the delete action (`POST /condition/photo/{id}/delete`). The module removes the database row, the master image file (preferring its stored `file_path`), and the small, medium, and large thumbnail siblings.

### Browse templates

- Open the template list at `/condition/templates` and a single template at `/condition/template/{id}`. Templates are reusable condition-assessment forms organised into sections and fields.

### Completing a check

A pending check can be transitioned to a real rating (`good`, `fair`, `poor`, or `unfit`) through the service's completion path. When the Spectrum setting **require photos** is enabled, completion is refused unless at least one photo exists for the check; the user sees a clear message rather than an error. See Configuration below.

---

## Routes

All routes are registered under the `web` middleware group. `auth` requires a signed-in user; `admin` additionally requires administrator rights.

### Authenticated routes (`auth`)

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/condition/check/{slug}` | `conditionCheck` | `condition.check` |
| GET | `/condition/{id}/view` | `view` | `condition.view` |
| GET | `/condition/check/{id}/photos` | `photos` | `condition.photos` |
| GET | `/condition/photo/{id}/annotate` | `annotate` | `condition.annotate` |
| GET | `/condition/export/{id}` | `exportReport` | `condition.export` |
| GET | `/condition/templates` | `templateList` | `condition.templates` |
| GET | `/condition/template/{id}` | `templateView` | `condition.template.view` |
| GET | `/condition/annotation` | `getAnnotation` | `condition.annotation.get` |
| POST | `/condition/annotation/save` | `saveAnnotation` | `condition.annotation.save` |
| POST | `/condition/photo/upload` | `upload` | `condition.photo.upload` |
| POST | `/condition/photo/{id}/delete` | `deletePhoto` | `condition.photo.delete` |

### Admin routes (`auth` + `admin`)

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/condition/admin` | `admin` | `condition.admin` |
| GET | `/condition/list` | `list` | `condition.list` |
| GET | `/admin/condition` | `admin` | `admin.condition` |
| GET | `/admin/condition/risk` | `risk` | `admin.condition.risk` |

### Legacy AtoM base-path aliases (`auth`, JSON)

| Method | URI | Action | Name |
|---|---|---|---|
| GET | `/condition/check` | `checkIndex` (JSON list) | `condition.check.index` |
| POST | `/condition/photo` | `upload` (alias) | `condition.photo.base` |
| GET | `/condition/photo` | JSON 400 (directs to a real photo action) | `condition.photo.index` |

The `checkIndex` endpoint returns JSON: with `?object_id=<id>` it lists that object's checks, otherwise the 20 most recent.

---

## Views

| View | Used by | Purpose |
|---|---|---|
| `admin.blade.php` | `admin` | Condition dashboard (stats, recent checks, breakdown). |
| `risk.blade.php` | `risk` | Risk assessment dashboard (poor and critical). |
| `list.blade.php` | `list` | Filterable list of condition checks. |
| `condition-check.blade.php` | `conditionCheck` | Per-object condition history. |
| `view.blade.php` | `view` | Single condition-check detail. |
| `photos.blade.php` | `photos` | Photo gallery and upload for a check. |
| `annotate.blade.php` | `annotate` | On-image annotation editor. |
| `export-report.blade.php` | `exportReport` | Printable condition report. |
| `template-list.blade.php` | `templateList` | Condition template list. |
| `template-view.blade.php` | `templateView` | Single template detail. |
| `_condition-template-form.blade.php` | embedded | Template form partial. |

Views use Bootstrap 5 and the central theme, consistent with the rest of the admin UI.

---

## Data model

Created by `database/install.sql` (idempotent `CREATE TABLE IF NOT EXISTS`). The screens documented above operate primarily on the `spectrum_*` tables.

### `spectrum_condition_check`

The core condition-check record. Notable columns:

| Column | Notes |
|---|---|
| `object_id` | The information object assessed. |
| `condition_check_reference` | Generated reference, e.g. `CC-YYYYMMDD-<objectId>`. |
| `check_date`, `checked_by`, `check_reason` | When, by whom, and why. |
| `overall_condition` | Rating used by the dashboards. New checks start `pending`; completion sets `good`, `fair`, `poor`, or `unfit`. The risk dashboard targets `poor` and `critical`. |
| `condition_note`, `completeness_note`, `hazard_note` | Narrative notes. |
| `recommended_treatment`, `treatment_priority` | Conservation recommendations. |
| `environment_recommendation`, `handling_recommendation`, `display_recommendation`, `storage_recommendation`, `packing_recommendation` | Care guidance. |
| `photo_count` | Denormalised photo count used by the photo gate. |
| `template_id`, `material_type`, `workflow_state` | Template binding and workflow state. |

### `spectrum_condition_photo`

Photographs attached to a check. Notable columns: `condition_check_id`, `photo_type` (before, after, detail, damage, overall, other - default `detail`), `caption`, `filename`, `original_filename`, `file_path`, `file_size`, `mime_type`, `width`, `height`, `photographer`, `photo_date`, `camera_info`, `sort_order`, `is_primary`, and `annotations` (a JSON column holding the on-image annotation regions).

### `spectrum_condition_template` and related

Reusable assessment templates: `spectrum_condition_template` (name, code, material_type, is_active, is_default, sort_order), with `spectrum_condition_template_section` and `spectrum_condition_template_field` (field_name, field_label, field_type - text, textarea, select, multiselect, checkbox, radio, rating, date, number - plus JSON `options`). Per-check field values are stored in `spectrum_condition_check_data`.

### Other supporting tables

The install also creates `condition_report`, `condition_image`, `condition_damage`, `condition_event`, `condition_assessment_schedule`, `condition_conservation_link`, `condition_vocabulary`, and `condition_vocabulary_term`. These hold the richer condition-report model and the site-configurable condition vocabulary (damage_type, severity, condition, priority, material, location_zone), including UI colour and icon hints. The screens documented above read the `spectrum_*` tables; the `condition_*` tables back the broader reporting model.

---

## Configuration

### Photo storage and quota

- Uploaded photos are processed by the media-processing photo processor, which writes a master file and small, medium, and large thumbnails, and extracts EXIF metadata (photographer, date, camera).
- Before processing, the upload is checked against the owning repository's storage quota (`spectrum_condition_check.object_id` -> `information_object.repository_id`). If the repository is over its cap, the upload is refused up-front and logged (`[condition] uploadPhoto rejected by repository quota`) so no processing is wasted.
- Photos are served to the annotation screen from `/uploads/condition_photos/{filename}`.

### Require photos before completion

- **Setting:** `spectrum_require_photos`, on the Spectrum settings page at `/admin/ahgSettings/spectrum`.
- When enabled, a condition check cannot be moved off `pending` to a real rating until at least one photo is attached. The gate uses the denormalised `photo_count`, falling back to a live count, and raises an operator-readable message (surfaced as a flash error, not a 500) when no photo exists.

### Condition vocabulary and dropdowns

- Condition terminology (damage types, severity, condition ratings, priorities, materials, location zones) is stored in `condition_vocabulary` / `condition_vocabulary_term` and the central Dropdown Manager, not hardcoded.
- On first boot the module seeds three AI-related dropdown taxonomies into `ahg_dropdown` (idempotent insert-or-ignore): **AI Assessment Source**, **AI Service Tier**, and **AI Confidence Level**. This replaces a stored procedure that PDO could not parse; the seeder retries on the next boot if the schema is not yet ready.

### Audit logging

Condition-check creation, completion, photo upload, annotation update, and photo deletion are all written to the audit log via the core audit support, capturing the relevant ids and key fields for each mutation.

### Access control

All condition routes require `auth`. The dashboard, list, and risk routes additionally require `admin`. The JSON base-path aliases require `auth` only.

---

## Notes and current behaviour

- New checks are created with overall condition `pending`; the dashboards and risk view rely on the rating being moved to a real value through the completion path.
- Photo deletion sweeps thumbnail siblings even if the master file is missing, to avoid leaving orphaned thumbnails behind.
- The risk dashboard is a Heratio addition (no direct AtoM equivalent) built to surface objects needing conservation attention.

---

## References

- Source package: `packages/ahg-condition/`
- Related package: `ahg-spectrum` (Spectrum 5.1 procedures and the `spectrum_require_photos` setting).
- Issue: [GH #552](https://github.com/ArchiveHeritageGroup/heratio/issues/552)
