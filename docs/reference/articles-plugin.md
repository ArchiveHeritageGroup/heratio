# Articles plugin (ahg-articles)

The articles / news feature in Heratio is a self-contained plugin so any
institution can run its own. It lives at `packages/ahg-articles/` (extracted from
the app in 2026-06; previously `app/Http/Controllers/BlogController`,
`BlogAdminController`, `app/Services/BlogService`).

## What it provides

- Public pages: `/articles` (index) and `/articles/{slug}` (article), plus
  anonymous, throttled comments at `/articles/{slug}/comments`.
- Admin authoring + moderation under `/admin/articles` (route names
  `admin.articles.*`), including a dual Markdown / WYSIWYG body editor and
  downloadable guides & templates.

## Structure

- Namespace `AhgArticles\` (PSR-4 -> `packages/ahg-articles/src/`), registered in
  the root `composer.json` autoload and `bootstrap/providers.php`
  (`AhgArticlesServiceProvider`).
- Routes are registered in the provider via `callAfterResolving('router')` so the
  single-segment `/articles` beats the locked `/{slug}` archival-record catch-all.
- Views use the `articles::` namespace (`articles::index`, `articles::show`,
  `articles::admin.form`, `articles::admin.index`, `articles::admin.comments`).
- Tables (self-installed on first boot from `database/install.sql`):
  `blog_post`, `blog_attachment`, `blog_comment`.

## Editor

- Body editor is Toast UI Editor v3.2.2, the self-contained `-all` bundle,
  self-hosted at `public/vendor/toastui/` (no external CDN at runtime). The plain
  npm bundle externalises ProseMirror and must not be used in the browser.
- Body is stored as Markdown and rendered with `Str::markdown` (CommonMark) on the
  public page. Inline images upload via `admin.articles.upload-image`.
- Toast UI is UMD; the page has other UMD libs, so the editor script is wrapped to
  hide `define`/`exports`/`module` and force the browser-global branch.

## Attachment types

The attachment "Type" field is driven by the Dropdown Manager taxonomy
`blog_attachment_kind` (taxonomy_label "Article Attachment Type", section
"content"), seeded by `database/seed_dropdowns.sql`. Add or rename types at
`/admin/dropdowns` - no code change. The view falls back to Guide/Template if the
taxonomy is empty.
