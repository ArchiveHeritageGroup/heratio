# Heratio dev workflow - status & way forward (2026-06-24)

Establishes the dev-first development model for Heratio and records the current
state of recent work. Source of truth for "where do I develop and how does it
reach the public demo".

## The model (standing, 2026-06-24)

- **Develop on the dev sandbox.** All new features and fixes are built and
  iterated on `heratio-dev` (LAN-only sandbox, isolated `heratio_dev` database,
  shared NAS, mail=log, queue worker on, **scheduler cron disabled** to avoid
  binlog FLUSH contention). Nothing new is built directly on the public demo.
- **Promote only when 100% working.** The public demo (heratio.theahg.co.za) is
  updated from the prod clone via `./bin/release` once a change is validated on
  dev. Every release bumps a version, tags, and pushes to GitHub.
- **Dev syncs only from GitHub.** The dev clone's `origin` is the **public HTTPS**
  GitHub URL, so it pulls read-only with no SSH key and no token. Run git as the
  web user that owns the dev working tree (not root, which would create
  root-owned files that break the web path):

  ```
  sudo -u www-data git -C <dev-path> pull --ff-only origin main
  # if PHP/blade changed: artisan view:clear + config:clear, then restart php-fpm
  ```

  A php-fpm restart is required after code changes because opcache runs with
  `validate_timestamps=0` on this host.

The loop: **develop on dev -> release from the prod clone (pushes to GitHub) ->
`git pull` on dev** to pick the change back up. Verified end-to-end on
2026-06-24 (roll dev back, pull from GitHub, confirm the code lands and serves).

## Status of recent work (shipped to main, v1.154.100-103)

- **Search result thumbnails** - resolved via the `digital_object` `parent_id`
  chain (derivatives carry `parent_id`; their own `object_id` is NULL on ~half
  the rows). Recovered ~180 thumbnails that were rendering as placeholders.
- **No-thumbnail fallback** - inline SVG type icon (the theme's FA6 subset
  omitted the glyph that was used before, so the box rendered empty).
- **Derivative pipeline** - `DerivativeService` now anchors master/derivative
  paths at `storage_path` (was a divergent uploads_path), and decrypts Heratio's
  own at-rest envelope before generating. `ahg:regen-derivatives` repaired
  (correct master usage term) and made an idempotent only-missing sweep; the
  weekly schedule moved off the nightly demo-reset collision.
- **Articles index** - Tile/List view toggle + sort (newest/oldest/title/most
  read), preserving the group filter and view across pagination.
- **Install docs** - the scheduler cron is now a first-class required install
  step, ImageMagick added to OS dependencies, and the derivative-regeneration
  schedule documented.

## Outstanding / way forward

- **Encrypted masters that Heratio cannot read** - a small number of image
  masters carry a *foreign* binary encryption envelope (not Heratio's own
  scheme), so derivative generation cannot decrypt them; they fall back to a
  type icon. Resolution needs the external decryptor's provenance or re-ingesting
  the plain originals.
- **OCR text store empty platform-wide** - the OCR pipeline does not currently
  populate the IIIF OCR text table; search/feature work that depends on it is
  blocked until the pipeline writes rows.
- **Researcher quotas** - schema/service work parked; resume on dev.
- **General** - new work starts on dev; promote to the public demo only after
  validation; keep docs + in-app help updated as part of each change.
