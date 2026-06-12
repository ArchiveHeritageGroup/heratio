# Research Milestones & Deliverables tracker

The Research Milestones & Deliverables tracker is part of the Research Operating System. For each research project it records the milestones and deliverables the project plans to reach - each with a due date, a status and a progress percentage - so a project plan is documented in one place, alongside its Data Management Plan, outputs, ethics record, funding and team.

A milestone is a planned point in the work; a deliverable is a tangible output the plan commits to producing. Both are tracked here under a single type. This is the project's PLAN, and it is distinct from the Research Outputs register, which records outputs that have actually been produced.

## What it records

Each milestone or deliverable on a project captures:

- Title - the name of the milestone or deliverable.
- Type - milestone, deliverable, decision point, review, dissemination, or other. The type list is drawn from the Dropdown Manager, so an administrator can extend it without code changes.
- Description - a free-text description of what the milestone or deliverable involves.
- Deliverable - the concrete output expected at this milestone, recorded as free text.
- Due date - the planned date the milestone or deliverable is due.
- Completed date - the date it was actually completed. When you set the status to Completed and leave the completed date blank, today's date is recorded for you.
- Status - planned, in progress, completed, delayed, or cancelled.
- Progress - a whole percentage from 0 to 100. A milestone marked Completed is recorded at 100%.

No country, institution or funding regime is assumed or defaulted - the tracker works across jurisdictions, and dates are plain calendar dates.

## Ordering, overdue and due-soon flags

The list is ordered by due date, soonest first, with undated items shown last.

- A milestone is flagged **overdue** when its due date is in the past AND its status is not one that closes it (not completed and not cancelled).
- A milestone is flagged **due soon** when its due date is today or within the next 30 days, it is not already overdue, and its status is still open (not completed and not cancelled).

These flags are derived from the dates and status each time the page is loaded; they are never stored, so they are always current. Overdue rows are highlighted in the list and shown with a warning on the detail page.

## Summary

The project summary shows:

- The total number of milestones, and how many are completed.
- The overall progress percentage - the mean of every milestone's progress percentage (so a project where all milestones read 100% reads 100% overall).
- A warning banner with the overdue count when one or more milestones are overdue.
- The next upcoming milestone - the soonest-due item that is still open and has a due date.
- Counts by status and by type.

## Export

A machine-readable JSON export of a project's plan is available. The document has a `project` block, a `generated_at` timestamp, a `count`, a `summary` block and a `milestones` array. Each milestone carries its title, type (code and human label), description, due and completed dates, status (code and label), progress percentage, deliverable, and the derived `is_overdue` and `is_due_soon` flags. The summary block repeats the total, completed, overdue and due-soon counts, the overall progress percentage, the counts by status and type, and the next upcoming milestone.

## Notes

- Entries are scoped to a project and to the researcher; you manage the milestones of projects you belong to.
- Full validation is applied: progress must be between 0 and 100, and the dates must be valid dates.
- The tracker is read and written only through its own table - it does not change any catalogue record or any other research slice.
- It is jurisdiction-neutral; no country, institution or funding regime is assumed or defaulted.
