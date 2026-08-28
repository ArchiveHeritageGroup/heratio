# When a single tool call de-seals a document

Date: 2026-08-28
Author: Dr Johan Pieterse
Status: fixed in the office and docx MCP servers; the working habit matters more than the fix

Some documents are built to withhold part of themselves from the reader. A blind-coding instrument keeps its answer key on a separate sheet. An assessment keeps its marking memo behind the questions. A sealed tender keeps its scoring apart from its submission. The structure carries an instruction: *read this much, not that much, and not yet*.

A file-reading tool does not see that instruction. It sees a container and returns the contents.

## What happened

Reading a research practice workbook returned every sheet in one response - the questions and the reference key together. No warning, no prompt, no opt-in. The call looked like "open this spreadsheet" and behaved like "reveal everything in it".

The exercise was a blind independent coding study, where the entire value of the coder's work rests on not having seen the researcher's classifications first. In this instance the cost was small: the practice set is training material, ships with a key intended to be opened afterwards, and carries no weight in the reliability statistic. Against the sealed formal sample the same call would have silently voided the coder's independence.

**And it would not have shown.** The returned workbook would look untouched. Nothing in the artefact records that the key was read. The only person who could know is the one who made the call.

## Why this is worth writing down

The failure is not really a bug in a tool. It is a mismatch between two models of a document.

To the author, a workbook is a sequence with a gate in it. To a reader tool, it is a bag of sheets. Everything that makes the gate meaningful - the ordering, the instruction to save your answers first, the expectation that a professional will not peek - lives outside the file format entirely. No amount of care in constructing the instrument protects it, because the protection was never encoded.

That generalises well beyond spreadsheets. The same shape appears wherever a container holds material the reader is meant to reach in a particular order, or not at all: examination papers, sealed bid evaluations, peer review with author identities withheld, staged disclosure in litigation, anything with an embargo.

## The habit that actually protects you

**Do not bulk-read a document that may be structured to withhold part of itself.**

Enumerate first, read second. List the sheets, the slides, the sections - metadata only - then read the specific part you are entitled to see. Treat "read the whole file" as an unsafe default the moment there is any chance the file is sealed, staged or blind.

This costs one extra step and removes an entire class of irreversible mistake. Listing sheet names does not reveal their contents.

The corollary matters too: **record your own results before reading anything that might contain someone else's.** In any exercise measuring independent judgement, the order of operations is the control. Once you have seen the answer you cannot un-see it, and no assurance afterwards is worth anything.

## What was changed in the tooling

The reading tools now make the hazard visible rather than relying on the caller to remember it:

- A spreadsheet read that is not given an explicit sheet now **withholds sheets whose names advertise answers** - key, solution, marking, memo, sealed, locked, confidential, restricted, classified, grader, scoring - and lists them separately with a notice. Every other sheet is returned as before, so ordinary work is unaffected.
- Naming the sheet explicitly still returns it. This is a guard against reading something by accident, **not a lock**. A tool that refused legitimate access would simply be worked around, and would teach people to distrust it.
- Presentation reads accept a slide range, and document reads accept a character cap, so a caller can look at the opening of something without pulling the whole of it.
- The tool descriptions now state the hazard, so the caller is told to name what it may see rather than discovering the problem afterwards.

**The limits are worth being honest about.** This catches sheets that name themselves. A key hidden on a sheet called "Sheet3" still comes through on a default read. The guard is a backstop for a lapse in habit; it is not a substitute for the habit, and it cannot be, because the tool has no way to know which parts of a document you are entitled to.

## The transferable point

Where a process depends on someone not having seen something, that dependency is almost always unenforced. It lives in an instruction, a convention, or a professional courtesy - and any tool that reads files will walk straight through it without noticing.

When designing such a process, ask what actually stops the material being read early, and whether the answer is anything more than good manners. When participating in one, assume nothing in your tooling is protecting you, and impose the order of operations yourself.
