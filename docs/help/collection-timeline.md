# Collection timeline

The collection timeline (`/timeline`) is a public, at-a-glance view of how the published holdings are distributed across time. It is an engaging way to browse by period - which centuries and decades the collection is strongest in.

## What it shows

Each published record's earliest recorded date places it in a period. The timeline buckets the records by century, and where a century is dense it drills down to its decades, drawing a horizontal bar sized by the number of records. Records with no usable date are reported honestly in an "undated" group rather than being dropped.

Where the browse page supports a date filter, each bar links straight into a date-filtered browse so you can open the records from that period.

## Machine-readable data

`GET /timeline.json` returns the same buckets as open data (CORS-enabled): each entry carries a period label, its from/to years, the record count, and a browse link where available. This lets the distribution be reused in dashboards or visualisations.

## Notes

- The timeline counts only published records.
- The "undated" group is shown plainly - absence of a date is never hidden.
- Period labels are neutral ("1900s", "1910s") with no era or calendar assumptions beyond the years already in the data.
- An empty or undated collection shows a calm "no dated records yet" state, never an error.
