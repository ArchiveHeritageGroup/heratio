# Session state 23 Nov 2026: Smuts House articles and Windhoek short decks

Handover note written before a session restart. It covers two strands of open work: two Smuts House articles now live on heratio.org, and the short decks for the Windhoek workshop.

## 1. Smuts House articles on heratio.org (live, ids 40 and 41, confirmed 23 Nov)

Correction 23 Nov: both articles were already published on 22 Nov and are protected from the reset. The publish commands below are not needed again; the script skips them.

The two articles are:

- "A Bookcase Is a Record", slug `a-bookcase-is-a-record`. It covers the philosophy bookcase: 135 spines and 112 records. The grading was 105 clear, 7 probable, 10 partial and 13 illegible.
- "Who Cut These Out?", slug `who-cut-these-out`. It covers the 1950 funeral scrapbook: 45 pages, a two-model read agreement of 87%, 279 names and 10 authorities.

Each article has a cover image and a narrated search video, attached as kind `presentation`. Both are marked protected from the weekly demo reset.

The staged files are in `/var/tmp/smuts/articles/`: the markdown, the covers, the narrated mp4s and `publish-articles.php`. The script skips any slug that already exists.

To publish, Johan runs:

```
cd /usr/share/nginx/heratio
sudo -u www-data php /var/tmp/smuts/articles/publish-articles.php --dry
sudo -u www-data php /var/tmp/smuts/articles/publish-articles.php
```

After publishing:

- Check /articles/a-bookcase-is-a-record and /articles/who-cut-these-out from outside: the cover, the tables and the video playing.
- Re-take the demo baseline: `sudo /usr/local/sbin/heratio-demo-snapshot.sh`.

LinkedIn posts: the short versions are about 880 characters each and were emailed on 22 Nov. The long versions are in the session scratchpad only.

Possible dev tickets, to be sent to heratio-dev only if Johan agrees:

- The "Search within this record" box ignores its term (glam/browse?subquery=...&ancestor=...).
- Explore shows "General J.C. Smuts" separately from the authority "Smuts, Jan Christiaan, 1870-1950".

## 2. Windhoek workshop short decks (23-27 Nov 2026)

The full pack is in `stuff/Windhoek Archives Reform Workshop 2026/`:

- five decks: S3, S4, P2, S6 and S7;
- the run-through, with timings, splits, key lines and Namibian law;
- the participant handout.

The decks are built with `_assets/build-deck.py SLIDES.md OUT.pptx "EYEBROW" "FOOTER"`.

On 23 Nov, 15-minute short versions were made in the `Short/` subfolder, for sessions where a co-presenter takes most of the time. Each has its slide markdown, pptx and PDF. They were emailed to Johan the same day.

| Deck | Session | Slides (full to short) |
|---|---|---|
| S3 AI records | Tue 24 Nov 09:00, with Ngololo | 13 to 6 |
| S4 EDRMS controls | Tue 10:50, Keakopa leads | 12 to 5 |
| P2 practical | Tue 13:15, with Shilongo | 10 to 5 |
| S6 transfer and preservation | Wed 25 Nov 10:50, with Ngololo | 12 to 5 |
| S7 sovereignty and hosting | Thu 26 Nov 09:00, Ngunga leads | 10 to 4 |

: Short Windhoek decks

All of them close on the line "rules first, AI second, a person decides, and every step leaves evidence".

The preparation items from the run-through still stand. The main one is to ask heratio-dev to switch on sharepoint_auto_sync for Tuesday 24 Nov, and off again afterwards.

## Other items carried over

- The explorer download-link take-down, once Johan confirms.
- RMJ revision answers.
- The resignation letter placeholders.
