# Heratio - Security Model

**Version:** 1.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

Heratio is a two-layer system: base AtoM (Symfony 1.x) and the AHG Framework (Laravel-based services). Security controls span both layers and must be understood together.

This document defines the trust boundaries, authentication model, secret management, and the four hardened attack surfaces: CSP, file uploads, outbound HTTP, CSRF, and shell execution.

---

## 2. Trust Boundaries

```
┌──────────────────────────────────────────────┐
│  BROWSER (untrusted)                         │
│  • User input, file uploads, AJAX requests   │
└────────────────┬─────────────────────────────┘
                 │ HTTPS (TLS 1.2+)
┌────────────────▼─────────────────────────────┐
│  NGINX reverse proxy                         │
│  • Rate limiting, bot protection, CSP headers│
└────────────────┬─────────────────────────────┘
                 │ FastCGI
┌────────────────▼─────────────────────────────┐
│  PHP-FPM 8.3 (application layer)             │
│  ┌─────────────────────────────────────────┐ │
│  │ AtoM 2.10 (Symfony 1.x)                │ │
│  │  • sfUser session, sfGuard ACL          │ │
│  │  • QubitCSPFilter (nonce generation)    │ │
│  ├─────────────────────────────────────────┤ │
│  │ AHG Framework (atom-framework)          │ │
│  │  • CsrfService, HttpClientService       │ │
│  │  • ShellCommandService, AclService      │ │
│  │  • FileValidationService                │ │
│  ├─────────────────────────────────────────┤ │
│  │ AHG Plugins (atom-ahg-plugins)          │ │
│  │  • Per-plugin actions + templates       │ │
│  └─────────────────────────────────────────┘ │
└────────────────┬─────────────────────────────┘
                 │
┌────────────────▼─────────────────────────────┐
│  DATA LAYER (trusted internal)               │
│  • MySQL 8 (localhost, no remote access)     │
│  • Elasticsearch 7.x (localhost)             │
│  • TrueNAS (NFS mount, digital objects)      │
│  • Fuseki RiC-O triplestore (localhost:3030)        │
└──────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_55f2abe6.png)
```

---

## 3. Authentication Model

### 3.1 Session-Based Authentication
- PHP session managed by Symfony's `sfUser`
- Session cookie: `HttpOnly`, `Secure`, `SameSite=Lax`
- Session stored in filesystem (`cache/sessions/`)
- Idle timeout: configurable (default 30 minutes)

### 3.2 API Authentication
- Bearer token: `Authorization: Bearer <token>` header
- API key: `X-API-Key` header
- Both exempt from CSRF validation (stateless auth)

### 3.3 Access Control
- **AclService**: enforces per-user, per-group permissions
- **SecurityClearanceService**: classification levels (Unclassified → Top Secret)
- **Role 99**: superuser bypass (framework applies `in_array` safety check)

---

## 4. Secret Management

| Secret | Storage | Access |
|--------|---------|--------|
| Database credentials | `config/config.php` (server-local, not in git) | PHP `sfConfig::get()` |
| API keys | `ahg_settings` table | `AhgSettingsService::get()` |
| Session secret | `config/factories.yml` | Symfony session handler |
| CSP nonce | Generated per-request | `sfConfig::get('csp_nonce')` |
| CSRF token | PHP session (`$_SESSION`) | `CsrfService::generateToken()` |

**Rules:**
- Never commit secrets to git
- Never log credentials or tokens
- Database credentials use empty password on dev (localhost only)

---

## 5. Content Security Policy (CSP)

- **Configuration:** `config/app.yml` → `all.csp` (per-server, not in git)
- **Filter:** `QubitCSPFilter` (base AtoM, DO NOT MODIFY)
- **Nonce:** Generated per-request, stored in `sfConfig::get('csp_nonce')`
- **Requirement:** ALL `<script>` and `<style>` tags MUST include the nonce
- **External CDNs:** Must be whitelisted in `script-src` / `style-src`
- **Policy:** NEVER use `'unsafe-inline'`; use nonces instead

---

## 6. File Upload Security

- **FileValidationService** (atom-framework): validates MIME type, extension, file size, and magic bytes
- Upload path: `uploads/r/<hash>/` (not user-controllable path segments)
- Executable files (.php, .sh, .py, etc.) are rejected
- Double-extension attacks prevented (e.g., `file.php.jpg`)
- Antivirus scanning available via ClamAV integration (ahgPreservationPlugin)

