> Heratio Help Center article. Category: Reference.

# Heratio Security Audit Report

**Version:** 2.8.2
**Date:** March 2026
**Standard:** OWASP Top 10, ISO 27001, Bell-LaPadula, POPIA
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Executive Summary

A comprehensive security audit was conducted against the Heratio platform covering OWASP Top 10, ISO 27001 alignment, Bell-LaPadula model compliance, MFA implementation, password policy enforcement, POPIA security requirements, authentication mechanisms, session management, input validation, and SSRF protection.

All critical and high-priority findings have been remediated. Medium-priority items have been addressed with new services and policy enforcement.

---

## Standards Assessed

| Standard | Scope | Status |
|----------|-------|--------|
| OWASP Top 10 (2021) | Web application security | Remediated |
| ISO 27001 | Information security management | Aligned |
| Bell-LaPadula | Mandatory access control model | Implemented (Simple Security + Star Property) |
| POPIA | South African data protection | Compliant (72h breach notification) |
| MFA/2FA | Multi-factor authentication | TOTP backend implemented |
| Password Policy | Strength, expiry, history | Enforced |

---

## Findings and Remediation

### Critical Priority (Remediated)

#### 1. Session Fixation (OWASP A07: Identification and Authentication Failures)
- **Finding:** Session ID not regenerated on login transition
- **Fix:** `AuthMiddleware.php` — `session_regenerate_id(true)` on login detection via `$_SESSION['_security_auth_id']` tracking
- **File:** `atom-framework/src/Http/Middleware/AuthMiddleware.php`

#### 2. CSRF Default Mode Set to 'log' (OWASP A01: Broken Access Control)
- **Finding:** CSRF protection logged violations but did not block them
- **Fix:** Default changed from `'log'` to `'enforce'`
- **File:** `atom-framework/src/Services/CsrfService.php`

#### 3. Shell Command Injection (OWASP A03: Injection)
- **Finding:** `$targetLang` variable interpolated directly into Python command string in IngestCommitService
- **Fix:** Passed via `sys.argv[2]` with `escapeshellarg()`
- **Files:** `ahgIngestPlugin/lib/Services/IngestCommitService.php`, `ahgPreservationPlugin/lib/PreservationService.php`

#### 4. XXE (XML External Entity) Processing (OWASP A05: Security Misconfiguration)
- **Finding:** 14+ XML parsing locations across plugins lacked `LIBXML_NONET | LIBXML_NOCDATA` protection
- **Fix:** Applied XXE-safe flags to all `simplexml_load_string()`, `simplexml_load_file()`, `DOMDocument::load()` calls
- **Files:** 14 files across ahgMigrationPlugin, ahgFederationPlugin, ahgDataMigrationPlugin, ahgPreservationPlugin, ahgMetadataExtractionPlugin, ahgAuthorityPlugin, atom-framework

#### 5. SQL Injection in ReportBuilder (OWASP A03: Injection)
- **Finding:** Null userId bypass in query execution; table name not sanitized in ColumnDiscovery
- **Fix:** RuntimeException on null userId; regex sanitization + backtick wrapping for table names; expanded dangerous keyword list
- **Files:** `ahgReportBuilderPlugin/lib/QueryBuilder.php`, `ahgReportBuilderPlugin/lib/ColumnDiscovery.php`

### High Priority (Remediated)

#### 6. No Account Lockout (OWASP A07: Identification and Authentication Failures)
- **Finding:** No brute force protection on login
- **Fix:** `LoginSecurityService` — 5 failed attempts = 15-minute lockout
- **Files:** `atom-framework/src/Core/Security/LoginSecurityService.php`, `atom-framework/src/Services/AuthService.php`
- **Table:** `login_attempt`

#### 7. Cookie Not HttpOnly
- **Finding:** `atom_authenticated` cookie exposed to JavaScript
- **Fix:** Changed `httponly: false` to `httponly: true`
- **File:** `atom-framework/src/Http/Middleware/AuthMiddleware.php`

#### 8. Missing Security Headers (OWASP A05: Security Misconfiguration)
- **Finding:** No HSTS, X-Frame-Options, Permissions-Policy, Referrer-Policy headers
- **Fix:** `SecurityHeadersMiddleware` added to middleware stack
- **File:** `atom-framework/src/Http/Middleware/SecurityHeadersMiddleware.php`

#### 9. Password Policy Disabled by Default
- **Finding:** `require_strong_passwords` setting defaulted to `0`
- **Fix:** Changed to `1` (enabled)
- **Setting:** `setting_i18n` id=65

