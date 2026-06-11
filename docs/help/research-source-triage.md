# Source Triage (Research OS)

Source Triage gives you a single board over all the sources gathered for a research project,
so you can decide what each one is worth and record - honestly - how far you have read it.

The board pulls together both kinds of project source:

- **Bibliography entries** - references in any bibliography attached to the project.
- **Collection items** - catalogue records and other items in the project's evidence collections.

Open it from a project at **Research > (your project) > Source Triage**, or directly at
`/research/projects/{projectId}/triage`.

## Triage categories

Give each source a category so you can see at a glance how it fits your project:

- **Essential** - core to the argument.
- **Useful** - helpful supporting material.
- **Background** - context, not directly cited.
- **Contested** - reliability or interpretation is in dispute.
- **Weak** - thin, low-quality, or marginal.
- **Duplicate** - repeats another source.
- **Excluded** - deliberately set aside.
- **Read later** - parked for a later pass.
- **Method source** - informs your method.
- **Theory source** - informs your theoretical framing.
- **Evidence source** - primary evidence.

You can change a source's category at any time, and filter the board by category.

## Honest read-status

Read-status records how far **you** have actually read a source. The system never marks
anything as read for you - opening a record, generating an AI preview, or anything else leaves
the status exactly where you set it.

- **Unread** - you have not read it yet.
- **Previewed** - you have only glanced at it (for example via the AI preview).
- **Skimmed** - you have skimmed it.
- **Read** - you have read it properly.
- **Deeply read** - you have studied it closely.

Filter the board by read-status to find, for example, every Essential source you have not yet
read.

## Notes

Each source has a free-text notes field for your own triage notes - why it matters, what to
check, page references, and so on.

## Optional AI preview

You can ask the system to generate a short structured preview of a source from its metadata
(summary, likely relevance, caveats). This is optional - the board works fully without it.

Every AI preview is shown under the label **"AI preview - not human verified"**. Treat it as a
starting point only: confirm anything before relying on it, and remember it does not count as
reading - your read-status stays whatever you set by hand.

AI previews run through the AHG AI gateway. If the AI service is unavailable, the rest of the
board keeps working normally.
