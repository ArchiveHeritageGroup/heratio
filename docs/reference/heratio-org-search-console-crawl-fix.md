# heratio.org Search Console errors - facet guard 429s, duplicates, www redirect (2026-10-26)

**Summary:** On 26 Oct 2026 Google Search Console showed two problems on heratio.org: "Server error (5xx)" and "Duplicate without user-selected canonical". The 5xx was never a real server fault. Heratio's own nginx facet-crawl guard was answering Googlebot with **429**, and Search Console lists a 429 under "Server error (5xx)". Googlebot kept getting 429s because every page links to many URL variants (sort, language, view and facet parameters), and nothing tells Google which URL is canonical. The duplicates have the same cause, plus www.heratio.org answering 200 alongside heratio.org. The www redirect is fixed live. The robots.txt disallows and a canonical tag are tracked in heratio#1568 for the dev session.

## Diagnosis

- **The guard.** `/etc/nginx/conf.d/heratio-browse-shed.conf` defines `$ahg_many_params` (four or more query parameters), `$ahg_noref_facets` (stacked facets with no referer) and `$ahg_block_facet_crawl`. The vhost runs `if ($ahg_block_facet_crawl) { return 429; }`. Browse traffic is also capped by `limit_conn heratio_browse 12`, which returns 503. The guard went in to shed botnet facet floods, and it works.
- **What Googlebot saw.** Counts from the access log, all requests from Google's crawler ranges:

| Date | 200 | 429 |
|---|---|---|
| 20 Oct 2026 | 1,812 | 1,619 |
| 25 Oct 2026 | 1,057 | 3,242 |
| 26 Oct 2026 (part day) | 276 | 522 |

: Googlebot response codes on heratio.org

- **Why there are so many variant URLs.** Every page links to variants such as `sort=`, `sf_culture=`, `view=`, `listPage=`, `displayMode=`, `limit=`, `dir=` and `sortDir=`, plus multi-facet `glam/browse` and `actor/browse` URLs. No page carries `rel=canonical`; the only template that has one is the marketing layout `packages/ahg-marketing/.../layout.blade.php`. `www.heratio.org` returned 200 instead of redirecting.
- **What was not the cause.** Ordinary pages load in about 0.6 s with a browser user agent. A multi-facet `glam/browse` URL gets a 429 by design. heratio.theahg.co.za already redirects to heratio.org with a 301.
- **A testing trap.** Sending a fake Googlebot user agent from the server itself returns 429 for every URL, so it tells you nothing. Test slowly with a browser user agent.

## Decision: keep the guard; do not exempt Googlebot by user agent

The botnet floods the guard was built for used ordinary browser user agents. Anyone can claim to be Googlebot in a user agent, so an exemption on that basis would reopen the hole. The fix is to stop Googlebot requesting the variants at all (robots.txt) and to tell Google which URL is the real one (canonical), so that it stops tripping the guard.

## Fix 1 - www redirect (live, 26 Oct 2026, 15:01)

The three lines changed in the vhost:

```
# 443 block, after server_name:
# Canonical host: www folds into heratio.org (Search Console duplicates, 26 Oct 2026)
if ($host = www.heratio.org) { return 301 https://heratio.org$request_uri; }

# port-80 block:
return 301 https://heratio.org$request_uri;   # was https://$host$request_uri
```

Checked after the reload: `https://www.heratio.org/...` and `http://www.heratio.org/...` both return a single 301 to `https://heratio.org/...` with the query string kept, and `https://heratio.org/...` returns 200. The certificate already covered www.

**A trap on this host:** `/etc/nginx/sites-enabled/heratio.org.conf` is a **regular file, not a symlink** to `sites-available`. The first attempt patched `sites-available`, nginx reloaded, and nothing changed. Edit the `sites-enabled` copy and check the live behaviour with curl, not just `nginx -t`. Both copies were made identical after the change. Backups are `/etc/nginx/heratio.org.conf.enabled-bak-www-20261026` and `sites-available/heratio.org.conf.bak-www-20261026`. Make backups outside `sites-enabled/`, because nginx loads everything in that directory, a `.bak` file included.

A second trap: a long root command pasted into the terminal can wrap. Here the wrap left `perl -pi` with no filename, so it waited for input from the terminal. Hand over a short script path to run with `sudo bash` instead.

## Released (v1.164.2, 26 Oct 2026; heratio#1568 closed)

All the remaining items shipped in **v1.164.2** and were checked on the live site:

1. **robots.txt** (`PublicSitemapController::robots`, routed from `packages/ahg-api/routes/api.php`; there is no static `public/robots.txt`) blocks `/glam/browse?`, `/actor/browse?`, and `sort`, `sf_culture`, `view`, `listPage`, `displayMode`, `limit`, `dir` and `sortDir`, each in both its `?` and `&` form.
2. **Canonical tag** in `packages/ahg-theme-b5/resources/views/layouts/master.blade.php`, built by `packages/ahg-core/src/Support/CanonicalUrl.php` on the configured host. The variant parameters are dropped and `page` is kept only when it is greater than 1. Example: `/term/ceremony?page=2&sort=alpha&sf_culture=en` gives `https://heratio.org/term/ceremony?page=2`.
3. **The repo nginx copy** `deploy/nginx/heratio.org.conf` carries the www redirect.

**What to watch:** Googlebot 429s in the access log should fall within about a day as Google re-reads robots.txt. The Search Console reports clear over days to weeks as pages are recrawled. No CallHub call covers this issue.

## Baseline and follow-up check (26 Oct 2026)

**Baseline before the fix**, Googlebot only, 26 Oct 00:00 to 15:00: **1,457** 429s and about 3,900 200s in 15 hours, so 45 to 156 429s an hour (about 97 on average). From 15:00, after the release at 15:12, the log shows 301s as Googlebot follows the www redirect.

**One 500 just after the release.** At 15:13 a Googlebot request to a multi-facet `glam/browse` URL with `sf_culture=af` returned 500. The same URL returned 200 when retried, and there was no matching Laravel log entry. It was most likely a passing error while the deploy finished, not a bug.

**Scheduled check.** A one-shot job in johanpiet's crontab runs `/home/johanpiet/.local/share/heratio-429-check/check.sh` at 08:00 on 27 Oct 2026. It counts Googlebot 2xx and 429 responses per hour for 26 and 27 Oct from `/var/log/nginx/heratio_access.log.1` and `heratio_access.log`, writes the hourly table to `result.txt` in the same folder, posts a before/after summary to the Workbench bell (spool folder), and then removes its own crontab line. Googlebot is identified by client ranges 66.249.* and 192.178.*.

**How to read it:** the fix is working if 27 Oct 00:00 to 08:00 shows far fewer than about 780 429s (8 hours at the baseline rate). If the number has not fallen, check when Googlebot last fetched `/robots.txt` and which URLs are still getting 429s.

Related: `heratio-org-launch.md` (domain, DNS, certificate), `geo-seo-visibility-baseline.md`.
