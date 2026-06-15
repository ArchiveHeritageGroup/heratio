# Static Page

> Static pages let administrators create and edit free-standing content pages (such as About, Privacy and Terms) that are published at clean URLs, support multiple languages and optional Markdown formatting, and protect core pages from accidental deletion.

## Overview

The Static Page module (`ahg-static-page`) manages standalone informational pages stored in the `static_page` / `static_page_i18n` tables with a human-readable slug. Visitors read pages at `/pages/{slug}`, with convenience routes for `/about`, `/privacy` and `/terms`. Administrators create, edit and delete pages from the admin browse and list screens. Content is translatable per culture and, when enabled, rendered from GitHub-flavored Markdown to HTML.

## Key features

- **Clean public URLs** - every page is reachable at `/pages/{slug}`, with built-in shortcuts `/about`, `/privacy` and `/terms`.
- **Multilingual content** - title and content are stored per culture; if a translation is missing, the page falls back to the source culture so visitors always see content.
- **Markdown rendering** - when the `markdown_enabled` setting is on, content is converted from GitHub-flavored Markdown (pipe tables, task lists and autolinks supported), and any rendered tables are wrapped in a responsive, Bootstrap-styled container.
- **Admin CRUD** - browse, list, create, edit and delete pages from the admin interface.
- **Protected pages** - core pages (home, about, contact and, for editing, privacy and terms) cannot be deleted, and their slugs are not changed on update, preventing broken core navigation.
- **Validation** - title is required (up to 1024 characters) and the slug is required and rejects semicolons.

## How to use

### Read a page (visitor)

Visit `/pages/<slug>`, or use a shortcut such as `/about`, `/privacy` or `/terms`. The page renders in your current language where a translation exists, otherwise in the source language.

### Manage pages (administrator)

All management routes require the `admin` middleware.

1. Go to the static-page admin area at `/staticpage/browse` (the legacy `/admin/static-pages` URL redirects here) or `/staticpage/list`.
2. To add a page, choose **Create** (`/staticpage/create`), enter a title, a slug and the body content, then save. You are taken to the new page.
3. To edit, open a page and choose **Edit** (`/pages/{slug}/edit`), change the title, content or slug, then save. For protected pages the slug is locked.
4. To delete a non-protected page, open its delete confirmation (`/pages/{slug}/delete`) and confirm. Attempting to delete a protected page returns you with an explanatory message.

## Configuration

- **Markdown** is controlled by the `markdown_enabled` core setting (in the `setting` / `setting_i18n` tables). Any value other than `0` enables Markdown rendering; set it to `0` to display content as stored.
- **Protected slugs** are defined in the module: `home`, `about` and `contact` cannot be deleted; `home`, `about`, `contact`, `privacy` and `terms` keep their slug on edit.
- Pages are language-aware via `app()->getLocale()`; create or edit a page while in a given language to author that culture's version.

## Known issues

- The editor stores raw content; Markdown is rendered only on display when the setting is enabled.
- The fixed set of protected slugs is defined in code, not as a configurable list.

## References

- Source: packages/ahg-static-page/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues (see the ahg-static-page tracker)
