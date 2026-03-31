# Contract Management

## A Guide for Administrators

---

## What is This For?

Manage **vendor contracts, service agreements, and institutional partnerships**. This system helps you:
- Track contracts with vendors and service providers
- Manage collaboration agreements with partner institutions
- Monitor contract expiration and renewal dates
- Store signed documents securely
- Set reminders for important deadlines

**Note:** For donor-related agreements (donations, gifts, bequests), see [Donor Agreement Management](donor-agreement-user-guide.md).

---

## The Dashboard

Find it at: **Admin > Vendor Management > Contracts**

```
+-------------------------------------------------------------+
|                  CONTRACT DASHBOARD                          |
+---------------+---------------+------------------------------+
| Active        | Pending       | Expiring Soon                |
|     28        |      5        |      3                       |
+---------------+---------------+------------------------------+
|                                                              |
|  RECENT CONTRACTS                                            |
|  --------------------------------------------------------    |
|  COL-2026-001  SARADA Collaboration    Active    Dec 2028    |
|  SLA-2026-005  IT Support Agreement    Active    Jan 2027    |
|  SVC-2026-012  Conservation Services   Pending   -           |
|                                                              |
|  EXPIRING THIS MONTH                                         |
|  --------------------------------------------------------    |
|  NDA-2024-003  Confidentiality - ABC Corp       15 Feb       |
|  MNT-2025-001  Equipment Maintenance            28 Feb       |
|                                                              |
+--------------------------------------------------------------+
```

---

## Types of Contracts

| Type | Code | When to Use |
|------|------|-------------|
| **Service Agreement** | SVC | General service provision contracts |
| **Service Level Agreement** | SLA | Contracts with defined service metrics |
| **Collaboration Agreement** | COL | Partnerships for joint projects, digitization, research |
| **License Agreement** | LIC | Software licenses, usage rights |
| **Memorandum of Understanding** | MOU | Non-binding agreements outlining intentions |
| **Non-Disclosure Agreement** | NDA | Confidentiality agreements |
| **Data Processing Agreement** | DPA | POPIA/GDPR compliance for data handling |
| **Maintenance Agreement** | MNT | Equipment or system maintenance |
| **Lease Agreement** | LEA | Equipment or space rental |
| **Supply Agreement** | SUP | Regular supply of materials |
| **Framework Agreement** | FRM | Master agreements for future work |
| **Consultancy Agreement** | CST | Professional consulting services |

---

## Creating a New Contract

1. Go to **Admin > Vendor Management > Contracts**
2. Click **New Contract**
3. Fill in the form:

### Basic Details

| Field | Description |
|-------|-------------|
| Contract Type | Select from dropdown |
| Contract Number | Auto-generated or enter manually |
| Title | Descriptive title for the contract |
| Status | Draft, Pending, Active, etc. |

### Counterparty Information

| Field | Description |
|-------|-------------|
| Counterparty Name | Name of the other party |
| Type | Vendor, Institution, Individual, Government |
| Link to Vendor | Connect to existing vendor record |
| Contact Information | Address, phone, email |
| Representative | Person signing for them |

### Dates

| Field | Description |
|-------|-------------|
| Effective Date | When the contract starts |
| Expiry Date | When it ends (if applicable) |
| Review Date | When to review the contract |
| Auto-renew | Whether it renews automatically |

### Financial Terms

| Field | Description |
|-------|-------------|
| Contract Value | Total value of the contract |
| Currency | ZAR, USD, EUR, GBP |
| Payment Terms | e.g., "30 days from invoice" |

### Terms & Conditions

| Field | Description |
|-------|-------------|
| Scope of Work | What services/deliverables are covered |
| Deliverables | Specific outputs expected |
| General Terms | Standard terms and conditions |
| Special Conditions | Any unique provisions |
| IP Terms | Intellectual property arrangements |
| Confidentiality | Confidentiality clauses |
| Governing Law | Jurisdiction for disputes |

4. Click **Create Contract**

---

## Contract Workflow

```
+------------------+
| Draft            |  Initial creation
+--------+---------+
         |
         v
+------------------+
| Pending Review   |  Internal review
+--------+---------+
         |
         v
+------------------+
| Pending Signature|  Awaiting signatures
+--------+---------+
         |
         v
+------------------+
| Active           |  Contract in force
+--------+---------+
         |
         +---------------+---------------+
         |               |               |
         v               v               v
+------------------+ +--------+ +------------------+
| Renewed          | | Expired| | Terminated       |
+------------------+ +--------+ +------------------+
```

---

## Adding a Logo

You can add your organization's logo to contracts for professional presentation.

### How to Add

1. Edit the contract
2. Find **Contract Logo** field
3. Click **Choose File** and select your logo
4. Supported formats: JPG, PNG, GIF, WebP
5. Save the contract

