# Heratio - Publish Gates & Type-Driven Editor

## Overview

The **Publish Gates** system provides configurable quality control rules that must pass before archival records can be published to public view. Combined with the **Type-Driven Editor** enhancements, it ensures metadata completeness, rights compliance, and security clearance before any record becomes publicly accessible.

This feature is part of the Heratio framework v2.8.2 by The Archive and Heritage Group (Pty) Ltd.

## Key Features

### Publish Gate Engine
- **Configurable Rules**: Define publish-readiness rules by type (field required, field not empty, has digital object, has rights, has access conditions, security cleared, IIIF ready, custom SQL)
- **Scoped Rules**: Rules can be scoped to specific entity types, levels of description, repositories, or material types
- **Dual Severity**: Rules are either **Blockers** (prevent publishing) or **Warnings** (advisory only)
- **Readiness Dashboard**: Visual pass/fail display per rule with actionable items
- **Publish Simulation**: Preview exactly what the public will see before publishing
- **Administrator Override**: Admins can force-publish despite blockers (fully audited)
- **Gate Events**: All gate evaluations, passes, failures, and overrides are logged in the workflow event history

### Change Summary
- **Field-Level Diff**: When saving edits, see a human-readable summary of exactly what changed
- **Before/After Comparison**: Side-by-side old and new values per field
- **Audit Integration**: Changes are stored in the audit trail with full field-level detail

### Type-Driven Editor Bridge
- **Standard-Aware Templates**: Form templates can now match by descriptive standard (ISAD-G, DACS, DC, RAD, MODS) alongside repository, level, and collection
- **Inline Gate Validation**: Editor fields linked to gate rules show real-time pass/fail indicators
- **Gate Severity Overlay**: Fields display colored indicators (red for blockers, yellow for warnings, green for passed)

## Default Gate Rules (Seeded)

| Rule | Type | Severity |
|------|------|----------|
| Title required | field_required | Blocker |
| Scope and content not empty | field_not_empty | Warning |
| At least one digital object | has_digital_object | Warning |
| Rights statement assigned | has_rights | Warning |
| Access conditions set | has_access_condition | Blocker |
| Security clearance passed | security_cleared | Blocker |

## Architecture

### Database Tables
- `ahg_publish_gate_rule` - Rule definitions with scoping
- `ahg_publish_gate_result` - Cached evaluation results

### Services
- `PublishGateService` - Rule evaluation, publish execution, admin CRUD
- `ChangeSummaryService` - Field diff computation, human-readable summaries
- `EditorGateBridgeService` - Bridges form templates with gate rules for inline validation

### Workflow Integration
Four new workflow event types: `gate_evaluated`, `gate_passed`, `gate_failed`, `gate_overridden`

### API Integration
- `GET /api/v2/publish/readiness/:slug` - Check gate status via API
- `POST /api/v2/publish/execute/:slug` - Publish via API with gate enforcement

## Access Points

| URL | Purpose |
|-----|---------|
| `/workflow/publish-readiness/:id` | Readiness check for a record |
| `/workflow/publish-simulate/:id` | Preview public view |
| `/workflow/publish-execute/:id` | Execute publish |
| `/workflow/admin/gates` | Manage gate rules |
| `/workflow/admin/gates/:id/edit` | Create/edit a gate rule |
| `/workflow/change-summary` | AJAX endpoint for change diff |

## Technical Requirements

- PHP 8.1+
- MySQL 8.0+
- Heratio Framework v2.8.2+
- ahgWorkflowPlugin (required)
- ahgFormsPlugin (optional, for editor bridge)
- ahgIiifPlugin (optional, for IIIF readiness checks)

## Standards Compliance

- Supports ISAD(G), DACS, Dublin Core, RAD, and MODS descriptive standards
- Integrates with NARSSA security classification requirements
- Full audit trail for compliance (POPIA, GDPR)

---

*The Archive and Heritage Group (Pty) Ltd*
*https://github.com/ArchiveHeritageGroup*
