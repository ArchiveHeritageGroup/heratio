# ahgDonorAgreementPlugin - Technical Documentation

**Version:** 1.2.0
**Category:** Acquisitions & Contracts
**Dependencies:** atom-framework

---

## Overview

Comprehensive donor agreement and contract management system for tracking donations, gift agreements, collaboration partnerships, restrictions, rights, and provenance chains. Supports templates, logo branding, and comprehensive audit trails.

---

## Database Schema

### ERD Diagram

```
┌────────────────────┐
│       actor        │
├────────────────────┤
│ PK id INT         │
│    ...             │
└────────────────────┘
         │
         │ 1:1
         ▼
┌────────────────────┐       ┌─────────────────────────┐       ┌─────────────────────┐
│       donor        │       │    donor_agreement      │       │   agreement_type    │
├────────────────────┤       ├─────────────────────────┤       ├─────────────────────┤
│ PK id INT (=actor) │       │ PK id INT              │       │ PK id INT          │
│    donor_type ENUM │◄──────│ FK donor_id INT        │──────▶│    name VARCHAR     │
│    tax_id VARCHAR  │ 1:N   │ FK agreement_type_id   │ N:1   │    slug VARCHAR     │
│    preferred_contact│      │    agreement_number     │       │    prefix VARCHAR   │
│    notes TEXT      │       │    title VARCHAR        │       │    description TEXT │
│    created_at      │       │    status ENUM          │       │    color VARCHAR    │
│    updated_at      │       │    effective_date DATE  │       │    is_active BOOL   │
└────────────────────┘       │    expiry_date DATE     │       │    sort_order INT   │
                             │    logo_path VARCHAR    │       └─────────────────────┘
                             │    logo_filename VARCHAR│
                             │    is_template BOOL     │
                             │    created_at TIMESTAMP │
                             │    updated_at TIMESTAMP │
                             └─────────────────────────┘
                                        │
              ┌─────────────────────────┼─────────────────────────┬──────────────────────────┐
              │                         │                         │                          │
              ▼                         ▼                         ▼                          ▼
┌─────────────────────────┐ ┌─────────────────────────┐ ┌─────────────────────────┐ ┌─────────────────────────┐
│  donor_agreement_right  │ │donor_agreement_restriction│ │ donor_agreement_reminder│ │donor_agreement_document │
├─────────────────────────┤ ├─────────────────────────┤ ├─────────────────────────┤ ├─────────────────────────┤
│ PK id INT              │ │ PK id INT              │ │ PK id INT              │ │ PK id INT              │
│ FK donor_agreement_id  │ │ FK donor_agreement_id  │ │ FK donor_agreement_id  │ │ FK donor_agreement_id  │
│    right_type ENUM     │ │    restriction_type ENUM│ │    reminder_type ENUM   │ │    document_type ENUM   │
│    permission ENUM     │ │    applies_to_all BOOL  │ │    subject VARCHAR      │ │    filename VARCHAR     │
│    conditions TEXT     │ │    start_date DATE      │ │    reminder_date DATE   │ │    file_path VARCHAR    │
│    start_date DATE     │ │    end_date DATE        │ │    is_recurring BOOL    │ │    mime_type VARCHAR    │
│    end_date DATE       │ │    auto_release BOOL    │ │    priority ENUM        │ │    is_signed BOOL       │
│    requires_fee BOOL   │ │    popia_category ENUM  │ │    status ENUM          │ │    signature_date DATE  │
└─────────────────────────┘ └─────────────────────────┘ └─────────────────────────┘ └─────────────────────────┘
![wireframe](./images/wireframes/wireframe_5b977c1c.png)
```

### Linking Tables

```
┌─────────────────────────┐    ┌─────────────────────────┐    ┌─────────────────────────┐
│ donor_agreement_record  │    │donor_agreement_accession│    │ donor_agreement_history │
├─────────────────────────┤    ├─────────────────────────┤    ├─────────────────────────┤
│ PK id INT              │    │ PK id INT              │    │ PK id INT              │
│ FK agreement_id INT    │    │ FK donor_agreement_id  │    │ FK agreement_id INT    │
│ FK information_object_id│    │ FK accession_id INT    │    │    action ENUM          │
│    relationship_type    │    │    is_primary BOOL      │    │    old_value TEXT       │
│    notes TEXT           │    │    notes TEXT           │    │    new_value TEXT       │
│    created_at TIMESTAMP │    │    linked_at TIMESTAMP  │    │    user_id INT          │
└─────────────────────────┘    └─────────────────────────┘    │    created_at TIMESTAMP │
                                                              └─────────────────────────┘
![wireframe](./images/wireframes/wireframe_d8d8291b.png)
```

---

## Agreement Types

Stored in `agreement_type` table for flexibility:

