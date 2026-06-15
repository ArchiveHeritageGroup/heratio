> Heratio Help Center article. Category: Collections Management / Accessions.

# Accession Management

## A Guide for Archivists and Registrars

---

## What is accession management?

An accession is the record of a body of material arriving at your institution. Accession management gives you the full intake workflow: creating the accession record, linking the donor or transferring body, appraising and valuing the material, recording its physical containers and rights, working a checklist, and finally promoting it into a catalogued archival description.

```
Material arrives -> Accession created -> Appraised / valued -> Checklist worked
        -> Finalised (accepted) -> Archival description created
```

Browse accessions at `/accession/browse`. Staff tools live under `/accession/...`.

---

## Key features

- Create, edit, and browse accession records with auto-generated accession numbers.
- Link one or more donors to an accession.
- Appraisal (significance and recommendation) and monetary or insurance valuation.
- Physical container and storage tracking.
- Rights records that can be inherited by the resulting archival description.
- An intake checklist, file attachments, and a chain-of-custody timeline.
- A status workflow with configurable finalisation gates.
- Dashboard, intake queue, valuation report, and CSV export.

---

## Creating and editing an accession

1. Go to **Add accession** (`/accession/add`).
2. Complete the core fields:

| Field | Notes |
|-------|-------|
| Accession number (identifier) | Required and unique. Can auto-generate the next number in the sequence, or be entered by hand. |
| Acquisition date | Required. The date of receipt. |
| Source of acquisition | Required. Immediate source, date, and method. |
| Location information | Required. Where the material is physically held. |

3. Add administrative detail as needed: acquisition type, resource type, title, creators, related dates, and events.
4. Save. To edit later, open `/accession/{slug}/edit`.

Alternative identifiers, related dates, and events are entered as repeatable rows. The acquisition type, resource type, event type, and identifier-type lists are all drawn from the Dropdown Manager rather than hard-coded.

---

## Linking a donor

From the accession edit form, search for an existing donor and link them. An accession can carry more than one donor. Links can be added or removed at any time using the link and unlink actions on the record.

---

## The intake workflow

Each accession has a status that moves through the workflow:

```
draft -> submitted -> under_review -> accepted
                                   -> rejected
                                   -> returned
```

**Finalising** an accession promotes it to *accepted*. Before that is allowed, any configured gates must be satisfied - for example a signed donor agreement or a completed appraisal, if those gates are switched on in settings. The system checks the gates server-side and blocks finalisation until they are clear.

Staff workflow tools:

| Tool | URL | Purpose |
|------|-----|---------|
| Dashboard | `/accession/dashboard` | Totals by status and priority, recent activity |
| Intake queue | `/accession/intake-queue` | Accessions not yet marked complete |
| Queue | `/accession/queue` | The full intake workflow queue |
| Valuation report | `/accession/valuation-report` | Accessions with valuation and appraisal data |
| CSV export | `/accession/export-csv` | Download the accessions list |

---

## Appraisal, valuation, containers, rights

For any accession you can open dedicated screens:

- **Appraisal** (`/accession/{id}/appraisal`) - record significance (low to exceptional) and a recommendation (accept, reject, partial, defer), optionally scored against an appraisal template.
- **Valuation** (`/accession/{id}/valuation`) - record monetary, insurance, or replacement value.
- **Containers** (`/accession/{id}/containers`) - boxes, folders, and other holdings with barcodes, dimensions, and condition.
- **Rights** (`/accession/{id}/rights`) - rights basis, holder, restriction type, and grant acts. Rights flagged to inherit are pushed to the archival description created from the accession.
- **Attachments** (`/accession/{id}/attachments`) - deeds of gift, photographs, correspondence, inventories.
- **Checklist** (`/accession/{id}/checklist`) - intake steps; each item records who completed it and when.
- **Timeline** (`/accession/{id}/timeline`) - the chain-of-custody event log.

---

## Creating an archival description from an accession

Once an accession is finalised, use **Create information object** (`/accession/{slug}/create-io`) to materialise a new archival description from the accession's metadata. The description is linked back to the accession, and (if rights inheritance is enabled) the accession's rights are copied across.

---

## Configuration

Intake behaviour is configured under `/accession/intake-config` and `/accession/numbering`:

- **Numbering mask** - the pattern used for new accession numbers (for example a year plus a zero-padded sequence).
- **Auto-assign** - optionally assign a new accession to the user creating it.
- **Finalisation gates** - require a donor agreement and/or an appraisal before an accession can be accepted.
- **Default priority**, **default checklist template**, and **default appraisal template**.
- **Rights inheritance** - whether accession rights flow to created descriptions (on by default).

Enumerated values (acquisition types, resource types, processing statuses and priorities, event types) are managed in the Dropdown Manager.

---

## References

- Source: `packages/ahg-accession-manage/`
- Issue: [GH #540](https://github.com/ArchiveHeritageGroup/heratio/issues/540)
