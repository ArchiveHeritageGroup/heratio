# Heratio — Security Assessment Report

**Date:** 2026-03-07
**Assessor:** The Archive and Heritage Group (Pty) Ltd
**Scope:** Public-facing Heratio deployment — PSIS instance (psis.theahg.co.za)
**Framework Version:** 2.8.2

---

## 1. Executive Summary

A security assessment of the Heratio PSIS instance identified **3 critical findings** and **2 medium findings** related to the Nginx configuration and exposed services. The underlying application layer security (CSP, CSRF, file validation, SSRF protection) was found to be well-implemented following the M0 Security Hardening milestone.

The primary risk for public-facing deployments is the use of Symfony 1.x (end-of-life since 2012) as the application router. This risk is mitigated through defense-in-depth at the Nginx layer and modern PHP 8.3 runtime security. A full application rewrite is not recommended — the cost-benefit analysis strongly favors infrastructure-level hardening.

### Risk Summary

| Severity | Count | Status |
|----------|-------|--------|
| Critical | 3 | Remediation documented |
| Medium | 2 | Remediation documented |
| Low | 2 | Remediation documented |
| Informational | 3 | Noted |

---

## 2. Infrastructure Assessment

### 2.1 Technology Stack

| Component | Version | EOL Status | Risk |
|-----------|---------|------------|------|
| PHP | 8.3.30 | Supported (active) | None |
| MySQL | 8.0.45 | Supported | None |
| Nginx | 1.24.0 | Supported (Ubuntu LTS) | None |
| Elasticsearch | 7.17.29 | Supported (maintenance) | Low |
| Node.js | 20.20.0 | Supported (LTS) | None |
| Ubuntu | 22.04 LTS | Supported until 2027 | None |
| Symfony | 1.4.20 | **EOL since 2012** | Medium (mitigated) |
| Java/OpenJDK | 11.0.30 | Supported | None |

### 2.2 Disk Usage Finding

During the assessment, the server disk usage was found at **87%** (2.7 TB of 3.3 TB), up from 16% one week prior. Root cause: the Apache Jena Fuseki RiC-O triplestore (`/var/lib/fuseki/databases/ric`) grew to **1.8 TB** due to a runaway RiC sync process that executed **1.8 million POST requests** continuously since Feb 27.

**Recommendation:** Stop the Fuseki container, purge the database, and implement sync job safeguards (record limits, disk usage checks) before re-enabling.

---

## 3. Findings

### Finding 1: SPARQL Endpoint Publicly Accessible (CRITICAL)

**Location:** `/sparql/` proxy in Nginx config
**Risk:** Data exfiltration — anyone on the internet can execute arbitrary SPARQL queries against the RiC-O triplestore

**Evidence:**
```nginx
location ^~ /sparql/ {
    proxy_pass http://192.168.0.112:3030/ric/;
    add_header Access-Control-Allow-Origin "*" always;  # Wildcard CORS
    # No authentication check
}
```

**Impact:** An attacker can query all RiC data, extract entity relationships, and enumerate the archive's holdings without authentication.

**Remediation:** Require Symfony session cookie. See NGINX_SECURITY_HARDENING.md Section 4.

---

### Finding 2: RiC API Endpoints Publicly Accessible (CRITICAL)

**Location:** `/api/ric/`, `/api/provenance/`, `/api/editor/` proxies in Nginx config
**Risk:** Information disclosure and potential data modification

**Evidence:**
```nginx
location ^~ /api/editor/ {
    proxy_pass http://127.0.0.1:5002/api/editor/;
    # No authentication check
}
```

**Impact:** The editor API may allow unauthenticated modifications to RiC data. The search and provenance APIs expose metadata to anonymous users.

**Remediation:** Require Symfony session cookie on all RiC API endpoints. See NGINX_SECURITY_HARDENING.md Section 4.

---

### Finding 3: Bot Blocker Exempts Sensitive Endpoints (CRITICAL)

**Location:** `/etc/nginx/conf.d/bot-blocker.conf` — `$api_bypass` map
**Risk:** Bot protection is explicitly disabled for SPARQL and RiC endpoints

**Evidence:**
```nginx
map $request_uri $api_bypass {
    ~^/sparql/ 1;      # Bots can freely access SPARQL
    ~^/api/ric/ 1;     # Bots can freely access RiC API
    ~^/ricExplorer/ 1; # Bots can freely access RiC Explorer
}
```

**Impact:** Even with bot protection enabled, known bad bots (scrapers, AI crawlers) are allowed through to sensitive data endpoints.

**Remediation:** Remove SPARQL/RiC entries from the bypass map. See NGINX_SECURITY_HARDENING.md Section 4.3.

---

### Finding 4: Missing HSTS Header (MEDIUM)

**Location:** Nginx site config — security headers section
**Risk:** Downgrade attacks — a man-in-the-middle could intercept the initial HTTP request before the 301 redirect to HTTPS

**Evidence:** No `Strict-Transport-Security` header present in server responses.

**Remediation:** Add HSTS header. See NGINX_SECURITY_HARDENING.md Section 3.2.

---

### Finding 5: Missing Permissions-Policy Header (MEDIUM)

**Location:** Nginx site config — security headers section
**Risk:** Browser features (camera, microphone, geolocation) are not restricted, which could be exploited if XSS is achieved

**Evidence:** No `Permissions-Policy` header present in server responses.

