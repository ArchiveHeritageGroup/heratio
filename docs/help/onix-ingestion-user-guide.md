> Heratio Help Center article. Category: Library.

# ONIX Ingestion - User Guide

**Version:** 1.0
**Date:** 2026-05-29
**Module:** ahg-library (heratio#1094)

---

## 1. Overview

ONIX Ingestion imports book-trade metadata supplied by publishers and vendors
(EDItEUR ONIX for Books, release 3.0 or 2.1) directly into Heratio. Each
ONIX `<Product>` becomes a staged record in a review queue. After you review
and commit, Heratio creates a full bibliographic record in the library
catalogue and a matching line on an acquisitions order - no manual MARC keying.

This is the recommended path for legal-deposit and supplier feeds.

## 2. Where to find it

Library dashboard -> **ONIX Ingestion**, or go to `/library-manage/onix`.

## 3. Importing a file

1. On the ONIX Ingestion page, either choose an ONIX **file** (.xml) or paste
   the ONIX XML into the text box.
2. Click **Parse & stage**. Heratio parses every `<Product>`, validates it, and
   shows the result in a review queue.
3. Each record is marked:
   - **Valid** - ready to import.
   - **Invalid** - missing title, no ISBN/ISSN, or a failed ISBN-13 / ISBN-10 /
     ISSN checksum. The reason is shown on the row.
   - **Duplicate** - an item with the same ISBN/ISSN is already in the catalogue.

## 4. Reviewing and committing

- Use **Skip** to exclude a valid record from this commit, and **Include** to
  put it back.
- Click **Commit valid records** to import. For each valid (non-skipped) record
  Heratio:
  1. Creates a catalogue bibliographic record (title, subtitle, ISBN/ISSN,
     edition, publisher, publication year, contributors as linked authority
     creators).
  2. Creates (once per commit) an acquisitions order of type **deposit** and
     adds an order line linked to the new catalogue item, carrying the supply
     price.
- After commit the ingest is locked (`committed`); imported rows link straight
  to their catalogue item.

## 5. API

`POST /api/library/ingest/onix` accepts the ONIX message as a raw request body,
a JSON `onix` field, or an `onix_file` multipart upload, and returns a JSON
summary. Add `?commit=1` to parse and commit in a single call. The endpoint
requires an authenticated session with the library `create` permission.

## 6. Field mapping (ONIX -> Heratio)

| ONIX | Heratio |
|---|---|
| `ProductIdentifier` (type 15 / 03 / 02) | `library_item.isbn` |
| `ProductIdentifier` (type 22 / 23) | `library_item.issn` |
| `TitleDetail / TitleElement / TitleText` | title (`information_object_i18n`) + `library_item.subtitle` |
| `Contributor / PersonName` (+ role) | linked creators (`library_item_creator`) |
| `EditionNumber` | `library_item.edition` |
| `ProductForm` | material type (BB/BC monograph, D\* ebook, A\* audio, V\* video) |
| `PublisherName` | `library_item.publisher` |
| `PublishingDate` / `PublicationDate` | publication year |
| `Price / PriceAmount` + `CurrencyCode` | order line `unit_price` + order `currency` |
| `Supplier / SupplierName` | order `vendor_name` |

## 7. Notes

- Parsing is namespace-agnostic, so namespaced ONIX 3.0 and bare ONIX 2.1 feeds
  both work.
- Deleting an ingest removes only its log and review queue; catalogue records
  and orders already committed are kept.
