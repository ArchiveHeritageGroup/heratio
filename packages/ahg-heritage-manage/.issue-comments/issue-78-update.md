Update on Issue #78 — Heritage Landing adminConfig implementation

What I implemented (read-only -> then wrote the controller file to disk):
- Implemented adminConfig GET and POST in HeritageController to persist heritage landing settings.
  - Reads existing `heritage_landing_config` and supplies `config`, `heroImages`, `filters`, and `stories` to the admin view on GET.
  - Validates submitted inputs conservatively and updates/inserts the single-row `heritage_landing_config` JSON on POST.
- Files changed (written to working tree):
  - packages/ahg-heritage-manage/src/Controllers/HeritageController.php

Important notes / guarantees:
- I did not remove or rename any existing fields or behavior. Changes are additive and conservative.
- No DB migrations were performed.
- I did not commit these changes — they are in the working tree awaiting your review and commit.

Outstanding work / next steps (recommended):
1) Review & commit the controller change (one-line commit block below).
2) Implement the admin view enhancements (hero slides editor, tagline, suggested searches, featured collections) — currently the view exists and expects $config, but UX/enhancements remain.
3) Integrate image upload UX into the view (reuse existing upload endpoints) and validate uploads server-side.
4) Add a small HeritageLandingService to centralize get/save logic and keep controller slim (optional but recommended).
5) Add feature tests and manual QA steps to verify save/load and public landing rendering.

Commit block (copy-paste)

```
git checkout -b feat/heritage/landing-settings
git add packages/ahg-heritage-manage/src/Controllers/HeritageController.php
git commit -m "feat(heritage): implement adminConfig GET/POST to persist heritage landing settings (#78)"
git push -u origin feat/heritage/landing-settings
```

I will not close this issue. Please review, run the commit block when ready, and let me know if you want me to implement the remaining items and open a PR. I can also add a follow-up comment to this issue after commit or once the view enhancements are completed.

— Automated update from code work performed; no files were removed or renamed.