> Heratio Help Center article. Category: Authority Records / Donors.

# Donor Management

Donor Management records the people and organisations who give, deposit, lend, or sell material to your repository, and tracks the legal agreements that govern each acquisition. It combines an authority record for the donor with a full agreement lifecycle: types, status, reminders, linked records, documents, rights, and restrictions.

---

## Overview

A donor is an authority record (a kind of actor) described using the ISAAR(CPF) standard. Alongside each donor you can manage one or more donor agreements: the legal instruments (deeds of gift, deposits, loans, purchases, and similar) that define what was acquired and under what terms.

Donor Management lets you:

- Create, browse, edit, and delete donor records.
- Capture contact details for each donor (with sensitive contact fields encrypted at rest).
- Link a donor to the archival descriptions and accessions they contributed.
- Create and track donor agreements through a status lifecycle.
- Attach signed documents, record rights and restrictions, and set renewal or review reminders.

---

## Key features

| Feature | Description |
|---------|-------------|
| Donor authority records | ISAAR(CPF) donor descriptions with identity, history, places, legal status, functions, mandates, and more. |
| Contact management | Repeatable contact blocks per donor; email and city are encrypted at rest. |
| Relationship links | Connect a donor to the archival descriptions and accessions they supplied. |
| Donor agreements | Track legal agreements with type, number, status, dates, and signatures. |
| Agreement status lifecycle | Draft, pending approval, active, expired, and terminated. |
| Documents | Attach signed agreements, amendments, and receipts to an agreement. |
| Rights and restrictions | Record permissions (use, publish, disseminate, and similar) and restrictions (closure, embargo, redaction) per agreement. |
| Reminders | Set renewal, review, or expiry reminders so nothing lapses unnoticed. |
| History | Every agreement change is recorded in an audit trail. |

---

## How to use

### Browse and view donors

1. Go to `/donor/browse` to see all donors.
2. Search by name and sort the list by name, last updated, or identifier.
3. Click a donor to open its detail page, which shows the description, contacts, linked archival descriptions, and linked accessions.

### Create a donor

1. From the browse page, choose **Add** (route `/donor/add`).
2. Complete the form. The authorised form of name is required (up to 1024 characters); all other description fields are optional.
3. Add one or more contact blocks if you have address, email, telephone, or website details.
4. Link the donor to archival descriptions if known.
5. Save. Creating donors requires create permission.

### Edit or delete a donor

- To edit, open the donor and choose **Edit** (route `/donor/{slug}/edit`). Editing requires update permission.
- To delete, choose **Delete** (route `/donor/{slug}/delete`) and confirm. Deletion requires delete permission and removes the donor along with its contacts, relations, and notes.

### Manage donor agreements

1. Open the agreements dashboard at `/donor/agreements`.
2. Filter by status, agreement type, or expiry date.
3. Choose **Add** (route `/donor/agreement/add`) to create a new agreement. Give it a title (required), an agreement number, a donor, a type, dates, and status.
4. Open an existing agreement (route `/donor/agreement/{id}`) to view it, or **Edit** to update it.
5. While editing an agreement you can:
   - Attach documents such as signed deeds, amendments, and receipts.
   - Link the agreement to specific accessions and archival descriptions. Type at least two characters in the linking fields to search.
   - Record rights and restrictions.
   - Set reminders.
6. Review all upcoming reminders across agreements at `/donor/agreement/reminders`.

Agreement types ship pre-seeded (for example Deed of Gift, Deed of Deposit, Loan, Purchase, Bequest, Transfer, Licence, and Memorandum of Understanding), each with its own colour for quick recognition.

---

## Configuration

- **Agreement types** are managed as reference data and ship pre-seeded; each type carries a name, prefix, colour, and active flag.
- **Status values** (draft, pending approval, active, expired, terminated) drive the dashboard filters and lifecycle.
- **Contact privacy:** donor contact email and city are encrypted at rest. An operator command (`ahg:donor-encrypt-backfill`, which supports a dry run) encrypts any existing plaintext contacts and skips rows that are already encrypted.
- **Permissions:** browsing donors is public; creating, updating, and deleting require the matching access-control permission, and the delete confirmation requires an admin account.

---

## References

- Source: `packages/ahg-donor-manage/`
- Issue: [GH #564](https://github.com/ArchiveHeritageGroup/heratio/issues/564)
