# Emerald journal submission requirements, verified for the Journal of Documentation

**Summary.** Emerald's word limit counts everything, including the references and the text inside tables, and charges a flat 250 words for every table or figure regardless of its real length. That single rule reshapes a paper plan more than any other requirement, and it is easy to discover too late. Confirmed against the publisher's own author guidelines on 28 August 2026 for the Journal of Documentation. Most of it applies across Emerald journals, but check the specific title before relying on it.

## The word count

Articles must be between 4,000 and 10,000 words. The count includes all text: the structured abstract, the references, and all text in tables, figures and appendices. On top of that, allow 250 words for each figure or table.

The practical consequence is that the prose budget is much smaller than the headline number. Working backwards from 10,000 with 55 references at roughly 30 words each, a 250-word abstract and three tables:

    10,000 - 1,650 - 250 - 750 = approximately 7,350 words of actual prose

A plan written to "just under 10,000 words of text" will overshoot by something like 40 per cent. Budget the prose, not the article.

## The structured abstract

Four sub-headings are mandatory and must always be included:

- Purpose
- Design/methodology/approach
- Findings
- Originality

Three more are optional where applicable: Research limitations/implications, Practical implications, Social implications.

This has a consequence worth planning around. A purely conceptual paper has to fudge two of the four mandatory headings, and a Findings heading with nothing under it is exactly what a reviewer circles. If a paper is conceptual, either build in something that produces a genuine finding, or choose a venue that does not demand the structure.

## Other requirements

- Article files in Microsoft Word. A PDF may accompany the Word file but is not accepted alone. LaTeX is accepted only with an accompanying PDF.
- A concisely worded title. Journal of Documentation has been described as capping the title at sixteen words; verify against the current guidance.
- Harvard referencing, Emerald's variant.
- Author details are added to the ScholarOne submission and extracted from each author's ScholarOne account. They do not go in the manuscript file.
- Biographies and acknowledgements go in a separate Word document uploaded alongside the manuscript. Biographies are capped at 100 words per named author.
- All external research funding must be referenced in the acknowledgements, describing the funder's role in the research.
- Journal of Documentation uses double-anonymous peer review, so the manuscript file must be anonymised.

## Anonymisation, in practice

Stripping the byline is not sufficient. Two further checks matter, both of which have caught AHG submissions before.

Check the document properties, not only the visible text. Unzip the docx and inspect `docProps/core.xml` for `dc:creator` and `cp:lastModifiedBy`. Pandoc leaves `dc:creator` empty, but a file that has been opened and saved in Word will carry the author's name there.

Check for distinctive project names. A googleable system name leads straight to a public repository and identifies the author as reliably as a byline. Replace with a phrase such as "an author-developed open-source system, name withheld for peer review" throughout, including in any abstract pasted into the submission form.

## Journal of Documentation scope

The journal describes itself as providing "a unique focus on theories, concepts, models, frameworks and philosophies related to documents and recorded knowledge". ISSN 0022-0418, eISSN 1758-7379. Open access is elected at submission on the editorial system. Conceptual and theoretical work is squarely in scope, which is not true of every records-adjacent journal.

## Generating the file

Journal manuscripts are the standing exception to the AHG branded-template rule. Use a plain unbranded reference docx and no table of contents, since house branding on a blind submission both identifies the author and is unwanted by the publisher.

One gotcha when generating through pandoc: the unbranded template leaves an empty `<w:sectPr />`, so page size falls through to a US Letter default. Insert a `w:pgSz` of 11906 by 16838 twips for A4, and assert that the substitution actually applied rather than trusting a silent string replace.
