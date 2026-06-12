# Data Management Plan (DMP) Builder

The Data Management Plan builder helps you write the FAIR data management plan
that funders increasingly require - Horizon Europe, NSF, Wellcome, NRF and many
others. It is structured on the RDA / Science Europe **machine-actionable DMP
(maDMP)** common standard, so the plan you write maps cleanly onto what reviewers
expect and onto the maDMP JSON other tools can read.

A DMP is scoped to a single research project. You can keep more than one plan per
project (for example, a draft for one funder and a published plan for another).

## Where to find it

Open a research project, then choose **Data Management Plan** from the project
tools:

- `Research > (your project) > Data Management Plans`

## Creating a plan

1. Click **New Plan**.
2. Give the plan a title (optional - it defaults to "Data Management Plan").
3. Record the **funder** if you have one. The funder is captured as data on the
   plan; it is never assumed and never defaulted to any one country.
4. Optionally choose a **funder template** hint (generic, Horizon Europe, NSF,
   Wellcome, NRF). These are selectable examples, not assumptions, and an
   administrator can add more from the Dropdown Manager.
5. Optionally set the plan **language**, and a **contact** name and email.
6. Click **Create plan**. The plan is created with the full maDMP section set,
   ready to fill in.

## The maDMP sections

Every plan carries the recognised maDMP question set:

- **Data description and collection** - what data you collect, generate or reuse.
- **Documentation and data quality** - metadata, README files, standards, quality.
- **FAIR - Findable** - persistent identifiers and rich, indexed metadata.
- **FAIR - Accessible** - how and under what conditions the data can be accessed.
- **FAIR - Interoperable** - open, standard formats and vocabularies.
- **FAIR - Reusable** - licences, provenance and how long the data stays usable.
- **Storage and backup during the project** - where data lives and how it is
  backed up and recovered.
- **Preservation and retention** - long-term repository and retention period.
- **Data sharing and access control** - when and how data is shared, any embargo.
- **Ethics, legal and privacy** - consent, personal data, IP and jurisdictional
  obligations (jurisdiction-neutral).
- **Responsibilities and resources** - who is responsible and what is needed.
- **Costs** - anticipated data-management costs and how they are covered.

## Completeness indicator

Both the plan list and the editor show a **completeness** bar: how many of the
maDMP sections carry an answer, as a count and a percentage. It reaches 100% when
every section has been filled in. This is a coverage guide, not a quality score.

## Editing a plan

The editor shows the plan details (title, status, funder, language, contact) at
the top, then one text box per maDMP section. Set a **status** (Draft, In Review,
Approved, Published, Superseded) and click **Save plan**. Use **View** to see the
assembled, read-only plan and print it.

## Machine-readable export (maDMP JSON)

Every plan has a **maDMP JSON** button. It downloads the plan as an RDA / Science
Europe maDMP common-standard document: a top-level `dmp` object with title,
language, created and modified dates, contact, a project block (including the
funder when set), and a `dataset` array. The full structured section set is also
preserved under a namespaced `extension`, so nothing is lost in the round trip.
This is the format other DMP tools and funder portals can ingest.

## Notes

- Statuses and funder-template hints are managed through the Dropdown Manager, so
  an administrator can add, rename or retire them without a code change.
- Defaults are jurisdiction-neutral; funder examples are illustrative only.
- The builder only ever writes to the plan - it never alters your project data.
