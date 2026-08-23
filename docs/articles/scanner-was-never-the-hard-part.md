---
category: Article
group: Framework
title: The Scanner Was Never the Hard Part
author: Dr Johan Pieterse
slug: scanner-was-never-the-hard-part
status: draft (held for next round)
---

# The Scanner Was Never the Hard Part

### A digitisation project looks finished the moment the scanning stops. That is precisely when it starts going wrong.

We were asked to audit a completed heritage digitisation delivery. By every visible measure it was finished: 6,351 files handed over, boxes closed, invoice paid. Then we actually looked.

2,375 of the files were duplicates. 6,342 of the 6,351 had no searchable text at all, even though the contract required OCR and listed easy retrieval as an outcome. More than 12,000 images had been captured below the resolution the specification demanded. And thousands of the files being called preservation masters were 1-bit bi-tonal PDFs, which is to say the tonal information in the original had been discarded and could never be recovered.

None of that was visible on the surface. Open any single file and it looks fine. The failure was in the aggregate, and in the things nobody had been asked to check.

This is the pattern every time. The scanner was never the hard part.

## The easy part is the part everyone watches

Scanning is a solved problem. A competent operator and a decent machine will turn a page into a clean image all day long. That is the visible, reassuring, measurable-by-the-hour part of a digitisation project, so it is the part everyone watches and the part contracts are written around. It is also the part that almost never fails.

The failures live in everything the image passes through afterwards, and in the one question nobody defines up front: what does "done" actually mean?

## What "done" actually means

Done is not "the pages went through the scanner." Done is:

- every item in the inventory is accounted for, and a manifest reconciles the delivery back to the box it came from;
- no file is an accidental duplicate of another, proven by a checksum on every file rather than by eye;
- every image meets the required resolution measured as effective ppi at the original size, not the number the scanner claims and not an interpolated one;
- the preservation master is genuinely use-neutral and lossless, not a 1-bit shadow of the original;
- every textual item carries a searchable text layer that has actually been tested, because a PDF can contain a text layer that is useless and still pass a glance;
- the metadata links each file to its place in the arrangement, so the collection can be found and not merely stored.

Every defect in that audit maps to one of those lines. And every one of them is invisible unless someone checks it on purpose, on every batch, against a written acceptance criterion.

## The defects were written into the contract

Here is the uncomfortable part. The operator did not cause most of those defects. The specification did.

A spec that never requires de-duplication will be delivered with duplicates. A spec that says "use OCR" but never defines a test for a searchable text layer will be delivered without one. A spec that permits a 1-bit PDF as a preservation format will get 1-bit masters, because they are smaller and cheaper to make. The delivery did exactly what the contract asked. The contract simply never asked for the things that matter.

So the hard part is not the scanning, and it is not even the checking. It is deciding, in advance and in writing, what good looks like, and turning each of those decisions into an acceptance criterion that a batch either passes or fails. Get that right and the scanner is almost an afterthought. Get it wrong and no amount of scanning quality will save you.

## Measured, not asserted

There is a discipline that separates the two. Conformance has to be measurable, not asserted. You image a recognised quality target, an ISO 19264 or FADGI chart, in every batch, so image quality is a number you can check rather than a claim you have to trust. You compute checksums, so uniqueness is arithmetic and not opinion. You verify effective ppi rather than believe the setting. "We scanned it to a high standard" is not a standard. A target in every batch and a per-criterion pass or fail is.

## The hard 80 percent

The model was never the hard part. The ontology was never the hard part. And the scanner was never the hard part either. The image is the easy twenty percent that everyone can see. The hard eighty percent is completeness, integrity, resolution, format, searchability and metadata, none of which announce themselves, all of which have to be defined and then proven on every batch, indefinitely.

When you next commission digitisation, do not buy scanning. Buy the acceptance criteria, and the per-batch verification that proves them. The scanner will do its job. The real question is whether anyone has decided, and written down, what "done" has to mean.