#### 10. Audit Trail Disabled by Default
- **Finding:** Audit logging required manual enablement
- **Fix:** Seed data in install.sql sets `audit_enabled=1`, `audit_authentication=1`
- **File:** `ahgAuditTrailPlugin/database/install.sql`

#### 11. SSRF (Server-Side Request Forgery) (OWASP A10: SSRF)
- **Finding:** 4 outbound HTTP locations lacked private IP/DNS rebinding protection
- **Fixes applied:**
  - `ahgReportBuilderPlugin/lib/LinkService.php` — Uses `HttpClientService` with SSRF protection; fallback adds IP validation, enables SSL verification, disables redirects
  - `ahgAPIPlugin/lib/Services/WebhookService.php` — DNS pre-resolution, private IP blocking, redirect disabled, resolved IP pinning
  - `ahgFederationPlugin/lib/HarvestClient.php` — Metadata host blocking, private IP blocking, SSL verification, response size limit, redirect disabled, IP pinning
- **Not fixed (locked):** `ahgLibraryPlugin/web/cover-proxy.php` — plugin is locked per policy

### Medium Priority (Remediated)

#### 12. POPIA 72-Hour Breach Notification (POPIA Section 22)
- **Finding:** No automated monitoring of breach notification deadlines
- **Fix:** `getOverdueBreaches()` and `checkDeadlines()` methods in `PrivacyBreachService`, plus `privacy:breach-check` CLI task with email alerting
- **Files:** `ahgPrivacyPlugin/lib/Service/PrivacyBreachService.php`, `ahgPrivacyPlugin/lib/task/privacyBreachCheckTask.class.php`

#### 13. Password Expiry and History (ISO 27001 A.9.4.3)
- **Finding:** No password expiry enforcement or reuse prevention
- **Fix:** `PasswordPolicyService` — configurable expiry (default 90 days), history (default 5 passwords)
- **File:** `atom-framework/src/Core/Security/PasswordPolicyService.php`
- **Table:** `password_history`
- **Settings:** `password_expiry_days`, `password_history_count`

#### 14. Bell-LaPadula Star Property (ISO 27001 A.9.1, MLS)
- **Finding:** Only Simple Security Property (no read-up) was enforced; Star Property (no write-down) was missing
- **Fix:** Added `checkStarProperty()` to `AccessFilterService.checkAccess()` — prevents users with high clearance from writing to lower-classification objects
- **File:** `atom-framework/src/Services/Access/AccessFilterService.php`

#### 15. MFA/2FA Backend (OWASP A07: Identification and Authentication Failures)
- **Finding:** 2FA UI existed but had no backend implementation
- **Fix:** TOTP (RFC 6238) implementation with QR enrollment, email fallback, database storage
- **Files:** `atom-framework/src/Core/Security/TotpService.php`, `ahgSecurityClearancePlugin/modules/securityClearance/actions/actions.class.php`, templates
- **Table:** `user_totp_secret`

---

## Database Changes

| Table | Purpose | Plugin |
|-------|---------|--------|
| `login_attempt` | Brute force tracking | atom-framework |
| `user_totp_secret` | TOTP enrollment | atom-framework |
| `password_history` | Password reuse prevention | atom-framework |

| Setting | Value | Table |
|---------|-------|-------|
| `require_strong_passwords` | `1` | setting_i18n |
| `audit_enabled` | `1` | ahg_audit_settings |
| `audit_authentication` | `1` | ahg_audit_settings |
| `password_expiry_days` | `90` | ahg_settings |
| `password_history_count` | `5` | ahg_settings |

---

## Architecture Overview

### Security Middleware Stack (atom-framework)
```
Request → SecurityHeadersMiddleware → AuthMiddleware → CsrfService → Application
                  ↓                       ↓                ↓
            HSTS/XFO/XCT         Session fixation    CSRF enforce
            Permissions-Policy    Cookie httponly     Token validation
            Referrer-Policy       Login lockout
```

