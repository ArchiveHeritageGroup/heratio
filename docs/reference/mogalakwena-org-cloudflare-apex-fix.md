# mogalakwena.org - apex DNS permanently fixed via Cloudflare (2026-05-04)

## Summary

The `mogalakwena.org` apex used to be a hand-pinned A record at the previous DNS host (cPanel shared-hosting nameservers `ns.dns1.co.za` / `ns.dns2.co.za`). It was pointing at the previous WAN IP. When the WAN IP rotated, `www.mogalakwena.org` kept working (it CNAMEs to a No-IP DDNS hostname that auto-tracks the new IP) but the apex went dark - the static A record kept resolving to a host nobody owned anymore.

Permanent fix: move DNS hosting (not domain registration) to Cloudflare's free tier. Cloudflare supports CNAME-at-apex via "CNAME flattening", so the apex can now CNAME to the same DDNS hostname `www` uses, and any future IP rotation propagates to both names automatically.

## Resolution chain after the fix

```
Domain registrar (mogalakwena.org)
        |
        | NS records point to:
        v
Cloudflare DNS (free tier)
        |
        | apex CNAME (Cloudflare-flattens to A at query time):
        v
theahg.ddns.net   (No-IP free DDNS hostname)
        |
        | A record auto-updated by ddclient on the origin box every ~5min:
        v
current WAN IP   (changes whenever ISP rotates it - tracked automatically)
```

## Step-by-step procedure (what was actually done, in order)

This is the full sequence that took `mogalakwena.org` from "apex dark, www working" to "both apex and www permanently tracking the DDNS hostname".

### Phase 1 - Diagnosis (before touching anything)

1. **Confirm the symptom.** `host mogalakwena.org` returned an IP that no longer answered on port 80/443. `host www.mogalakwena.org` returned the current WAN IP and worked. So it was an apex-only DNS problem, not an origin / nginx / cert problem.
2. **Find current authoritative nameservers.** `dig NS mogalakwena.org +short` returned `ns.dns1.co.za` and `ns.dns2.co.za` - the previous DNS host (cPanel shared-hosting nameservers, set when the domain was first configured years ago).
3. **Inventory the existing zone at the previous DNS host.** Logged into the previous cPanel DNS panel and exported / screenshotted every record (A, CNAME, MX, TXT, SRV) so we could replay the full zone into Cloudflare without losing mail / autodiscover / DMARC / SPF.
4. **Confirm the DDNS hostname is healthy.** `host theahg.ddns.net` returned the current WAN IP. So if we could just point the apex *at* that hostname instead of at a static IP, the problem would self-heal forever.
5. **Discovered the blocker.** Standard DNS forbids a CNAME at a zone apex (RFC 1034 - apex must coexist with SOA + NS, and CNAME excludes all other records). That's why the previous host was using a hard A record. The fix is a DNS provider that supports apex CNAME flattening - i.e. accepts `CNAME` at apex and resolves it to a synthetic `A` answer at query time. Cloudflare free tier does this; the previous DNS host did not.

### Phase 2 - Cloudflare zone setup

1. **Created a Cloudflare account** (free tier) using the user's standard ops email.
2. **Add a Site -> entered `mogalakwena.org`** -> selected the **Free** plan.
3. **Cloudflare auto-scanned the existing public DNS** and pre-populated the new zone with whatever records its scan could see. This auto-import is best-effort - it catches public records but commonly misses TXT, SRV, and any record that wasn't in a recent query cache. Treat it as a starting point, not a finished zone.
4. **Reconciled the auto-imported records against the inventory from Phase 1 step 3.** Manually added every record the auto-scan missed (chiefly TXT / DMARC / SPF / DKIM / SRV / autoconfig). Manually removed nothing at this stage.
5. **Set the apex (`@`) to `CNAME -> theahg.ddns.net`, DNS only (grey cloud).** This is the whole point of the migration. Cloudflare flattens this CNAME to a synthetic A record on every query, so the apex now follows the DDNS hostname automatically.
6. **Set `www` to `CNAME -> theahg.ddns.net`, DNS only.** Replaces the old `www` record (which already CNAMEd to the same DDNS hostname at the previous host).
7. **Set every other record to DNS only (grey cloud).** No proxying. Reason: the Let's Encrypt cert lives on the origin box's nginx; turning on the orange cloud would terminate TLS at Cloudflare's edge and break HTTPS until an Origin Cert was installed and Cloudflare was set to Full (Strict). That's a separate, optional hardening task and was deliberately not done in this change.
8. **Cloudflare assigned two nameservers** to this zone - random per account. For `mogalakwena.org` they are `amy.ns.cloudflare.com` and `peter.ns.cloudflare.com`. Cloudflare displayed these on the zone overview page along with a "Continue" button that we did NOT click yet (clicking it tells Cloudflare to start checking for the NS change at the registrar; pre-flight first).

