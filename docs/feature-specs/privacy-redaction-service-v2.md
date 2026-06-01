# Feature Spec: Field-Level Structured Redaction Service

**Spec version:** 1.0  
**Author:** AHG Workbench Agent  
**Date:** 2026-05-31  
**Status:** Draft  
**Related:** `PrivacyRedactionService` gap identified in KM doc "Data Redaction in Heratio" (2026-05-31)

---

## 1. Problem Statement

Heratio currently handles redaction at two levels:
- **Digital object level** — `RedactionRenderService` + `privacy_visual_redaction` renders black-box redactions into PDFs/images at serve time (working implementation in `ahg-information-object-manage` worktree).
- **DSAR workflow level** — `ahg-privacy` tracks formal requests, breach incidents, consent records, and ROPA (working implementation).

There is no field-level structured redaction for **archival description metadata fields** (e.g., the creator's date of birth in a structured `information_object` record, the witness name in a court case description, the personnel file subject's ID number in a HR archive entry).

Archival description metadata in Heratio is rich and structured. Personal data appears in:
- `creator_birth_date`, `creator_death_date`
- `creator_qualifier` (often contains birth/death year ranges)
- `subject_occupation` (historical personal data)
- `subject_biography` (free text with personal information)
- `related_material_note` (may contain third-party personal data)
- `access_condition` (may contain personal data about access restrictions)

The absence of field-level redaction means an archivist cannot:
1. Mark "creator_birth_date" as redacted for public view
2. Serve authenticated researchers the full field, and public viewers the redacted version, without the manual workaround of applying a visual redaction overlay (which does not work on structured metadata)
3. audit which fields on which descriptions are redacted, why, and by whom

---

## 2. Design

### 2.1 Data Model

New table: `information_object_privacy`

```sql
CREATE TABLE information_object_privacy (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id  BIGINT UNSIGNED NOT NULL UNIQUE,
    privacy_reason_id      TINYINT UNSIGNED NOT NULL,
    redaction_status       ENUM('none','partial','full','pending') DEFAULT 'none',
    applied_by             BIGINT UNSIGNED NULL,
    applied_at             DATETIME NULL,
    legal_basis_reference  VARCHAR(500) NULL COMMENT 'e.g. POPIA s.37, GDPR Art.17(3)(e)',
    notes                  TEXT NULL,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (information_object_id) REFERENCES information_object(id) ON DELETE CASCADE,
    FOREIGN KEY (privacy_reason_id)     REFERENCES privacy_reason(id),
    FOREIGN KEY (applied_by)             REFERENCES user(id)
);
```

New table: `information_object_privacy_field`

```sql
CREATE TABLE information_object_privacy_field (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    privacy_id        BIGINT UNSIGNED NOT NULL,
    field_name        VARCHAR(100) NOT NULL,
    redaction_type    ENUM('full','partial','pseudonymised') DEFAULT 'full',
    redaction_pattern VARCHAR(100) NULL COMMENT 'regex or format pattern for partial',
    reason            VARCHAR(500) NOT NULL,
    is_sensitive      BOOLEAN DEFAULT FALSE COMMENT 'e.g. medical, biometric, financial',
    reviewed_by       BIGINT UNSIGNED NULL,
    reviewed_at       DATETIME NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (privacy_id)  REFERENCES information_object_privacy(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES user(id),
    UNIQUE KEY (privacy_id, field_name)
);
```

New table: `privacy_reason`

```sql
CREATE TABLE privacy_reason (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE,
    label_en    VARCHAR(200) NOT NULL,
    label_af    VARCHAR(200) NULL,
    requires_review BOOLEAN DEFAULT TRUE COMMENT 'high-sensitivity reasons need dual review',
    requires_legal_review BOOLEAN DEFAULT FALSE COMMENT 'e.g. erasure requests need DPO sign-off',
    sort_order  TINYINT UNSIGNED DEFAULT 0
);

INSERT INTO privacy_reason (code, label_en, requires_review, requires_legal_review) VALUES
('personal_data', 'Contains personal data', TRUE, FALSE),
('special_category', 'Special category data (GDPR Art.9 / POPIA s.26)', TRUE, TRUE),
('biometric', 'Biometric or facial recognition data', TRUE, TRUE),
('minor', 'Data subject is or may be a minor', TRUE, TRUE),
('legal_case', 'Related to legal proceedings', TRUE, TRUE),
('third_party', 'Contains third-party personal data', FALSE, FALSE),
('erasure_request', 'Data subject erasure request (GDPR Art.17 / POPIA s.24)', TRUE, TRUE),
('access_request', 'Data subject access request pending', TRUE, FALSE),
('cultural_sensitivity', 'Culturally sensitive personal data', TRUE, FALSE),
('confidential', 'Confidential personnel or institutional data', FALSE, FALSE);
```

### 2.2 PrivacyRedactionService

