# Attaching Guides & Templates to Articles

Articles can carry downloadable files - **guides** (e.g. a how-to PDF) and
**templates** (e.g. a Word or Excel template) - so readers can download them
straight from the published article. Each article is the parent; the files are
its children, and every file has its own short description.

## Where to upload

1. Go to **Admin → Articles** (`/admin/articles`).
2. **Create the article and Save it first.** Attachments belong to a saved
   article, so the upload area only appears once the article exists. On a new,
   unsaved article you will see "Save the article first, then you can attach
   guides and templates."
3. Open the article's **Edit** page and scroll to the **Guides & Templates**
   card near the bottom.

## Adding a guide or template

In the Guides & Templates card, fill in the upload row and submit:

- **Type** - choose **Guide** or **Template**. This drives the icon and badge
  shown to readers.
- **Title** - the label readers see. If left blank, the file name is used.
- **Description** - a short note describing the file (the small description
  area). Optional.
- **File** - the file to upload.

Click the upload button. The file is added immediately and appears in the list
above, where each row shows its type, title, description, file name, size, and a
delete button.

### Allowed file types and size

PDF, Word (`doc`, `docx`), Excel (`xls`, `xlsx`), PowerPoint (`ppt`, `pptx`),
OpenDocument (`odt`, `ods`), `csv`, `txt`, and `zip`. Maximum **20 MB** per file.

## Downloads intro message

On the article Edit page, below the body, there is an optional **Downloads intro
message** field. Whatever you type there is shown as a short lead line directly
above the guides & templates download list on the published article (for example,
"Download the cataloguing guide and templates below"). Leave it blank to show the
download list with no intro.

## Publishing

Attachments are shown to the public only when the article's **Status** is
**Published**. On the public article page they appear in a prominent, highlighted
**Guides & Templates** download panel - guides listed first, then templates -
each with its title, type badge, description, file name, and size. If you set a
Downloads intro message it appears at the top of that panel.

## Spotting articles that have downloads

Articles that carry guides or templates show a **paperclip badge with a count**
so you can spot them at a glance:

- **Admin → Articles** list: a paperclip count appears next to the article title.
- **Public articles grid** (`/articles`): a paperclip count appears in each
  card's footer next to the read count (published attachments only).

## Removing a file

In the Guides & Templates card on the Edit page, click the delete (trash) button
on the file's row. This removes both the database record and the stored file.
Deleting the article also removes all of its attachments automatically.

## Notes

- There is no limit on how many files an article can carry; add as many guides
  and templates as you need.
- Files are stored on the server and served over the public files URL - only
  attach material you are comfortable publishing alongside the article.
