> Heratio Help Center article. Category: Discovery & Browse.

# Explore by Theme User Guide

## Overview

Explore by Theme groups the collection by what its records are about. It surfaces the collection's strongest subjects - the topics under which the most published records sit - as "ways into the collection", so you can start from a theme rather than from a search box. Each theme is a card showing how many published records carry that subject and a few example records, and links straight to the records filed under it. Open it at **/themes**.

---

## What it does

Explore by Theme reads the subjects already attached to your published records and ranks them by how many records carry each one:

- The busiest subjects become the headline themes - the collection's de-facto "ways in".
- Each theme shows its published-record count and a few example records.
- Opening a theme lists the published records under it, with links to each record and a one-click route into the full browse for that subject.

Only published records are counted and shown. Themes update automatically as records are described with subjects and made public - there is nothing to configure or generate.

---

## How to use it

1. Go to **/themes**.
2. Browse the theme cards. Each card names a subject, shows how many published records carry it, and lists a few example records.
3. Click **Explore theme** (or the theme name) to open one theme.
4. On a theme page you will see:
   - The subject's label and, where the catalogue holds one, a short scope note.
   - The total number of published records under the theme.
   - A paginated list of those records, each linking to the record in full.
   - A **Browse all in this theme** button that opens the main browse page pre-filtered to this subject, where you can apply further filters (creator, place, media, level, and so on).
5. Use the page links at the bottom of a theme to move through long lists.

---

## Machine-readable theme list

A read-only JSON list of the themes is available at **/themes.json** for reuse in
other tools and integrations. It is CORS-open and cacheable, and returns each
theme's id, label, published-record count, and links. No record content or
unpublished material is exposed.

---

## Good to know

- **Published only.** Themes are built from published records, so the counts match
  what the public can actually open.
- **It stays current.** Theme rankings and record lists are computed on the fly
  from the live catalogue, so they reflect the collection as it is right now.
- **It complements connections.** Where **Discoveries** and **Research Leads** show
  AI-found links *between* records, Explore by Theme groups records *by subject* -
  the two are different ways to find related material.
