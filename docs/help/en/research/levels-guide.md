# Research modes: Beginning, Intermediate, Advanced

This article explains the three research modes available in the Research UI: Beginning, Intermediate, and Advanced. Each mode shows a curated set of quick links in the left sidebar and suggests a short workflow tailored to the user's familiarity and the project's maturity.

## When to use each mode

- Beginning: new or occasional researchers who need to create a simple project and collect a few references quickly.
- Intermediate: researchers conducting ongoing projects that need structured evidence capture, drafting, and project management.
- Advanced: power users preparing outputs for publication, needing reproducibility, ethics tracking, and cross-fonds analysis.

## Suggested workflows

### Beginning
1. Create a Project with a concise title and abstract.
2. Import 3-5 references into Bibliography.
3. Upload a couple of sources and save them in a Collection.
4. Capture quick notes in a Notebook entry.
5. Export your bibliography and start drafting an outline.

### Intermediate
1. Set up Project metadata and a DMP stub.
2. Ingest and tag sources; capture bibliographic metadata.
3. Record claims and link evidence to sources.
4. Draft sections in the Writing Studio and cite items.
5. Run the Contradiction Engine as needed to check consistency.

### Advanced
1. Finalise manuscript sections and accept reviewed AI drafts.
2. Run the Contradiction Engine and resolve conflicts.
3. Create a Replication Pack with data and code artifacts.
4. Complete ethics approvals and link them to the project.
5. Publish outputs, export metadata, and track impact.

## Quick tips
- Share notebooks during Intermediate mode for collaborative drafting.
- AI Drafts should always be treated as suggestions; review and accept them explicitly.
- Provenance: AI suggestions and key decisions are logged; review the ai_provenance table for audit traces.

## Implementation notes for administrators
- The user selection is stored in the Researcher profile (experience_level column). If desired, administrators can set a default via the Research settings.
