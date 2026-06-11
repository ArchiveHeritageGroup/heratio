# Method Design Studio

The Method Design Studio helps you design a rigorous, reusable methodology for a
research project. Rather than trying to encode every methodology natively, it
offers a set of **discipline templates**. Each template carries structured
guidance prompts for the parts of a sound method, and you turn one into a
per-project **Method Protocol** that you write once and reuse everywhere.

Open it from any project at **Research > Projects > [your project] > Method
Studio** (URL: `/research/projects/{id}/method`). Browse the full template
gallery at **Research > Method Studio > Templates** (`/research/method/templates`).

## Discipline templates

The Studio ships with templates covering the common research traditions:

- Design Science Research
- Archival / Documentary Method
- Ethnography
- Case Study
- Qualitative (general)
- Quantitative (general)
- Mixed Methods
- Discourse Analysis
- Historical Method
- Legal / Policy Analysis
- Computational / Digital Humanities

Each template lays out the same set of guidance areas, worded for that
discipline:

- Research design
- Sampling / selection
- Data sources
- Instruments
- Coding / analysis framework
- Variables / constructs
- Validity
- Reliability
- Ethics
- Consent
- Bias control
- Reproducibility
- Data management

The guidance is **jurisdiction-neutral**. Ethics, consent, and data-management
prompts speak to general principles - informed consent, lawful basis, retention,
anonymisation - and never assume a particular country's regime. Name your
jurisdiction in your own answers; your site can layer a market module (such as a
GDPR or POPIA module) on top.

## Starting a Method Protocol

1. Open a project and go to **Method Studio**.
2. Click **New Method Protocol** (or **Browse templates** to compare them).
3. Choose the discipline template closest to your approach, give the protocol a
   title (optional - it defaults to the template name), and click **Create**.
4. The protocol opens in the editor pre-filled with every guidance area for that
   template.

## Editing the protocol

The editor presents one card per guidance area, each showing the guidance prompt
and a free-text box for your answer. Fill in as many or as few as you need. Set
the **status** as the protocol matures:

- **Draft** - work in progress.
- **In Review** - ready for a supervisor or collaborator to look at.
- **Final** - locked in for the project.

Click **Save** to persist your answers. Statuses come from the Dropdown Manager
(taxonomy `method_protocol_status`), so an administrator can adjust the labels.

## Viewing and printing

The **View** screen shows the finished protocol, area by area, with each
guidance prompt above your answer. Use **Print** for a clean, sidebar-free copy
to attach to an ethics application, a grant submission, or a thesis appendix.

## Write once, reuse

A Method Protocol is designed to be referenced by other parts of the platform -
a thesis methodology chapter, a grant application, or an ethics application can
pull the protocol's structured content instead of you re-typing it. The Studio
exposes the protocol as clean structured data at
`/research/projects/{id}/method/{protocolId}/reuse`, so once you have written
your methodology here you do not have to write it again.
