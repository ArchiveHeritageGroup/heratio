# AI usage transparency report

The AI usage transparency report (Administration, `/admin/ai-usage`) is a read-only dashboard showing where AI has assisted with the catalogue and how much of that assistance a person has reviewed. It counts only - it makes no AI calls and changes no record.

It exists for honesty and accountability: AI here is an assistant that proposes metadata (entity recognition, summaries, handwritten-text recognition, translation, and so on). A person always remains responsible for what enters the record. Nothing in this report implies that AI decides anything.

## What it shows

- **Inferences logged** - the total number of AI inferences recorded against the catalogue.
- **Records touched** - how many distinct records have had at least one AI inference applied.
- **By inference type** - a breakdown of which AI tasks ran (for example entity recognition, summarisation, handwritten-text recognition, translation), each with a count, a share of the total, and a progress bar. Where an endpoint was recorded, the gateway host is shown as a small hint.
- **By model** - which models produced the inferences, each with a count, share, and bar.
- **Human oversight** - how many inferences carry a recorded human review or correction, shown as a count and a "reviewed share" percentage. This is the accountability headline: it measures how much AI output a person has checked, not how accurate the AI was.
- **Inferences over time** - inferences logged per month over the trailing year, drawn as simple bars.

## How to use it

Use it to answer "how much has AI touched our catalogue, and have we reviewed it?" A low human-oversight share is a prompt to review more of the AI-assisted metadata. The over-time bars show when AI assistance happened. The type and model breakdowns show which tools and models did the work.

## Notes

- The report is read-only and never edits a record or calls any AI service.
- It is built from cheap aggregate counts, so it is safe to open on a large log.
- It is jurisdiction-neutral and makes no country-specific assumptions.
- With no AI activity recorded yet, it shows a calm "No AI activity recorded" state rather than an error.
