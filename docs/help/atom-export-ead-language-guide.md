# Exporting EAD in a specific language with `export:bulk`

This guide explains how the command-line **bulk EAD export** chooses its output
language, and how to export a finding aid in a non-default language such as
**Greek (el)** or **Afrikaans (af)** — a common point of confusion because
`--criteria` always seems to return the English version.

## The key point

`export:bulk --criteria` selects **which descriptions** are exported — it does
**not** set the **output language**. There is **no `--culture`/`--language`
option** on `export:bulk` in AtoM 2.10. The EAD is rendered in the application's
**default culture** (normally `en`) when run from the command line, which is why
every CLI export comes out in English regardless of the criteria.

## Why

In the export render path the output language is taken from the active
**interface (user) culture**:

```php
$exportLanguage = sfContext::getInstance()->user->getCulture();
```

A command-line task initialises the context with the **default culture**
(`sf_default_culture`, normally `en`), so `getCulture()` returns `en`. Selecting
records that merely *have* a translation (for example
`--criteria="i.id IN (SELECT id FROM information_object_i18n WHERE culture='el')"`)
does **not** change the render culture — those records still export in English.

Note also that there is **no `language`/`langcode` column** on the `slug` table
(or as a bare field), so `--criteria="langcode='ell'"` or `slug.language='el'`
cannot work. The metadata language lives on `information_object.source_culture`
and, per translation, on `information_object_i18n.culture`.

## "I see no language field" — it's called *culture*

A very common stumbling block: people look for a `language` column to filter on
and there **isn't one**. AtoM has **no `language` field** on
`information_object`. What you are looking for is the **`culture`** field — and
there are in fact **two** culture fields, plus a separate "language of material"
concept that is *not* a culture:

| What you want to select on | Field / location | `--criteria` |
| --- | --- | --- |
| Records **catalogued in** a language (the description's primary/original language) | `information_object.source_culture` — **one value per record** | `i.source_culture = 'el'` |
| Records that **have a translation** in a language | `information_object_i18n.culture` — **one row per translation** (already joined as alias `i18n`) | `i18n.culture = 'el'` |
| **Language of the archival materials themselves** (ISAD 3.4.3 *Language of material*) | a **property** (`property.name='language'`), stored as a PHP-**serialized array** of ISO-639-1 codes such as `a:1:{i:0;s:2:"el";}` | `i.id IN (SELECT object_id FROM property p JOIN property_i18n pi ON p.id=pi.id WHERE p.name='language' AND pi.value LIKE '%"el"%')` |

**To bulk-export all Greek records**, the field you want is almost always
**`source_culture`** (the language the record is described in):

```
php symfony export:bulk --criteria="i.source_culture = 'el'" --public /path/to/exportFolder
```

`source_culture` vs `culture` in one line: `source_culture` is the record's
**single primary language**; `i18n.culture` marks **each translated version** of
that record (so a record can have several `culture` rows but exactly one
`source_culture`). The *language of material* property is a different thing
again — it describes the language of the documents, not of the catalogue entry,
and because it is serialized you must match it with `LIKE '%"xx"%'` rather than
`=`.

> **Remember the two-step nature of the task.** The `culture`/`source_culture`
> criteria choose **which** records are exported. They do **not** change the
> language the EAD is **rendered** in — for that you still switch the default
> culture (below). Selecting `source_culture='el'` but leaving the default
> culture at `en` gives you *the Greek records, exported in English*.

## How to export EAD in Greek (or any non-default language)

**Option A — temporarily change the default culture (no code change):**

1. **Admin → Settings → Default culture** → set to the target language
   (e.g. **Ελληνικά / el**). (Equivalently, set `default_culture` in
   `apps/qubit/config/settings.yml` or the `setting` table.)
2. Clear the cache: `php symfony cc`.
3. Run the export:
   ```
   php symfony export:bulk --public /path/to/exportFolder
   ```
   The CLI context now uses culture `el`, so the EAD renders the Greek i18n
   fields, falling back to the source culture for any field that has no Greek
   translation.
4. **Revert** the default culture to English and clear the cache again.

**Option B — export from the web interface in that language:** switch the
interface language to **Ελληνικά**, open the fonds/description, and use the EAD
export there. Interface exports respect the **active interface culture**, so you
get the Greek EAD without changing any settings.

## Bulk export — copy-paste `--criteria` samples

> ⚠️ **This is the EXPORT task, not import.** `export:bulk` and `import:bulk`
> are two different commands. Do not follow the *import-bulk* / *import-xml
> matching-criteria* documentation for this — `--criteria` here is a plain SQL
> `WHERE` clause, nothing to do with the XML matching criteria used on import.

How `--criteria` is used: the task builds exactly this query and drops your
clause in after `WHERE` —

```sql
SELECT i.*, i18n.*
FROM information_object i
INNER JOIN information_object_i18n i18n ON i.id = i18n.id
LEFT JOIN digital_object do ON i.id = do.object_id
WHERE <your --criteria here>
ORDER BY i.lft
```

So your clause can reference **`i`** (the `information_object` row) and
**`i18n`** (the joined `information_object_i18n` row) directly — `i18n.culture`
is available without a subquery.

**Greek records — the common cases:**

```bash
# All records CATALOGUED IN Greek (source_culture = el) — usually what you want
php symfony export:bulk --criteria="i.source_culture = 'el'" --public /home/me/export

# Records that HAVE a Greek translation row (i18n is already joined, alias i18n)
php symfony export:bulk --criteria="i18n.culture = 'el'" --public /home/me/export

# Greek records, MODS instead of EAD
php symfony export:bulk --criteria="i.source_culture = 'el'" --format=mods --public /home/me/export

# Only Greek records in one repository (by repository slug)
php symfony export:bulk --criteria="i.source_culture = 'el' AND i.repository_id = (SELECT object_id FROM slug WHERE slug='my-repo-slug')" --public /home/me/export
```

**Other handy selections (not language-specific):**

```bash
# A single fonds and all its children (EAD nests children automatically)
php symfony export:bulk --single-slug=my-fonds --public /home/me/export

# Everything below a given id
php symfony export:bulk --criteria="i.id > 100" --public /home/me/export

# Only top level, no children
php symfony export:bulk --criteria="i.source_culture = 'el'" --current-level-only --public /home/me/export
```

> **Two-step reminder (the whole point of this guide):** the samples above pick
> **which** records export. They do **not** make the EAD come out *in* Greek —
> for that you still switch the default culture first (Option A above). So to get
> *Greek records, rendered in Greek*: set default culture → `el`, `php symfony
> cc`, then run `--criteria="i.source_culture = 'el'"`, then revert.

## All options

| Option | Effect |
| --- | --- |
| `--criteria="…"` | SQL `WHERE` over `information_object i` / `information_object_i18n i18n` |
| `--single-slug=SLUG` | Export one fonds/collection (and its children) by slug |
| `--format=ead\|mods` | Output format (default `ead`) |
| `--current-level-only` | Do not export child descriptions |
| `--public` | Exclude draft (unpublished) descriptions |
| `--items-until-update=N` | Print progress every N items |

(The atom-framework equivalent is `php bin/atom export:bulk` with the same
options.)

## Reference

Official AtoM CLI **export** documentation (bulk export of descriptions):
<https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/cli-import-export/#bulk-export>

Do **not** use the *import-bulk* section on that same page — that is for
ingesting XML, not exporting it.
