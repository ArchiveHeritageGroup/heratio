# Contextual in-app help (#1332)

The in-app Help Center (`/help` + search + 556 ingested articles, navbar link)
already shipped. This change adds the remaining piece: **contextual** help that
deep-links a page to its most relevant article instead of only the global index.

## How it works

- **Map** - `packages/ahg-help/config/help-context.php` associates URL-path
  prefixes (and optional exact route-name overrides) with a help-article slug.
  Resolution is longest-prefix-wins; route-name overrides take precedence.
  Every slug must be a real, published article.
- **Service** - `HelpArticleService::contextualFor(?string $routeName, ?string $path)`
  resolves the slug, validates it against a published, visible article (admin-only
  articles are filtered out for guests by the existing admin filter), and returns
  `['slug','title','url']` or null.
- **View composer** - `AhgHelpServiceProvider` shares `$contextualHelp` with every
  view (`View::composer('*')`), resolved once per request at render time and cached.
- **UI** - the theme navbar (`ahg-theme-b5/.../partials/header.blade.php`) renders a
  second, info-tinted question-circle icon **only** when `$contextualHelp` is set,
  linking straight to the page's article ("Help for this page: <title>"). Unmapped
  pages show no change. Uses the proven `fa-question-circle` glyph to avoid the
  FA6-subset empty-icon issue.

## Extending the map

Add a `'path/prefix' => 'article-slug'` entry (specific paths before their parent)
or a `'route.name' => 'article-slug'` override to `config/help-context.php`. No code
change needed; a stale/unknown slug simply yields no link rather than a broken one.
Current seed covers settings, reports, research (+ several research sub-areas),
ingest, search, dam, library.
