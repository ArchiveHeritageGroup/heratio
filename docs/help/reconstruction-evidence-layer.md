# Reconstruction evidence layer (AI-assisted provenance metadata)

The reconstruction "evidence layer" is an optional, AI-assisted way to record the
provenance of each rebuild stage of a lost-place reconstruction. For any stage that
has a caption (and optionally a body and a date label), a curator can ask the AI to
suggest structured provenance metadata, review and edit it, and save it. The saved
metadata then appears read-only to visitors in the public montage.

It is entirely optional and additive: a reconstruction and its montage work exactly
as before when no evidence metadata is set.

## What the evidence layer records

For each rebuild stage, the evidence layer captures four facets plus a short note:

- **Date estimate** - a human date or range, e.g. `c. 1905` or `early 1900s`.
- **Evidence type** - photograph, survey plan, architectural drawing, written
  account, oral history, archaeological, comparable structure, inference, or unknown.
- **Confidence** - high, medium or low.
- **Source credibility** - primary, secondary, tertiary, conjectural, or unknown.
- **Rationale** - one short sentence explaining the assessment.

## How a curator uses it

1. Open **Reconstructions** (admin) and find the reconstruction whose stages you want
   to annotate: `/exhibition-space/reconstructions/manage`.
2. Each rebuild stage has an **Evidence layer** panel (click to expand it).
3. Click **Suggest with AI**. The system reads the stage's caption and body text and
   proposes a date estimate, an evidence type, a confidence level, a source-credibility
   judgement, and a one-line rationale.
4. **Review and edit** every field. The AI only suggests; you decide what is correct.
5. Click **Save evidence metadata**. Clearing every field and saving removes the
   metadata again.

## How visitors see it

On the public reconstruction montage (`/reconstructions/{id}`), open
**Show the rebuild stages as a list**. Any stage with saved evidence metadata shows
its date estimate, evidence type, confidence and source credibility as small badges,
with the rationale beneath. Stages without metadata show nothing extra.

## How the AI is called

The suggestion is produced through the AHG AI gateway (`ai.theahg.co.za`) using the
platform's standard LLM service - never a direct model endpoint. The annotator reads
the stage's caption and body **text** (not the image bytes), which is normally the
richest cue for provenance.

The feature fails softly: if the AI gateway is unavailable, over quota, or returns an
unreadable answer, the **Suggest with AI** button shows a short inline message and you
can fill the fields in by hand. It never blocks adding or editing a stage.

## Interpretive caveat

A reconstruction is one informed reading of the evidence, assembled for interpretation.
The evidence layer makes that reading transparent - it records how confident the
curator is and what kind of source each stage rests on. It is not a claim that the
reconstruction matches the original's exact appearance.
