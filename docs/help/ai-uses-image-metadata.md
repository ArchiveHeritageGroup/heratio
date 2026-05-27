# AI services use embedded image metadata to ground their answers

When you upload a photograph, Heratio's metadata extractor reads the EXIF, IPTC, and XMP tags the camera and your editing software wrote inside the file. Beginning with issue #750, those tags are no longer just stored - they are now fed to the AI services as **context hints** so the model can disambiguate entities it would otherwise have to guess at.

## What you will notice

When you run AI tools (NER, handwriting recognition, "AI Suggest Description", document extraction) against an information object that has a digital object attached, the AI sees a short prefix in its prompt:

```
Hints from image metadata: date=1969-07-20 20:17:40; location=28.0473,-26.2041; creator=Neil Armstrong; subjects=Apollo, Moon, NASA. Use these to disambiguate entities.
```

Practically this means:

- **Better dates.** The NER service stops inventing dates - it sees the real EXIF DateTimeOriginal and grounds extracted date entities accordingly.
- **Better places.** When GPS coordinates are embedded, the AI knows where the photo was taken and is far less likely to confuse a place name like "Paris" between France and Texas.
- **Better creator attribution.** IPTC By-line and XMP dc:creator are surfaced so the AI doesn't fabricate a photographer.
- **Better subjects.** IPTC Keywords already curated by a cataloguer are made visible to the AI so its suggestions stay aligned.

## What the AI does not see

- **Suppressed GPS.** If the Privacy team has flagged a coordinate as sensitive (issue #751 PII findings), the location hint is removed entirely from the AI's prompt. The AI cannot leak a coordinate it was never given.
- **Empty metadata.** If a digital object has no embedded EXIF / IPTC / XMP - for example, a scanned page from an old film negative - no hint prefix is added and the AI sees the same prompt it saw before this change.
- **Other digital objects.** Hints are computed per-file. The AI only sees hints from the specific image it is processing.

## Audit trail

Every AI call that consumes hints writes an `inference_context_used` row to the inference receipt chain. Operators can ask, for any AI inference: "exactly what hints did the model see?" The answer is recoverable from `/admin/ai-compliance/receipts`.

## Settings

There is no new operator-facing toggle. The behaviour is automatic: if metadata has been extracted into the property table (which `ahg-metadata-extraction` does on upload), the hints are visible to the AI. To stop hint injection, clear the metadata properties for the digital object.

GPS coordinates respect the existing `meta_extract_gps` operator toggle - if GPS extraction is disabled at the metadata layer, the AI never sees coordinates either.
