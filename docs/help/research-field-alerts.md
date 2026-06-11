# Living Field Alerts

Living Field Alerts watch the works your research project cites and tell you when
something important happens to one of them - so a retracted or corrected source
never sits unnoticed in your bibliography.

## What it watches

Heratio builds a per-project **watch list** from the DOIs in your project
bibliography. Every DOI you cite is added to the list automatically (read-only -
the bibliography is never modified). You can also watch a work by hand from the
watch list page.

Each watched work is checked **daily** against two public scholarly catalogues:

- **Crossref** (`api.crossref.org`) - retraction and correction notices, errata
  and new versions.
- **OpenAlex** (`api.openalex.org`) - retraction flags and related work.

These are public bibliographic services. They are not AI services, so the check
talks to them directly; nothing about your project is sent to an AI model.

## The three kinds of alert

| Alert | Meaning |
|-------|---------|
| **Retraction** (red, shown first) | A work you cite has been retracted or withdrawn. Review every claim that relies on it. |
| **Update** | A correction, erratum or new version of a cited work has been published. Check whether it changes how you should cite it. |
| **New related** | A new work related to one you cite has appeared. It may be worth reading. |

## Using it

1. Open a research project and go to **Field Alerts**.
2. The panel lists current alerts, retractions first and in red. Filter by type
   with the chips along the top.
3. Mark an alert read with the tick, or **Mark all read** to clear the unread
   badge.
4. Click **Watch list** to see every work being watched. Add a work by DOI or
   title, or remove one you no longer cite.

New alerts appear after the overnight check. To pull the latest immediately, an
administrator can run the check on demand (see below).

## Running the check manually (administrators)

```
php artisan ahg:research-field-alerts                 # scan every project with watches
php artisan ahg:research-field-alerts --project=42     # scan one project
php artisan ahg:research-field-alerts --json           # machine-readable summary
```

The check is resilient: if a catalogue is slow or unreachable it simply finds no
new alerts and moves on - it never fails the run and never duplicates an alert
you have already seen.

## Notes

- An empty watch list or no alerts is normal for a new project - add the DOIs you
  cite to your bibliography and they flow through automatically.
- Watch lists and alerts are per project. Access follows the project's own
  sharing: owners and collaborators can view; owners, editor-collaborators and
  administrators can manage the watch list.
