# heratio.org launch - canonical product domain + GEO/SEO Phase 0-1 (2026-07-19)

**Summary:** On 2026-07-19 Heratio was given its own canonical product domain, **https://heratio.org**, and the first two phases of a GEO/SEO exposure programme shipped on it. This closed the "branded invisibility" gap found in the GEO baseline (LLMs and web search had no record of Heratio). heratio.org now serves the live app over HTTPS, carries structured data and an llms.txt for AI answer engines, hosts a Heratio-vs-AtoM comparison page and a migration-assessment lead form, and is wired into a three-domain brand entity graph. The old demo hostname heratio.theahg.co.za now 301-redirects to heratio.org.

Related KM docs: geo-seo-visibility-baseline.md, geo-phase0-exposure-pack.md, geo-comparison-heratio-vs-atom.md, spectrum-partner-licensing.md.

## Why (the baseline problem)

A GEO (Generative Engine Optimization) visibility test on 2026-07-19 found Heratio absent from both layers buyers use: five cold LLM agents across five buyer segments all answered "never heard of it", and web search returned nothing even for the branded query. The category answer space was owned by aggregators (Capterra, AlternativeTo, SourceForge), Wikipedia, and incumbents (Axiell, TMS, ArchivesSpace, AtoM, CollectiveAccess). Heratio also had no dedicated product domain - it lived on a subdomain of the services site.

## Domain / brand architecture

Three domains, three distinct roles (kept separate to avoid keyword cannibalization and to let each signal correctly):

- **heratio.org** - the product. Canonical home for all product SEO + GEO. International `.org` (matches OSS peers accesstomemory.org, archivesspace.org, collectiveaccess.org, omeka.org). Owns category/competitor terms.
- **theahg.co.za** - The Archive and Heritage Digital Commons Group (The AHG): the publisher/vendor Organization and the archive/heritage services brand (digitisation, management, consulting, hosting). `.co.za` geo-signal is correct for its SA/SADC services market.
- **plainsailingisystems.co.za** - Plain Sailing Information Systems: the software house and copyright/IP holder (the creator entity).

SEO vs GEO in this context: SEO wins a ranking when someone searches Google/Bing; GEO wins a mention when someone asks an LLM (ChatGPT/Claude/Perplexity/Google AI Overviews). GEO is the higher-leverage half because buyers increasingly start at the AI.

## DNS (Cloudflare, DNS-only)

- Registrar: Afrihost. The domain was initially on Afrihost shared hosting (sunfyre.aserv.co.za); DNS was moved to Cloudflare so the apex could survive a dynamic origin IP.
- The app server uses **No-IP** dynamic DNS (hostname `theahg.ddns.net`) because its public IP changes regularly. A hardcoded apex A record would go stale, and a DNS apex cannot be a CNAME.
- Solution: Cloudflare nameservers (amy/peter.ns.cloudflare.com) with **CNAME flattening at the apex**: `heratio.org` = CNAME -> `theahg.ddns.net` (Cloudflare flattens to the current IP and follows No-IP updates, self-healing). `www.heratio.org` = CNAME -> `theahg.ddns.net`.
- **DNS-only (grey cloud), not proxied**: the app allows 2 GB uploads and Cloudflare's free proxy caps uploads at 100 MB; DNS-only avoids that and lets Let's Encrypt/certbot on the origin handle SSL. Email records (MX, SPF, DMARC, autodiscover, SRV) were carried across to Cloudflare unchanged so mail keeps flowing via Afrihost.
- Gotcha observed: after the nameserver switch, the apex resolved from Cloudflare's authoritative NS immediately but was **negative-cached** on public resolvers because Afrihost's old zone SOA set a 24h negative TTL. `www` resolved first; the apex cleared within hours. certbot had to wait for public apex resolution.

## Nginx + SSL

