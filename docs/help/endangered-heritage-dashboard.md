# The public endangered-heritage dashboard

The endangered-heritage dashboard is the open, public face of the "race against
loss". It gathers, on one page, an aggregate overview of every item that
curators have flagged as at risk: how many there are, why they are at risk, how
urgent the work is, and how far the work of capturing them has progressed. It is
read-only and needs no sign-in. Open it at **/endangered-heritage**.

It sits alongside the public at-risk register (**/at-risk**, which lists the
individual published items still awaiting capture) and the admin capture-priority
worklist (**/endangered/priority**, where curators flag records and advance the
capture workflow). The dashboard summarises; the register lists; the worklist
acts.

## What the dashboard shows

- **Big numbers** - the total items flagged at risk, how many are still awaiting
  capture, how many are being captured right now, and how many have already been
  captured and safeguarded.
- **Capture progress** - a single bar showing the share of at-risk items already
  captured, out of those captured plus those still awaiting capture. Items no
  longer treated as at risk are left out of this calculation.
- **Why heritage is at risk** - a breakdown by risk category (conflict, climate,
  material decay, funding or stewardship risk, displacement, a digitisation gap,
  or another documented risk), as simple bars.
- **How urgent the work is** - a breakdown by urgency band (critical, high,
  medium, low).
- **Highest-priority items awaiting capture** - a short, ranked list of the most
  urgent published items still to be captured, each linking to its catalogue
  record, with a link onward to the full **/at-risk** register.

## Open data

The same aggregate is available as machine-readable JSON at
**/endangered-heritage.json**. It is CORS-open public data, so partner sites and
dashboards can re-use it. It carries the totals, the capture-progress split, the
risk and urgency breakdowns, and the highest-priority items (published records
only, each with a record link).

## Good to know

- The dashboard is factual and non-alarmist. A flag is a prioritisation aid: it
  records that an item should be captured sooner rather than later, and the
  documented reason why. It is **not** a prediction that an item will be lost,
  **not** a statement about any institution's stewardship, and **not** advice.
- The highest-priority list and the JSON only ever surface **published** records.
  Unpublished records can be flagged and worked on internally, but they never
  appear on the public dashboard.
- Until something has been flagged, the dashboard shows a calm empty-state rather
  than empty charts. It never reports figures it does not have.
- The order in which to act, and the assessment of risk, are matters for
  qualified staff to weigh against the evidence in every case.