---

## 7. Outbound HTTP / SSRF Protection

- **HttpClientService** (atom-framework): safe HTTP client with SSRF protection
- Private IP blocking: `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE`
- Cloud metadata blocking: `169.254.169.254`, `metadata.google.internal`
- DNS pre-resolution: prevents DNS rebinding attacks
- Redirect IP re-validation: SSRF checks re-applied on every redirect
- SSL verification: ON by default
- Response size limit: 10 MB
- Timeout: 15 seconds

See: [OUTBOUND_HTTP_POLICY.md](OUTBOUND_HTTP_POLICY.md)

---

## 8. CSRF Protection

- **CsrfService** (atom-framework): per-session tokens, 1-hour rotation
- **Validation:** `hash_equals()` constant-time comparison
- **Template helpers:** `csrf_field()`, `csrf_token()`, `csrf_meta()`
- **AJAX:** `csrf.js` auto-injects token via `X-CSRF-TOKEN` header
- **Enforcement modes:** `'log'` (default), `'enforce'`, `'off'`
- **Exemptions:** Bearer auth, API key, safe methods (GET/HEAD/OPTIONS)

See: [CSRF_POLICY.md](CSRF_POLICY.md)

---

## 9. Shell Execution Security

- **ShellCommandService** (atom-framework): safe shell command builders
- All external input MUST use `escapeshellarg()` before passing to shell
- Service name allowlist for `systemctl` operations
- Directory validation via `realpath()` before `cd` operations
- PostScript injection prevention for label generation

See: [SHELL_EXECUTION_POLICY.md](SHELL_EXECUTION_POLICY.md)

---

## 10. XML Parsing (XXE Prevention)

- **XmlParserService** (atom-framework): safe XML parsing
- `LIBXML_NONET | LIBXML_NOCDATA` flags on all XML operations
- `substituteEntities = false` on DOMDocument
- Used for EAD import, MODS/DC metadata, IIIF manifests

---

## 11. Audit & Compliance

| Control | Implementation |
|---------|----------------|
| Audit trail | ahgAuditTrailPlugin - logs all CRUD operations |
| PII detection | ahgPrivacyPlugin - scans for personal information |
| Access logging | Nginx access logs + application audit log |
| Error logging | PHP error_log + ahgAuditTrailPlugin |
| Backup integrity | Checksums in backup manifests |

---

## 12. Deployment Checklist

- [ ] `config/config.php` has correct database credentials (not committed)
- [ ] `config/app.yml` has CSP policy configured
- [ ] SSL/TLS enabled on nginx
- [ ] PHP `display_errors = Off` in production
- [ ] Session cookie flags: `HttpOnly`, `Secure`, `SameSite`
- [ ] `csrf_enforcement` set to `'enforce'` when all forms have tokens
- [ ] ClamAV installed and running for upload scanning
- [ ] Elasticsearch not exposed to network (localhost only)
- [ ] MySQL not exposed to network (localhost only)
- [ ] File permissions: `www-data` owns `cache/`, `uploads/`
- [ ] Nginx security headers hardened (HSTS, Permissions-Policy)
- [ ] SPARQL/RiC API endpoints require authentication
- [ ] Login endpoint rate-limited with fail2ban jail
- [ ] `server_tokens off` in nginx.conf

---

## 13. Related Security Documents

| Document | Location | Content |
|----------|----------|---------|
| [NGINX_SECURITY_HARDENING.md](NGINX_SECURITY_HARDENING.md) | `docs/technical/` | Nginx hardening guide for public-facing deployments |
| [M0_SECURITY_HARDENING.md](M0_SECURITY_HARDENING.md) | `docs/technical/` | PHP deserialization and upload hardening |
| [CSRF_POLICY.md](CSRF_POLICY.md) | `docs/technical/` | Cross-site request forgery protection |
| [OUTBOUND_HTTP_POLICY.md](OUTBOUND_HTTP_POLICY.md) | `docs/technical/` | SSRF prevention policy |
| [SHELL_EXECUTION_POLICY.md](SHELL_EXECUTION_POLICY.md) | `docs/technical/` | Shell command safety |
| Security Assessment Reports | `docs/` | Periodic security assessment findings |
