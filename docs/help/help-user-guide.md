> Heratio Help Center article. Category: Help & Support.

# Using the Help Center

The Help Center is Heratio's built-in, searchable documentation library. It collects every user guide, technical reference, and module manual into one place, organised by category and full-text searchable down to the individual section. This guide explains how to find the answers you need, how to browse the library, how to use the interactive system diagrams, and - for administrators - how new articles are published into the library.

## Overview

Every page in the Help Center is a help article that has been ingested from a Markdown source file. Each article is rendered to formatted HTML, broken into its individual headings (its sections), word-counted, and indexed for full-text search. Because articles are stored as data rather than hard-coded pages, the library grows automatically as new modules are documented, and search always reflects the current contents.

The Help Center lives at the `/help` route. From there you can search, browse by category, open any individual article, and open two interactive diagrams that map the whole platform. Most articles are visible to everyone; a small number of operator-focused categories are reserved for signed-in users.

## Key features

- **Full-text search** across every published article, returning both whole-article matches and matches inside individual sections, ranked by relevance with a short snippet of context.
- **Browse by category** - articles are grouped into categories such as Browse & Search, Collection Mgmt, Compliance, Integration, Public Access, Research, and Viewers & Media, each with a description and icon.
- **Per-article table of contents** - every article shows a contents sidebar built from its headings, with anchor links that jump straight to the relevant section.
- **Previous / next navigation** - move sequentially through the articles in a category without returning to the listing.
- **Recently updated list** - the landing page highlights the five most recently changed articles.
- **System Map** - a single traversable diagram of the platform journey (acquire, ingest, describe, preserve, search, display, exhibit, AI, interoperate) that you can pan, zoom, and drill into.
- **System Breakdown** - a four-level capability tree (Heratio, then each record type, then its functional aspects, then the concrete tools) so you can see exactly what the platform can do for every kind of record.
- **Word count and last-updated date** shown on every article.

## How to use

### Open the Help Center

1. Go to **/help** in your browser, or follow the Help link in the main navigation.
2. The landing page shows a search box, the System Map and System Breakdown panels, a link to the external Documentation Portal, the category cards, and the Recently Updated list.

### Search for help

1. From the landing page (or the smaller search box in any article sidebar) type at least two characters into the search field and submit.
2. You are taken to **/help/search**, which runs two searches at once:
   - **Articles** - whole articles whose title or body matched, each with a relevance score and a snippet.
   - **Sections** - individual headings within articles that matched, so you can jump to the exact part of a longer guide.
3. Click any result to open the article. Section results take you directly to the matching heading via its anchor link.

Search uses a prefix match, so typing `provenan` will also find `provenance`. Results are ordered by relevance.

### Browse by category

1. On the landing page, the **Browse by Category** grid lists every category with a one-line description and the number of articles it holds.
2. Click a category card to open **/help/category/{category}** (for example `/help/category/research`).
3. The category page groups its articles by subcategory and lists them in reading order. Click any title to open the article.

### Read an article

1. Opening an article takes you to **/help/article/{slug}** (for example `/help/article/help-user-guide`).
2. The left sidebar shows a **Contents** list built from the article's headings - click an entry to jump to that section.
3. The sidebar also shows the category, word count, and last-updated date, plus a quick search box.
4. At the foot of the article, use the **previous** and **next** buttons to move through the rest of the category.

### Explore the system diagrams

1. From the landing page, click **Open System Map** (**/help/system-map**) for the end-to-end platform journey, or **Open System Breakdown** (**/help/system-breakdown**) for the capability tree.
2. Both diagrams are interactive: pan, zoom, and drill into any node to expand it.

### For administrators: publishing an article

Articles are not edited in the browser - they are written as Markdown files (the source for this very guide lives in `docs/help/`) and ingested with an artisan command. To publish or update one:

1. Write or edit the Markdown source file. The first top-level heading (`# Title`) becomes the article title unless you override it.
2. Run the ingest command, supplying at least a path and a unique slug:

   ```
   php artisan ahg:help-ingest --path=docs/help/help-user-guide.md --slug=help-user-guide --category="Help & Support"
   ```

3. The command renders the Markdown to HTML, adds anchor IDs to every heading, extracts each section (H2 to H4) for section-level search, counts the words, and builds the table of contents. It then inserts a new article or updates the existing one matching that slug, and rebuilds the article's sections.

Useful options:

- `--title=` - override the title (otherwise taken from the first H1).
- `--category=` and `--subcategory=` - place the article in the library. Category defaults to `Technical`.
- `--tags=` - comma-separated tags.
- `--sort-order=` - position within the category (default 100).
- `--related-plugin=` - link the article to a module.
- `--unpublish` - ingest the article but keep it hidden.

Because ingest matches on the slug, re-running the command with the same slug safely updates the existing article in place rather than creating a duplicate.

## Configuration

The Help Center needs no per-site configuration. Its two tables, `help_article` and `help_section`, are created automatically on first boot, and the `ahg:help-ingest` artisan command is registered automatically when the module loads. The full-text search index is part of the table definition, so search works as soon as the first article is ingested.

Article visibility is governed by category. The categories `Technical` and `Plugin Reference` are treated as operator material and are hidden from anonymous visitors; signed-in users see all categories. There are no other access settings to manage. The administration interface follows the standard Bootstrap 5 theme.

## References

- Source package: `packages/ahg-help/`
- GH issue: [GH #579](https://github.com/ArchiveHeritageGroup/heratio/issues/579)