- New vhost mirrors the existing app vhost (IIIF/Cantaloupe proxy, RiC explorer, uploads, security rules) with `server_name heratio.org www.heratio.org`. Staged in `deploy/nginx/heratio.org.conf`.
- SSL issued with `certbot --nginx -d heratio.org -d www.heratio.org --redirect` (Let's Encrypt, auto-renew). Precedent: an openric.org cert already existed on the box.
- The app enforces HTTPS at the Laravel layer (http -> https), and `SESSION_DOMAIN=null` means cookies bind to the request host, so login worked on the new domain with no session change.

## Canonical cutover

- `APP_URL` flipped to `https://heratio.org` (so generated absolute URLs are canonical); config cache cleared.
- `heratio.theahg.co.za` vhost replaced with a redirect-only vhost: both :80 and :443 (SSL retained) `return 301 https://heratio.org$request_uri`. The original app vhost is backed up alongside as a non-.conf file so nginx (which includes only `*.conf`) does not load it.
- Result: one canonical domain; the old subdomain consolidates its authority into heratio.org.

## What shipped on heratio.org (the ahg-marketing package)

New Laravel package `packages/ahg-marketing` (namespace `AhgMarketing`), registered via composer autoload + bootstrap/providers.php. Routes use two path segments to sidestep the locked `/{slug}` single-segment catch-all in ahg-information-object-manage:

- `GET /compare/atom` - a fair, verified Heratio-vs-AtoM comparison page with a self-contained SEO layout, `SoftwareApplication` + `Organization` + `FAQPage` JSON-LD in the head, canonical tag, and a migration-assessment CTA.
- `GET/POST /migration/assessment` - a "book a free AtoM migration assessment" lead form. On submit it validates, drops bots via a honeypot, and writes a lead JSON to the Workbench notification bell inbox (`/var/spool/workbench/notifications/`) so leads surface in Johan's bell.
- `public/llms.txt` - a clean, factual brief for LLM crawlers at the domain root.
- GitHub repo description + 20 topics updated (GitHub is the surface LLMs crawl hardest).

## Two deployment editions (key differentiator)

The comparison page and llms.txt lead with two ways to adopt Heratio, which reframes the AtoM story from rip-and-replace to no-lock-in:

1. **Heratio (standalone)** - the pure Laravel 12 platform on its own stack.
2. **AtoM / Heratio (overlay)** - Heratio's Laravel modules run alongside an existing AtoM (Symfony) installation, over the same AtoM database. The original AtoM is retained and fully functional, and the overlay is **fully reversible** (uninstall returns you to stock AtoM). An AtoM site can add RiC, DAM, museum/Spectrum-capable workflows, AI-assisted description, and digital preservation with no data migration and no lock-in.

## Brand entity graph (SEO/GEO)

JSON-LD on heratio.org links the three domains: Heratio (product) has `publisher`/`author` = The AHG and `creator`/`copyrightHolder` = Plain Sailing Information Systems, each as its own Organization node keyed by its own domain URL. Marketing pages carry visible footer cross-links to theahg.co.za, plainsailingisystems.co.za, and openric.org. Reciprocal Organization JSON-LD + links (reusing the same `@id` URIs) are to be added on theahg.co.za and plainsailingisystems.co.za to complete the bidirectional graph.

## Positioning notes

- Spectrum wording is "Spectrum-capable" (factual capability), never "Spectrum compliant/Partner", until the Collections Trust commercial licence is signed (see spectrum-partner-licensing.md).
- Uncontested white space the cold test revealed and that the copy now owns: Heratio is Laravel-native and RiC-native, and is a modern open-source AtoM/ArchivesSpace alternative.

## Operational learnings / gotchas

- **Dev-first flow:** build on heratio-dev (`/usr/share/nginx/heratio-dev`, port 8090, isolated DB) -> `bin/release` -> push -> prod pulls at `/usr/share/nginx/heratio` -> live at heratio.org. heratio.org and heratio.theahg.co.za are two names for the same single prod instance (same code + DB); the subdomain just 301s to the .org.
- **Dev push is broken** (www-data deploy key rejected over HTTPS): `sudo -u www-data ./bin/release` commits + tags but cannot push; push the dev commit with root's SSH key, then prod pulls.
- **Prod ownership trap:** `bootstrap/` and `public/` were owned by `johanpiet`, so a www-data `git pull` could not write `bootstrap/providers.php` or `public/llms.txt` and half-applied. Fixed with `chown -R www-data:www-data bootstrap public`; needed once so future pulls do not break.
- **Composer drift on dev:** dev's composer.json/composer.lock carry uncommitted registrations (ahg/mva-claims, ahg/rdm) not part of the marketing work. Each marketing release stashed those two files before `bin/release` and restored them after, keeping releases clean.

## Versions

- v1.154.372 - ahg-marketing package (comparison page, migration form -> bell, llms.txt).
- v1.154.373 - two deployment editions added to the comparison page + llms.txt.
- v1.154.374 - brand entity graph (creator/copyrightHolder + footer cross-links).
- SSL, DNS, and the 301 canonical cutover were nginx/DNS operations, not app releases.

## Outstanding

- Reciprocal Organization JSON-LD + links on theahg.co.za and plainsailingisystems.co.za (owner action; snippets provided).
- Translation depth (#1410): repair MT fill pipeline, fill priority + SADC locales, human review, coverage CI.
- Track two currently-untracked prod files in git: `deploy/nginx/heratio.org.conf` and this reference doc.
- Re-run the GEO visibility test after a few weeks to measure movement.

*Recorded 2026-07-19.*
