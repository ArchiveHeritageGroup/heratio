# Heratio - Nginx Security Hardening Guide

**Version:** 1.0
**Date:** 2026-03-07
**Author:** The Archive and Heritage Group (Pty) Ltd
**Framework Version:** 2.8.2

---

## 1. Overview

This document provides security hardening instructions for public-facing Heratio deployments. AtoM runs on Symfony 1.x (end-of-life since 2012), which introduces inherent risk. The mitigation strategy is **defense-in-depth at the Nginx layer** - blocking attack vectors before they reach the application.

### Architecture Security Position

Heratio uses a hybrid architecture:

```
┌─────────────────────────────────────────────┐
│  Internet (untrusted)                       │
└──────────────┬──────────────────────────────┘
               │ HTTPS (TLS 1.2+)
┌──────────────▼──────────────────────────────┐
│  Nginx (SECURITY BOUNDARY)                  │
│  • TLS termination                          │
│  • Rate limiting, bot protection            │
│  • Security headers (HSTS, CSP, etc.)       │
│  • Path traversal / exploit blocking        │
│  • Authentication gating for APIs           │
│  • fail2ban integration                     │
├─────────────────────────────────────────────┤
│  PHP-FPM 8.3 (application)                  │
│  • Symfony 1.4 (router + template engine)   │
│  • AHG Framework (Laravel services)         │
│  • AHG Plugins (business logic)             │
├─────────────────────────────────────────────┤
│  Data Layer (localhost only)                │
│  • MySQL 8, Elasticsearch 7.x              │
│  • Fuseki RiC-O triplestore, TrueNAS NFS         │
└─────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_8d04a639.png)
```

**Key principle:** Symfony 1.4's attack surface is minimized because Nginx intercepts and blocks exploit patterns before they reach PHP. The application layer adds its own defenses (CSP nonces, CSRF tokens, file validation, SSRF protection) as a second line.

---

## 2. Symfony 1.x Risk Assessment

### Why Symfony 1.4 Is a Risk

| Concern | Detail |
|---------|--------|
| End of life | No security patches since 2012 |
| Known CVEs | CSRF bypass, XSS in forms, session fixation |
| Deserialization | `unserialize()` used in session/config handling |
| Routing | Path-based routing may expose internal module names |

### Why the Risk Is Manageable

| Mitigation | Effect |
|------------|--------|
| PHP 8.3 | Modern PHP handles sessions, crypto, and I/O - not Symfony |
| Nginx filtering | Exploit patterns blocked before reaching PHP |
| CSP nonces | XSS mitigated at browser level |
| Laravel QB | SQL injection mitigated - plugins don't use Propel for queries |
| `unserialize()` hardened | All instances use `['allowed_classes' => false]` (see M0_SECURITY_HARDENING.md) |
| File validation | FileValidationService validates MIME, extension, size |
| CSRF tokens | CsrfService provides per-session tokens |
| SSRF protection | HttpClientService blocks private IPs and metadata endpoints |

### Residual Risk

The primary residual risk is a **zero-day in Symfony 1.4's routing or session handling** that bypasses Nginx. This is low-probability because:
- Symfony 1.4 has been static for 14 years (no new code = no new bugs)
- The routing layer is simple and well-understood
- Session handling is delegated to PHP 8.3's native session implementation

---

## 3. Required Security Headers

### 3.1 Headers Already Present

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

### 3.2 Headers to Add

Add these to the `server` block in your Nginx site configuration, in the `SECURITY HEADERS` section:

```nginx
# HSTS - force HTTPS for 1 year, include subdomains
# Only enable after confirming HTTPS works correctly
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# Permissions-Policy - restrict browser features
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()" always;
```

**HSTS warning:** Once enabled, browsers will refuse HTTP connections for the specified duration. Test with a short `max-age` (e.g., 300) first, then increase to 31536000 (1 year) once confirmed working.

---

## 4. API & SPARQL Endpoint Protection

### 4.1 Problem

The following endpoints are publicly accessible without authentication:

| Endpoint | Risk |
|----------|------|
| `/sparql/` | Full SPARQL query access to RiC-O triplestore - data exfiltration |
| `/api/ric/` | RiC semantic search API - information disclosure |
| `/api/provenance/` | Provenance API - information disclosure |
| `/api/editor/` | RiC editor API - potential data modification |
| `/ric-dashboard/` | Admin dashboard - information disclosure |

### 4.2 Fix: Require Authentication

