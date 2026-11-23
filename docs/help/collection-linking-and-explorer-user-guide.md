> Heratio Help Center article. Category: Heritage.

# Collection Text Linking and the Offline Explorer

## What this feature does

Large digitised collections often repeat themselves. The same story is clipped from two newspapers, or one album leaf is photographed twice. Two command-line tools help with this. The first compares the text of every item in a collection, links items that tell the same story, flags leaves that look duplicated, and can propose names for authority review. The second packages a collection as a small browsable website that runs with no server, even from a USB drive.

An administrator with server access runs both. Results are then reviewed in the normal Heratio screens.

## What the comparison reads

For each item under the collection you name, Heratio uses the OCR text if there is any, and otherwise the scope and content. Where a record has both English and its own source language, the source language wins, so non-English collections are compared in their own words.

Two items are linked when one contains a set share of the other's six-word phrases. Very common phrases are ignored, because a masthead shared by hundreds of items says nothing about the story.

## Preview a collection

Always start with a dry run, using the slug of the fonds or collection:

```bash
sudo -u www-data php artisan ahg:cross-reference my-collection-slug --dry-run --report=/path/to/duplicate-leaves.csv
```

The command reports how many items have text, how many same-story pairs it found, and how many page pairs share three or more identical items. Those pages may be one leaf photographed twice. The CSV lists each pair with its slugs and the number of shared items, so someone can check the images.

If the links look too loose or too strict, adjust the threshold. `--min-overlap` defaults to 0.3, and a higher number demands more shared wording. `--stock` defaults to 20, meaning a phrase found on more than 20 items is ignored.

## Write the links

Remove `--dry-run` to save the results:

```bash
sudo -u www-data php artisan ahg:cross-reference my-collection-slug --report=/path/to/duplicate-leaves.csv
```

Each link is stored as a related description with a note beginning "Same story:" and the percentage of wording in common. You can safely run this again. Links made by an earlier run inside the collection are replaced, while relationships entered by hand are left alone. Items that merely repeat because a leaf was photographed twice are kept out of the links and stay in the QA report.

## Propose names for review

Adding `--entities` extracts people, places, organisations and newspaper titles through the AI gateway, making one model call per item, so allow time on a large collection. It does nothing during a dry run.

```bash
sudo -u www-data php artisan ahg:cross-reference my-collection-slug --entities
```

A name is kept only if it is actually printed in the item, matched on whole words, and it is stored in the printed form. Kept names go to the entity review list at `/admin/ai/review` and become pending candidates in the authority queue at `/admin/authority-resolution/queue`. Nothing is linked to an authority record, and no authority is created, until a person decides.

## Check place names

If a gazetteer has been loaded, you can export a review list of the collection's place names:

```bash
sudo -u www-data php artisan ahg:gazetteer-load /path/to/geonames-country-file.zip
sudo -u www-data php artisan ahg:cross-reference my-collection-slug --dry-run --places=/path/to/places.csv
```

The CSV marks each place as found, a possible misspelling, or not in the gazetteer. It draws on names already extracted, so run `--entities` first. Loading the gazetteer is only needed once per source file.

## Build an offline explorer

The explorer lets people browse a collection by subject, place and name in a web browser. Give it a collection, a year, or both:

```bash
sudo -u www-data php artisan ahg:explorer-export --ancestor=my-collection-slug --title="Collection Explorer" --with-files --out=/path/to/empty-folder
```

Open `index.html` in the output folder to use it. With `--with-files`, the released image and PDF of each record are copied in, so the package works with no connection. Without that option, images link back to your site.

Other useful options are `--year=1985` to limit by date, `--limit` for the maximum number of records (8000 by default), `--min` to leave out names found on fewer than that many records (2 by default), `--hide` for a comma-separated list of general words to keep off the starting list and map, and `--base-url` for the site address that record links point at.

## What is kept out of a package

A package leaves the building, so only published records are eligible. Embargoed, protocol-restricted and classified records are excluded. Each file is put through the same check an anonymous visitor would face, which withholds drafts, restricted, redacted and unscreened files and protected originals. The command reports how many files were copied and how many were withheld or missing.

One limit to know about: Heratio tries only one image per record, normally the reference copy. If that copy is withheld or missing, the record has no image in the package, even when its thumbnail is public.

## Troubleshooting

"No archival description has that slug" means the slug is wrong. Copy it from the end of the record's address.

If the explorer refuses with "Give --ancestor or --year", add one of them. The whole catalogue is too large to browse this way.

"No published records in that scope" means everything under that collection is draft or restricted, or the year matched nothing.

When an export says a folder "holds files this export did not write", choose a new empty folder. Packages made before this version carry no marker file, so a `--with-files` export into one is refused, and an export without `--with-files` leaves its old files in place. Use a fresh folder, or empty the old one by hand, so that nothing since restricted stays on the drive.

If a place-name list comes back empty, run the command with `--entities` first, and check that the gazetteer has been loaded.