### Access Control Model (Bell-LaPadula)
<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 596 148" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="595" height="147" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="157.6" y1="18.0" x2="161.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="18.0" x2="157.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="18.0" x2="164.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="18.0" x2="168.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="18.0" x2="172.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="18.0" x2="175.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="18.0" x2="179.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="18.0" x2="182.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="18.0" x2="186.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="18.0" x2="190.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="18.0" x2="193.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="18.0" x2="197.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="18.0" x2="200.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="18.0" x2="204.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="18.0" x2="208.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="18.0" x2="211.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="18.0" x2="215.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="18.0" x2="218.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="18.0" x2="222.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="18.0" x2="226.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="18.0" x2="229.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="18.0" x2="233.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="18.0" x2="236.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="18.0" x2="240.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="18.0" x2="244.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="18.0" x2="247.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="18.0" x2="251.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="18.0" x2="254.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="18.0" x2="258.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="18.0" x2="262.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="18.0" x2="265.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="18.0" x2="269.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="18.0" x2="272.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="18.0" x2="276.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="18.0" x2="280.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="18.0" x2="283.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="18.0" x2="287.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="18.0" x2="290.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="18.0" x2="294.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="18.0" x2="298.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="18.0" x2="301.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="18.0" x2="305.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="18.0" x2="308.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="18.0" x2="312.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="18.0" x2="316.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="18.0" x2="319.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="18.0" x2="323.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="18.0" x2="326.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="18.0" x2="330.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="18.0" x2="334.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="18.0" x2="337.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="18.0" x2="341.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="18.0" x2="344.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="18.0" x2="344.8" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="26.0" x2="157.6" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="34.0" x2="157.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="26.0" x2="352.0" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="34.0" x2="352.0" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="42.0" x2="157.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="50.0" x2="157.6" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="164.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="50.0" x2="172.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="50.0" x2="175.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="50.0" x2="179.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="50.0" x2="182.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="50.0" x2="186.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="50.0" x2="190.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="50.0" x2="193.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="50.0" x2="197.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="50.0" x2="200.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="50.0" x2="204.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="50.0" x2="208.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="50.0" x2="211.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="50.0" x2="215.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="50.0" x2="218.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="50.0" x2="222.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="50.0" x2="226.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="50.0" x2="229.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="50.0" x2="233.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="50.0" x2="236.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="50.0" x2="240.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="50.0" x2="244.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="50.0" x2="247.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="50.0" x2="251.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="50.0" x2="254.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="50.0" x2="258.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="50.0" x2="262.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="50.0" x2="265.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="50.0" x2="269.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="50.0" x2="272.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="50.0" x2="276.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="50.0" x2="280.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="50.0" x2="283.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="50.0" x2="287.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="50.0" x2="290.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="50.0" x2="294.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="50.0" x2="298.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="50.0" x2="301.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="50.0" x2="305.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="50.0" x2="308.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="50.0" x2="312.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="50.0" x2="316.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="50.0" x2="319.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="50.0" x2="323.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="50.0" x2="326.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="50.0" x2="330.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="50.0" x2="334.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="50.0" x2="337.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="50.0" x2="341.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="50.0" x2="344.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="42.0" x2="344.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="50.0" x2="344.8" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="58.0" x2="157.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="66.0" x2="157.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="58.0" x2="352.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="66.0" x2="352.0" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="74.0" x2="157.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="82.0" x2="157.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="74.0" x2="352.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="82.0" x2="352.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="90.0" x2="157.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="98.0" x2="157.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="90.0" x2="352.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="98.0" x2="352.0" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="106.0" x2="157.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="114.0" x2="157.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="106.0" x2="352.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="114.0" x2="352.0" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="130.0" x2="161.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="122.0" x2="157.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="130.0" x2="164.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="130.0" x2="168.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="130.0" x2="172.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="130.0" x2="175.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="130.0" x2="179.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="130.0" x2="182.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="130.0" x2="186.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="130.0" x2="190.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="130.0" x2="193.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="130.0" x2="197.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="130.0" x2="200.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="130.0" x2="204.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="130.0" x2="208.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="130.0" x2="211.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="130.0" x2="215.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="130.0" x2="218.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="130.0" x2="222.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="130.0" x2="226.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="130.0" x2="229.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="130.0" x2="233.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="130.0" x2="236.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="130.0" x2="240.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="130.0" x2="244.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="130.0" x2="247.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="130.0" x2="251.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="130.0" x2="254.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="130.0" x2="258.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="130.0" x2="262.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="130.0" x2="265.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="130.0" x2="269.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="130.0" x2="272.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="130.0" x2="276.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="130.0" x2="280.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="130.0" x2="283.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="130.0" x2="287.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="130.0" x2="290.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="130.0" x2="294.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="130.0" x2="298.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="130.0" x2="301.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="130.0" x2="305.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="130.0" x2="308.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="130.0" x2="312.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="130.0" x2="316.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="130.0" x2="319.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="130.0" x2="323.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="130.0" x2="326.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="130.0" x2="330.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="130.0" x2="334.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="130.0" x2="337.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="130.0" x2="341.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="130.0" x2="344.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="122.0" x2="344.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><path d="M371.4 62.0 L364.4 66.0 L371.4 70.0 Z" fill="#10373E"/><path d="M371.4 78.0 L364.4 82.0 L371.4 86.0 Z" fill="#10373E"/><text x="182.8" y="38.0" font-size="9.5" fill="#10373E">AccessFilterService</text><text x="168.4" y="70.0" font-size="9.5" fill="#10373E">1.</text><text x="190.0" y="70.0" font-size="9.5" fill="#10373E">Classification</text><text x="298.0" y="70.0" font-size="9.5" fill="#10373E">Check</text><text x="377.2" y="70.0" font-size="9.5" fill="#10373E">Simple</text><text x="427.6" y="70.0" font-size="9.5" fill="#10373E">Security</text><text x="492.4" y="70.0" font-size="9.5" fill="#10373E">(no</text><text x="521.2" y="70.0" font-size="9.5" fill="#10373E">read-up)</text><text x="168.4" y="86.0" font-size="9.5" fill="#10373E">2.</text><text x="190.0" y="86.0" font-size="9.5" fill="#10373E">Star</text><text x="226.0" y="86.0" font-size="9.5" fill="#10373E">Property</text><text x="290.8" y="86.0" font-size="9.5" fill="#10373E">Check</text><text x="377.2" y="86.0" font-size="9.5" fill="#10373E">Star</text><text x="413.2" y="86.0" font-size="9.5" fill="#10373E">Property</text><text x="478.0" y="86.0" font-size="9.5" fill="#10373E">(no</text><text x="506.8" y="86.0" font-size="9.5" fill="#10373E">write-down)</text><text x="168.4" y="102.0" font-size="9.5" fill="#10373E">3.</text><text x="190.0" y="102.0" font-size="9.5" fill="#10373E">Donor</text><text x="233.2" y="102.0" font-size="9.5" fill="#10373E">Restrictions</text><text x="168.4" y="118.0" font-size="9.5" fill="#10373E">4.</text><text x="190.0" y="118.0" font-size="9.5" fill="#10373E">Embargo</text><text x="247.6" y="118.0" font-size="9.5" fill="#10373E">Status</text></svg></div>

