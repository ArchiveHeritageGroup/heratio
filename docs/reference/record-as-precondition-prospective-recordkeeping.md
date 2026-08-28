# The record as precondition: prospective recordkeeping in algorithmic decision systems

**Summary.** Recordkeeping theory rests on an assumption so ordinary it is rarely stated: a record is a trace left by a transaction. The act occurs and the record follows. Algorithmic decision systems that gate execution on the adequacy of the record invert this, making the record a precondition of the act rather than a description of it. That inversion is not new in law, where registration systems have worked this way for over a century, but it has never been generalised into recordkeeping theory, and it is about to become ordinary. This note records the argument and the verified source material behind it. It is the thesis of a sole-authored paper in preparation for the Journal of Documentation.

## Why this is worth keeping

Two uses beyond the paper.

It is the strongest available framing for why AHG's own systems are designed the way they are. Where Heratio evaluates an ODRL policy before permitting access or reproduction, that is a prospective gate in the sense described here, and the vocabulary below is how to explain it to an auditor, a funder or a standards body without sounding merely technical.

It is also a caution. Any system that gates an action on the adequacy of a record acquires the ability to refuse, and refusal is a harm that a passive audit log cannot cause. Designs of this kind need an equivalent of the caveat and the provisional entry that registration systems developed for exactly this reason.

## The two modes

**Retrospective.** An act occurs and generates a record. The record's function is descriptive. Its evidential value comes from the fidelity and circumstances of capture. An inadequate record makes reconstruction hard but prevents nothing.

**Prospective.** A proposed act is evaluated against a record of it and proceeds only if stated conditions hold. The record's function is constitutive. A record that fails its conditions stops the transaction.

The difference is not one of degree. Only the second can deny anyone anything.

## That recordkeeping assumes the first

The assumption is shared across schools that agree on very little else, which is the strongest form the claim can take.

Jenkinson defined an archive as a document "drawn up or used in the course of an administrative or executive transaction (whether public or private) of which itself formed a part; and subsequently preserved in their own custody for their own information by the person or persons responsible for that transaction and their legitimate successors". He then added a corollary that states the premise outright: "Archives were not drawn up in the interest or for the information of Posterity." That corollary is not a remark about motive. It is the ground of the evidential claim, since records are trustworthy on this account precisely because they were not composed for whoever later reads them.

Schellenberg broke with Jenkinson over appraisal, arguing the archivist must select rather than merely receive, but that quarrel concerned what happens to records after they exist.

Bearman moved capture much closer to the moment of the act, arguing that archivists should focus on business applications "because business applications generate records and because the specific requirements for retention of evidence arise from the nature of the transactions which characterize different business functions". Note the verb. Applications generate records. Bearman moved capture nearer its source without reversing the direction.

The records continuum dissolves the boundary between current and archival records and holds that a record is simultaneously evidence of an act, of an organisation and of a society. Continuum theorists have disputed the lifecycle model on nearly every point of substance and have not disputed this one.

## Where the law already inverts it

Under a Torrens system, title derives from the register rather than being evidenced by it. Barwick CJ put it decisively in Breskvar v Wall: the Torrens system "is not a system of registration of title but a system of title by registration". Registration attracts indefeasibility, so a registered proprietor takes title despite a defect in the instrument behind it, while an instrument executed, delivered and paid for but never registered transfers nothing. Company registers behave similarly for certain corporate acts, and deeds registration in the civil law tradition reaches comparable results by other routes.

The doctrinal detail varies by jurisdiction and Breskvar is Australian authority on immediate indefeasibility rather than a statement of universal law. The narrow point survives the variation: there exist long-established recordkeeping arrangements in which the record is the operative act.

Diplomatics holds a related notion in the perfected document, and Duranti's separation of the juridical act from the document evidencing it keeps the necessary distinction open. But these are treated as properties of particular documentary forms in particular legal systems, not offered as a general mode.

## Why the mode is becoming general now