### Phase 3 - Pre-flight at the registrar (DNSSEC OFF first)

1. **Logged into the domain registrar's control panel** for `mogalakwena.org`.
2. **Checked DNSSEC status.** If DNSSEC was ON, it had to be turned OFF *before* changing nameservers. Reason: the existing DS record at the registrar is signed against the old nameservers' KSK. Switching nameservers without first removing the DS record SERVFAILs the entire domain on every DNSSEC-aware resolver (which is most major resolvers) until either DNSSEC is disabled or a fresh DS record from the new nameservers is published. The TTL on DS records is typically long, so the outage can last hours.
3. **Disabled DNSSEC at the registrar** and waited for the registrar UI to confirm the DS record was removed.

### Phase 4 - Nameserver switch

1. **At the registrar, changed the authoritative nameservers** for `mogalakwena.org` from `ns.dns1.co.za` / `ns.dns2.co.za` to `amy.ns.cloudflare.com` / `peter.ns.cloudflare.com`.
2. **Saved the change at the registrar.** The registrar pushes the new NS records up to the `.org` TLD registry; propagation across the public DNS typically completes in 5-30 minutes but can take up to a few hours depending on the resolver's cache.
3. **Back in Cloudflare**, clicked "Continue" / "Check nameservers". Cloudflare polls the TLD periodically and flips the zone status to **Active** once it sees its own nameservers as authoritative. Until then the zone status stays "Pending".

### Phase 5 - Propagation verification

1. **Watched propagation with `dig`:**
   ```bash
   dig NS mogalakwena.org +short            # expect amy/peter.ns.cloudflare.com
   dig NS mogalakwena.org @1.1.1.1 +short   # bypass any local cache
   dig NS mogalakwena.org @8.8.8.8 +short
   ```
   Once all three returned the Cloudflare nameservers, propagation was complete.
2. **Verified apex resolution:**
   ```bash
   dig mogalakwena.org +short               # should now match...
   dig theahg.ddns.net +short               # ...this (current WAN IP)
   dig www.mogalakwena.org +short           # also matches
   ```
   All three returning the same IP = Cloudflare's apex CNAME flattening is working.
3. **Cloudflare zone overview flipped to Active** (green checkmark). Confirmed by email from Cloudflare.

### Phase 6 - End-to-end verification

1. **`curl -I https://mogalakwena.org`** returned `HTTP/2 200` and the existing Let's Encrypt cert (issued for `mogalakwena.org` + `www.mogalakwena.org`) - confirming DNS now reaches the origin and the existing cert is still valid for both names.
2. **Browser test:** loaded `https://mogalakwena.org` and `https://www.mogalakwena.org` - both rendered the live site, no cert warnings.
3. **Mail flow check:** sent a test message to a `@mogalakwena.org` address - delivered. MX/SPF/DMARC records were carried over correctly.

## Final Cloudflare DNS configuration (record-by-record)

