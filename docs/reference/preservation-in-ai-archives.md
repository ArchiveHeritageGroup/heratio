# Digital Preservation in AI-Era Archives (AHG thought-leadership position)

**Summary:** Digital preservation is the neglected, unglamorous half of the archive, and AI has made it more urgent rather than less. This is AHG's published position (Heratio blog article #27, "Preservation: The Unglamorous Half of the Archive That AI Just Made Urgent", by Dr Johan Pieterse, group "Framework"). It sits alongside the earlier "The Model Was Never the Hard Part" framework piece and the KARMA 2026 "epistemic transparency" paper.

Public URL: https://heratio.theahg.co.za/articles/preservation-the-unglamorous-half-of-the-archive-that-ai-just-made-urgent

## What preservation is (and is not)

Digital preservation = active, ongoing, managed activities that keep digital objects **usable** (not merely stored) over time, across technology change, with authenticity and integrity **provable**. Three load-bearing words: active (a forever process, not a state), usable (keeping bits is necessary but insufficient if the format is dead), provable (must demonstrate, not assert).

Preservation is NOT backup. Backup = recover last Tuesday after an accident (days/weeks). Preservation = authentic, readable, trustworthy after the vendor dies, the format is forgotten, the creators retire (decades/centuries). A backup strategy with no preservation strategy is a fast car with no destination.

## Why AI makes preservation MORE urgent

1. **AI multiplies the objects to preserve.** Transcriptions, extracted entities, confidence scores, summaries, embeddings, audit logs are records in their own right (the machine's interpretation of the collection). Preserve the original scan but discard the AI-derived layer and you lose the interpretation permanently.
2. **AI raises the authenticity bar.** Amid confident machine-generated text/synthetic media, "is this the real record?" and "who/what produced this metadata?" are operational questions. Fixity, provenance and immutable audit become load-bearing. Ties to AHG's epistemic-transparency argument: provenance you fail to preserve is provenance you never had.
3. **Models are ephemeral.** To explain/audit/reproduce an AI decision years later you must have preserved which model, which version, on which input, with which result. Preservation turns a fleeting inference into a durable, accountable fact.

Framing: AI is a preservation problem wearing an access costume.

## Why preservation is neglected (four structural reasons)

- Failure is invisible and delayed (no complaint until years later when a file won't open).
- There is no demo (access has a UI; preservation is fixity manifests and format registries).
- It competes with the visible (shiny AI feature this quarter beats checksums preventing an unnoticed loss).
- Everyone assumes someone else does it ("IT has backups" / "it's in the cloud" / "the vendor handles it"). Cloud storage is a substrate, not a preservation programme.

## Format obsolescence

Bits can survive perfectly and you still lose everything if the format dies. Two defences, used together by mature institutions:

- **Migration** - periodically convert into current, open preservation formats (TIFF/JPEG2000 for images, PDF/A for documents, open AV containers). Controlled documented change now to avoid catastrophic loss later; track what changed.
- **Emulation** - keep original bits, recreate the reading environment (old OS/app). Higher fidelity, higher complexity.

**Significant properties** = what must actually survive (map: spatial accuracy + resolution; spreadsheet: formulas not just rendered numbers; email: headers + thread not just body). Migration targets become tomorrow's obsolete formats, so this is a tide managed forever.

## Offline storage / 3-2-1

Canonical rule: **3-2-1** = >=3 copies, on >=2 media types, >=1 off-site. The piece institutions skip and the one that matters most: **>=1 copy offline, air-gapped, immutable.** Why offline specifically:

- **Ransomware / malicious deletion** - anything reachable from a compromised live system (online copies, synced replicas, credentialed cloud mirrors) can be encrypted/wiped in one operation. Air-gapped is unreachable.
- **Propagated error** - sync is not preservation; a corruption / bad migration / mass-delete replicates instantly to every online copy. An offline known-good copy breaks the propagation chain.
- **Silent bit rot** - media degrade; without fixity checks against an independent offline baseline you can back up already-corrupted data for years.

Cloud is good infrastructure and a legitimate part of the 2 and the 1, but a single provider is a single point of failure. Diversity of media, location, provider and connectivity is the actual insurance.

## PREMIS

**PREMIS** (Preservation Metadata: Implementation Strategies) = international standard data model for the metadata needed to preserve digital objects and to PROVE it. Entities:

- **Objects** - the things preserved (files, representations, bitstreams) + technical characteristics.
- **Events** - everything that happens (ingest, fixity check, format migration, virus scan, replication); the object's timestamped, attributable life story.
- **Agents** - who/what performed each event: person, organisation, or software (increasingly an AI model).
- **Rights** - what may lawfully be done, and the basis.

Power is in the Events chain: a complete tamper-evident history lets you demonstrate authenticity/integrity rather than assert it. Extends naturally to AI: an AI transcription is an Event by an Agent (model + version) on an Object, producing a new Object with its own rights and preservation obligations. Fixity, provenance and audit are the same primitives whether the actor is a scanner or a language model.

## How to do it properly

1. Adopt **OAIS** (ISO 14721) - SIP/AIP/DIP; ingest, storage, data management, access.
2. Ingest into open, well-supported preservation formats; record significant properties.
3. Generate fixity at ingest, re-check on a schedule against copies that include an offline baseline.
4. Record everything in PREMIS (event, agent, migration) - if it isn't in preservation metadata it can't be proven.
5. Run 3-2-1 with a genuine air-gapped immutable copy (not sync, not "three cloud regions").
6. Monitor formats actively against a registry; migrate on policy, not panic.
7. Treat AI outputs as first-class preservable records (provenance, confidence, verification state, retention).
8. Budget as a permanent operating cost, not a project - preservation has no finish line.

## One-liners

- Access is a promise about today; preservation is a promise about the future - only one is hard to keep.
- Backup is not preservation. Sync is not preservation. "It's in the cloud" is not a strategy.
