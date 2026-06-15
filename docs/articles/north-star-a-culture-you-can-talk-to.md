# A Culture You Can Talk To

### Heratio's North Star: corpus-grounded history that answers in your own language

*By Johan Pieterse, The Archive and Heritage Group*

---

## The one sentence

Every product needs a north star: a single, honest sentence that every feature has to serve or be cut. Heratio's is this:

> **A culture you can talk to.**

Not a search box. Not a catalogue you have to already understand before you can use it. A collection that answers a plain question, in the language the question was asked in, grounded in the institution's own records, and honest about what it does not know.

That last clause is the whole game. Anyone can bolt a chatbot onto an archive. The hard part, and the only part worth doing, is making it true.

## Why archives are hard to talk to

A gallery, library, archive or museum (GLAM) holds the memory of a community. But that memory is locked behind a vocabulary only specialists own: finding aids, fonds, ISAD(G) levels of description, controlled terms, reference codes. A school teacher, a grandchild tracing a family, a journalist on deadline, a researcher in another country, none of them speak that language. So the collection stays silent for almost everyone it is meant to serve.

The usual fix is a better search interface. That helps the people who already know what they are looking for. It does nothing for the far larger group who have a question, not a query.

The North Star inverts the problem. Instead of teaching the public to speak archive, we teach the archive to speak human.

## What "grounded" has to mean

There is a fast, dishonest way to build this, and a slow, honest one.

The dishonest way: let a large language model answer from its training data, dressed in the institution's branding. It will sound confident. It will also invent provenance, fabricate dates, and quietly launder the model's biases as the institution's voice. For a memory institution, whose entire authority rests on being trustworthy about the past, that is not a shortcut. It is a self-inflicted wound.

The honest way is harder and is the one we took:

- **Answer only from the corpus.** Heratio's "Ask the Collection" surface is wired to the institution's own knowledge base through a retrieval-augmented pipeline. The answer is built from indexed records, and it carries its sources with it so a reader can follow the citation back to the document.
- **Say "I don't know" out loud.** When the corpus does not cover a question, the system returns a plain "I don't have enough in this collection to answer that" instead of guessing. A gap stated honestly is worth more than a fluent fabrication.
- **Never leak the back office.** The public, anonymous surface suppresses anything that looks like internal or technical content, raw queries, schema, operator notes, so a retrieval slip can never expose the plumbing to a visitor.
- **Cite, or stand down.** An answer that cannot point at where it came from is treated as not confidently grounded, and framed that way.

Grounded is not a feature you add. It is a discipline you hold, in the cases where it would be easier not to.

## Answering in your own language

A collection you can talk to is only "yours" if it answers in your language.

Heratio localizes answers through a dedicated machine-translation route, not by asking a general model to "reply in French." Generative translation drifts: it hallucinates, it blends neighbouring languages, it flattens the ones with less data on the internet. For languages where that risk is real, we lead with the translation engine and fall back to the original text rather than ship a confident mistranslation.

This matters most for the languages the big models serve worst. Afrikaans, for example, is treated as first-class and is kept off general generative translation precisely because the easy path produces Dutch-flavoured nonsense. The principle generalises: the smaller the language, the more care it is owed, not less.

## From a sentence to a building you can walk through

"A culture you can talk to" is a conversation. But culture is also spatial, social and shared, so the North Star pulls in three directions at once.

**You can walk through it.** Heratio builds a digital twin of an exhibition: a real-time 3D walkthrough of a gallery you can move through in a browser, with objects on the walls, in cases, and on the floor exactly where a curator placed them. Large buildings stream room by room, building each space as you approach and releasing it as you leave, so a phone can hold a cathedral. A guided docent can walk a visitor from object to object and answer questions about each one, in their language, grounded in the record.

**You can ask the room, not just the database.** The same grounded, multilingual conversation works at the scale of a single object or a single room. Stand in front of a piece and ask about it; the answer comes from that object's description, not from the open internet.

**You can share it across institutions.** Culture does not stop at one organisation's walls. Heratio's federated twin lets a curator borrow an object from a partner institution into their own exhibition: read-only, attributed "Courtesy of" the owner, linked back to the owning record, with the media served from the partner and nothing silently copied. A small museum can mount a show that draws on a national collection. A travelling exhibition can assemble loans that never physically move. The long-horizon parts, cross-institution rights that travel with the object, shared identity between nodes, are deliberately scoped as the next climbs, not pretended to be done.

Every one of these is the same north star seen from a different angle: lower the barrier between a person and a collection until the collection can simply answer.

## Built for everywhere, governed everywhere

Two commitments keep the North Star from collapsing into a demo.

**International by default.** Heratio is built for the global GLAM market. The core is jurisdiction-neutral. Compliance regimes, whichever data-protection, heritage-accounting or access-to-information law applies in a given country, are pluggable modules that sit alongside the core, never baked into it. A collection in Harare, Helsinki or Halifax runs the same platform with a different module clipped on.

**Governed AI, not loose AI.** Every production AI call, translation, retrieval, docent answer, routes through a single governed gateway that authenticates, meters and logs it, and chooses where to run it. There is no app wired directly to a raw model port. That is what makes "grounded and honest" auditable rather than aspirational: when an institution puts its name on an answer, the path that produced it can be traced.

## Why this is the right star

A memory institution's promise to its community is simple: *we will keep this, and we will make it findable when you need it.* For a century that promise has been kept in reading rooms and finding aids, available to the few who could come in person and speak the language of the catalogue.

The North Star is the same promise, kept for everyone else. A culture you can talk to is a culture that answers the teacher, the grandchild, the journalist and the overseas researcher in their own words, from its own records, honestly about its own gaps, across institutional and national borders.

That is worth building slowly and correctly. Everything in Heratio is measured against that one sentence, and the things that do not serve it do not ship.

---

*Heratio is a standalone archival management platform for the international GLAM sector, built by The Archive and Heritage Group. See the companion post: [`north-star-linkedin-post.md`](./north-star-linkedin-post.md).*
