> Heratio Help Center article. Category: Viewers & Media.

# NER Annotations on IIIF - Auto-Tag Entities on Page Images

## User Guide

Heratio can read the text on a page image (the OCR output), find the named entities in it (people, organisations, places, dates), and pin each entity to the exact word on the image as a Web Annotation. The annotations show up in the IIIF viewer's annotations side panel and as outlines on the page itself - clicking an outline highlights the entity and shows its label.

This guide covers what NER annotations look like, how to turn them on for an archival record, and what to do if you don't see them.

---

## What you see in the viewer

When NER annotations exist for a canvas, the IIIF viewer adds an annotations companion panel (the "comments / tags" toggle in the Mirador toolbar). Each entry in the panel is one entity:

- The entity text - e.g. **Nelson Mandela** or **Cape Town**.
- The entity type tag - **Person**, **Organization**, **Place**, or **Date**.
- (Optional) a link out to a reference URI when the NER service was able to resolve the entity to a Wikidata QID or a similar identifier.

Clicking the entry zooms the viewer to the word on the page and highlights its bounding box.

---

## How to enable NER annotations for an archival record

NER annotations are produced by a background job. Before you run it, two things have to be in place:

1. **OCR text must exist for the record.** Open the archival description, scroll to the Digital Object section, and check the "OCR / Transcription" tab. If the tab is empty, run OCR first (Tools menu -> "Run OCR" or the OCR step in the Scanner Capture pipeline).
2. **NER must be enabled in AI Services.** Go to Settings -> AI Services -> Named Entity Recognition and confirm the "Enable" checkbox is ticked.

Then dispatch the job:

1. Open the archival description.
2. From the **AI Tools** menu, choose **Annotate entities on canvases**.
3. The job runs in the background. Refresh the IIIF viewer after a minute or two and the annotations panel should populate.

If you want to limit the run to a single digital object (e.g. one TIFF, not the whole multi-image record), pass `digital_object_id` when you dispatch the job from the artisan console:

```bash
php artisan iiif:ner-annotate <io-id> --digital-object=<do-id>
```

---

## What the annotations look like under the hood

Each annotation is a standards-compliant **W3C Web Annotation** document with:

- `motivation: tagging` - this is the canonical motivation for entity tags.
- `target` - a region on the canvas, expressed as an IIIF FragmentSelector xywh box. The region is the OCR word block that matched the entity's first word.
- `body` - a TextualBody with the entity text, plus a classifier TextualBody with the entity type. If the entity has a Wikidata URI, a SpecificResource body carries it as well.

The annotations live in the same store as your manual annotations - everything you can do with a manual annotation (edit the label, delete it, change its visibility) also works on a NER-generated annotation.

---

## Quality controls

The bridge has two built-in filters so it doesn't flood you with noise:

- **OCR-block confidence floor.** If the underlying OCR word has a confidence below 30%, the bridge skips the box. Very-low-confidence OCR usually pins entities to the wrong region.
- **Per-type cap.** No more than 100 annotations of the same entity type land on a single canvas. This prevents runaway emissions on watermarked or repetitive pages.

If you find the defaults too aggressive for your archive, the operator can raise or lower both in the package config (see the operator reference doc).

---

## Cleaning up a run you don't like

Every annotation produced by a single dispatch carries the same `run_id` provenance marker. To remove every annotation from one specific run:

1. Open Settings -> AI Tools -> NER Annotation Runs.
2. Find the run by timestamp / archival record.
3. Click **Delete run** - this removes every annotation tagged with that run's id and leaves your manual annotations and previous runs alone.

You can also re-run the job at any time. Re-runs are append-only - the previous run's annotations are not removed unless you delete the run yourself first. This is deliberate so you can compare two NER passes side by side if you upgraded the NER model.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Annotations panel is empty after a job run | OCR text was empty or only contained stop-words | Re-run OCR with a higher-quality pipeline (Tesseract LSTM, Donut for handwriting). |
| Annotation outlines are in the wrong place | OCR word boxes were misaligned | Re-run OCR; the bridge can only be as accurate as the underlying OCR boxes. |
| Same entity tagged twice on the same word | Two NER models agreed on the entity | This is the expected behaviour today. Re-running the job does NOT dedupe across runs - delete one run if both are duplicates. |
| No "Annotate entities" entry in the AI Tools menu | NER is disabled in AI Services settings | Settings -> AI Services -> Named Entity Recognition -> Enable. |
| Job ran but the viewer's annotation panel is empty | The viewer's annotations layer is toggled off | Open Mirador's "Comments / Tags" sidebar toggle. |

---

## Related help

- "Run OCR on a digital object" (`ocr-user-guide.md`)
- "AI Services - Named Entity Recognition" (`ahgaiplugin.md`)
- "Edit and review annotations in the IIIF viewer" (`iiif-annotations-user-guide.md`)