| Name | Type | Target | Proxy | Purpose |
|---|---|---|---|---|
| `@` (apex) | CNAME | `theahg.ddns.net` | DNS only | Web - apex follows DDNS, flattened to A at query time by Cloudflare |
| `www` | CNAME | `theahg.ddns.net` | DNS only | Web - same target as apex |
| `cpanel` | A | cPanel host (mail provider) | DNS only | cPanel control panel for the legacy mailbox / hosting account |
| `ftp` | A | cPanel host | DNS only | FTP into the cPanel account (legacy) |
| `mail` | A | cPanel host | DNS only | Mail submission endpoint (SMTP / IMAP) |
| `webmail` | A | cPanel host | DNS only | Webmail UI |
| `autoconfig` | CNAME | `envoy.aserv.co.za` | DNS only | Mail client autoconfig hint (Thunderbird, etc.) |
| `autodiscover` | CNAME | `envoy.aserv.co.za` | DNS only | Mail client autoconfig hint (Outlook) |
| MX records | MX | mail provider's MX hostnames | DNS only | Inbound mail routing - imported as-is from previous host |
| SPF | TXT | `v=spf1 ...` | DNS only | Sender Policy Framework - imported as-is |
| DMARC | TXT | `_dmarc.mogalakwena.org -> v=DMARC1; ...` | DNS only | DMARC policy - imported as-is |
| DKIM | TXT | `<selector>._domainkey -> v=DKIM1; k=rsa; p=...` | DNS only | DKIM signing key - imported as-is |
| SRV records | SRV | mail / autodiscover SRV targets | DNS only | Imported as-is from previous host |

**Universal rule for this zone: every record is DNS only (grey cloud), nothing Proxied (orange cloud).** Switching to proxied is a separate, optional later task that requires installing a Cloudflare Origin Cert on the origin box's nginx and enabling Full (Strict) SSL mode in Cloudflare.

## Cloudflare DNS panel - record conventions

After the migration, every record in the Cloudflare DNS panel for `mogalakwena.org` must be:

