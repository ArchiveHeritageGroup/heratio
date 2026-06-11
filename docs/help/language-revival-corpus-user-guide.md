# Language revival - the collection as a living language resource

Some of the languages a collection holds are heritage or endangered languages -
living languages that belong to the communities who speak them. The language-revival
corpus turns the collection into a read-only resource for a chosen language: it
gathers the records described in it, the place-names and subject terms that carry a
label in it, any transcriptions, and a community-built glossary. This is part of the
North Star "a culture you can talk to - corpus-grounded history and language
revival".

## Respectful, jurisdiction-neutral framing

A heritage language is living and owned by its community of speakers. This surface
gathers what the collection holds **in or about** a language as a resource for
speakers, learners and researchers. It does **not** claim authority over the
language itself, and it never machine-translates a whole language into the catalogue.
The community glossary is contributed by people and reviewed before it appears - a
shared starting point, not a definitive dictionary.

## The public pages

- **Language directory** (`/language-corpus`) - every language the collection holds,
  with how many published records are described in it and how many terms carry a
  label in it. Richest first; Afrikaans is ordered immediately before Dutch.
- **A language page** (`/language-corpus/{culture}`, e.g. `/language-corpus/af`) -
  for one language, read-only:
  - **Records described in it** - published holdings whose description is written in
    the language, each linking to its record.
  - **Words from the collection** - place-names and subject terms that carry a label
    in the language (a starting word-list, not a definitive vocabulary).
  - **Transcriptions and full text** - published full-text passages in the language.
  - **Community glossary** - approved words and meanings contributed by the community.

Only published records (publication status, type 158, status 160; the catalogue root
is never shown) appear publicly.

## Optional machine translation

Each transcription offers an optional, on-demand machine translation to English.
The translation runs through the AHG AI gateway (never a direct model node) and is
**always** labelled:

> Machine translation via the AHG gateway - not an official or authoritative
> translation.

If translation is unavailable or disabled, the original text is left intact with a
clear note. Heritage and SA languages are never bulk machine-translated into the
catalogue - this is read-only, on-demand assistance only.

## Contributing to the glossary

Anyone can add a word in a language and its meaning from the language page. A
contribution lands as **pending** and is **not** shown publicly until a reviewer
approves it.

## Moderating the glossary (admin)

1. Go to **Glossary moderation** (`/language-corpus-admin/glossary`).
2. Filter by status (pending / approved / not published).
3. **Approve** an entry to publish it on the language page, or **Reject** to hide it.

## What gets written

The only table this feature writes to is the additive `language_revival_glossary`.
Everything else is read read-only from the existing catalogue - no existing table is
written or altered.