### Removing a Logo

1. Edit the contract
2. Check **Remove logo** checkbox
3. Save

---

## Using Templates

Save time by creating template contracts.

### Creating a Template

1. Create a contract with standard terms
2. Check **Save as Template**
3. Save

### Using a Template

1. Find a suitable template in the list
2. Clone it
3. Update specific details
4. Uncheck **Save as Template**
5. Save as new contract

---

## Managing Documents

### Uploading Documents

1. Go to the contract
2. Click **Add Document**
3. Select document type:
   - Signed contract
   - Draft
   - Amendment
   - Addendum
   - Schedule/Annexure
   - Correspondence
   - Quote/Invoice
   - Certificate
   - Insurance
   - Legal opinion
4. Upload the file
5. Add title and description

### Document Types

| Type | Purpose |
|------|---------|
| **Signed Contract** | Final executed agreement |
| **Amendment** | Changes to existing contract |
| **Addendum** | Additional terms |
| **Schedule** | Detailed specifications |
| **Insurance** | Proof of insurance |

---

## Setting Reminders

Never miss a deadline.

### Creating a Reminder

1. Go to the contract
2. Click **Add Reminder**
3. Fill in:
   - Type (Expiry warning, Review due, Payment due, etc.)
   - Subject
   - Date
   - Priority
   - Who to notify
4. Save

### Reminder Types

| Type | Use For |
|------|---------|
| Expiry Warning | Contract ending soon |
| Review Due | Scheduled contract review |
| Renewal Required | Time to renew |
| Payment Due | Payment deadline |
| Deliverable Due | Expected delivery date |
| Compliance Check | Regulatory requirements |
| Insurance Expiry | Insurance renewal needed |

### Recurring Reminders

For ongoing obligations:
1. Check **Is Recurring**
2. Select pattern (Monthly, Quarterly, Yearly)
3. Set end date

---

## Risk Management

Contracts are categorized by risk level:

| Level | Description | Review Frequency |
|-------|-------------|------------------|
| **Low** | Standard contracts, minimal risk | Annually |
| **Medium** | Moderate value or complexity | Bi-annually |
| **High** | Significant value or risk | Quarterly |
| **Critical** | Major contracts, compliance required | Monthly |

---

## Collaboration Agreements

For partnerships with other institutions (digitization projects, research collaborations):

### Key Sections

| Section | Purpose |
|---------|---------|
| Purpose & Scope | What the collaboration aims to achieve |
| Responsibilities | What each party must do |
| Intellectual Property | Who owns what (Background IP, Foreground IP) |
| Access Levels | Who can access resulting materials |
| Fees & Payment | Publication fees, cost sharing |
| Duration | Start date, end date, renewal terms |

### Sample: SARADA Collaboration Agreement

A pre-configured template for Rock Art digitization partnerships:

1. Go to **Contracts > New Contract**
2. Select **Collaboration Agreement**
3. Or clone from "SARADA Collaboration Framework" template

The template includes:
- Three-tier access levels (Educational, Researcher, Bona Fide)
- Standard publication fees (R650 Africa / US$150 international)
- IP terms for digitization projects
- Code of Ethics compliance

---

## Contract vs Donor Agreements

| Use Contract Management for: | Use Donor Agreements for: |
|------------------------------|---------------------------|
| Vendor service agreements | Donations from individuals |
| Collaboration partnerships | Gifts and bequests |
| SLAs with service providers | Deposits and loans TO you |
| NDAs and data processing agreements | Access restrictions on donated materials |
| Maintenance and lease contracts | Reproduction rights from donors |
| Software licenses | Material transfers from donors |

---

## Quick Reference

### Contract Statuses

| Status | Meaning |
|--------|---------|
| Draft | Being prepared |
| Pending Review | Under internal review |
| Pending Signature | Awaiting signatures |
| Active | In force |
| Suspended | Temporarily on hold |
| Expired | Past end date |
| Terminated | Ended early |
| Renewed | Replaced by new version |

### Best Practices

**Always use contract numbers**
- Provides unique reference
- Makes tracking easier

**Set review reminders**
- Before expiry dates
- Regular compliance checks

**Keep documents current**
- Upload signed versions immediately
- Archive superseded documents

**Link to vendors**
- Maintains relationship history
- Enables reporting

---

## Reporting

### Available Reports

| Report | Description |
|--------|-------------|
| Active Contracts | All current contracts |
| Expiring Soon | Contracts ending within 30/60/90 days |
| By Type | Contracts grouped by type |
| By Vendor | Contracts per vendor |
| Financial Summary | Total contract values |
| Reminder Overview | Upcoming deadlines |

---

*For technical support, contact your system administrator.*
