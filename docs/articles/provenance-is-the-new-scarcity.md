---
category: Article
group: Framework
title: Provenance Is the New Scarcity
author: Dr Johan Pieterse
slug: provenance-is-the-new-scarcity
status: draft (flagship - review before publishing)
---

# Provenance Is the New Scarcity

*When plausible content becomes abundant, content alone stops being evidence. The archive's infrastructural role becomes impossible to ignore.*

There is a hard drive on my desk holding ten thousand photographs. Some came out of cameras. Some I generated this morning, in minutes, for almost nothing, and opening them one by one you cannot reliably tell which is which. Neither, honestly, can I.

Five years ago that was science fiction. Now it is a Tuesday. And it quietly breaks an assumption the entire history of recorded information rests on: that the existence of a document, a photograph, a recording is itself evidence that something occurred. The archive held the record, the database held the fact, the library held the book, and possession carried weight. Generative AI has made plausible content nearly free to produce. A convincing contract, minutes of a meeting that never happened, a photograph of an event that never occurred - anyone can make these now, at negligible cost, in any volume they like.

**The moment plausible content became abundant, content alone stopped being evidence.**

So what is scarce? Not information. Evidence *about* information. **Provenance**: where a record came from, who created or held it, what happened to it, under whose authority, and whether its integrity can still be demonstrated. When anything can be synthesised, value shifts to the chain of evidence that makes origin, custody and alteration independently assessable rather than merely plausible.

## The craft we mislabelled as compliance

Technology is busy rediscovering provenance through content credentials, data lineage, digital forensics, audit logs and cryptographic verification. Useful, all of it. But these approaches mostly treat provenance as a technical property of a file: who signed it, which software touched it, whether the bits still match. That matters, and it is not the whole problem.

Archival provenance works at a wider, institutional scale. It asks where a body of records came from, why it was created, how it accumulated, what relationships give it meaning, who exercised custody and under what authority. It connects an object to people, functions, events, systems and other records over time. Archives are not the only institutions in this business - courts, forensic laboratories, registries, scientific repositories and newsrooms all run related disciplines - but archives have unusually deep experience in preserving context across generations, not just across a lawsuit or a news cycle.

For decades we filed that expertise under compliance: the underfunded function down the corridor that looks after old records. In an environment saturated with synthetic content, it becomes one of the foundations trustworthy information gets built on.

## The inversion

The heritage sector's anxiety about AI currently runs in one direction: AI will consume us. It will train on our collections without permission, answer questions from their contents and make the holding institution invisible while doing it. That concern is real. It is also only half the story.

The movement in the other direction is the more consequential one. As AI outputs carry legal, financial, scientific and reputational weight, high-consequence systems will have to ground material claims in authoritative sources and make those sources traceable. Regulation, litigation, professional accountability and plain institutional risk all pull the same way. A confident answer that cannot be defended becomes a liability precisely where the stakes are highest.

That turns the archive from a passive store into an active trust endpoint: not merely a place a person searches, but an institutionally governed service that a person or machine can query and cite. The response is not "here are some search results." It is: according to this responsible record-holder, this is the identified record; this is its context and custody history; this is what has been verified; these are the rights and restrictions; these are the gaps; and this is when the evidence was last checked.

## What a trust endpoint looks like

Picture an AI system answering a question about who authorised a public contract. Instead of returning a fluent sentence and a link to an isolated PDF, it queries the responsible records authority. The authority returns an evidence package that lets the answer be tested rather than merely believed.

| Evidence component | What the response would disclose |
|---|---|
| Identity | Persistent record identifier, creating authority and responsible custodian. |
| Context | The official series, function, related records and relevant people or events. |
| Custody | Documented transfers, preservation actions and accountable agents. |
| Integrity | Fixity evidence, signatures, timestamps and known transformations. |
| Authority and rights | Who is making the assertion; applicable access, reuse and community rules. |
| Citation and status | A stable citation, verification date, version, known gaps and any revoked or superseded assertion. |

The archive does not tell the system what to believe. It supplies accountable evidence against which the answer can be evaluated.

## What provenance cannot prove

Let me be blunt about the limits, because this is where the argument is most often oversold. Provenance is not a truth machine. A document can be authentic and still be false. A government department can create a misleading report, preserve it perfectly and transfer it through an impeccable chain of custody. Provenance proves the department produced the report; it does not prove that a single statement in it is correct.

Nor does a digital signature make its signer infallible. Credentials can be compromised. Metadata can be fabricated. A chain may begin only when a record enters a managed system, leaving its earlier history uncertain. And institutional authority itself may be contested, especially where official archives reproduce the silences and biases of the state that created them.

That distinction strengthens the argument rather than weakening it. Trustworthy memory is not memory that demands belief. It is memory that exposes enough evidence - origin, context, authority, custody, integrity, uncertainty, competing accounts - for a claim to be assessed. The product is not certainty. It is accountable traceability.

## Provenance is the door; trustworthy memory is the room

It would be tidy to declare provenance the whole answer. It is the way in, nothing more. Provenance happens to be the part of trust that synthetic media made urgent and that machines can increasingly inspect, which is why the door opens there. The room beyond is bigger.

