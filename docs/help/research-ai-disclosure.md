> Heratio Help Center article. Category: Research / AI Containment.

## Overview
The AI Disclosure page produces a one-click AI-use disclosure for any output of a research project. It aggregates the project's AI usage from your existing research tools (read-only) and from a manual interaction log, then assembles a ready-to-paste AI Disclosure Statement suitable for a journal's AI-use statement.

Heratio routes every AI call through the AHG AI gateway, which logs and meters each request. The disclosure makes that visible and asserts that you remained the author and that AI was assistive only.

## Where to find it
Open a research project, then go to its AI Disclosure page at `/research/projects/{id}/ai-disclosure`.

## What it shows
- **AI Disclosure Statement** - a generated, copyable statement assembled from records. No AI call is made to produce it. A Copy button puts it on your clipboard; a Download button saves it as a `.txt` file.
- **Recorded AI usage** - a table of every detected and logged AI interaction, with where it happened, what it was for, the tool, the model (where known), and when.
- **Record an AI interaction** - a form to log AI assistance the system cannot detect on its own.

## Detected (read-only) AI usage
Heratio automatically detects AI usage already captured by other research tools, without changing any of their data:

- **Review Studio** - each AI peer-review run (its persona and model).
- **Source Triage** - each AI relevance preview of a candidate source.
- **Contradiction Engine** - each AI contradiction-detection pass over the claim ledger.

Some tools (for example the Question Builder diagnosis and the Analysis Bridge captioning) call AI transiently and do not store an AI marker. Record those uses in the manual log if they contributed to your output.

## Recording AI use manually
Use the form to capture AI assistance Heratio cannot see, such as a model used outside the platform. Fields:

- **Tool** (required) - the assistive tool or surface used.
- **Model** (optional) - the model identifier where you know it.
- **Output reference** (optional) - what the assistance touched (a section, a figure, a DOI).
- **Purpose** (required) - in your own words, what the AI was used for.

Logged entries appear alongside detected ones and feed straight into the statement.

## Empty state
If nothing has been recorded, the page shows "No AI assistance recorded for this project." and the statement reflects that no AI assistance was recorded. The page never errors, even when AI tools are not installed.

## What the statement asserts
- All AI calls routed through the AHG AI gateway; no model was contacted directly.
- AI was used in an assistive capacity only.
- You reviewed, verified, and take full responsibility for all AI-assisted content.
- The analysis, interpretation, and conclusions remain your own.