### Password Policy Chain
```
Login → AuthService → LoginSecurityService (lockout check)
                    → Password verify
                    → PasswordPolicyService (expiry check)
                    → Session creation

Password Change → PasswordPolicyService.isPasswordReused()
                → PasswordPolicyService.recordPasswordChange()
```

---

## Recommended Cron Jobs

```bash
# Breach notification deadline check (hourly)
0 * * * * cd /usr/share/nginx/archive && php symfony privacy:breach-check --email=dpo@example.com >> /var/log/atom/breach-check.log 2>&1

# Login attempt cleanup (daily)
0 3 * * * cd /usr/share/nginx/archive && php bin/atom tools:cleanup-login-attempts 2>&1

# Audit log retention (weekly)
0 4 * * 0 cd /usr/share/nginx/archive && php bin/atom tools:audit-retention 2>&1
```

---

## Residual Risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| ahgLibraryPlugin cover-proxy.php SSRF | Medium | Plugin is locked; URL construction uses sanitized ISBN only (limited exploitation) |
| TOTP requires integration with login flow | Low | Backend complete; login form integration pending |
| Password expiry requires integration with password change form | Low | Service complete; form integration pending |

---

## Compliance Mapping

| Control | Standard | Implementation |
|---------|----------|----------------|
| A.9.2.4 Authentication | ISO 27001 | LoginSecurityService + AuthService |
| A.9.3.1 Password Policy | ISO 27001 | PasswordPolicyService + require_strong_passwords |
| A.9.4.3 Password Management | ISO 27001 | Password expiry + history |
| A.12.4.1 Event Logging | ISO 27001 | ahgAuditTrailPlugin (enabled by default) |
| A.14.2.5 Security Testing | ISO 27001 | This audit report |
| Section 19 Security Safeguards | POPIA | Encryption, access control, audit trail |
| Section 22 Notification | POPIA | 72h breach monitoring + alerts |
| A01 Broken Access Control | OWASP | CSRF enforce + Bell-LaPadula |
| A03 Injection | OWASP | XXE protection + shell escaping + SQL parameterization |
| A05 Security Misconfiguration | OWASP | Security headers + audit defaults |
| A07 Auth Failures | OWASP | Session fixation + lockout + MFA + password policy |
| A10 SSRF | OWASP | HttpClientService + DNS pre-resolution |
