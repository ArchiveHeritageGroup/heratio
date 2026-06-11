# Analysis Bridge

The Analysis Bridge records the **provenance** of analysis results you produced
elsewhere - in Jupyter, R, QDA software, or a statistics package - and links each
result to the project claims it bears on. It does **not** run analysis engines.
Its value is that no result in your project is a black box: every chart, table,
theme or statistic carries where it came from and how it connects to your claims.

## Overview

For each result you register, the bridge stores:

- **Title** and **type** (chart, table, theme, statistic, other)
- **Source data** and its **version / snapshot** - what the result was computed from
- **Method** - the technique or analysis applied
- **Code / notebook reference** - the repo URL, notebook name or script path
- **Generated at** - when the external result was produced
- **Researcher decision** - the interpretation you drew from it
- An optional **artifact** upload (the figure, exported table, output file)

You then link the result to one or more **claims** from the Claim Ledger, marking
each link as **supports**, **weakens**, or **contextualises**.

## Usage

1. Open a project and go to **Analysis Bridge**.
2. Click **Register result** and fill in the provenance fields. Attach the
   artifact file if you have one. Save.
3. Open the result and use **Link claim** to connect it to a claim, choosing the
   relationship (supports / weakens / contextualises).
4. Use the **Theme tags** and **Memos** panel for lightweight thematic coding and
   analytic notes kept with the project.

## Why provenance, not analysis

Heratio does not reproduce the work your stats or QDA tools already do. Instead it
makes the chain auditable: a reader can see the source data and version, the
method, the code, the date, and the human decision behind every result, and can
follow each result to the claims it strengthens or undermines.

## AI assistance

Any AI assistance (for example a suggested plain-language caption) routes only
through the AHG AI gateway and is clearly labelled as AI-generated. AI never
invents findings; it only rephrases the provenance you supplied.

## References

- Source: `packages/ahg-research/` (Analysis Bridge slice, Research OS Stage 11)
- Related: Claim Ledger (claims live in `research_assertion`)
