> Heratio Help Center article. Category: Discovery & Browse.

# People and Organisations User Guide

## Overview

People and Organisations organises the published holdings by the people and organisations that created them. It surfaces the creators already credited on your published records - the people and organisations that made the most records - as "ways into the collection", so you can start from a creator rather than from a search box. Creators are shown as a frequency-sized cloud and a ranked list; each one shows how many published records they created and links straight to those records. Open it at **/people**. It is the creator companion to **Explore by Theme** (which groups by subject) and **Browse by Place** (which groups by geography).

---

## What it does

People and Organisations reads the creators already credited on your published records and ranks them by how many records each one made:

- The busiest creators become the largest names in the cloud - the collection's creator "ways in".
- Each creator shows its published-record count.
- Opening a creator lists the published records they made, with links to each record and a one-click route into the full browse for that creator.

Only published records are counted and shown. Creators update automatically as records are credited to a person or an organisation and made public - there is nothing to configure or generate. No person or organisation is built in: the names come entirely from your own catalogue.

---

## How to use it

1. Go to **/people**.
2. Browse the cloud - larger names made more published records - or use the ranked **All people and organisations by frequency** list below it.
3. Click a creator's name to open it.
4. On a creator page you will see:
   - The creator's authorized form of name and, where the catalogue holds them, the dates of existence and a short history.
   - The total number of published records by the creator.
   - A paginated list of those records, each linking to the record in full.
   - A **Browse all by this creator** button that opens the main browse page pre-filtered to this creator, where you can apply further filters (subject, place, media, level, and so on).
5. Use the page links at the bottom of a creator to move through long lists.

---

## Machine-readable creator list

A read-only JSON list of the creators is available at **/people.json** for reuse in
other tools and integrations. It is CORS-open and cacheable, and returns each
creator's id, name, published-record count, and links. No record content or
unpublished material is exposed.

---

## Good to know

- **Published only.** Creators are built from published records, so the counts match
  what the public can actually open.
- **It stays current.** Creator rankings and record lists are computed on the fly
  from the live catalogue, so they reflect the collection as it is right now.
- **It complements the other slices.** Where **Explore by Theme** groups records by
  subject, **Browse by Place** groups them by geography, and **Discoveries** /
  **Research Leads** show AI-found links between records, People and Organisations
  groups records by who made them - different ways to find related material.
