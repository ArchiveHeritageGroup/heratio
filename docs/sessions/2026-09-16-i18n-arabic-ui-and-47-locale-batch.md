# Arabic UI completion and a 47-locale machine-translation batch (15-16 Sep 2026)

Releases v1.154.736 through v1.154.766. Everything below is deployed to prod.

## What prompted it

An external tester, matthewbrutonall, opened two issues against v1.154.733 after running an
Arabic-only install: #1512 (the non-English UI is largely untranslated, `ar.json` about 6%
complete, and many Blade strings bypass `__()`) and #1513 (no way to make `APP_LOCALE`
authoritative for anonymous visitors). Both root causes he named were real, and the second one
matters more than catalogue coverage: completing the locale files alone would not have fixed
the interface.

## Arabic coverage

`lang/ar.json` went from 1,251 keys to roughly 19,200 of 19,544. The bulk came from the
machine-translation batch below; several hundred strings are hand-written, including all of the
standards help text, the dropdown labels and the taxonomy terms, because the machine output was
not good enough to ship (see "Where qwen3:8b is not good enough").

## The hardcoded-literal sweep

A logged-in Playwright crawl of 500 admin pages in Arabic found about 8,900 distinct English
strings and sorted them by cause: database content, literals bypassing `__()`, keys with a
translation that never reached the page, and text that is correct in English. That list drove
the work, package by package:

- shared chrome: the voice-command help panel (46 descriptions), the header, skip link, login
  labels, cart, feedback tab, clipboard counts and the language menu;
- the shared pager and `auth/login.blade.php`, plus 27 more Previous/Next labels - these are the
  exact examples in #1512;
- eight edit forms: actor, gallery, DAM, archival description create and edit, accession, museum
  and library, about 800 labels, help texts and placeholders;
- 115 ISAD, ISAAR, ISDIAH and ISDF field help quotations, translated by hand with every standard
  reference and ISO number preserved;
- 46 labels that rendered a visible `&amp;` because an entity was escaped twice.

Three fixes were structural rather than per-string, and they help every locale:

- `AhgSettingsService` now passes a dropdown label through `__()` when the current culture has no
  `ahg_dropdown_i18n` row, so 1,113 hand-translated labels in the locale files reach every
  dropdown without a database write per instance;
- the shared autocomplete component translates the label, placeholder and help text it is given,
  which fixes every form that includes it;
- language and script names come from ICU in the current language instead of a fixed English
  list, so Arabic shows "الأبخازية" rather than "Abkhazian". English output is unchanged.

Two migrations carry data that locale files cannot: 288 standard taxonomy terms (levels of
description, event types, note types, relation types, genres, rights basis) and 35 ICIP
cultural-sensitivity labels. Both insert only where the English row matches and no Arabic row
exists, both are no-ops on re-run, and both roll back only their own rows. The ICIP labels also
went into `data/vocabularies/icip.ttl` as `@ar` prefLabels so a future Fuseki import keeps them.

## #1513

`LOCALE_IGNORE_ACCEPT_LANGUAGE=true` makes `APP_LOCALE` win for anonymous visitors by skipping
the `Accept-Language` step in `SetLocale`. Explicit choices (`?sf_culture`, the switcher cookie,
the session) still take priority, the default is unchanged, and
`tests/Feature/SetLocaleAcceptLanguageTest.php` covers all three cases. Issue closed.

## The 47-locale batch

Roughly 800,000 strings through qwen3:8b via the local translate adapter, whose model calls go
through the gateway's Ollama passthrough. A translation is accepted only when placeholders and
HTML survive intact, the output is in the locale's own script, and it is neither a degenerate
repetition nor wildly long or short. Rejected strings stay untranslated and fall back to English.

**No locale was enabled in Settings > I18n.** The files ship dormant; enabling is a separate
decision.

Two check bugs cost real coverage before they were caught: the Japanese long-vowel mark `ー` has
a Unicode name starting `KATAKANA-HIRAGANA`, which the script check did not allow, and the
minimum-length rule rejected correct CJK output for being shorter than the English. Japanese and
Chinese were re-run afterwards and recovered 8,612 and 3,056 strings.

Not translated: the 16 African locales and Amharic. qwen3:8b produced unusable Xhosa (one string
came back as the same phrase repeated 24 times), and NLLB-200 - which does cover them, and is
installed - is CC-BY-NC-4.0 and was deliberately retired from Heratio for that reason.

## Where qwen3:8b is not good enough

Short, context-free strings. A spot check of 1,163 machine-translated dropdown labels found
"Breach" rendered as the Arabic for suicide, "Appraised" as "judge", "Scanner" as "sender",
brand names transliterated, and several strings with Latin or Vietnamese words spliced in. All
1,163 were removed and the labels written by hand instead. The same applies to the homepage: its
machine translation transliterated "Heratio" and garbled a sentence, so it was written by hand.
Longer UI sentences are considerably better than single words.

Coverage also varies by locale. Hebrew sits at 79% and Serbian at 60% (the file is Cyrillic-only,
so Latin-script output is refused on purpose); most European locales are 88-97%.

## Two incidents worth remembering

**The 02:00 demo reset failed mid-restore** with "Table 'ahg_io_funding' already exists". The
restore stops only the queue worker, so php-fpm and the every-minute scheduler keep booting the
app, and Heratio packages auto-create missing tables at boot - one got recreated between the
dump's DROP and CREATE. It is a race, so it will recur on some nights and not others.
`deploy/sbin/heratio-demo-reset.sh` now holds the scheduler's own cron lock and puts the app in
maintenance mode for the restore, releasing both on every exit including the abort path. The
deploy that day had already re-stamped the baseline from the half-applied database; Johan chose
to keep it.

**The batch exhausted the gateway key's quota**, and prod shares that key, so prod's AI features
were refused for about three hours. The batch now stops after 25 consecutive gateway failures
instead of running blind. Prod and dev were moved to separate keys, and the adapter runs on the
dev key so a bulk run can never starve prod again.

## Smaller fixes

`heratio:user:add-superuser` had never worked on this schema (it omitted `actor.source_culture`),
the footer disclaimer, policy links and header tagline became per-language and editable in
Settings, and `heritage-manage/creators.blade.php` was deleted: it had not compiled since it was
written, and nothing rendered it because the route redirects.

## Still open

- Fuseki credentials are absent from both instances' `.env`, so `ahg:vocabulary-import` cannot
  run anywhere. The ICIP labels reached the cache through a migration instead.
- #1512 stays open: the crawl's remaining hardcoded strings live in screens nobody has swept yet.
- Enabling any of the 47 locales is Johan's call.