Making the record constitutive requires someone to evaluate it before the act completes. For most of recordkeeping history that was a registrar: a person, a queue, a fee, a delay. The arrangement was reserved for transactions valuable enough to justify all that. Three things have changed together.

Volume makes retrospective reconstruction economically hopeless. An institution taking millions of automated decisions a year can sample, and sampling finds the systematic fault but not the individual injustice.

The registrar's evaluation has become cheap. Checking whether conditions hold over a structured record is what software is good at, and the marginal cost approaches zero.

There is no longer anyone to ask afterwards. When a human official decides, reasons can be sought later, imperfectly but genuinely. When a system decides at machine speed, whatever was not captured at the moment of decision is unrecoverable, because nobody remembers.

## The consequence for appraisal

Appraisal has always been retrospective in the same sense records are. The archivist confronts material that exists and decides what becomes of it, whether at creation as functional and macro-appraisal would have it, or much later.

Under a prospective regime this no longer holds. If evidential adequacy is a precondition of the act, the decision about what will be documented and to what standard is made before anything happens, and it is a decision about which acts may occur. Appraisal becomes a design-time function exercised over transactions rather than over records. Stated at full strength: where recordkeeping is prospective, the appraisal decision and the authorisation decision are the same decision, and whoever determines what must be recorded thereby determines what may be done.

## Authenticity is not veracity

This is the point at which the archival tradition holds something AI governance urgently needs and does not have.

Frameworks that capture a decision's rationale, where that rationale is produced by a generative model, capture a text the model produces about its own processing. Turpin et al. found such explanations "can systematically misrepresent the true reason for a model's prediction". Lanham et al. intervened on the reasoning itself to test whether it was load-bearing and found that larger and more capable models produce less faithful reasoning on most tasks tested, which forecloses the assumption that scale will resolve it.

A well-built traceability layer will capture that rationale at decision time, bind it to the evidence then available, fix it against alteration and preserve it under the decision's identifier. Every operation is performed correctly. The result is a record that is authentic in the full technical sense and whose stated reason is not the reason. Done faithfully, this is worse than no record at all, because it manufactures a warrant for confidence that the facts do not support, at scale and with an audit trail attached.

Diplomatics has never confused the two. A record is authentic when it is what it purports to be, created when and by whom it claims, and unaltered since. Whether its content is true is a separate question answered by different means: a forged deed and a genuine deed recording a lie are different objects with different remedies.

The practical upshot for system designers is a limit on what may be claimed. A traceability layer can warrant that this is what was said, when, by what, and that it has not changed. It cannot warrant that this is why. Frameworks eliding the difference promise something no record has ever delivered.

## Verified sources

Checked against the source on 28 August 2026 rather than cited from memory.

- Jenkinson, H. (1922), *A Manual of Archive Administration*, Clarendon Press. Definition and corollary confirmed verbatim against the 1922 edition on the Internet Archive, identifier manualofarchivea00jenkuoft. Page number still to be confirmed against a paginated copy.
- Bearman, D. (1994), *Electronic Evidence: Strategies for Managing Records in Contemporary Organizations*, Archives and Museum Informatics. Quotation confirmed from chapter one.
- Breskvar v Wall (1971) 126 CLR 376; [1971] HCA 70. High Court of Australia, unanimous. Pinpoint page to be confirmed.
- Turpin, M., Michael, J., Perez, E. and Bowman, S.R. (2023), "Language models don't always say what they think", NeurIPS 36. arXiv:2305.04388.
- Lanham, T. et al. (2023), "Measuring faithfulness in chain-of-thought reasoning". arXiv:2307.13702.
- Schellenberg (1956), Upward (1996), Duranti (1998), Cook (2005), ISO 15489-1:2016, ISO 23081-2:2021.

## Related

The supporting crosswalk of decision-object components against ISO 23081-2 recordkeeping entities is in `recordkeeping-standards-decision-object-crosswalk.md`. That note carries the requirement set, the four settled questions and the standards list; this one carries the argument built on top of it.