| Code | Name | Prefix | Description | Witness Required |
|------|------|--------|-------------|------------------|
| deed-of-gift | Deed of Gift | DOG | Unconditional transfer of ownership | Yes |
| deed-of-donation | Deed of Donation | DON | Formal donation under SA law | Yes |
| deed-of-deposit | Deed of Deposit | DOD | Materials deposited, ownership retained | No |
| loan-agreement | Loan Agreement | LOA | Temporary loan for exhibition/research | No |
| purchase-agreement | Purchase Agreement | PUR | Acquisition through purchase | No |
| bequest | Bequest | BEQ | Transfer through will or testament | Yes |
| transfer-agreement | Transfer Agreement | TRA | Inter-institutional transfer | No |
| custody-agreement | Custody Agreement | CUS | Temporary custody pending disposition | No |
| license-agreement | License Agreement | LIC | Rights license without ownership transfer | No |
| reproduction-agreement | Reproduction Agreement | REP | Agreement for reproduction rights | No |
| access-agreement | Access Agreement | ACC | Special access arrangements | No |
| mou | Memorandum of Understanding | MOU | Non-binding agreement outlining intentions | No |
| sla | Service Level Agreement | SLA | Agreement defining service levels | No |
| collaboration-agreement | Collaboration Agreement | COL | Partnership for joint projects, digitization, research | No |

---

## Agreement Statuses

| Status | Description |
|--------|-------------|
| draft | Still being prepared |
| pending_review | Under internal review |
| pending_signature | Awaiting signatures |
| active | Signed and in effect |
| expired | Past expiry date |
| terminated | Ended before expiry |
| superseded | Replaced by newer agreement |

---

## Rights Types

| Type | Description |
|------|-------------|
| replicate | Create copies |
| migrate | Format migration |
| modify | Make alterations |
| use | General use |
| disseminate | Share/distribute |
| delete | Destroy materials |
| display | Public display |
| publish | Publication rights |
| digitize | Create digital versions |
| reproduce | Reproduction rights |
| loan | Lend to others |
| exhibit | Exhibition display |
| broadcast | Media broadcast |
| commercial_use | Commercial exploitation |
| derivative_works | Create derivative works |

---

## Restriction Types

| Type | Description |
|------|-------------|
| closure | Complete closure |
| partial_closure | Partial access restriction |
| redaction | Content must be redacted |
| permission_only | Requires explicit permission |
| researcher_only | Bona fide researchers only |
| onsite_only | Reading room access only |
| no_copying | No reproduction allowed |
| no_publication | Cannot be published |
| anonymization | Names must be anonymized |
| time_embargo | Time-based embargo |
| review_required | Each request reviewed |
| security_clearance | Requires clearance level |
| popia_restricted | POPIA data protection |
| legal_hold | Legal hold in place |
| cultural_protocol | Cultural sensitivity protocols |

---

## Logo Feature

Agreements can include an organization logo for professional presentation:

### Database Columns
```sql
logo_path VARCHAR(500)     -- Relative path: /agreements/logos/logo_abc123.png
logo_filename VARCHAR(255) -- Original filename: company_logo.png
```

### Storage Location
```
/uploads/agreements/logos/
```

### Supported Formats
- JPEG (image/jpeg)
- PNG (image/png)
- GIF (image/gif)
- WebP (image/webp)

### Template Rendering
```php
<?php if (!empty($agreement->logo_path)): ?>
<img src="<?php echo '/uploads' . esc_entities($agreement->logo_path) ?>"
     alt="Logo"
     style="max-height: 120px;">
<?php endif; ?>
```

---

## Template System

Agreements can be saved as templates for reuse:

### Creating Templates
```php
$agreementData['is_template'] = 1;
DB::table('donor_agreement')->insert($agreementData);
```

### Using Templates
1. Query templates: `WHERE is_template = 1`
2. Clone template data to new agreement
3. Set `is_template = 0` on clone
4. Update specific details

### Template Fields Preserved
- Agreement type
- General terms
- Special conditions
- Rights configuration
- Restriction patterns
- Reminder schedules

---

## Actions

### Main Routes

| Action | URL | Method | Description |
|--------|-----|--------|-------------|
| browse | /donorAgreement/browse | GET | List agreements with filters |
| view | /donorAgreement/view/:id | GET | View agreement details |
| add | /donorAgreement/add | GET/POST | Create new agreement |
| edit | /donorAgreement/edit/:id | GET/POST | Edit existing agreement |
| delete | /donorAgreement/delete/:id | POST | Delete agreement |
| reminders | /donorAgreement/reminders | GET | View upcoming reminders |

### Form Processing

```php
protected function processForm(sfWebRequest $request, $id = null)
{
    // Handle logo upload
    if (isset($_FILES['agreement_logo'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        // Validate, upload, store path
    }

    // Handle logo removal
    if ($request->getParameter('remove_logo')) {
        // Delete file, clear columns
    }

    // Save agreement with i18n
    DB::beginTransaction();
    // ... save logic
    DB::commit();

    // Audit logging
    $this->logAudit($action, $id, $oldValues, $newValues);
}
```

---

## Services

### DonorAgreementService

