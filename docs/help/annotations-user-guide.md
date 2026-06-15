> Heratio Help Center article. Category: User Guide / Annotations.

# Image Annotations

Heratio lets you pin notes, shapes, and quoted-text markers directly onto the images of a digital object. When you open a record's image in the deep-zoom viewer, you can draw a box or freeform shape over a region, attach a comment or tag, and save it. The annotation stays anchored to that exact spot on the image and reappears for the next person who opens it. Annotations follow the open W3C Web Annotation Data Model and the IIIF Web Annotation conventions, so they are portable, standards-based, and not locked into any one viewer. The feature also doubles as the storage layer for AI-generated markers (for example, named entities detected on a page of text), so machine and human annotations live side by side on the same canvas.

## Overview

An annotation is a small record that links a **body** (what you want to say, such as a comment, a tag, or a description) to a **target** (the exact place you want to say it about, such as a rectangular region of a scanned page). Heratio stores each annotation as a full W3C Web Annotation document, so the rich detail of the standard, including multiple bodies, motivations, and many selector types, is preserved exactly as it was created.

Annotations are **canvas-scoped**. In IIIF terms a "canvas" is one page or one image inside a digital object. An annotation pins itself to the canvas's IRI (its stable web address), so it always lands on the right page even when a record has many images.

Reading annotations is open to anyone who can already see the image. Creating, editing, or deleting an annotation requires being signed in. There is no separate admin screen to configure: annotations are created and managed inside the image viewer itself.

## Key features

- **Draw and comment on image regions.** Mark a rectangle or a freeform shape over any part of a zoomable image and attach a note or tag to it.
- **Anchored to exact positions.** Annotations use standard selectors so they reappear in the same place every time, at any zoom level.
- **Wide selector support.** Beyond simple rectangles, the store understands and round-trips:
  - Rectangular image regions (FragmentSelector, `xywh=`).
  - Freeform drawn shapes (SvgSelector).
  - Exact-text anchors inside a text body (TextQuoteSelector and TextPositionSelector).
  - Time ranges on audio and video (TimeSelector and MediaFragmentSelector).
  - Geographic points and areas (GeoSelector and PointSelector).
  - Web-page region anchors (RangeSelector, CssSelector, XPathSelector).
  Any selector type the standard allows is preserved even if it is not in the list above, so nothing is lost.
- **Visibility scopes.** Each annotation can be **private** (only its author), **project** (shared with everyone collaborating on a research project), or **public** (visible to all readers). The default is private.
- **Shared annotation layers.** Search can be narrowed to a project, a visibility level, or a specific author, so a research team can view its own shared layer over a document.
- **Standards-compliant API.** The store speaks the W3C Web Annotation Protocol, including the correct content types, link headers, and container shapes, so external standards-aware tools can read and write to it.
- **Safe concurrent editing.** Each annotation carries an ETag. If two people edit the same annotation at once, the second save is rejected with a clear "precondition failed" message instead of silently overwriting the first person's work.
- **Home for AI markers.** Machine-generated annotations, such as named entities detected by the AI text and entity services, are stored in the same place and can be filtered apart from human notes.

## How to use

### Viewing annotations

1. Open any archival record (information object) that has a digital image.
2. Open the image in the deep-zoom viewer. Existing annotations appear as overlays on the image.
3. Select an annotation overlay to read its note, tag, or description.

Anyone who can see the image can see its public annotations. You do not need an account to read them.

### Creating an annotation

1. Sign in to Heratio. Writing requires an authenticated session.
2. Open the record's image in the deep-zoom viewer.
3. Use the annotation tool to draw a region. You can draw a simple rectangle or a freeform shape over the part of the image you want to mark.
4. Type your comment, tag, or description into the annotation body.
5. Save. The annotation is stored and immediately reappears anchored to the region you drew.

If you try to save while signed out, the viewer reports that authentication is required rather than silently failing.

### Editing and deleting

1. Open the image and select the annotation you want to change.
2. Edit the body, or move or reshape the region, then save; or choose delete to remove it.
3. Edits and deletes also require being signed in, and only succeed if no one else has changed the same annotation since you loaded it.

### Setting who can see an annotation

Each annotation has a visibility level: `private`, `project`, or `public`. Private is the default. When an annotation is scoped to a project, every collaborator on that project sees it as part of the shared annotation layer. Public annotations are visible to everyone who can view the image.

### Behind the scenes (for the technically curious)

Annotations are served over a small REST surface that the image viewer talks to automatically. You do not normally call these yourself, but they explain how the data flows:

| Action | Method and path | Who can use it |
|--------|-----------------|----------------|
| List annotations on a page | `GET /api/annotations/search?targetId=<canvas>` | Public |
| Read one annotation | `GET /api/annotations/{uuid}` | Public |
| Create an annotation | `POST /api/annotations` | Signed-in users |
| Update an annotation | `PUT /api/annotations/{uuid}` | Signed-in users |
| Delete an annotation | `DELETE /api/annotations/{uuid}` | Signed-in users |

The search endpoint also accepts optional `projectId`, `visibility`, and `createdBy` filters, so a viewer can request just one project's shared layer, just public annotations, or just one author's notes. Standards-aware clients can send a `Prefer` header to receive only the list of annotation addresses instead of the full documents.

## AI-generated annotations

The annotation store is also where machine-detected markers are kept. When the AI named-entity service runs over the text of a page, the entities it finds (such as people, places, and organisations) can be written as annotations on that page's canvas, sitting alongside any human notes. These rows carry extra provenance detail recording the entity type, an optional confidence value, and the identifier of the run that produced them, so AI markers can always be told apart from notes a person wrote. All AI processing is performed by the platform's configured AI service; no third-party AI provider is contacted directly from this feature.

## Configuration

There are no end-user settings to configure for annotations. The feature is self-contained:

- The storage table (`ahg_iiif_annotation`) is created automatically the first time the package boots, and additional columns are added in place on existing installs, so no manual migration step is required.
- The image viewer is pre-configured to use the `/api/annotations` endpoint; there is nothing to point it at by hand.
- Read versus write access is governed by the standard sign-in state, not by a separate permission screen. Anonymous visitors read; signed-in users write.

### Maintenance command (administrators)

One console command exists for administrators who run AI entity detection at scale:

```
php artisan ahg:annotations:backfill-ner-columns
```

This fills in the AI-provenance columns (entity type, confidence, run identifier) for older AI-generated annotations that were stored before those columns existed. It is idempotent and supports `--dry-run` to preview changes, `--force` to overwrite existing values, and `--chunk=<n>` to control batch size on large collections. It is not needed for normal day-to-day use.

## References

- Source package: `packages/ahg-annotations/`
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/544
- Standards: W3C Web Annotation Data Model and Web Annotation Protocol; IIIF Presentation API 3.0 canvases.