- **Apex** (`@` / `mogalakwena.org`): `CNAME` to `theahg.ddns.net`, **DNS only** (grey cloud).
- **www**: `CNAME` to `theahg.ddns.net`, **DNS only**.
- **cpanel / ftp / mail / webmail**: `A` records to the cPanel mail-host IP, **DNS only** (these are non-HTTP services, can't be proxied anyway).
- **autoconfig / autodiscover**: `CNAME` to `envoy.aserv.co.za`, **DNS only** (mail-client autoconfig hints).
- **MX / SRV**: imported as-is from the previous DNS host. **DNS only** (MX records can never be proxied).
- **TXT** (DMARC, SPF, mailconf): **DNS only**.

**Rule of thumb: every record is DNS only (grey cloud), nothing Proxied (orange cloud).** Reason: the Let's Encrypt cert lives on the origin box's nginx. Proxy mode would terminate TLS at Cloudflare's edge with a Cloudflare cert and then need an Origin Cert installed on the origin nginx (Full Strict mode). Until that's set up, grey cloud is correct everywhere. Switching individual records to Proxied later is a separate optional task and would also enable Cloudflare's DDoS / cache features for those names.

## DNSSEC

DNSSEC must be **OFF at the registrar before the nameserver switch**. Reason: DNSSEC ties cryptographic signatures to whichever nameservers were authoritative when the DS record was published. Switching nameservers without first disabling DNSSEC SERVFAILs the entire domain on every DNSSEC-aware resolver until either DNSSEC is disabled or the new DS record is republished.

DNSSEC can be re-enabled later via Cloudflare's DNS panel (which then publishes a fresh DS record, which the user manually copies back to the registrar). That's a separate optional hardening task.

## Decision rationale - why Cloudflare free over alternatives

| Option | Cost | Pros | Cons |
|---|---|---|---|
| Cloudflare free | $0 | apex CNAME flattening, free DDoS / edge cache available later, unlimited domains | adds a third-party DNS vendor |
| No-IP Plus Managed DNS | ~$30-50/yr/domain | single-vendor (already use No-IP for DDNS), native dynamic A at apex | not free |
| Static A record at registrar + cron updater script | $0 | no third party | requires writing + maintaining a registrar-API updater |

Picked Cloudflare free for the zero-cost + bulletproof apex behaviour. The user already runs No-IP DDNS for the `theahg.ddns.net` hostname; that piece stays in place unchanged.

## Debug recipe - if mogalakwena.org breaks again

1. **`host mogalakwena.org`** should resolve to the same IP as **`host theahg.ddns.net`** (which should be the current WAN IP). If they match, DNS is healthy and the problem is on the origin (nginx vhost, cert, app).
2. **If apex resolves to a stale or wrong IP**: open the Cloudflare DNS panel for `mogalakwena.org`. The apex should be `CNAME mogalakwena.org -> theahg.ddns.net` (DNS only). If someone has "fixed" it by switching to a hard A record, that's the regression - revert to CNAME.
3. **If `theahg.ddns.net` itself resolves to a stale IP**: ddclient on the origin box isn't updating. Check `/var/log/ddclient.log` or `systemctl status ddclient`. The DDNS update should happen every ~5 min.
4. **If DNS is correct but HTTPS fails**: the nginx vhost on the origin box must have `server_name mogalakwena.org www.mogalakwena.org;` covering both names. The Let's Encrypt cert on the origin already covers both names; if it's expired, run `sudo certbot renew`.

## Cloudflare nameservers assigned to this domain

Random per Cloudflare account. For this domain:
- `amy.ns.cloudflare.com`
- `peter.ns.cloudflare.com`

(These are the two nameservers configured at the registrar after migration. Recorded for next-time-you-need-to-prove-it-was-Cloudflare debugging.)

## Operator memory cross-reference

The full operator-side detail (which IPs are which, where ddclient runs, NAS layout for the Mogalakwena Drupal VM backups) lives in the local operator memory file `reference_projects_inventory.md` (not in this KM doc - that file deliberately holds IPs and access details that we don't ingest into the public RAG corpus).

## What changed vs. what stayed the same

**Changed:**
- DNS hosting moved from `ns.dns1.co.za` / `ns.dns2.co.za` (cPanel shared-hosting nameservers at the previous DNS host) to `amy.ns.cloudflare.com` / `peter.ns.cloudflare.com` (Cloudflare free tier).
- Apex (`mogalakwena.org`) record type changed from a hand-pinned `A` record (pointing at a stale WAN IP) to a `CNAME` to `theahg.ddns.net` (flattened to A by Cloudflare at query time).
- DNSSEC toggled OFF at the registrar (was a precondition for the nameserver switch, can be re-enabled later via Cloudflare's DNS panel + a fresh DS record at the registrar).

**Stayed the same:**
- Domain registration - still at the same registrar. Only DNS *hosting* moved; ownership / WHOIS unchanged.
- The DDNS hostname `theahg.ddns.net` and the `ddclient` agent on the origin box that updates it every ~5 min - unchanged.
- The origin box's nginx vhost for `mogalakwena.org` / `www.mogalakwena.org` - unchanged.
- The Let's Encrypt cert for both names, served from the origin nginx - unchanged.
- Mail flow - same mail provider, same MX / SPF / DKIM / DMARC values, just hosted at a different DNS provider now.
- The `www` CNAME continued working uninterrupted throughout the migration (it was already a CNAME to the DDNS hostname; only its NS path changed).

## Outage window during migration

The user kept the old A record alive at the previous DNS host *and* added the apex CNAME at Cloudflare *before* changing nameservers. The actual cutover (NS change) is the only point where some resolvers see one set of authoritative nameservers and others see the other; during that window, a resolver might serve either the stale A or the fresh CNAME. Both pointed to a working address by the time the NS change went in (the stale A was tolerated, the CNAME was correct), so end-users saw at worst a brief HTTP-only / wrong-IP blip. After the NS propagation completed (well under an hour for most resolvers), the apex was permanently on the CNAME path.

## Optional follow-up tasks (deliberately deferred)

1. **Re-enable DNSSEC** via Cloudflare's DNS panel. Cloudflare publishes a DS record on its end; the matching DS value must be copied into the registrar control panel manually. Until that's done, the domain is unsigned (functionally fine, just no DNSSEC validation).
2. **Switch web records to Proxied (orange cloud)** to get Cloudflare's DDoS / WAF / edge cache. Requires installing a Cloudflare Origin Certificate on the origin nginx, configuring nginx to present it on the existing vhost, and setting Cloudflare SSL/TLS mode to **Full (Strict)**. Mail / FTP / cPanel records stay grey forever (Cloudflare only proxies HTTP/HTTPS).
3. **CAA records** to lock down which CAs may issue certs for this domain. Currently absent (so any CA may issue) - adding `CAA 0 issue "letsencrypt.org"` would be a small hardening win.
4. **Email security records hardening** - tighten DMARC from `p=none` (monitor) to `p=quarantine` then `p=reject` once mail flow is confirmed clean. Optional, separate exercise.