Trust also needs context: what a record means and what sits around it. Custody: who has held it, and how. Integrity: whether it has changed. Authority: who has the right and competence to make an assertion about it. Rights: who may access or reuse it. Sovereignty: whose laws and community protocols apply. And preservation, the least glamorous question of all: whether it will remain intact, understandable and usable in fifty years.

Put those together and the real object comes into view. Not the archive as a building, or even as a database, but **trustworthy memory**: a society's capacity to preserve evidence about its past and present, govern that evidence legitimately, and make it available in forms people and machines can interrogate. Like other infrastructure, it is easy to ignore while it works and catastrophic when it fails. A society that can no longer distinguish its records from plausible fabrications loses more than files. It loses the common ground on which accountability, law and public argument depend.

## The hard part, again

Many of the technical building blocks already exist. C2PA gives us cryptographically verifiable information about the origin and editing history of digital assets. PREMIS records preservation events, agents, rights and fixity. Records in Contexts models records and their relationships with real richness. Persistent identifiers, digital signatures, trusted timestamps, access controls, machine-readable APIs: all established technology. None of that is the obstacle.

What does not yet exist at scale is the institutional and governance architecture that joins these components into authoritative memory services. A standard can describe a relationship without deciding who is entitled to assert it. A hash can reveal that bits changed without explaining whether the change was authorised. An API can expose a record without resolving whether it should be accessible at all.

Trustworthy memory is a governance problem wearing a technical costume. It asks an institution to decide what it will vouch for, document how it knows, expose the supporting evidence in a form a machine can inspect, preserve that evidence through change, and remain accountable when an assertion is challenged or corrected. That is a discipline, not a procurement. Disciplines are far harder to buy than software.

## Whose memory, whose rules

Trustworthy memory is never governance-free. Centralise it in someone else's cloud, describe it only through someone else's categories, make it accessible under someone else's terms, and it quietly becomes someone else's memory.

Where records embody community-held or culturally governed knowledge, the affected community must have meaningful authority over how those records are described, accessed and reused - authority that operates alongside applicable public-records, access-to-information and privacy obligations. Sometimes community authority will legitimately limit openness, reuse or interoperability. A system that treats unrestricted access as the only form of progress can reproduce dispossession in digital form.

The world has spent years debating the right to be forgotten. For many Indigenous, marginalised and colonised communities, the deeper historical injustice runs the other way: being erased, never recorded, misdescribed, or having history overwritten by institutions with greater power. Trustworthy memory has to be sovereign as well as verifiable. Missing either half, the word trustworthy starts to collapse.

## Why the Global South changes the argument

In the Global South this is not an abstract debate about deepfakes. Missing, inaccessible and poorly governed records already defeat accountability, frustrate access-to-information rights and let communities disappear from institutional memory. Under-resourced archives are asked to preserve evidence across obsolete media, fragile infrastructure, changing administrations and systems designed elsewhere. AI can amplify those absences and distortions at enormous scale.

The answer cannot be to move every collection into a foreign platform and call it modernisation. Data residency matters. Public accountability matters. Indigenous and community protocols matter. So does the capacity to maintain and govern the infrastructure locally. Sovereignty without interoperability creates isolation; interoperability without sovereignty creates dependency. Trustworthy memory requires both.

This is where the opportunity gets specific. Pieces of provenance infrastructure already exist, but the combination of archival context, long-term preservation, public-record accountability, community authority and jurisdictional control remains poorly served - especially outside the institutions and markets that can afford bespoke systems. The gap is not another archive database. It is governed, standards-based memory infrastructure that smaller institutions, public bodies and communities can actually control.

## The role we did not ask for

For generations, archives have maintained records so that origin, context and authenticity could be assessed when those records mattered. While most information stayed expensive to create and hard to manipulate convincingly, that function was easy to mistake for housekeeping. Generative AI has changed the economics of plausible fabrication. It does not make archives obsolete. It makes their infrastructural role impossible to ignore.

Institutions holding authoritative memory are being handed a responsibility most are not yet funded, equipped or governed for: to become part of the layer against which consequential information is checked. The ones that recognise the shift will stop thinking of themselves only as custodians of the past. They will start designing services for the present: preserved records with machine-readable context, transparent custody, verifiable integrity, enforceable rights, visible uncertainty, and governance that stays answerable to the people whose memory is at stake.

**Provenance is the new scarcity. Trustworthy memory is what we build with it. The question is not only whether we build it, but who is allowed to govern it.**

## Notes and further reading

- C2PA Technical Specification: https://spec.c2pa.org/specifications/specifications/2.4/specs/C2PA_Specification.html
- ICA Records in Contexts Ontology: https://www.ica.org/standards/RiC/ontology
- Library of Congress, Understanding PREMIS: https://www.loc.gov/standards/premis/understandingPREMIS_english_2021.pdf
- EU Regulation 2024/1689 (AI Act): https://eur-lex.europa.eu/eli/reg/2024/1689/oj
- Global Indigenous Data Alliance, CARE Principles: https://www.gida-global.org/careprinciples
- Local Contexts, Traditional Knowledge and Biocultural Labels: https://localcontexts.org/labels/
