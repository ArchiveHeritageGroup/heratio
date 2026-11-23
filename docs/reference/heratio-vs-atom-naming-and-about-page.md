# Heratio vs AtoM: naming, the About page fix, and how to describe the products (9 Nov 2026)

**Summary:** Google described heratio.org as "AtoM with plugins". The main cause was heratio.org's own About page (static_page id 8), which still carried the old PSIS text, "About AtoM Heratio ... a comprehensive modernization of Access to Memory ... 79 specialized plugins", and listed AtoM 2.10 and Symfony 1.4 as its base. On 9 Nov 2026 the page was rewritten in the database, the AtoM-extension repos on GitHub were redescribed, and Dr Johan Pieterse was given a correction to post on the two ica-atom-users threads that used "AtoM Heratio".

## The names, used the same way everywhere

| Product | What it is |
|---|---|
| Heratio | A standalone application on Laravel 12 and PHP 8.3 for archives, libraries, museums, galleries and records management (GLAM). It contains no AtoM program code. It reads and writes the AtoM database structure, so an AtoM site can switch over in place. |
| AHG Extensions for AtoM | The plugins and themes that run inside AtoM 2.10 (repos atom-ahg-plugins and atom-framework); PSIS runs on these. Never call this Heratio. |
| OpenRiC Workspace | A separate application, in development, for archival description only, built natively on Records in Contexts. Heratio is GLAM; the Workspace is archival description only. |

: The three products and how to describe them

Never write "AtoM Heratio", "Heratio plugins" or "Heratio is built on AtoM". The approved wording: "Heratio is not AtoM and not a set of AtoM plugins; it uses the same database structure as AtoM."

## What changed

- **About page:** static_page_i18n id 8, English plus the eight stock AtoM translations (de, es, fr, it, ko, nl, pt, sl), which said "this is the default About page of the open-source AtoM application". The new text adds a FAQ: "Is Heratio an AtoM plugin?", "Why an AtoM database?", "Can it run without one?" and "How does Heratio relate to OpenRiC Workspace?". The page lives in the database, not in code, so a release does not overwrite it.
- **GitHub:** the atom-framework and atom-ahg-plugins descriptions now start "AHG Extensions for AtoM" and say "Not Heratio". The heratio repo's website field is set to https://heratio.org. AtomProject is private and was left alone.
- **atom-ahg-plugins docs:** "AtoM Heratio" removed from 89 lines in 36 Markdown files, released as v3.121.149 (archive session, 9 Nov). Still open: PSIS help_article titles (archive session); Heratio's own remaining mentions (authority-resolution help article, install.sql, lang/eu.json, auth-res docs) handed to heratio-dev. Done 9 Nov: Johan posted the mailing-list correction on both threads, resubmitted the sitemap and requested indexing for both pages.

- **Sitemap (v1.164.56, 9 Nov 2026):** Search Console showed /solutions/migrating-from-atom had never been crawled, and /about was found only through facet URLs, because the sitemap listed neither. `PublicSitemapController::staticPageUrls()` now adds /about, /solutions, tour, capabilities, documentation and one URL per `SolutionsController::PAGES` key. /about and the solutions pages also carry a meta description. Both pages passed Google's live test and indexing was requested on 9 Nov.

## Why it matters

Search engines repeat what a project's own pages say, and give extra weight to mailing-list posts. The two ica-atom-users threads from January 2026 ("AtoM Heratio Framework", "AtoM Plus/AtoM Heratio") were the only Heratio results Google returned for a general query. Expect results to change over weeks, as pages are recrawled.
