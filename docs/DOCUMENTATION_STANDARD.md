# Heratio — Documentation Standard

**Version:** 1.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

All Heratio plugins and framework components MUST follow this documentation standard. Consistent documentation ensures maintainability, reduces onboarding time, and supports the commercial distribution of the platform.

---

## 2. Required Document Types

### 2.1 Per Plugin

Every plugin MUST have the following documents in its `docs/` directory or in the central `atom-extensions-catalog/docs/` repository:

| Document | Format | Audience | Content |
|----------|--------|----------|---------|
| User Guide | `.md` + `.docx` | End users | How to use the plugin features |
| Flow Guide | `.md` + `.docx` | End users | Visual workflows with diagrams |
| Technical Manual | `.md` | Developers | Architecture, API, database schema |
| Feature Overview | `.md` + `.docx` | Clients/prospects | Marketing-ready feature summary |

### 2.2 Framework-Level

| Document | Format | Location |
|----------|--------|----------|
| SECURITY_MODEL.md | `.md` | `docs/technical/` |
| CSRF_POLICY.md | `.md` | `docs/technical/` |
| SHELL_EXECUTION_POLICY.md | `.md` | `docs/technical/` |
| OUTBOUND_HTTP_POLICY.md | `.md` | `docs/technical/` |
| DOCUMENTATION_STANDARD.md | `.md` | `docs/` |
| PR_REVIEW_CHECKLIST.md | `.md` | `docs/` |

---

## 3. Naming Conventions

### User Guides
```
{feature-name}-user-guide.md
{feature-name}-user-guide.docx
```
Example: `data-ingest-user-guide.md`

### Flow Guides
```
{feature-name}-flow-guide.md
{feature-name}-flow-guide.docx
```
Example: `data-ingest-flow-guide.docx`

### Technical Manuals
```
{PluginName}.md
```
Example: `ahgIngestPlugin.md`

### Feature Overviews (Distributable)
```
Heratio_{ComponentName}_Feature_Overview.md
Heratio_{ComponentName}_Feature_Overview.docx
```
Example: `Heratio_DataIngest_Feature_Overview.md`

---

## 4. Format Requirements

### 4.1 Dual Format Rule

All user-facing documents (User Guides, Flow Guides, Feature Overviews) MUST be produced in **both** `.md` AND `.docx` format.

- The `.md` file is the **source of truth** (maintained in git, easy to diff/edit)
- The `.docx` file is the **distributable deliverable** (for users, clients, printing)

### 4.2 Conversion

Use pandoc to convert markdown to docx:

```bash
pandoc input.md -o output.docx \
    --from=markdown \
    --to=docx \
    --metadata title="Document Title" \
    --metadata author="The Archive and Heritage Group (Pty) Ltd"
```

### 4.3 Markdown Standards

- Use ATX-style headers (`# H1`, `## H2`, etc.)
- Use fenced code blocks with language identifiers
- Tables must use pipe syntax with header separators
- No HTML in markdown unless absolutely necessary
- Maximum line length: none (use semantic line breaks if desired)

---

## 5. Document Structure Templates

### 5.1 User Guide Template

```markdown
# {Feature Name} — User Guide

**Version:** X.Y
**Date:** YYYY-MM-DD
**Plugin:** {pluginName}

---

## Overview
Brief description of the feature and its purpose.

## Prerequisites
- Required plugins
- Required permissions
- Required configuration

## Getting Started
Step-by-step instructions for first-time use.

## Features

### Feature 1
Description and usage instructions.

### Feature 2
Description and usage instructions.

## Configuration
Admin settings and configuration options.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| ... | ... |

## Related Documentation
Links to related guides and technical docs.
```

### 5.2 Feature Overview Template

```markdown
# Heratio — {Component Name}

**Feature Overview**

---

## What It Does
2-3 sentence summary for non-technical audience.

## Key Features
- Feature 1: brief description
- Feature 2: brief description
- ...

## Compliance & Standards
List applicable standards (ISAD(G), POPIA, Spectrum, etc.)

## Technical Requirements
- PHP version
- Required services
- Dependencies

## Screenshots / Diagrams
(Include as appropriate for the audience)

## About Heratio
Standard boilerplate about the platform.
```

### 5.3 Technical Manual Template

```markdown
# {PluginName} — Technical Manual

**Version:** X.Y
**Date:** YYYY-MM-DD

---

## Architecture
High-level architecture diagram and description.

## Database Schema
Table definitions and relationships.

## API / Service Layer
Public methods and their signatures.

## CLI Commands
Available CLI commands with usage examples.

## Configuration
Settings keys and their effects.

## Security Considerations
Authentication, authorization, input validation.

## Dependencies
Required plugins, services, and external tools.
```

---

## 6. Quality Checklist

Before submitting documentation:

- [ ] Document follows the appropriate template structure
- [ ] All code examples are tested and correct
- [ ] Screenshots/diagrams are current (if included)
- [ ] Version number and date are set
- [ ] Both `.md` and `.docx` formats exist (for user-facing docs)
- [ ] No internal/sensitive information in client-facing documents
- [ ] Spelling and grammar checked
- [ ] Links to related documents are valid
- [ ] Table of contents is present for documents > 3 pages
