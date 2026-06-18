# Talk to a language's corpus (language-scoped chatbot)

The Heratio research assistant can be scoped to one OR MORE languages so that it
answers only from the records held in or about the selected languages - turning
the chatbot into a guide to those heritage-language corpora. This is part of the
"a culture you can talk to" north star: a culture boundary is a language.

The scope is **multi-select and user-selectable**: tick any combination of the
languages the collection holds, and the assistant draws on the **union** of
their corpora. Tick nothing and the assistant searches the whole catalogue.

## What it does

When the chatbot is scoped to one or more languages (each a "culture"):

- **Retrieval is constrained** to the records described IN any of the selected
  languages (the union of their corpora). This uses exactly the same definition
  as the Language revival pages: a record counts as being "in" a language when
  its published archival description (`information_object_i18n.culture`) is
  written in that language (matched on the base language subtag, so regional
  variants such as `pt-BR` count under `pt`).
- **The assistant is told its role**: it is a guide to the selected languages'
  heritage corpora and must answer from the cited records, not from general
  knowledge. Every selected language is named in the assistant's instructions.
- **Follow-up turns stay scoped**: the selected SET of languages is remembered
  for the conversation, so you do not have to repeat it on every question.

When no language is selected, the chatbot behaves exactly as before - unscoped,
across the whole catalogue.

## How to start a scoped conversation

1. Open the chatbot. A **Language scope** panel lists the languages the
   collection holds (with how many records each has), pulled from the same
   directory as the Language revival pages.
2. **Tick one or more languages.** The summary line confirms the active scope
   (for example, "Scoped to: isiZulu, isiXhosa"). The selection is applied to
   the next question and every follow-up.
3. Ask your questions normally. Answers are drawn only from records in the
   selected languages and cite their sources.
4. To search the whole catalogue again, click **All languages** (or untick
   every language).

You can also open the chatbot pre-scoped from a URL:

- A single language: `?language=zu`
- A set, comma-separated: `?language=xh,zu,nso`
- A set, as repeated parameters: `?language[]=xh&language[]=zu`

where each code is a language code such as `af`, `zu` or `nso`. The
**Talk to this corpus** button on a Language revival page opens the chatbot
pre-scoped to that single language; you can then add more from the scope panel.

## Grounding and citations

Scoped answers are grounded the same way as normal answers: every claim should
cite a source record with a `[N]` reference, and the sources are listed beneath
the reply. If the language's corpus does not contain anything relevant, the
assistant says so and invites you to broaden your search rather than answering
from outside the corpus.

## Glossary injection (the catalogue's own vocabulary)

When the conversation is scoped to one or more languages, the assistant is also
given a **glossary** drawn from the catalogue's own authority records for those
languages: the **place-names** and **subject access points** actually used to
describe the in-language holdings. This means the assistant:

- uses the **catalogue's spellings** of places and subjects rather than a
  generic or anglicised form, and
- can **match your wording to a real access point** - if you ask about a place
  or topic that the collection indexes under a particular term, the assistant
  recognises it as that access point.

The glossary is built live from the same authority terms shown elsewhere in the
catalogue (place and subject taxonomies), de-duplicated across the selected
languages and capped per kind to keep responses fast. It is **additive** - it
never replaces the cited SOURCES, and a language with no in-corpus place or
subject terms simply contributes nothing. Administrators can turn it off with
`AHG_CHATBOT_GLOSSARY_INJECTION=false`.

## Notes and limits

- Unknown or empty language codes are simply dropped - the conversation stays
  unscoped (or keeps only the recognised languages) rather than failing.
- A language with no published records is not offered in the scope panel (there
  is nothing in-corpus to answer from); use the Language revival page to see and
  help build that language's holdings and community glossary.
- Selecting several languages scopes retrieval to the UNION of their corpora and
  names all of them in the assistant's instructions. There is a bound on the
  total number of in-language records considered, so very large unions are
  capped for performance.
- This increment scopes retrieval and the assistant's role across the selected
  set, and injects the catalogue's place-name and subject vocabulary for the
  scoped languages into the prompt (see "Glossary injection" above). Scoping the
  WhatsApp channel remains a planned follow-up.

## Privacy

Scoped conversations follow the same privacy rules as the rest of the chatbot:
answers stay within the public catalogue description and do not reveal
personally identifiable information beyond what is already published.
