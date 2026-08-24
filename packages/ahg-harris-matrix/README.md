# ahg-harris-matrix

Stratigraphic interchange and analysis for Heratio - #1483.

A **separate** plugin from `ahg-archaeology` on purpose. That module is the
site, context and finds catalogue. This one is about the Harris Matrix as an
analytical object: checking a recorded sequence for contradictions, and moving
it in and out of the formats archaeologists actually use.

Ported from `ahgArchaeologyPlugin` in `atom-ahg-plugins`, which is the reference
implementation.

## What it adds

- **Consistency report.** Cycle detection catches only the error that makes a
  matrix impossible to draw. These are the errors that leave a *drawable matrix
  which is wrong* - the class Le Stratifiant checks.
- **LST import.** The format BASP Harris, Stratify and ArchEd write, so it is how
  an existing site archive arrives.
- **GraphViz DOT export** of the reduced matrix.
- **Harris Matrix Data Package export**, following the `hm` table schema.
- **Deposit / interface typing** in Harris's sense: a cut is an interface, not a
  deposit.

## Depends on ahg-archaeology

It reads `archaeology_site`, `archaeology_context` and
`archaeology_context_relationship` and adds no tables of its own. Every entry
point degrades to absent when `ahg-archaeology` is not installed, so the
Stratigraphy page keeps working with only the base module present.
