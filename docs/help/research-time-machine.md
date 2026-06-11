> Heratio Help Center article. Category: Research / Time Machine.

## Overview
The Time Machine reconstructs how a research project developed over time. It is read-only and works entirely from records the project already keeps - question briefs, decisions, claims, arguments, captured items, and method protocols. Nothing is editable here. It is the honest record of what happened, and when.

## What it shows
- **Project timeline** - every dated event across the project, merged into one feed and grouped by month. Switch between newest-first and oldest-first.
- **State as of a date** - a date scrubber that rebuilds exactly what the project looked like on, or before, any chosen date: which version of the question was current, which claims had been made, which decisions had been taken, which arguments existed, what had been captured, and which method protocols were defined.

## How the timeline is built
Events are merged from these sources, each carrying its own timestamp:
- Question brief versions (when each version was saved, with its change reason)
- Decisions (when each decision was taken)
- Claims (when each claim was recorded, with its status)
- Arguments and argument steps (when each was created)
- Captured items (when each was captured)
- Method protocols (when each was created or revised)

If a source has nothing recorded, it simply contributes no events.

## State as of a date
1. Open the Time Machine for a project.
2. Click **State as of a date**.
3. Pick a date and click **Rewind** (or jump to **Latest**).

The snapshot shows the question brief version that was current on or before that date (the most recent version saved by then), plus the claims, decisions, arguments, captured items, and method protocols that already existed. It is reconstructed from creation dates and version numbers - nothing is rewound or changed.

## Notes
- This feature never writes or alters any data. It only reads.
- An invalid or empty date safely defaults to the present.
- A project with no recorded activity shows a friendly empty state rather than an error.

## Access
From a project, open the Time Machine at /research/projects/{id}/timemachine. The state-as-of view is at /research/projects/{id}/timemachine/as-of.