```php
namespace ahgDonorAgreementPlugin\Services;

class DonorAgreementService
{
    // Agreements
    public function createAgreement(array $data): int
    public function updateAgreement(int $id, array $data): bool
    public function getAgreement(int $id): ?object
    public function listAgreements(array $filters): Collection
    public function deleteAgreement(int $id): bool

    // Rights
    public function addRight(int $agreementId, array $data): int
    public function updateRight(int $id, array $data): bool
    public function getRights(int $agreementId): Collection

    // Restrictions
    public function addRestriction(int $agreementId, array $data): int
    public function updateRestriction(int $id, array $data): bool
    public function getActiveRestrictions(int $agreementId): Collection
    public function checkRestriction(int $recordId, string $type): bool

    // Reminders
    public function createReminder(int $agreementId, array $data): int
    public function getUpcomingReminders(int $days = 30): Collection
    public function completeReminder(int $id, ?string $notes = null): bool
    public function snoozeReminder(int $id, string $until): bool

    // Documents
    public function uploadDocument(int $agreementId, array $file, string $type): int
    public function getDocuments(int $agreementId): Collection
    public function deleteDocument(int $id): bool

    // Linking
    public function linkRecord(int $agreementId, int $recordId): bool
    public function unlinkRecord(int $agreementId, int $recordId): bool
    public function linkAccession(int $agreementId, int $accessionId): bool
    public function unlinkAccession(int $agreementId, int $accessionId): bool

    // Templates
    public function getTemplates(): Collection
    public function cloneTemplate(int $templateId, array $overrides): int
}
```

---

## Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        DONOR AGREEMENT WORKFLOW                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│   ┌─────────────┐     ┌─────────────┐                                       │
│   │ Select/Create│────▶│  Choose     │                                       │
│   │   Template   │     │  Type       │                                       │
│   └─────────────┘     └─────────────┘                                       │
│                              │                                               │
│                              ▼                                               │
│   ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                   │
│   │  Add Logo   │────▶│   Draft     │────▶│    Add      │                   │
│   │  (optional) │     │  Agreement  │     │   Rights    │                   │
│   └─────────────┘     │ status=draft│     └─────────────┘                   │
│                       └─────────────┘            │                           │
│                                                  ▼                           │
│                                          ┌─────────────┐                    │
│                                          │     Add     │                    │
│                                          │ Restrictions│                    │
│                                          └─────────────┘                    │
│                                                  │                           │
│                                                  ▼                           │
│   ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                   │
│   │   Upload    │◀────│   Review    │◀────│    Add      │                   │
│   │  Documents  │     │   & Sign    │     │  Documents  │                   │
│   └─────────────┘     │ status=pending│    └─────────────┘                   │
│         │             └─────────────┘                                       │
│         ▼                    │                                               │
│   ┌─────────────┐           ▼                                               │
│   │   Active    │◀─────────────────────────────────────────────────────────│
│   │  Agreement  │  status=active                                            │
│   └─────────────┘                                                           │
│         │                                                                    │
│         ▼                                                                    │
│   ┌─────────────┐     ┌─────────────┐     ┌─────────────┐                   │
│   │    Link     │────▶│    Set      │────▶│   Monitor   │                   │
│   │   Records   │     │  Reminders  │     │  & Review   │                   │
│   └─────────────┘     └─────────────┘     └─────────────┘                   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_ace8ac4f.png)
```

---

## Collaboration Agreements

Special support for institutional partnerships:

### Key Fields
| Field | Purpose |
|-------|---------|
| `scope_description` | Project scope and objectives |
| `special_conditions` | IP terms, access levels, fees |
| `general_terms` | Standard collaboration terms |

### Sample: SARADA Collaboration Agreement
Pre-configured template for Rock Art digitization partnerships:
- Three-tier access (Educational, Researcher, Bona Fide)
- Publication fees (R650 Africa / US$150 international)
- IP terms for digitization
- Code of Ethics compliance

---

## Audit Integration

Uses `ahgAuditTrailPlugin` if available:

```php
protected function logAudit(string $action, int $id, array $oldValues, array $newValues): void
{
    if (class_exists('AhgAuditTrail\\Services\\AhgAuditService')) {
        \AhgAuditTrail\Services\AhgAuditService::logAction(
            $action,
            'DonorAgreement',
            $id,
            [
                'title' => $newValues['title'] ?? $oldValues['title'],
                'module' => 'ahgDonorAgreementPlugin',
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'changed_fields' => $changedFields,
            ]
        );
    }
}
```

---

## Installation

### Database Migration
```bash
mysql -u root archive < /path/to/ahgDonorAgreementPlugin/database/install.sql
```

### Enable Plugin
```bash
php bin/atom extension:enable ahgDonorAgreementPlugin
```

### Create Upload Directory
```bash
mkdir -p /usr/share/nginx/archive/uploads/agreements/logos
chmod 755 /usr/share/nginx/archive/uploads/agreements/logos
```

---

## Security Considerations

1. **File Upload Validation**: Only allowed MIME types accepted
2. **Path Sanitization**: No directory traversal in file paths
3. **Access Control**: Standard sfGuard permissions apply
4. **Audit Trail**: All changes logged if audit plugin enabled
5. **POPIA Support**: Restriction types for data protection compliance

---

*Part of the AtoM AHG Framework*
