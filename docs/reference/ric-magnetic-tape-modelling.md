# RiC modelling: magnetic tapes with mixed-provenance tracks (Record vs Record Part)

**Summary:** When cataloguing an audio carrier (e.g. an ethnographic magnetic tape) whose individual tracks have different provenance (different agents, places, dates, topics), model the **tape as a Record Resource** and **each track as a Record Part** - or as separate Record Resources where tracks need independent curatorial lives. Capture the per-track provenance as **typed Relations and Events** in the RiC graph, and store the assertion metadata (who asserted it, when, on what evidence, with what confidence) **on the relation itself**. That is RiC-CM's intended pattern: context is a graph and relations carry meaning.

This note answers a recurring archival-modelling question and records the canonical pattern for Heratio's RiC-O implementation.

## The question

Cataloguing ethnographic magnetic tapes (PhD project): from a RiC perspective, is it appropriate to use the **Record** entity for the tape as a whole and the **Record Part** entity for each track, given that tracks often have mixed provenance (different agents, places, dates, topics)?

## Short answer

Yes. Model the physical tape as a **Record Resource** and each track as a **Record Part** (or as separate Record Resources if you need item-level descriptions or the tracks are reused/lent/curated independently). Model provenance (creators, dates, places, custody events) as **Activities/Events** and as **typed Relations** linking the Record Resource/Parts to Agents/Places/Mandates. Record who asserted each relation and the evidence for it **on the relation** (RiC allows relations to carry properties, including source and confidence).

## Why

- RiC-CM treats context as a graph where relations carry meaning. A tape is a single physical carrier (Record Resource) whose internal components - tracks - can legitimately have different provenances. Representing tracks as Record Parts preserves the physical whole/part relationship while letting you attach separate provenance chains to each part.
- RiC explicitly allows relations to have properties (start/end dates, asserting agent, evidence, confidence). That is the right place to record the differing provenance (who asserted that Track 2 was created by Agent X on Date Y in Place Z) rather than forcing all provenance onto the tape-level record.
- If some tracks are functionally independent (different creators, reused tracks, later editing), prefer modelling tracks as distinct Record Resources linked to the tape via hasPart/isPartOf. RiC is agnostic about granularity; both are valid depending on how the data will be used.

## Practical modelling pattern (entities and relations)

- **Record Resource** (tape) - physical carrier; identifier, physical description, carrier type.
- **Record Part** (track) - component of the tape; track number, duration, format, technical note.
- **Agent** - person/corporate/collective (performer, recorder, donor).
- **Activity** - creation, editing, transfer, digitisation, accession, cataloguing.
- **Event** - custody change, migration (tape to digital), accession, appraisal, disposition.
- **Place** - recording place, custody locations.
- **Mandate** - donor deed, copyright, access restriction.
- **Relations** - typed edges such as RecordResource hasPart RecordPart; RecordPart wasCreatedBy Agent; RecordPart hasSubject Topic; RecordPart hasOrHadCustodian Agent; Activity generated RecordPart. Put provenance metadata (assertedBy, evidence, confidence) on the relation.

## Where to record "who asserted what"

- **On relations and on Events.** RiC recommends that assertions and their evidence live with the relation: the relation carries properties (assertedBy, assertedAt, evidenceRef, confidence).
- For machine-aided assertions (audio analysis, AI diarisation), record an Evidence/Assertion relation pointing to the algorithm run (as an Agent), its parameters, a confidence score, and a reference to a supporting artefact (spectrogram, transcript excerpt). Heratio's AI inference provenance pattern fits here as the evidence object the relation references.
- Also record Events for observable facts (custody transfers, digitisation). These become part of the Record Part's provenance chain and enable path queries like "who had custody of track 3 between 1982 and 1990?"

## Two options (choose by use-case)

**Option A - tracks as Record Parts** (recommended when the tape is primarily a carrier, described at tape level but needing per-track provenance):
- Tape: RecordResource R1; Track 1: RecordPart P1 (R1 hasPart P1).
- P1 wasCreatedBy Agent A, with relation properties assertedBy=Cataloguer X, evidence=label, confidence=1.0.
- P1 generated-by DigitisationActivity D1 -> DigitalObject DO1; Event D1 has timestamp + operator Agent.

**Option B - tracks as Record Resources** (recommended when tracks are treated as independent items in access/reuse or have their own accession histories):
- Tape: RecordResource R1; Track 1: RecordResource T1 (R1 hasPart/contains T1).
- T1 has its own chain of Events and Relations (creation, transfer, donor).
- Use when tracks are rehoused, lent, or curated independently.

## Cataloguing notes

- Use the relation's assertedBy/evidence fields to keep the cataloguer's judgement separate from the track's creator field - preserving the difference between "cataloguer asserts this provenance" and "the provenance itself".
- Record physical/capture metadata on the tape (reel id, tape speed, format) as properties of the Record Resource. Record track-level technical metadata (start/end time, duration, channel, sampling) on the Record Part.
- On digitisation, record a Digitisation Event linking the Record Part to a Digital Representation, capturing checksums, file format, and file-level provenance.

## Minimal RiC triples (pseudo-RDF)

```
R1 a RecordResource ; R1 hasPart P1 .
P1 a RecordPart ; P1 wasCreatedBy A1 [ assertedBy: C1 ; evidence: 'label-001' ; confidence: 0.9 ] .
P1 wasGeneratedBy Activity D1 ; D1 performedBy Agent Digitiser ; D1 atTime "2023-02-14" .
P1 hasOrHadCustodian Agent ArchivalDepot between Event E1 and E2 .
```

## One-paragraph answer

Model the tape as a Record Resource and the tracks as Record Parts (or as separate Record Resources where tracks need independent curatorial lives). Capture track-level provenance as relations and Events in the RiC graph, and store the assertion metadata (who asserted it, when, evidence, confidence) on the relations themselves - RiC's intended pattern. For AI or algorithmic suggestions, capture the algorithm run as an Agent and write inference-provenance evidence that the relation references.

Source: archival-modelling forum exchange (Valentin Mansilla question), captured for Heratio RiC-O guidance.
