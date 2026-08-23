# Where digitisation projects actually fail - the delivery-audit argument

**Summary.** An independent audit of a completed, accepted and paid-for heritage digitisation delivery found that roughly a third of the files were duplicates, that almost none carried the searchable text the contract required, that thousands of images fell below the specified resolution, and that the files called preservation masters were one-bit bi-tonal PDFs from which the tonal information of the original had been permanently discarded. None of it was visible at handover, and almost none of it was the supplier's fault: the specification never required the things that were missing. This is the evidence base for the AHG's standing position that scanning is the solved, low-risk part of a digitisation programme and that the real risk sits in completeness, integrity, resolution, format, searchability and metadata. Written up as a conference paper for the UKS Digitization Conference 2026.

## The finding

A delivery of 6,351 files, accepted on the strength of sampled images. Examined as a set rather than file by file:

- 2,375 of the 6,351 were duplicates of other files in the same delivery.
- 6,342 of the 6,351 carried no extractable text layer, against a contract requiring optical character recognition and naming easy retrieval as an outcome.
- Approximately 12,552 captured images fell below the 300 ppi minimum. The image count exceeds the file count because multi-page items contain many images, which matters: a delivery reported in files can hide a defect distributed across images, and specification and verification must not conflate the two units.
- More than 5,500 files designated preservation masters were one-bit bi-tonal PDFs, a large share using JBIG2. In a one-bit image the greyscale and colour of the original is not compressed but discarded, irreversibly, along with the evidential detail of paper tone, ink density, foxing and condition.

The client is not named in the paper and should not be named in derivative material.

## Why it was invisible

Each defect is a property of the aggregate or of the file's encoding, not of its appearance. Duplication is a relationship between files rather than a property of one. A missing text layer is invisible because the page image still shows the text. Resolution deficiency is invisible on screen, where everything displays at screen resolution. Bit depth is invisible unless someone inspects the encoding, and a one-bit scan of clean printed text can look crisp.

The practical consequence: digitisation quality is mostly not a visual property, so inspection by a professional looking carefully at a screen cannot establish it. Sampling images tests the dimension that rarely fails and leaves untested every dimension that does.

## The specification caused the defects

The supplier substantially delivered what was asked. A specification with no de-duplication requirement gets duplicates. One that requires OCR but defines no acceptance test for a text layer gets no usable layer and breaches no term. One that permits a one-bit PDF as a master gets one-bit masters, because they are smaller and cheaper. One that states a resolution minimum without requiring effective-ppi verification gets whatever the device reported, interpolation included.

This is the liberating half of the finding. Defects that originate in the specification are preventable at the specification stage, which is the cheapest point at which anything in a digitisation programme can be fixed.

## The remedy

Define acceptance before procurement rather than at handover, as criteria a batch passes or fails: completeness with manifest reconciliation, uniqueness by checksum, effective resolution at original size, lossless masters at minimum eight-bit grey or twenty-four-bit colour, a tested text layer, legibility and fidelity, integrity, naming and structure, descriptive and technical and structural metadata, indexing validated against a data dictionary, verified delivery, and AI governance where AI is used. Acceptance becomes arithmetic: full reconciliation, full automated validation, plus risk-based sampling that supplements and never replaces the automated checks. A failed batch is reworked.

Conformance must be measurable rather than asserted. "Scanned to a high standard" is not a standard; a quality target imaged in every batch with a recorded pass or fail is. The instruments exist - ISO 19264-1 for measurable image-quality analysis, ISO/TR 19263-1 for capture practice, Metamorfoze and FADGI for preservation imaging levels, ISO/TR 13028 and ISO 15489 for the records frame. Citing a standard is not enough; the audited delivery cited standards. What matters is requiring the evidence the standard makes possible, as named deliverables: the imaged target, the checksum manifest, the effective-resolution report, the text-extraction result, the reconciliation against inventory.

The verification is cheap. Checksums, header reads, scripted text extraction and a comparison of two lists all automate and run per batch for a small fraction of the capture effort. The audited delivery would have failed four criteria within an hour of its first batch.

## The planning consequence

A production feed scanner handles many thousands of pages a day. The full cycle around it - preparation, capture, quality control, metadata - does not; plan on roughly one to two thousand pages per operator-day for mixed loose records, less for bound, fragile, oversized or handwritten material needing recognition and heavier indexing. These are AHG planning assumptions from project experience, not published measurements, and each institution should calibrate against its own material.

The ratio is the argument. The scanner is about an order of magnitude faster than the human process around it, so it is never the constraint, and a programme costed on scanner throughput is costed at a fraction of what the work needs. The shortfall gets absorbed by whatever compresses most easily, which is preparation, quality control and metadata - precisely the activities whose absence produces every defect above.

## Where this is written up

Full paper (about 3,000 words, nine sections with abstract, keywords and references) in `/usr/share/nginx/conferences/uks-2026/`, markdown source plus branded docx, prepared for the UKS Digitization Conference 2026 (online, 30 September to 1 October 2026, submissions closing 15 September 2026). The short-form argument is the article "The Scanner Was Never the Hard Part" in `docs/articles/`. The full specification, capture table, twelve acceptance criteria and per-batch QA procedures are in the digitisation toolkit reference.

## Related

The digitisation toolkit carries the operational detail this argument rests on. The companion articles "Digitising the Un-Digitisable" and "Born-Digital Was Never a Digitisation Problem" cover material that resists capture and the category error of treating born-digital as a scanning problem. The AI governance controls summarised here are the same human-in-the-loop model used across the AHG's AI work.
