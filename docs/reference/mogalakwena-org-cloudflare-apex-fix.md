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