Replace the existing unprotected `location` blocks with authenticated versions. The fix uses the Symfony session cookie - if the user is not logged in, they get a 403.

```nginx
# ======================================
# RiC ENDPOINTS - AUTHENTICATED ONLY
# ======================================

# SPARQL Proxy - require login
location ^~ /sparql/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://192.168.0.112:3030/ric/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_connect_timeout 30;
    proxy_read_timeout 180;
    proxy_send_timeout 180;
    # Remove wildcard CORS - only allow same-origin
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
}

# RiC Semantic Search API - require login
location ^~ /api/ric/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://127.0.0.1:5001/api/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_connect_timeout 30;
    proxy_read_timeout 30;
}

# RiC Provenance API - require login
location ^~ /api/provenance/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://127.0.0.1:5003/api/provenance/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}

# RiC Editor API - require login
location ^~ /api/editor/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://127.0.0.1:5002/api/editor/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}

# RiC Dashboard - require login
location ^~ /ric-dashboard/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    alias /usr/share/nginx/archive/web/ric-dashboard/;
    index index.php index.html;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }
}
```

### 4.3 Bot Blocker Update

Remove the API bypass for SPARQL/RiC in `/etc/nginx/conf.d/bot-blocker.conf`:

```nginx
# BEFORE (allows bots to bypass protection for these endpoints)
map $request_uri $api_bypass {
    default 0;
    ~^/sparql/ 1;       # REMOVE
    ~^/api/ric/ 1;      # REMOVE
    ~^/ricExplorer/ 1;  # REMOVE
    ~^/api/library/ 1;
}

# AFTER
map $request_uri $api_bypass {
    default 0;
    ~^/api/library/ 1;
}
```

---

## 5. Login Brute Force Protection

### 5.1 Nginx Rate Limiting for Login

Add a dedicated rate limit zone for login attempts in `/etc/nginx/conf.d/bot-blocker.conf`:

```nginx
# Login rate limiting - 1 attempt per second per IP
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=1r/s;
```

Add the login location block in the site configuration, **before** the main PHP handler:

```nginx
# Rate-limit login attempts
location ~ ^/index\.php/user/login$ {
    limit_req zone=login_limit burst=5 nodelay;

    include fastcgi_params;
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_param PATH_TRANSLATED $document_root$fastcgi_path_info;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param SCRIPT_NAME $fastcgi_script_name;
    fastcgi_index index.php;
    fastcgi_read_timeout 300;
    fastcgi_buffer_size 128k;
    fastcgi_buffers 4 256k;
    fastcgi_busy_buffers_size 256k;
}
```

### 5.2 fail2ban Login Jail

Create a fail2ban filter for AtoM login failures.

**Filter** - `/etc/fail2ban/filter.d/atom-login.conf`:

```ini
[Definition]
failregex = ^<HOST> .* "POST /index\.php/user/login HTTP/.*" (401|403)
            ^<HOST> .* "POST /index\.php/user/login HTTP/.*" 200 .*
ignoreregex =
```

**Jail** - add to `/etc/fail2ban/jail.local`:

```ini
[atom-login]
enabled  = true
filter   = atom-login
port     = http,https
logpath  = /var/log/nginx/psis_access.log
maxretry = 5
findtime = 300
bantime  = 1800
```

This bans an IP for 30 minutes after 5 failed login attempts within 5 minutes.

---

## 6. Additional Hardening

### 6.1 Hide Server Version

In `/etc/nginx/nginx.conf`, within the `http` block:

```nginx
server_tokens off;
```

### 6.2 Limit Request Body Size

Already configured (`client_max_body_size 2G`). This is appropriate for digital object uploads. For non-upload endpoints, consider a tighter limit:

```nginx
# Default limit for most requests
client_max_body_size 10m;

# Override for upload endpoints only
location ~ ^/index\.php/.*/digitalobject/ {
    client_max_body_size 2G;
    # ... existing config ...
}
```

### 6.3 Timeout Tuning

The current `fastcgi_read_timeout 3600` (1 hour) on the main PHP handler is very generous. For public-facing, consider:

```nginx
# Main handler - 5 minutes max
fastcgi_read_timeout 300;

# Import/export jobs - allow longer (only for authenticated admin)
location ~ ^/index\.php/(import|export|jobs) {
    if ($cookie_symfony = "") {
        return 403;
    }
    fastcgi_read_timeout 3600;
    # ... fastcgi config ...
}
```

