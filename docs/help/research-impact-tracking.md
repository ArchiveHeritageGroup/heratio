> Heratio Help Center article. Category: Research / Impact Tracking.

## Overview
Impact Tracking follows what happens to your research after it is published. Once a project has a published output with a DOI, Heratio watches the public scholarly record for downstream impact - citations, mentions and dataset reuse - and gathers it into a per-project Impact panel.

It works entirely from public bibliographic services. There is nothing to configure and no account to link: as soon as a submission is marked published with a DOI, the tracking begins.

## Where the published outputs come from
Impact Tracking reads your published outputs from Publication Studio. A submission counts as a published output when:

- its status is **Published** or **Accepted**, and
- it has a **DOI** recorded.

These are read only. Impact Tracking never changes your submissions; it simply uses their DOIs as the starting point for tracking.

If a project has no published output with a DOI yet, the Impact panel shows a short prompt to record one in Publication Studio first.

## What it tracks
For each published DOI, Heratio gathers four kinds of signal:

- **Citation** - a work that cites your output, plus a running citation count (from OpenAlex).
- **Mention** - a blog post, news item, Wikipedia article, social post or similar that references your output (from Crossref Event Data).
- **Dataset reuse** - a link recording that a dataset tied to your output has been reused (from Crossref Event Data / DataCite).
- **Other** - any signal that does not fit the categories above.

## The Impact panel
Open a project and choose **Impact Tracking**. The panel shows:

- Headline metrics: total citations, total signals, the number of tracked outputs, and when the data was last refreshed.
- The list of tracked published outputs with their DOIs.
- Filter chips to focus on one signal type.
- The signals themselves, grouped by type, each with a title, a short explanation, a link out to the citing work or mention, the source, and when it was detected.

If there are tracked outputs but no signals yet, the panel says "No impact signals yet" and offers a manual check.

## Refreshing
Impact data refreshes automatically once a day. Project owners, editor collaborators and administrators can also trigger an immediate refresh with **Refresh now**. A refresh polls the public services for each tracked DOI and adds any new signals; a signal is never recorded twice.

If the services cannot be reached, the refresh simply finds nothing new and tells you to try again later - it never breaks the page.

## Privacy and sources
All lookups go directly to the public OpenAlex and Crossref Event Data services. No AI gateway is involved, and nothing about your project is sent anywhere beyond the published DOI that is already part of the public record.
