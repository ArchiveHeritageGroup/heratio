# Rights and access report

The rights and access report (Administration, `/admin/rights-report`) is a read-only administrator dashboard showing how the catalogue breaks down by access status, rights and licensing, and ODRL policy coverage. It answers the question: what is open, what is restricted, and what carries a rights statement. It counts only - it makes no changes to any record.

It is distinct from its report siblings: the data-quality report measures how completely records are described, the AI-usage report measures where AI assisted, the catalogue-growth report measures size and composition, and the preservation-health report measures preservation integrity. This report measures rights, access and policy coverage.

## What it shows

- **Publication (the access baseline)** - how many records are published (publicly visible) versus draft or unpublished, each with a count, share and bar. Publication is the baseline access signal that lives on a record directly. On this system there is no separate accessibility-status field, so publication stands in for the open-versus-not-yet-open split, and the report says so rather than inventing one.
- **Rights statement coverage** - how many records carry a rights statement (a linked rights record) versus how many have none recorded. A record with no rights statement is shown as "not recorded" - it is never assumed to be free of rights.
- **Copyright status** - of the records that carry a rights statement, how they break down by copyright status (for example under copyright, public domain), drawn from the rights record's copyright-status term. Copyright status is a neutral vocabulary; the specific terms come from the dropdown manager and are not tied to any one country. A rights statement that does not record a copyright status is shown as "not recorded".
- **ODRL policy coverage** - how many records are governed by a digital-rights (ODRL) policy versus how many are open by default. A record with no policy is open access by default; the report frames it as open, not as restricted.
- **ODRL policies by action** - of the governed records, how many are governed for each ODRL action: use (viewing) versus reproduce (printing), plus any other action present. A record may be governed for more than one action.

## How to use it

Use it to answer access and rights questions at a glance: what proportion of the catalogue is public, how much carries a rights statement, what the copyright mix is, and how much is under an active access policy. Where a signal is not recorded the report shows it as such, so a low rights-coverage figure reads as "rights not yet captured" rather than "no rights".

## Notes

- The report is read-only and never edits a record.
- It is built from cheap aggregate counts, so it is safe to open on a large catalogue.
- It never infers a right it cannot see: a missing policy is open by default, a missing rights statement is "not recorded", and a missing copyright status is "not recorded".
- It is jurisdiction-neutral. ODRL actions, rights statements and copyright status are the neutral vocabulary; no single country's copyright regime is assumed.
- With nothing catalogued yet, it shows a calm "Nothing catalogued yet" state rather than an error. If the rights or ODRL store is absent, that section explains why it cannot be shown rather than failing.
