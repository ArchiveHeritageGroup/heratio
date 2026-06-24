# Contextual Help

Heratio's Help Center (the question-mark icon in the top bar) holds hundreds of
articles you can browse and search at any time. On many pages you also get a
**second, page-specific help icon** that links straight to the article most
relevant to where you are.

## Using it

- The plain question-mark icon always opens the full **Help Center** (`/help`).
- When a blue-tinted question-mark icon appears next to it, click it to jump
  directly to the help article for the current page (its tooltip names the
  article). It only appears on pages that have a mapped article.

## For administrators

Page-to-article mappings live in `ahg-help`'s contextual-help configuration and
are matched by URL path (most specific path wins) with optional exact
route-name overrides. To point a page at an article, add an entry mapping the
page's path or route name to a published help-article slug - no code change is
needed, and an unknown slug simply shows no link rather than a broken one.

## Research audit & AI provenance

Every AI suggestion and human accept/reject in the research module is recorded
for a defensible audit trail: AI inferences are logged to the provenance store
and human actions (create/update/delete and similar) are written to the
research activity log. You do not need to do anything to enable this - it is
automatic across the research portal.
