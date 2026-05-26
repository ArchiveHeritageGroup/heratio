# ahg-static-page

CMS-style static pages for Heratio - About / Privacy / Terms / arbitrary CMS slugs. Admin-editable through a Bootstrap 5 admin UI, rendered through the central theme.

## Purpose

- Admin CRUD for arbitrary content pages keyed by slug
- Public `/pages/{slug}` view + named shortcuts (`/about`, `/privacy`, `/terms`)
- Rich-text body stored in the `static_page` + `static_page_i18n` tables (i18n-aware)
- Soft fallback - missing slugs return 404 cleanly

## Install

Auto-discovered via composer path repositories. The ServiceProvider boots routes + views; tables come from `database/install.sql` (loaded by the core install pipeline - this package piggybacks on AtoM's existing `static_page` schema).

## Routes

Public:

- `GET /pages/{slug}` - render page
- `GET /about` / `/privacy` / `/terms` - named aliases

Admin (`admin` middleware):

- `GET /staticpage/browse` - list view
- `GET /staticpage/create` - new page form
- `POST /staticpage/store` - create
- `GET /pages/{slug}/edit` - edit form
- `PUT /pages/{slug}` - update
- `DELETE /pages/{slug}` - destroy

## Key classes

| Class | Role |
|---|---|
| `Controllers\StaticPageController` | Browse, show, CRUD |
| `Providers\AhgStaticPageServiceProvider` | Registers routes (`web` middleware) and the `ahg-static-page` view namespace |

## Views

Bootstrap 5 with `bi-*` icons, extends the `ahg-theme-b5` layout. Located in `resources/views/`.

## Notes

- The legacy `/admin/static-pages` URL 301-redirects to `/staticpage/browse`.
- Page bodies are stored as HTML; CSP / sanitisation is the caller's responsibility (admin-only authoring).