```php
<?php
namespace AhgPrivacy\Services;

use App\Models\InformationObject;
use App\Models\User;
use AhgPrivacy\Models\InformationObjectPrivacy;
use AhgPrivacy\Models\InformationObjectPrivacyField;

class PrivacyRedactionService
{
    public function getPrivacyProfile(InformationObject $io): ?InformationObjectPrivacy
    {
        return InformationObjectPrivacy::with(['fields', 'reason'])
            ->where('information_object_id', $io->id)
            ->first();
    }

    public function applyRedaction(InformationObject $io, User $user): InformationObject
    {
        $profile = $this->getPrivacyProfile($io);

        // Authorised user: return full record
        if ($this->authService->canAccessRedacted($user, $io)) {
            return $io;
        }

        // Apply per-field redaction to the IO's metadata
        return $this->redactFields($io, $profile->fields);
    }

    public function redactFields(InformationObject $io, Collection $fields): InformationObject
    {
        $redacted = $io->replicate();

        foreach ($fields as $field) {
            $value = $redacted->getAttribute($field->field_name);

            if ($value === null) {
                continue;
            }

            $redacted->setAttribute(
                $field->field_name,
                $this->applyRedactionType($value, $field->redaction_type, $field->redaction_pattern)
            );
        }

        return $redacted;
    }

    private function applyRedactionType(mixed $value, string $type, ?string $pattern): mixed
    {
        return match ($type) {
            'full'        => '[REDACTED — personal data removed]',
            'partial'     => $pattern ? $this->applyPattern($value, $pattern) : '[PARTIALLY REDACTED]',
            'pseudonymised' => $this->pseudonymise($value),
        };
    }

    private function applyPattern(string $value, string $pattern): string
    {
        // e.g. 'email' → 'j***@***.com'
        // e.g. 'phone' → '*** *** ****'
        // e.g. 'id_number' → '**** **** **** 1234' (last 4 shown)
        return match ($pattern) {
            'email_partial'  => preg_replace('/^(.{1}).*@/', '$1***@***.', $value) ?: $value,
            'phone_partial'  => preg_replace('/\d(?=\d{4})/', '*', $value) ?: $value,
            'id_last4'       => str_repeat('*', strlen($value) - 4) . substr($value, -4),
            default          => '[PARTIALLY REDACTED]',
        };
    }

    public function auditLog(InformationObject $io, User $user, string $action): void
    {
        InformationObjectPrivacyLog::create([
            'information_object_id' => $io->id,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

### 2.3 Middleware — Auto-apply Redaction

New middleware `ApplyRedactionMiddleware` registered in the archival description API routes:

```php
// In routes/api.php or the IO controller
Route::middleware(['auth:sanctum', ApplyRedactionMiddleware::class])
    ->get('/information-objects/{id}', [InformationObjectController::class, 'show']);
```

The middleware reads the privacy profile, checks the user's role against the auth service, and applies field-level redaction before the response is serialised.

For non-authenticated public users, the redaction is always applied. For authenticated researchers with an active access agreement on file, the full record is returned.

### 2.4 Admin UI — Privacy Panel on IO Detail Page

A new blade component `privacy/description-privacy-panel.blade.php` renders on the IO detail page (for admin users only), showing:
- Current privacy profile status
- List of redacted fields with reason and reviewer
- Button to "Add field redaction" (opens a field selector + reason picker)
- Link to the DSAR log if a request is active on this IO

### 2.5 DSAR Integration

When a DSAR is marked as `processing` and the privacy officer selects which IOs are in scope, the system should pre-populate `information_object_privacy` records for each IO so that the officer can mark fields for redaction as part of the DSAR response preparation.

---

## 3. Rejected Alternatives

### Alternative A — Field-level redaction through visual redaction editor
Draw a box over the metadata field in the UI. **Rejected** because structured metadata fields render as text, not as canvases. The visual redaction editor (Fabric.js over PDF.js) is the wrong tool for this.

### Alternative B — Treat all personal data as "restricted access" and require login
**Rejected** because this fails the GDPR/POPIA principle of data minimisation. A birth date in a 1954 personnel file may be publicly releasable in a historical context; a national ID number in the same file is not. Granular field-level decisions are required, not all-or-nothing access control.

### Alternative C — Store redaction decisions in the existing `information_object` table
Add a `is_redacted` boolean flag to the IO. **Rejected** because one flag cannot capture per-field decisions, the legal basis, or the audit trail. A dedicated table is the right model.

---

## 4. Acceptance Criteria

1. An admin can open the privacy panel on any IO detail page and mark individual metadata fields as redacted
2. Non-admin public users see the redacted version; admin users see the original
3. Authenticated researchers with an active research agreement see the full record
4. Every redaction decision (field, type, reason, who, when, legal basis) is logged
5. DSAR preparation workflow surfaces IOs in scope and pre-populates privacy profiles
6. Field-level redaction integrates with the existing visual redaction system — IOs with both structured field redactions and digital object visual redactions are handled consistently

---

## 5. Priority and Effort

**Priority:** High — gap identified in the 2026-05-31 redaction article, blocks full POPIA/GDPR compliance for structured archival metadata.

**Effort estimate:** 3–5 days (data model + service + admin UI + middleware + DSAR integration + tests).

**Depends on:** Nothing blocking — the `ahg-privacy` package is already in the main codebase.