### 6.4 SSL Hardening

The current SSL config is good. Optionally add OCSP stapling:

```nginx
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

---

## 7. Complete Security Checklist

### Nginx Layer
- [ ] HSTS header enabled
- [ ] Permissions-Policy header enabled
- [ ] `server_tokens off` set
- [ ] SPARQL endpoint requires authentication
- [ ] RiC API endpoints require authentication
- [ ] RiC Dashboard requires authentication
- [ ] Login endpoint rate-limited
- [ ] Bot blocker API bypass removed for RiC/SPARQL
- [ ] SSL OCSP stapling enabled
- [ ] Main handler timeout reduced to 300s

### fail2ban
- [ ] SSH jail enabled
- [ ] nginx-badbots jail enabled
- [ ] atom-login jail enabled

### Application Layer (already done)
- [x] CSP nonces on all script/style tags
- [x] `unserialize()` hardened with `allowed_classes => false`
- [x] FileValidationService for uploads
- [x] CSRF tokens via CsrfService
- [x] SSRF protection via HttpClientService
- [x] Shell command escaping via ShellCommandService
- [x] XXE prevention via XmlParserService

### Infrastructure
- [x] PHP 8.3 (current)
- [x] MySQL 8 (current, localhost only)
- [x] Elasticsearch 7.x (localhost only)
- [x] TLS 1.2+ with strong ciphers
- [x] Let's Encrypt auto-renewal
- [ ] Fuseki RiC-O triplestore not exposed to network (verify firewall)

---

## 8. Applying the Changes

### Step 1: Backup Current Config

```bash
sudo cp /etc/nginx/sites-enabled/psis.theahg.co.za.conf \
        /etc/nginx/sites-enabled/psis.theahg.co.za.conf.bak.$(date +%Y%m%d)
sudo cp /etc/nginx/conf.d/bot-blocker.conf \
        /etc/nginx/conf.d/bot-blocker.conf.bak.$(date +%Y%m%d)
```

### Step 2: Apply Nginx Changes

1. Add security headers (Section 3.2) to the site config
2. Replace RiC/SPARQL location blocks (Section 4.2) in the site config
3. Update bot-blocker map (Section 4.3) in `/etc/nginx/conf.d/bot-blocker.conf`
4. Add login rate limit zone (Section 5.1) to bot-blocker.conf
5. Add login location block (Section 5.1) to the site config
6. Set `server_tokens off` in `/etc/nginx/nginx.conf`

### Step 3: Test and Reload

```bash
sudo nginx -t                      # Validate config
sudo systemctl reload nginx        # Apply without downtime
```

### Step 4: Configure fail2ban

```bash
# Create the atom-login filter
sudo nano /etc/fail2ban/filter.d/atom-login.conf
# Add the atom-login jail to jail.local
sudo nano /etc/fail2ban/jail.local
# Restart fail2ban
sudo systemctl restart fail2ban
# Verify
sudo fail2ban-client status
```

### Step 5: Verify

```bash
# Check HSTS header
curl -sI https://psis.theahg.co.za | grep -i strict

# Check Permissions-Policy header
curl -sI https://psis.theahg.co.za | grep -i permissions

# Verify SPARQL blocked for anonymous
curl -s -o /dev/null -w "%{http_code}" https://psis.theahg.co.za/sparql/

# Verify server version hidden
curl -sI https://psis.theahg.co.za | grep -i server

# Check fail2ban jails
sudo fail2ban-client status atom-login
```

---

## 9. Security References

- [OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/)
- [Mozilla Observatory](https://observatory.mozilla.org/)
- [Nginx Security Hardening Guide](https://nginx.org/en/docs/http/configuring_https_servers.html)
- [fail2ban Documentation](https://www.fail2ban.org/wiki/index.php/Main_Page)
- [HSTS Preload List](https://hstspreload.org/)

---

## 10. Related Documents

- [SECURITY_MODEL.md](SECURITY_MODEL.md) - Application security architecture
- [M0_SECURITY_HARDENING.md](M0_SECURITY_HARDENING.md) - PHP deserialization and upload hardening
- [CSRF_POLICY.md](CSRF_POLICY.md) - Cross-site request forgery protection
- [OUTBOUND_HTTP_POLICY.md](OUTBOUND_HTTP_POLICY.md) - SSRF prevention
- [SHELL_EXECUTION_POLICY.md](SHELL_EXECUTION_POLICY.md) - Shell command safety
