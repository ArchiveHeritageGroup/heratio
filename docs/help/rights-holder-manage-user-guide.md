# Rights Holders and Rights Management

> Heratio Help Center article. Category: Rights and Access.

Manage the people and organisations that hold rights over your material, record PREMIS rights statements against archival descriptions, place and lift access embargoes, apply rights statements, licences and Traditional Knowledge labels in bulk, and track orphan works. Standards supported include PREMIS, ISAAR(CPF), Creative Commons, and TK Labels.

---

## Overview

This area covers everything to do with who holds rights over your collections and how those rights are recorded and enforced. It has several connected parts:

- **Rights holders** - authority records for the people and organisations that hold rights, with full contact details.
- **Rights statements (PREMIS)** - structured rights attached to individual archival descriptions, including the legal basis, the acts that are allowed or disallowed, copyright status, and licence terms.
- **Embargoes** - time-bound or perpetual access restrictions on a record and, optionally, its digital surrogates.
- **Extended rights** - higher-level rights metadata applied to records, including rights statements, Creative Commons licences, and Traditional Knowledge (TK) labels, with bulk tools.
- **Rights administration** - an administrator console for embargoes, orphan works, statements, TK labels, and a coverage report.

Browsing rights holders and viewing rights on a record are open to all visitors. Creating and editing require sign-in and the relevant permission; administrative tools require an administrator account.

---

## Key features

### Rights holders

- An authority record with the authorised form of name plus history, dates of existence, places, legal status, functions, mandates, internal structures, general context, rules, sources, revision history, and identifiers (aligned with the ISAAR(CPF) standard for archival authority records).
- Multiple contact records per rights holder: contact person, contact type, a primary-contact flag, full postal address, telephone, fax, email, website, coordinates, and notes.
- A detail page that shows the identity, all contacts, attached PREMIS rights, and extended rights (statement codes, Creative Commons licences, and TK labels shown as colour-coded badges).

### Rights statements (PREMIS)

- Attach a rights statement to an archival description, recording the legal basis (for example copyright, statute, licence, or donor agreement), start and end dates, copyright status and jurisdiction, the rights holder, and notes.
- Record granted acts (such as publish, disseminate, modify, use, display, or discover), each marked as allowed, disallowed, or conditional, with their own dates and conditions.

### Embargoes

- Place an embargo on a record to restrict access: full (hide the record), metadata-only (hide the digital objects), digital-object (restrict downloads), or custom.
- Set a start and end date, or mark the embargo perpetual.
- Record an internal reason and a public message shown to users who are blocked.
- Lift an embargo, recording who lifted it, when, and why.
- View embargoes that are expiring soon (within a chosen number of days) and check the embargo status of any record.

### Extended rights

- A dashboard with totals: records with a rights statement, with a Creative Commons licence, with TK labels, with an active embargo, and with embargoes expiring soon.
- Bulk apply rights statements, Creative Commons licences, TK labels, a donor, a rights holder, and rights/expiry dates to many records at once, with an option to overwrite existing rights.
- Bulk apply embargoes to selected records.
- Clear extended rights from a record.
- Export extended-rights data.

### Rights administration

- Manage embargoes (edit type, dates, reason, perpetual and active flags).
- Track orphan works: record the diligent-search status, search start and completion dates, and any response received - supporting orphan-works compliance regimes.
- View the list of rights statements and TK labels in use.
- Run a coverage report: how many records have rights recorded, the gap, the percentage covered, a breakdown by legal basis, and the top rights holders.

---

## How to use

### Manage a rights holder

1. Browse rights holders at `/rightsholder/browse` (open to all).
2. To add one, go to `/rightsholder/add`, enter the authorised form of name and any descriptive and contact details, then save. (Requires sign-in and create permission.)
3. To edit, open a rights holder and use `/rightsholder/{slug}/edit`. (Requires update permission.)
4. View a rights holder at `/rightsholder/{slug}` to see its identity, contacts, and attached rights.
5. Deleting a rights holder is restricted to administrators.

### Record a rights statement on a record

1. Open the archival description, then go to `/{slug}/rights`.
2. Choose the legal basis, dates, copyright status and jurisdiction, and the rights holder.
3. Add the granted acts, marking each as allowed, disallowed, or conditional, with any conditions.
4. Save. (Requires sign-in and create permission.)

### Place or lift an embargo

1. To embargo a record, go to `/embargo/{objectId}/add`, choose the embargo type and dates (or mark it perpetual), and enter the reason and public message. Save. (Requires create permission.)
2. To lift an embargo, open it and use the lift action, recording the reason. (Requires update permission.)
3. Review all embargoes at `/embargo`, those expiring soon under extended rights, or manage them in the rights-admin console.

### Apply rights in bulk

1. Go to `/extended-rights/batch`.
2. Select the records to act on.
3. Choose to assign rights (statement, Creative Commons licence, TK labels, donor, rights holder, dates), apply an embargo, or clear rights.
4. Optionally tick overwrite to replace existing rights, then apply. (Requires create permission.)

### Administer rights

1. Go to `/rights-admin` (administrators only) for the rights administration dashboard.
2. Manage **embargoes**, track **orphan works**, and review **statements** and **TK labels**.
3. Open the **report** for rights coverage across the catalogue.

---

## Configuration

- **Access.** Browsing rights holders and viewing a record's rights are public. Creating, updating, and deleting require sign-in plus the matching create, update, or delete permission. The rights administration console and deleting rights holders require an administrator account.
- **No enumerated values are hardcoded.** Legal bases, acts, copyright statuses, rights statements, Creative Commons licences, and TK labels are drawn from controlled vocabularies, so the options reflect your own configuration.
- **TK labels** carry a code, a reference URI, a colour, and a sort order, and are shown as colour-coded badges. Several can apply to one record.
- **Embargo enforcement.** An embargo's type determines what is hidden; the public message is what blocked users see. Lifting an embargo keeps an audit trail of who lifted it and why.
- **Standards.** Rights statements follow PREMIS; rights-holder records follow ISAAR(CPF); licensing supports Creative Commons; and Traditional Knowledge labels and orphan-works tracking support indigenous-rights and orphan-works frameworks.

---

## References

- Source: packages/ahg-rights-holder-manage/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/621
