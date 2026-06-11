> Heratio Help Center article. Category: Discovery & Access.

# Language Coverage User Guide

## Overview

Language Coverage is a public, read-only page that shows which languages this collection's catalogue can be read in today, and how much of it. It answers a simple question for any visitor: "Can I read this collection in my language, and how much of it is available?" The page is framed as an open invitation - it celebrates the languages already present and invites communities and contributors to help extend coverage to more languages.

It is the discovery and invitation layer that sits in front of the per-record "Read a record in your language" feature: the dashboard shows the big picture, and the on-demand translation panel lets a visitor instantly try a machine translation of any single published record.

Open it at **/language-coverage**. It is also reachable as a card on the public **Explore** hub.

---

## What it shows

The page draws entirely on the catalogue's existing multilingual metadata. Every figure is a count of records that already carry text in a given language - nothing is invented and nothing is changed.

- **Headline figures** - the total number of published descriptions, how many distinct languages are present, the primary (most-covered) language, and what share of records are written in it.
- **Archival descriptions by language** - how many published records carry a title in each language, shown as counts and simple bars. Percentages are of all published descriptions, so the bars communicate reach.
- **People and organisations by language** - how many authority records (people, families, organisations) have a name recorded in each language.
- **Subjects and places by language** - how many controlled-vocabulary terms have a label in each language.

Bars are plain progress bars - there is no chart library and no external dependency. If the collection is empty or still being catalogued, the page shows a calm "still being catalogued" message rather than an error.

---

## Read any record in your language (on demand)

The page includes a small translation panel. Enter a published record's id, pick a language, and the record's key descriptive metadata (such as title and scope-and-content) is translated for reading on the spot.

- The translation is produced through the **AHG AI gateway**, the platform's single, governed route for AI features. There is nothing to configure.
- The original text is always shown alongside and **remains authoritative**. The translated text is clearly labelled: *"Machine translation via the AHG gateway - not an official translation."*
- If a field cannot be machine-translated, its original text is shown instead, flagged as such.
- If the AI service is unavailable, the page still works as an analytics view - only the on-demand panel falls back to showing originals.
- Only **published** records can be translated through this panel. Draft records are not exposed.

---

## How to use it

1. Open **/language-coverage** (or follow the "Languages of this collection" card on the Explore hub).
2. Read the headline figures and the per-language breakdowns to see what is covered.
3. To try an instant translation, enter a published record's id, choose a language, and select the translate button.
4. Read the translated metadata in place, keeping in mind it is a machine translation and the original remains the source of truth.

---

## Good to know

- The page is **public** and **read-only**. It makes no changes to the catalogue and stores no translations back into it.
- Coverage figures reflect only **published** records, so the page is safe to share with the public.
- Machine translation quality varies by language. Some languages are well supported; others are weaker. The original text is always the authoritative version.
- The set of offered languages is drawn from the languages actually present in the collection, plus a neutral default set, so the panel is useful even on a young collection.

---

## Why it matters

A catalogue that can only be read in one language is closed to most of the world. Language Coverage makes the current state honest and visible, and turns it into an invitation: every language added widens who the collection serves. This supports the goal of universal access - every museum, for everyone.
