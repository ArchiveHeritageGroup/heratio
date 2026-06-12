# Catalogue growth report

The catalogue growth report (Administration, `/admin/catalogue-growth`) is a read-only management dashboard showing how the catalogue has grown over time and how it is composed today. It counts only - it makes no changes to any record.

It is a management metric, distinct from its two siblings: the data-quality report measures how completely records are described, and the AI-usage report measures where AI assisted. This report measures size, growth and composition.

## What it shows

- **Headline totals** - the total number of records, how many are published versus not yet published, how many carry a digital object, the number of digital objects held, and the number of authority records (actors) and repositories.
- **Records created over time** - records created per month over the trailing year, drawn as simple bars. This is shown only when the system records a creation timestamp. If it does not, the report says so plainly and shows current composition only - it never invents dates. This system records a creation timestamp but not a publication timestamp, so there is no "published per month" series; the bars count when records were created, not when they were published.
- **By level of description** - how the catalogue is composed across arrangement levels (fonds, series, file, item, and so on), each with a count, a share of the total, and a bar. Records with no level set are shown as their own row.
- **By repository** - the repositories holding the most records (top ten), each with a count, share and bar. Any records with no repository assigned are shown separately.
- **By digital surrogate** - how much of the catalogue carries a digital object versus how much is description only.

## How to use it

Use it to answer management questions: how big is the catalogue, how fast is it growing, how much is published, and how much is digitised. The over-time bars show the pace of cataloguing month by month. The composition breakdowns show where the records sit - which levels, which repositories, and how much has a digital surrogate.

## Notes

- The report is read-only and never edits a record.
- It is built from cheap aggregate counts, so it is safe to open on a large catalogue.
- It never invents dates: the growth-over-time chart appears only when a real creation timestamp is recorded; otherwise the report shows current composition and says the time series is not available.
- It is jurisdiction-neutral and makes no country-specific assumptions.
- With nothing catalogued yet, it shows a calm "Nothing catalogued yet" state rather than an error.
