> Heratio Help Center article. Category: Discovery & Browse.

# Related Records User Guide

## Overview

Related Records answers a single question for one archival record: "what else in the collection is like this one?" Given a published record, it lists the most similar OTHER published records, so a reader can move sideways through the catalogue from a record they are already looking at. Open it at **/related/{record}**, where `{record}` is the record's slug or its numeric id - for example **/related/title-of-object** or **/related/553**.

---

## What it does

Related Records reuses the collection's existing semantic index. Every record's description is already represented as a point in that index; Related Records takes the record's own stored representation and asks the index for the published records whose descriptions are closest in meaning. It does not run a fresh analysis and does not need anything generated first.

- Only published records appear in the results.
- The record itself is never listed.
- Results are ranked by a similarity score (higher is closer) and capped at a small number (12 by default, up to 20).

---

## How to use it

1. Open a published record's page.
2. Go to **/related/** followed by that record's slug or id (for example **/related/title-of-object**).
3. You will see:
   - A short summary line saying how many similar published records were found.
   - A grid of cards, each a related record with its similarity score and a link straight to that record.
   - An honest note explaining that relatedness is the semantic similarity of the record descriptions.
4. Click any card to open that related record.

A machine-readable version is available at **/related/{record}.json** for integrations and feeds.

---

## When there are no results

If nothing appears, the page says so plainly - nothing is hidden. There are a few honest reasons this can happen:

- The record has not yet been added to the semantic index.
- No other published record is close enough in meaning to count as related.
- The discovery service is temporarily unavailable.

In all of these cases the page still loads normally and simply shows an empty result. The JSON version returns an empty list rather than an error.

---

## What you will and will not see

- **Published only.** Unpublished records never appear, and the record you started from is always excluded.
- **Unknown or unpublished record.** Asking for a record that does not exist, or one that is not published, returns "not found".
- **No configuration.** There is nothing to set up or generate; results follow the existing index automatically.

---

## How relatedness is computed

Each record's description is turned into a vector (a numeric representation of its meaning) and stored in a shared semantic index. Related Records compares the record's stored vector with the others in the index and returns the closest published records. The similarity score on each card reflects how close two descriptions are in meaning - it is not a quality or relevance judgement, just a measure of textual and conceptual closeness.

---

## Privacy and scope

Related Records is read-only. It never changes any record, never stores anything, and only surfaces published material. It is jurisdiction-neutral and works the same way for any collection in any market.
