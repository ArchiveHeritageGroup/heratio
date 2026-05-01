# Heratio - Security Feature Overview

**Version:** 2.8.2
**Date:** March 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Overview

Heratio provides enterprise-grade security for GLAM and DAM institutions, aligned with international standards including OWASP Top 10, ISO 27001, Bell-LaPadula mandatory access control, and POPIA data protection requirements.

---

## Security Features

### Authentication and Access Control

**Multi-Factor Authentication (TOTP)**
- Time-based One-Time Password (RFC 6238) support
- QR code enrollment via authenticator apps (Google Authenticator, Authy, etc.)
- Email code fallback for users without authenticator apps
- Per-user enrollment and removal

**Account Lockout Protection**
- Automatic lockout after 5 failed login attempts within 15 minutes
- Configurable lockout duration
- Automatic cleanup of expired attempt records
- IP address tracking for forensic analysis

**Password Policy Enforcement**
- Strong password requirements enforced by default (minimum 8 characters, mixed case, numbers, special characters)
- Password expiry with configurable interval (default 90 days)
- Password history to prevent reuse of the last 5 passwords
- Configurable via Admin > AHG Settings

**Session Security**
- Session ID regeneration on login (prevents session fixation)
- HttpOnly cookies (prevents JavaScript access to session tokens)
- CSRF token enforcement on all state-changing requests

### Mandatory Access Control (Bell-LaPadula)

**Simple Security Property (No Read-Up)**
- Users can only view objects at or below their security clearance level
- Hierarchical clearance levels: PUBLIC < CONFIDENTIAL < SECRET < TOP SECRET
- Browse queries automatically filtered by clearance level

**Star Property (No Write-Down)**
- Users with high clearance cannot write to lower-classification objects
- Prevents accidental leakage of classified information into public records
- Applies to edit, create, update, delete, and publish actions
- Administrators are exempt for operational flexibility

### Security Headers

All responses include security headers:
- **HSTS** - forces HTTPS connections
- **X-Frame-Options** - prevents clickjacking
- **X-Content-Type-Options** - prevents MIME sniffing
- **Permissions-Policy** - restricts browser features (camera, microphone, geolocation)
- **Referrer-Policy** - controls referrer information leakage

### SSRF Protection

Outbound HTTP requests are protected against Server-Side Request Forgery:
- DNS pre-resolution to detect private/reserved IPs
- Cloud metadata endpoint blocking (169.254.169.254, etc.)
- Resolved IP pinning to prevent DNS rebinding
- Redirect following disabled or re-validated
- Response size limits enforced

### Input Validation

- XXE (XML External Entity) protection on all XML parsing
- Parameterized SQL queries via Laravel Query Builder
- Shell command escaping with `escapeshellarg()`
- Allowlist validation for command-line options
- HTML sanitization via HtmlPurifierService

### Audit Trail

- Comprehensive audit logging enabled by default
- Authentication events (login, logout, failed attempts)
- Entity CRUD operations with old/new value tracking
- Security classification access logging
- Configurable retention period
- NARSSA and POPIA compliant

### POPIA Compliance

- Privacy breach register with multi-jurisdiction support
- Automated 72-hour breach notification monitoring
- CLI task for hourly deadline checks with email alerts
- DSAR (Data Subject Access Request) management
- PII scanning and visual redaction editor
- Consent management and processing activity records (ROPA)

---

## CLI Commands

```bash
# Breach notification monitoring
php symfony privacy:breach-check                         # Console report
php symfony privacy:breach-check --email=dpo@example.com # Email alert
php symfony privacy:breach-check --json                  # JSON (for cron)

# PII scanning
php symfony privacy:scan-pii                             # Scan for personal data

# Jurisdiction management
php symfony privacy:jurisdiction                         # List jurisdictions
php symfony privacy:jurisdiction --install=popia         # Install POPIA
```

---

## Technical Requirements

- PHP 8.3+
- MySQL 8.0+
- AtoM 2.10 base installation
- atom-framework v2.8.2+
- ahgSecurityClearancePlugin (security classification)
- ahgPrivacyPlugin (POPIA compliance)
- ahgAuditTrailPlugin (audit logging)

---

## Configuration

### Password Policy Settings (ahg_settings table)

| Setting | Default | Description |
|---------|---------|-------------|
| `password_expiry_days` | 90 | Days before password expires (0 = disabled) |
| `password_history_count` | 5 | Number of previous passwords to remember |

### Audit Settings (ahg_audit_settings table)

| Setting | Default | Description |
|---------|---------|-------------|
| `audit_enabled` | 1 | Enable audit trail logging |
| `audit_authentication` | 1 | Log authentication events |
| `audit_views` | 0 | Log view-only actions (high volume) |
| `retention_days` | 365 | Days to retain audit entries |

---

## Compliance Standards

| Standard | Coverage |
|----------|----------|
| OWASP Top 10 (2021) | All 10 categories addressed |
| ISO 27001:2022 | A.5-A.8 organizational, A.9 access control, A.12 operations, A.14 development |
| POPIA (South Africa) | Sections 19 (safeguards), 22 (notification), Part A (conditions) |
| GDPR (EU) | Article 32 (security), Article 33 (breach notification) |
| Bell-LaPadula | Simple Security Property + Star Property |
| NARSSA | Audit trail and record keeping requirements |

---

*For detailed technical implementation, see the Security Audit Report.*
*For questions, contact: johan@theahg.co.za*
