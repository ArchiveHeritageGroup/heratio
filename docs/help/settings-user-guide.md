# Settings

> The Settings module is Heratio's central administration console: dozens of dedicated pages under Admin -> Settings let you configure site identity, security, uploads and storage, digital objects, search, AI services, integrations, compliance and more, all backed by the database so nothing is hardcoded.

## Overview

The Settings module (`ahg-settings`) provides the `/admin/settings` console and the many configuration pages reached from it. Each page reads and writes either the core `setting` / `setting_i18n` tables or the `ahg_settings` key-value table through the `SettingsController` and `SettingsService`. The module also hosts the Dropdown Manager, the cron-job manager, the error log viewer, system information, and the dynamic theme CSS endpoint.

Settings pages are rendered with the central Bootstrap 5 admin theme. Most pages accept both GET (display the form) and POST (save changes), and legacy AtoM-style URLs are redirected to their Heratio equivalents so older bookmarks keep working.

## Key features

- **Settings landing page** at `/admin/settings`, linking to every configuration area.
- **Site identity and presentation** - global settings, site information, header customizations, page elements, themes, treeview, visible elements, interface labels, languages, markdown, default template and clipboard.
- **Security and access** - security settings, permissions, LDAP, data protection, privacy notification, encryption and audit settings.
- **Storage and uploads** - storage service, uploads, paths, DIP upload, preservation, integrity, FTP and SharePoint.
- **Digital objects and media** - digital objects, media settings, photos, faces, IIIF group settings and finding-aid options.
- **Identifiers and structure** - identifier settings, numbering schemes (with a per-scheme editor), sector numbering, levels and authority settings.
- **AI services** - the AI services page (with server-side Test Connection and machine-translation test proxies), AI condition scanning (including condition-client key management and an API test), text-to-speech and voice AI.
- **Integrations and standards** - AHG integration, AHG import, Fuseki, OAI, webhooks, web analytics, ICIP settings, multi-tenant settings, metadata, accession, ingest, jobs, library, spectrum, compliance and portable export.
- **Operational tools** - the Dropdown Manager, cron-job manager (toggle, edit, run-now and seed), error log viewer, services check and system information.
- **Dynamic theme CSS** served publicly at `/css/ahg-theme-dynamic.css` so colour and branding choices apply site-wide without a rebuild.

## How to use

1. Sign in as an administrator. All settings routes are protected by the `admin` middleware.
2. Go to **Admin -> Settings** (`/admin/settings`) to see the console of available areas.
3. Click the area you want to configure, for example **Global**, **Security**, **Uploads** or **AI Services**.
4. Edit the fields on the page and save. Pages submit back to the same URL via POST and confirm the save before returning you to the form.
5. For enumerated values (status lists, types and similar), open the **Dropdown Manager** (`/admin/settings/dropdown`) and edit the relevant taxonomy rather than looking for a hardcoded option list.
6. To manage scheduled tasks, open **Cron Jobs** (`/admin/ahgSettings/cronJobs`) where you can toggle a job on or off, edit its schedule, run it immediately, or seed the default job set.
7. To investigate problems, open the **Error Log** (`/admin/errorLog`) and **System Info** (`/admin/ahgSettings/systemInfo`).
8. On the **AI Services** and **AI Condition** pages, use the built-in Test Connection / API Test buttons; these call same-origin server-side proxies so the test runs from the server and avoids browser cross-origin and mixed-content issues.

Many pages have both a modern path (for example `/admin/settings/security`) and one or more legacy aliases (for example `/ahgSettings`, `/admin/settings/ahg/...`) that redirect to the canonical page.

## Configuration

- Core presentation settings (site title, description, logo toggles) are stored in `setting` / `setting_i18n`; AHG feature settings are stored as key-value rows in `ahg_settings`, often grouped (for example the `version_control` group seeds retention keys).
- Storage paths are defined centrally in `config/heratio.php` and driven by environment variables; the storage and paths pages surface these rather than hardcoding locations.
- The generic AHG group page (`/admin/settings/ahg/{group}`) and the generic section page (`/admin/settings/{section}`) act as catch-alls for key-value settings that do not have a bespoke page.
- The Dropdown Manager is the single source for all enumerated values; never use database ENUM columns or hardcoded `<option>` lists.

## Known issues

- Some settings pages are full bespoke forms while others are generic key-value editors reached through the catch-all section and group routes; the available depth of validation and help text varies by page.
- Several legacy AtoM URLs are kept only as redirects to their Heratio equivalents.

## References

- Source: packages/ahg-settings/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/626
