# Research Writing Studio

The Writing Studio is a per-project, write-as-you-go editor. You draft your
chapter, article, review, or section right inside the research project, and you
cite your own claims and pull in your own sources without leaving the page.

Open it from any project at **Research > Projects > [your project] > Writing
Studio** (URL: `/research/projects/{id}/writing`).

## Documents

A document is one writing piece in your project. Each document carries:

- **Title** - what you are writing.
- **Type** - Thesis chapter, Journal article, Literature review, Section, or
  Other.
- **Status** - Draft, In review, Final, or Archived.

Create a document from the **New Document** button on the Writing Studio page.
You can change the title, type, and status at any time from the editor header.

## Writing as you go

A document is made of ordered **sections**. Each section has an optional heading
and a body. Add a section, write into it, and press **Save** - there is no
separate "compile" step. Add as many sections as your piece needs; reorder by
adding them in the order you want to write.

Every save updates the document's "last updated" time so the document list keeps
your most recent work at the top.

## Cite a claim

The editor sidebar lists the claims you have built in this project's Claim
Ledger. Pick a claim, pick the section to cite it into, and the studio inserts
the claim text with a `[Claim #N]` marker so you can always trace the assertion
back to the ledger. The Claim Ledger itself is never changed - the studio only
reads from it.

## Pull a source

The sidebar also lists the sources in this project's bibliography. Pick a source,
pick the section, and the studio appends a formatted reference (authors, date,
title, container, publisher) with a `[Source #N]` marker. Your bibliography is
never changed - the studio only reads from it.

## Versions

Press **Save version** to capture a full snapshot of the document (title plus
every section) as Markdown. The version history lists every snapshot with its
note and timestamp, and you can open any one to read it. Versions are a safety
net: you can always see what the document looked like at an earlier point.

## Markdown export

**Export Markdown** downloads the whole document as a `.md` file - the title as a
top-level heading, each section heading as a sub-heading, and the bodies as
prose. Use it to move your draft into any other tool.

## Optional AI drafting

If AI is enabled on your install, each section has an **AI draft** action. It
sends the section, your optional instruction, and a digest of the project's
claims and sources to the AHG AI gateway and returns a draft. The draft is
clearly labelled **AI-assisted draft (review required before use)** and is shown
for your approval - it is never saved automatically. Review it, edit it, and only
then accept it into the section. If AI is switched off, the studio works exactly
the same in every other respect.

All AI calls route through the AHG AI gateway; the studio never talks to an
inference node directly.
