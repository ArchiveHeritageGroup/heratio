---
title: Bots, crawlers and search engines
slug: bots-crawlers-and-search
category: Administration
---

# Bots, crawlers and search engines

A public catalogue has to stay findable in Google while scrapers try to download all of it at once. Heratio handles both with rate limiting and a clear robots.txt, not with a JavaScript challenge. A challenge page stops scrapers, but it stops Googlebot, OAI harvesters, link previews and some screen readers too. A catalogue behind one drops out of search.

This page describes what Heratio does and the recommended web-server setup, using heratio.org as the worked example. The nginx files are in the repository under `deploy/nginx/`.

## What search engines get

- **robots.txt** at `/robots.txt`, built by Heratio. Published records and public hubs are open. Admin, account and machine surfaces are disallowed (`/admin`, `/api`, `/login`, `/research`, print and export views), and so are the sort, language and view variants of a page (`sort=`, `sf_culture=`, `view=` and others). Those variants show the same records in another order, and crawling them wastes the crawl budget on duplicates. Plain browse pages and `?page=N` stay open. The last line points to the sitemap.
- **A sitemap** at `/sitemap.xml`, an index of paged sitemaps covering the published records.
- **One canonical address per page.** Each page carries a `<link rel="canonical">` built from the configured `APP_URL`, never from the address the visitor typed, with the variant parameters removed. A `www.` address redirects to the bare domain with a single 301.
- **robots.txt during maintenance.** A 5xx on robots.txt makes Google stop crawling the whole site until it gets a good answer. So while Heratio is in maintenance mode, for example during a nightly demo reset, robots.txt is still answered with a 200 from the last copy Heratio served. Every other page gets the maintenance 503.

## Four layers of protection

Each layer answers a different kind of traffic, and each was added after a measured incident on heratio.org.

### 1. Abusive clients are refused outright

The host's `bot-blocker.conf` answers `444` (the connection is closed without a reply) to:

- bulk AI-training crawlers: GPTBot, ClaudeBot, anthropic-ai, CCBot, Amazonbot, Bytespider, meta-externalagent;
- aggressive SEO crawlers: AhrefsBot, SemrushBot, MJ12bot, DotBot, PetalBot and similar;
- vulnerability scanners, and requests with no user agent;
- the default user agents of scripting libraries (Python's requests, urllib and httpx; Node's fetch and axios; Go; Java).

Some are deliberately allowed: search engines, archive.org's Wayback crawler, OAI-SearchBot (ChatGPT search referrals), and Facebook's link-preview fetcher.

**The machine doors are exempt.** The REST API (`/api/v1/`, `/api/v2/`), OAI-PMH (`/oai`), ResourceSync, robots.txt and the sitemap are meant for scripts, and they have their own keys and limits. So on those routes the user-agent rule does not apply, and a harvester or an AI agent with a key gets through whatever library it uses. The AI-training crawlers listed above are the exception: they stay refused there too, and may read only robots.txt. The exemption is `$ahg_machine_door` in `conf.d/heratio-browse-shed.conf`.

### 2. Crawlers are kept out of the facet space

Browse pages can be filtered in endless combinations. Each combination is a separate URL, and each costs a database query. Two rules answer `429` (back off, nothing is deindexed):

- a crawler user agent with **four or more query parameters**: a language switch, a page number and one filter still pass;
- **two or more facets on a browse page with no Referer from the site itself**. A person narrowing a list clicks the facet links on the page, so their requests carry the site's Referer. On 12 Oct 2026 heratio.org took 120,979 browse requests from 63,682 addresses with ordinary Chrome user agents, and about 95% of the requests that combined two or more facets had no Referer at all.

### 3. Browse has a global cap

`/glam/browse` and `/actor/browse` may have at most 12 requests in flight across the whole site. Past that, the answer is `503` (busy, retry), not a queue. The cap is global rather than per address because the traffic it answers is spread out: on 24 Aug 2026 heratio.org took 36,157 browse requests from 31,722 addresses, about one each. A per-address limit never fires on that. With 25 PHP workers and about a second per browse page, a cap of 12 leaves 13 workers for record pages, search and the API.

Caching does not help here: of 30,445 distinct browse URLs that day, 28,063 were requested exactly once.

### 4. The API has per-key limits

Every API key has an hourly limit (1,000 by default) and, optionally, a daily limit. REST v1, which needs no key, allows 60 requests a minute per address. See [API worked examples](/help/article/api-worked-examples#rate-limits).

## Recommended setup for your own install

1. Copy `deploy/nginx/conf.d-heratio-browse-shed.conf` to `/etc/nginx/conf.d/heratio-browse-shed.conf`. In it, change the two host names in `$ahg_same_site_ref` to your own.
2. In your Heratio vhost, include the facet-guard and browse-cap rules and the machine-door exemption, as `deploy/nginx/heratio.org.conf` does.
3. Set the browse cap to about half your PHP-FPM `pm.max_children`.
4. Set `APP_URL` in `.env` to your public address, so canonical tags and the sitemap use it.
5. Run `sudo nginx -t`, then `sudo systemctl reload nginx`.
6. Submit `https://<your site>/sitemap.xml` in Google Search Console and Bing Webmaster Tools.

## Checking that it works

- Search Console's *Crawl stats* should show robots.txt answering 200, and few 429s or 5xx for Googlebot.
- In the access log, count what a crawler got: `grep Googlebot /var/log/nginx/heratio_access.log | awk '{print $9}' | sort | uniq -c`.
- After a change, request robots.txt as a script would: `curl -A "python-requests/2.31" https://<your site>/robots.txt` should answer 200.

How people, harvesters and AI agents may use the site is set out in the [Agent and harvester access policy](/help/article/agent-access-policy).
