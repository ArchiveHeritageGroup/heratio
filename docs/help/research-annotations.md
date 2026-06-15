> Heratio Help Center article. Category: Research / Notes.

# Research Notes and Annotations

## A Guide for Researchers

---

## What are research notes?

Research notes let you capture your own thoughts against the collection. A note has a title, content, optional tags, and a visibility setting, and it can be linked to a specific archival item so your reasoning stays attached to the evidence. You can search, filter, and tag your notes, and export them as PDF or CSV for writing up.

Open your notes from the Research sidebar ("My Notes and Annotations") at `/research/annotations`.

---

## Key features

- **Card grid** view of all your notes, with a thumbnail when the linked item has one.
- **Search** across note titles and content.
- **Filter by visibility**: private, shared, or public.
- **Tag cloud** showing every tag you have used, with counts, clickable to filter.
- **Link** a note to an archival description, an actor, a repository, an accession, or a term.
- **Export** selected notes, or all notes, as PDF or CSV.
- **Per-note** PDF and CSV export, plus a permalink to jump straight to a note.

---

## Creating a note

You can add a note from the notes page itself, or while looking at an archival record.

```
+------------------+
|  Click Add Note  |
+--------+---------+
         |
         v
+------------------+
|  Enter title     |
|  (optional)      |
+--------+---------+
         |
         v
+------------------+
|  Set visibility  |
+--------+---------+
         |
         v
+------------------+
|  Write content   |
|  (required)      |
+--------+---------+
         |
         v
+------------------+
|  Add tags        |
|  (optional)      |
+--------+---------+
         |
         v
+------------------+
|  Save            |
+------------------+
```

| Field | Notes |
|-------|-------|
| **Title** | Optional short label. |
| **Visibility** | Private, shared, or public (see below). Defaults to private. |
| **Content** | Required. The body of your note. |
| **Tags** | Optional, comma-separated, for example "genealogy, 19th century, photographs". |

When you create a note from a record, the note is automatically linked to that item.

---

## Visibility

Each note carries one of three visibility settings:

| Visibility | Who can see it |
|------------|----------------|
| **Private** | Only you (the default) |
| **Shared**  | Project collaborators |
| **Public**  | All researchers |

You can change a note's visibility at any time by editing it.

---

## Finding notes again

The notes page gives you three complementary ways to narrow down:

- **Search box** - type a term to match titles and content.
- **Visibility buttons** - show only private, shared, or public notes.
- **Tag cloud / tag links** - click any tag to show only notes carrying it. Tags also appear as clickable badges on each card.

Each note card carries a permalink so you can link straight to a specific note.

---

## Exporting

- **Bulk export**: select notes and export them together as PDF or CSV.
- **Per-note export**: each card has its own PDF and CSV links.
- **CSV** includes title, content, tags, visibility, and the created date.
- **PDF** renders a printable page including your name and the note details.

---

## Annotating image regions

When you are working with a digitised image, you can go beyond a plain note and pin an annotation to a specific region of the image. This advanced studio is reached from a record at `/research/annotations/{slug}` and follows the W3C Web Annotation model, so an annotation can target one or more regions of a canvas. Plain notes and region annotations live side by side.

---

## References

- Source: `packages/ahg-research/` and `packages/ahg-information-object-manage/`
- Stored in: `research_annotation`, `research_annotation_v2`, `research_annotation_target`
