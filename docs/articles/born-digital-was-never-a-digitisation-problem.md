---
category: Article
group: Framework
title: Born-Digital Was Never a Digitisation Problem
author: Dr Johan Pieterse
slug: born-digital-was-never-a-digitisation-problem
---

# Born-Digital Was Never a Digitisation Problem

### The record was digital from birth. There is nothing to scan, and that is exactly why so many institutions get it wrong.

An archive is handed a hard drive by a retiring executive. Twelve years of email, board papers, a finance database, thousands of photographs, a folder of CAD drawings for a building that no longer exists. Someone asks the obvious question: "When do we scan it?"

You don't. There is nothing to scan. The record is already digital. And the moment that question gets asked, the institution has made a category error that will cost it the collection.

We have spent this series pulling apart the same illusion in different clothes. The model was never the hard part. The ontology was never the hard part. Preservation is the unglamorous half we chose to ignore. Born-digital is the next one, and it is the sharpest of them, because it removes the safety net entirely.

## The category error

Digitisation makes a copy. A fragile 1953 minute book goes under a camera and a faithful surrogate comes out the other side. If the file is lost, the minute book is still on the shelf. The original survives its own digital shadow.

Born-digital records have no shelf. The email is the record. The database export is the record. The file is the original, not a stand-in for something more real sitting in a box. Lose the bytes and there is no fallback, no re-scan, no second attempt. That single fact changes everything downstream, and almost none of the usual digitisation thinking carries over.

There is no capture stage. No resolution to choose, no colour target, no effective ppi to measure. The disciplines a digitisation team is genuinely good at simply do not apply. What applies instead is custody.

## Custody is the whole job

Think about what "authentic" means for a born-digital record. For the minute book, authenticity is anchored in the physical object: the paper, the ink, the binding all testify to it. For an email there is no object. The only thing standing between a genuine record and a plausible fake is an unbroken, documented chain from the moment the records left the creator's hands.

That is why accession is not a copy operation. It is evidence-gathering. When records arrive on a carrier you read them through a write-blocker, so that the act of reading cannot alter the source. You take a forensic image of the whole drive, not just the visible files, because the file-system metadata, the timestamps, the arrangement, and sometimes the deleted-but-recoverable material are all part of what the record is. Tooling built for exactly this exists and is free: the BitCurator environment is the sector's workbench for it. You write down who handed it over, from whom, when and how. That transfer record is itself a permanent preservation record.

Then you prove nothing has changed since. You compute a checksum for every file at the point of capture, SHA-256 will do, and you re-verify it on a schedule and after every copy or migration. You record every action that touches the object as a preservation event using the PREMIS data model, so that in ten years the object can account for its own history. This is not bureaucracy. It is the entire basis on which a record with no physical anchor can be trusted at all.

## Understand it before you preserve it

A file with a .pdf extension is not necessarily a PDF. Before you can preserve anything you have to know what it actually is, whether it is valid against its own specification, and what about it matters for the long term.

The tools here are mature and, again, open. PRONOM is The National Archives format registry that gives each format a stable identifier; DROID and Siegfried identify files against it at scale; JHOVE validates them and tells you whether an object is genuinely well-formed or merely wearing the right extension. Run them across an accession and you get a format profile: what you are holding, in what versions, and which objects are already broken and need attention now, rather than in a decade when the software that made them is gone.

## The threat is obsolescence, not decay

Paper rots slowly and visibly. Digital formats fail suddenly and invisibly: the software that renders them disappears, and one day the file opens to nothing. So format policy is not optional. You always keep the original bitstream. Where a format is at risk you also make a preservation copy in something open, documented and widely supported, and you record the relationship between the two as a provenance event. You watch formats against community intelligence, the Digital Preservation Coalition publishes precisely this, and you migrate before the cliff, not after.

None of this is a single storage decision. It is a running service: more than one copy, at least one off-site, fixity checked over time, repaired from a good copy when a check fails, and measured against a published trustworthiness standard such as ISO 16363 rather than a vague assurance that it is "backed up." Backup is not preservation. We said that about physical records. It is more true here, because there is no physical record to fall back on.

## Where the governance actually lives

Notice what this list is not. It is not a scanner specification. It is a sequence of decisions and disciplines: what to keep and what to let go, whether an original is retained or normalised, who may see it and when, how integrity is proved, who is accountable for the running service that keeps it alive. Every one of those is a governance question wearing a technical costume.

The standards that answer them have existed for years and cost nothing. OAIS gives you the shape of the whole system, with its submission, archival and dissemination packages. METS binds the structure, PREMIS carries the history, Dublin Core supports discovery, and BagIt moves the bytes with their checksums attached. The toolchain is not the constraint. The constraint is whether an institution will make the decisions, and make them consistently, on every accession, indefinitely.

## Why this one has no escape hatch

With a digitised collection you can be sloppy and get away with it for a while, because the original is still on the shelf to correct you. Born-digital removes that grace. Get the custody wrong at accession, skip the fixity, guess at the format, leave the storage on a single unmonitored disk, and the record does not degrade politely over decades. It is simply gone, and you often will not know until someone asks for it.

The model was never the hard part. The ontology was never the hard part. And born-digital was never a digitisation problem. It is a governance problem that refuses to let you pretend otherwise, because for the first time there is no object behind the file to save you from your own process.

The good news is the same as it has been all along. The hard part is not the technology. It is deciding to do the work, and building the discipline to keep doing it. The tools are waiting, and they are open, proven and free. The one thing they cannot supply is the decision to use them before the drive in the drawer becomes unreadable.
