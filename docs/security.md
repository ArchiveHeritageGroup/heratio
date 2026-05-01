# Heratio - Security Model & Operator Runbook

One-pager. Read before exposing a Heratio instance to the public internet.

---

## 1. Threat model - what Heratio protects against

| Threat | Layer | Mitigation in v1.37+ |
|---|---|---|
| SQL injection | App | Eloquent ORM parameterises everything. Manual `DB::raw()` paths exist in ~10 files (ported from AtoM Symfony) - review before public exposure. |
| XSS (stored / reflected) | App | Blade auto-escapes `{{ … }}`. `{!! … !!}` raw output exists in views - audit those when extending. CSP blocks third-party scripts. |
| CSRF | App | Laravel `VerifyCsrfToken` middleware on all POST/PUT/DELETE in the `web` group. |
| Session hijacking | Transport + cookies | HTTPS (operator runs `certbot`), `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_ENCRYPT=true`, `SESSION_SAME_SITE=lax`. |
| Click-jacking | Headers | `X-Frame-Options: SAMEORIGIN`, `Content-Security-Policy: frame-ancestors 'self'`. |
| MIME-type confusion | Headers | `X-Content-Type-Options: nosniff`. |
| Information disclosure via .env / stack traces | App + nginx | `APP_DEBUG=false`, `APP_ENV=production`, `LOG_LEVEL=warning`. nginx denies `*.env`, `*.sql`, `*.log`, `*.md`, `vendor/`, `storage/`, `database/`, `config/`, `composer.lock`. |
| Credential leak via AI / RAG | KM service | `/api/ask` requires `Authorization: Bearer <KM_API_KEY>`. Ingest pipeline scrubs passwords / API keys / private IPs / SSH keys via `redact_secrets()` before chunking. |
| Brute-force login | Operator | Heratio has no built-in rate limiting; **add nginx `limit_req_zone` or fail2ban per deployment**. |
| Privilege escalation | App | Admin role checked via `AhgAcl\Middleware\AuthAdmin` on `/admin/*`. |
| File-upload abuse | App | `ahg-ingest` validates MIME + extension + virus-scans via `ahg:preservation-virus-scan`. |
| Path traversal | App + nginx | Laravel `Storage` facade + nginx `^~ /uploads/` + `try_files`. |

