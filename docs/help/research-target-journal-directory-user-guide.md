# Where to Publish — Target-Journal Directory

The target-journal directory is a curated list of journals you can publish **to**. Each entry records what the journal **mainly accepts** (subject scope) and its **submission rules** (reference style, word limits, peer-review type, open access, indexing). The directory feeds the manuscript side of the Journal Builder, so a manuscript can be checked and formatted against a real target journal.

Open it from the research portal sidebar → **Where to Publish** (`/research/target-journals`).

## The directory

Each journal lists its title, publisher, ISSN, subject scope, article types, accreditation/indexing (DHET, Scopus, Web of Science, DOAJ, Sabinet, …), reference style, word and abstract limits, peer-review type, open-access status and APC, turnaround, and homepage/submission links.

- **Search/filter** by title, scope, publisher, indexing or market.
- **Add Journal** to create an entry; **Edit**/**Delete** to maintain it.

## Seeding DHET-accredited journals

Use **Seed DHET starter** to load a curated starter set of DHET-accredited South African journals (with an emphasis on GLAM / LIS / archives / heritage plus multidisciplinary titles). The seed is idempotent — re-running refreshes the set without creating duplicates. You can also run it from the CLI:

```
php artisan ahg:seed-target-journals
```

The DHET list is the **South-African accreditation module**: the directory core is jurisdiction-neutral, so other markets seed from DOAJ, Scopus, Web of Science or ERIH-PLUS instead. The starter seed captures each journal's scope, publisher, indexing and reference style; ISSNs and exact numeric limits should be completed/verified per journal via Edit.

## How it powers the manuscript builder

In the Journal Builder, a **manuscript** can point at a target journal from this directory. The manuscript's **submission checks** then use the target's rules:

- the **reference style** must match the journal's,
- the manuscript must stay within the journal's **word limit** (when set),
- plus the standard completeness checks (title, abstract, authors, body).

The directory also supports **best-fit suggestions** — matching a manuscript's subject/abstract against journal scope to recommend where to submit.

## Tips

- Fill in `max_words` and `reference_style` on the journals you target so the manuscript checks are meaningful.
- Keep `accreditation` accurate — it is how researchers find DHET/Scopus/WoS-listed venues.

## Related

- **Journal Builder** (#1105) — manuscripts target journals from this directory.
- This directory is issue **#1107** (PSIS twin: atom-ahg-plugins#114).