**Remediation:** Add Permissions-Policy header. See NGINX_SECURITY_HARDENING.md Section 3.2.

---

### Finding 6: No Login-Specific Rate Limiting (LOW)

**Location:** Nginx site config — login endpoint
**Risk:** Brute force password attacks against the login form

**Current state:** The general rate limit (`zone=slow`, 10r/s with burst=40) applies to all requests, but the login endpoint has no stricter limit. fail2ban has a `nginx-badbots` jail but no login-specific jail.

**Remediation:** Add dedicated login rate limit (1r/s) and fail2ban jail. See NGINX_SECURITY_HARDENING.md Sections 5.1 and 5.2.

---

### Finding 7: Server Version Exposed (LOW)

**Location:** Nginx response headers
**Risk:** Information disclosure — reveals Nginx version to potential attackers

**Remediation:** Set `server_tokens off` in nginx.conf. See NGINX_SECURITY_HARDENING.md Section 6.1.

---

### Informational Findings

| # | Finding | Note |
|---|---------|------|
| I1 | Symfony 1.4 EOL | Mitigated by Nginx layer, PHP 8.3, and application hardening. Full rewrite not cost-effective. Monitor for zero-days. |
| I2 | Elasticsearch 7.x maintenance mode | Plan migration to OpenSearch or ES 8.x within 12 months. See OPENSEARCH.md. |
| I3 | Fuseki Docker container runs as `systemd-network` user | Consider dedicated service account with minimal permissions. |

---

## 4. Existing Security Controls (Positive Findings)

The following security controls were found to be properly implemented:

| Control | Status | Reference |
|---------|--------|-----------|
| TLS 1.2+ with strong ciphers | Implemented | Nginx SSL config |
| X-Frame-Options: SAMEORIGIN | Implemented | Nginx headers |
| X-XSS-Protection: 1; mode=block | Implemented | Nginx headers |
| X-Content-Type-Options: nosniff | Implemented | Nginx headers |
| Referrer-Policy: strict-origin-when-cross-origin | Implemented | Nginx headers |
| CSP with nonces | Implemented | QubitCSPFilter |
| PHP exploit scanner blocking | Implemented | Nginx location rules |
| Path traversal blocking | Implemented | Nginx if rules |
| Bot protection (user agent) | Implemented | bot-blocker.conf |
| IP blocking | Implemented | bot-blocker.conf |
| Browse/search rate limiting | Implemented | browse_limit, search_limit zones |
| Taxonomy anonymous access blocked | Implemented | Nginx cookie check |
| Direct PHP access denied | Implemented | Nginx location rule |
| .git/.ht/.svn access denied | Implemented | Nginx location rule |
| `unserialize()` hardened | Implemented | M0 milestone |
| File upload validation | Implemented | FileValidationService |
| CSRF protection | Implemented | CsrfService |
| SSRF protection | Implemented | HttpClientService |
| Shell command escaping | Implemented | ShellCommandService |
| XXE prevention | Implemented | XmlParserService |
| fail2ban (SSH + bad bots) | Implemented | jail.local |

---

## 5. Architecture Decision: Hybrid vs. Standalone

### Decision

**The hybrid architecture (Symfony base + Laravel framework + AHG plugins) is the recommended long-term approach.** Standalone Heratio (full Laravel rewrite) is parked.

### Rationale

| Factor | Hybrid | Standalone Heratio |
|--------|--------|--------------------|
| Security improvement | Marginal — Nginx layer provides equivalent protection | Removes Symfony 1.4 but introduces new Laravel attack surface |
| Development cost | Zero — already working | ~4,400 templates to replicate |
| AtoM upstream compatibility | Full — pull updates directly | Broken — separate codebase |
| Time to market | Immediate | 6+ months minimum |
| Plugin compatibility | All 80 plugins work | All 80 plugins need migration |
| Current completion | 100% functional | 0.3% done (15 of 4,435 views) |

### Conclusion

The security risk from Symfony 1.4 is **theoretical and mitigated**, not **practical and exploitable**. The Nginx hardening documented in this assessment provides more security value than a full application rewrite at a fraction of the cost.

---

## 6. Remediation Priority

| Priority | Finding | Effort | Impact |
|----------|---------|--------|--------|
| 1 (Immediate) | F1: SPARQL auth | 15 min | Closes data exfiltration vector |
| 2 (Immediate) | F2: RiC API auth | 15 min | Closes information disclosure |
| 3 (Immediate) | F3: Bot bypass removal | 5 min | Closes bot access to sensitive data |
| 4 (This week) | F4: HSTS header | 5 min | Prevents HTTPS downgrade |
| 5 (This week) | F5: Permissions-Policy | 5 min | Restricts browser features |
| 6 (This week) | F6: Login rate limit | 30 min | Prevents brute force |
| 7 (This week) | F7: Server tokens | 2 min | Reduces information disclosure |

**Total remediation effort: approximately 1.5 hours.**

---

## 7. Next Assessment

Recommended reassessment in **6 months** (September 2026) or after any major infrastructure change.

Items to monitor:
- Elasticsearch 7.x EOL timeline — plan OpenSearch migration
- Ubuntu 22.04 LTS support ends April 2027 — plan upgrade path
- Any Symfony 1.4 zero-day advisories (monitor CVE databases)
- Fuseki container resource usage after re-enabling RiC sync
