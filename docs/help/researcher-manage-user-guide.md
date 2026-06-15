# Researcher Submissions and Records

> Heratio Help Center article. Category: Research Portal.

Researchers create submissions (a collection of items and files, built directly or imported from an offline editing session), send them to archivists for a two-step review, and on approval have them published as permanent archival records. Staff manage the submissions queue and researcher records.

---

## Overview

This area supports the contributor side of the research portal. A researcher assembles a **submission** - a package of proposed descriptive records and digital files - and submits it for review. Archivists then review it in two steps and, once approved, publish it into the catalogue as permanent archival descriptions, digital objects, and authority records.

It works hand in hand with the offline editing workflow: a researcher can do their work offline, export it as an exchange file, and import that file here to create a submission ready for review.

Every signed-in user has a personal researcher dashboard. Regular users see only their own submissions; administrators see all submissions from everyone.

---

## Key features

- **Personal dashboard.** A per-user overview with counts of total, draft, pending-review, approved, published, and returned-or-rejected submissions, plus the ten most recent submissions.
- **Submissions list.** A filterable list of submissions by status: draft, submitted, under review, approved, published, returned, rejected. Administrators also see the researcher's name on each row.
- **Pending queue.** A pre-filtered view of submissions awaiting action (submitted or under review).
- **New submission.** Create a draft submission with a title, description, target repository, optional link to a research project, and an optional parent record to place it under in the hierarchy.
- **Import from an exchange file.** Upload a JSON exchange file produced by the offline editing tool to create a draft submission automatically, with its notes, files, new items, new creators, new repositories, and collections counted up.
- **Two-step review workflow.** A "Researcher Submission Review" workflow with a Content Review step followed by a Publication Approval step. An archivist can approve, return for revision, or reject at each step.
- **Publish.** Turn an approved submission into permanent catalogue records: archival descriptions from the submission's items, digital objects from its files, and authority records from its new creators.
- **Researcher records.** A basic register of researchers (name, email, institution) with browse, view, add, edit, and (administrator-only) delete.
- **Research portal integration.** When the research portal is in use, the dashboard also shows the researcher's profile, linked projects, collections, and recent annotations.

---

## How to use

### For researchers

**Open your dashboard.** Go to `/researcher/dashboard` to see your submission counts and recent activity.

**Create a submission from scratch.**

1. Go to `/researcher/submission/new`.
2. Enter a title (required) and an optional description.
3. Optionally choose a target repository, link a research project, and select a parent record to place the submission under.
4. Save to create a draft. Add items and files to the draft, then submit it for review.

**Create a submission from offline work.**

1. Finish your work in the offline editing tool and export it as an exchange file.
2. Go to `/researcher/import`.
3. Upload the exchange file and optionally choose a target repository.
4. The tool creates a draft submission, stores your file, and summarises what it found (notes, files, new items, new creators, new repositories, collections).
5. Review the draft and submit it when ready.

**Track your submissions.** Use `/researcher/submissions` to list and filter your submissions by status, and `/researcher/pending` to see those awaiting action.

### For archivists and administrators

1. Open `/researcher/submissions` to see every submission across all researchers, with the researcher's name shown on each row.
2. Use the **pending** view to find submissions awaiting review.
3. Open a submission to review its items and files.
4. Work it through the two-step workflow: **Content Review** first, then **Publication Approval**. Approve, return for revision, or reject at each step.
5. When a submission is approved, **publish** it. Publishing creates permanent archival descriptions, digital objects, and authority records, then marks the submission as published. This step is shown with a warning because it creates permanent records.
6. Manage researcher records under `/researcher/browse` (browse), `/researcher/add`, and `/researcher/{id}/edit`. Deleting a researcher record is restricted to administrators.

---

## Configuration

- **Access.** All dashboard, submission, browse, and import routes require sign-in. Deleting a researcher record requires an administrator account.
- **Visibility.** Non-administrators are confined to their own submissions throughout the dashboard, submissions list, and pending queue. Administrators see everyone's submissions.
- **The review workflow** is seeded automatically as "Researcher Submission Review" with two steps (Content Review, then Publication Approval), each requiring an approve-or-reject decision and able to notify the relevant people.
- **Imported files** are stored per researcher and retained with the submission so the original exchange file remains available.
- **Research portal context** (profile, projects, collections, annotations) appears on the dashboard only when the research portal tables are present.

---

## References

- Source: packages/ahg-researcher-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/619