**Out of scope (operator's responsibility):**
- Operating system hardening (kernel, sshd, ufw)
- TLS certificate provisioning + renewal
- DNS / CDN / DDoS mitigation
- Backup encryption + off-site rotation
- Network segmentation (DB / ES / Redis on private interfaces only)

---

## 2. Pre-deployment checklist

Run before `https://your-domain` is reachable from the public internet.

```bash
# 1. Database not bound to 0.0.0.0
ss -tln | grep :3306                         # expect: 127.0.0.1:3306 only

# 2. .env not world-readable
stat -c '%a %n' /usr/share/nginx/heratio/.env  # expect: 640

# 3. APP_DEBUG off, APP_ENV=production
grep -E "^APP_(DEBUG|ENV)=" /usr/share/nginx/heratio/.env

# 4. Admin password is not Merlot@123 / not the install default
mysql -u root -p heratio -e "
  SELECT username, email FROM \`user\`
  WHERE sha1_password = '\$2y\$12\$mFx…known-default-bcrypt-of-Merlot…';
"   # expect: zero rows

# 5. SSL cert valid + HSTS header set on 443
curl -sI https://your-domain | grep -iE "strict-transport-security"

# 6. CSP header present
curl -sI https://your-domain | grep -iE "content-security-policy"

# 7. Sensitive paths return 403
for p in /.env /vendor/composer.json /storage/logs/laravel.log /database/seeds/00_taxonomies.sql; do
  printf "%-50s %s\n" "$p" "$(curl -sI -o /dev/null -w '%{http_code}' https://your-domain$p)"
done   # expect: all 403/404

# 8. Login rate limit in place
for i in {1..20}; do curl -sw "%{http_code}\n" -o /dev/null \
  -X POST https://your-domain/admin/login \
  -d "email=admin@example&password=wrong"; done
# expect: 429s after a few requests (if rate limit configured)

# 9. KM, Qdrant, Cantaloupe (if installed) not publicly reachable
for url in https://km.your-domain/api/stats \
           http://your-domain:6333/ \
           http://your-domain:8182/iiif/3/; do
  curl -m 3 -sI -o /dev/null -w "$url -> %{http_code}\n" "$url"
done   # expect: all 403 / connection refused / timeout

# 10. No literal credentials in tracked files
git -C /usr/share/nginx/heratio grep -InE "(Merlot@|AtoM@|password\s*[:=]\s*[A-Za-z0-9!@#\$%^&*]{6,})"
# expect: nothing (only docs / placeholders)
```

---

## 3. Routine operations

| Cadence | Task |
|---|---|
| Each release | Run `gitleaks detect --source . --redact` (or pattern grep above) before tagging. |
| Each release | `composer audit` - check for known CVEs in dependencies. |
| Weekly | Review `tail /usr/share/nginx/heratio/storage/logs/laravel.log` + nginx error log for repeated 401/403/500. |
| Weekly | Tail `audit.db` of the KM service for unexpected query patterns. |
| Quarterly | Rotate `KM_API_KEY` (`/etc/systemd/system/ahg-km.service.d/override.conf`) and update every consumer's `.mcp.json` / `ahg_settings`. |
| Quarterly | Rotate MySQL root password (script: `/root/rotate-creds.sh` - adapt for ongoing use). |
| Quarterly | Rotate admin user passwords (`heratio:user:reset-password` artisan command, or via `/admin/users`). |
| As needed | Reissue letsencrypt certs (cron or `certbot renew` daily). |

---

## 4. Incident response

### Suspected credential leak

1. **Find the exposure**: where did the secret land?
   ```bash
   # In tracked files
   git -C /usr/share/nginx/heratio grep -In "<the secret>"

   # In RAG corpus
   curl -s -X POST http://localhost:6333/collections/km_heratio/points/scroll \
     -H 'Content-Type: application/json' \
     -d '{"limit":2000,"with_payload":true}' \
     | python3 -c "import sys,json; [print(p) for p in json.load(sys.stdin)['result']['points'] if '<secret>' in str(p)]"

   # In server logs
   grep -r "<the secret>" /var/log/nginx /var/log/mysql 2>/dev/null
   ```

2. **Contain**: delete the offending Qdrant point IDs (`POST /collections/<col>/points/delete`); if in committed git history, the credential is **forever burned** - go to step 4.

3. **Assess blast radius**: log into each consumer service and find where the secret was used.

4. **Rotate** all instances of the burned credential. See `/root/rotate-creds.sh` for the established rotation pattern.

5. **Patch the source**: update sanitisers (`/opt/ai/km/ingest_heratio.py:redact_secrets()`, `.gitignore`, pre-commit hook).

6. **Audit logs** for evidence of malicious use during the exposure window: `audit.db`, nginx access logs, MySQL `general_log`.

### Suspected app compromise

1. Revoke all sessions: `php artisan session:flush` (or truncate `sessions` table).
2. Force password reset for all admin users.
3. Rotate `APP_KEY` (will invalidate sessions + encrypted columns; coordinate carefully).
4. Take a forensic snapshot before changing anything.
5. Compare `vendor/` and `node_modules/` to release tarball checksums.

---

## 5. Ports & service inventory

What runs where on a single-host install. Lock down anything not explicitly LAN-only.

| Port | Service | Bind | Public-internet exposure |
|---|---|---|---|
| 80 / 443 | Heratio (nginx → php-fpm) | 0.0.0.0 | YES (intended) |
| 3306 | MySQL | 127.0.0.1 | NO |
| 6379 | Redis | 127.0.0.1 | NO |
| 9200 | Elasticsearch | 127.0.0.1 | NO |
| 5050 | KM (ahg-km Flask) | 127.0.0.1 (proxied at km.your-domain, LAN-locked) | NO (LAN only) |
| 6333 | Qdrant | 127.0.0.1 | NO |
| 8182 | Cantaloupe IIIF | 127.0.0.1 (proxied at /iiif/) | NO (proxied only) |
| 11434 | Ollama (on AI host, separate machine) | LAN | NO |

---

## 6. Known weak spots - patch these before going public

| Weak spot | Severity | Workaround |
|---|---|---|
| CSP allows `unsafe-inline` + `unsafe-eval` (legacy AtoM JS + CDN libs) | Medium | Audit + remove inline JS, then tighten CSP |
| Cantaloupe `Access-Control-Allow-Origin: *` | Low | Restrict to your domain in `nginx-iiif-snippet.conf` |
| 79 ported AHG plugins not independently security-reviewed | Medium | `composer audit`, manual review of `DB::raw()` call sites |
| No login rate-limiting in app | High when public | Add nginx `limit_req_zone`: 10 req/min/IP on `/admin/login` |
| `heratio:user:add-superuser` prints password to stdout | Low | Pipe stdout to a file with `chmod 600` for audit trail |
| Default MySQL on Ubuntu accepts socket auth as `root` | Low | After install, set MySQL `root` password and update `.env` accordingly |
| `.mcp.json` carries `KM_API_KEY` if you add it; gitignored but still on disk | Low | `chmod 600 .mcp.json`; rotate `KM_API_KEY` quarterly |

---

## 7. References

- [OWASP Top 10 (2021)](https://owasp.org/www-project-top-ten/) - baseline coverage targets
- [Laravel security](https://laravel.com/docs/12.x/authentication) - framework-provided protections
- [`docs/standalone-install-plan.md`](standalone-install-plan.md) - install architecture
- [`docs/standalone-install-howto.md`](standalone-install-howto.md) - operator install steps
- `/root/rotate-creds.sh` (operator-only) - credential rotation script template
- 2026-04-30 incident: 4 credentials leaked via public KM `/api/ask`. Remediated in v1.37.1. See git log + `feedback_km_no_credentials.md` memory entry.
