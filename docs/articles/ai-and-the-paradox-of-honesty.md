# AI and the Paradox of Honesty: Truth in the Age of Machine Learning

*By Johan Pieterse, Archivist and AI Ethics Researcher*

## Introduction: The Machine That Cannot Lie and Cannot Tell the Truth

Artificial intelligence (AI) has become a cornerstone of modern archival practice, from automating metadata tagging to enhancing searchability in digital repositories. Yet, as I argued in my recent presentation at the Rhodes / Post-Truth conference, AI's utility is inseparable from a deeper problem than error: a large language model has no internal concept of truth at all.

The popular defence of AI is that it is *honest*, that it merely reflects its data and does not deceive. This is the paradox I want to unsettle. A language model does not lie, because lying requires knowing the truth and choosing to depart from it. But for the same reason it cannot tell the truth either. It generates the most statistically plausible continuation of a prompt, and plausibility is not accuracy. When the plausible answer happens to be false, the model states it with exactly the same fluency and confidence as when it is right. Bender and colleagues named this well: the model is a "stochastic parrot," assembling convincing language with no grounding in meaning or fact.

For a discipline whose entire authority rests on the integrity of the record, this is not a footnote. It is the central issue. This article argues that AI's real danger in the archive is not that it is dishonest, but that it is *indifferent to truth* while sounding authoritative, and that the archivist's task is to build the scaffolding of verification, provenance, and human judgement that the machine itself cannot supply. I draw on my work with the Heratio platform, the SAMAB Vol. 48 manuscript, and the SASA 2026 conference pipeline.

## The Child Genius: A Metaphor from the Rhodes Podium

At the Rhodes / Post-Truth conference I reached for a metaphor that has stayed with me since:

> "AI is a child genius, unmatched in its capacity to process and recall information. Yet its 'truth' is a reflection of the data it was trained on, which may carry the scars of historical bias, omission, or error."

I want to press on that image, because it holds the whole argument in miniature. A child prodigy can recite more than any adult in the room and still have no judgement about what any of it means, no sense of when it is out of its depth, and no instinct that a confident answer might be wrong. Prodigious recall and absent judgement live in the same mind. That is precisely the machine we are installing into our finding aids. The room at Rhodes, an audience convened around the erosion of shared truth, understood the danger immediately: a Post-Truth culture does not fail for lack of fluent, confident speech. It drowns in it. An archive that automates fluent, confident speech without automating accountability is pouring petrol on that fire. The rest of this article is an attempt to say what accountability, in concrete archival terms, would actually look like.

## Fluency Is Not Truth: The Hallucination Problem

The failure mode that most threatens the archive has a misleadingly gentle name: hallucination. A model asked for a citation will invent a plausible one, complete with author, journal, and year. Asked for the provenance of an object it does not know, it will construct a provenance. This is not a bug that better prompting eliminates; it is a direct consequence of how the system works. The model optimises for output that *looks* like a good answer, and a fabricated citation looks exactly like a real one until someone checks.

This inverts the reassuring story. AI does not merely reproduce what is in its data; it will confidently produce what was never in any data at all. In archival terms, an unverified AI output is not a record. It is a claim with no chain of custody, no author, and no accountability, dressed in the register of authority. Treating it as anything more is a category error.

Two properties make this especially corrosive in a Post-Truth environment:

- **Confidence is decoupled from accuracy.** The model gives no reliable signal of its own uncertainty. A wrong answer and a right one arrive with the same tone.
- **Consistency can entrench error.** Unlike a human, a model will reproduce the same mistaken classification uniformly across a million records. Systematic, invisible error is worse than sporadic human error, because it is harder to detect and easier to trust.

## The Training Data Dilemma: Bias as Inherited Truth

Even where a model is not fabricating, it is inheriting. Training data is never neutral; it is a sediment of human history, shaped by cultural, political, and institutional power. What the archive world already knows about classification, that categories are never innocent (Bowker and Star), applies with full force to the systems now automating those categories.

- **Archival silences become machine defaults.** If a corpus underrepresents marginalized voices or non-Western records, the model reproduces that silence and presents it as the normal shape of the world.
- **Amplification.** Statistical learning does not merely copy bias; it can sharpen it, over-weighting whatever was most common in the data.

This is not, strictly, a failure of the machine. It is a failure of curation that the machine then scales. As archivists the questions are old ones with new stakes: Who assembled the training data? Which archives were counted as authoritative? Whose records were absent, and how does that absence now propagate?

## From Honesty to Verifiability: What the Archive Can Actually Build

If the machine cannot be trusted to tell the truth, the archival response is not to reject it but to wrap it in verification. The goal shifts from an unattainable "honest AI" to an achievable *accountable AI*: every AI act logged, attributed, reviewable, and tamper-evident. This is where archival science has something distinctive to offer, because provenance and chain of custody are exactly our tradition. In the Heratio platform I have tried to make this concrete rather than aspirational:

1. **Tamper-evident inference logging.** Every AI call is written to an append-only ledger (`ai_inference_log`) in which each entry carries the hash of the previous entry and an Ed25519 signature. Like a well-kept register, the chain cannot be silently altered after the fact: a changed or deleted entry breaks the hash chain and fails verification. The record captures the service, the model identity and version, and fingerprints of the input and output, so an AI-derived assertion can always be traced back to what produced it.

2. **Human-in-the-loop, on the record.** AI outputs are not self-certifying. Archivist review is captured as a first-class act (`ai_review_decision`), linked to the specific inference, attributed to the reviewer, and, for sensitive decisions, countersigned by a second person. The human judgement that the model lacks is thereby made part of the record, not left implicit.

3. **Correction as a feedback signal.** When an archivist overrides an AI suggestion, that correction is retained (for example `ahg_ner_feedback` for named-entity work) so that human judgement accumulates rather than evaporating after each interaction.

4. **Content provenance at the object level.** Where AI touches or generates media, C2PA content credentials (`ahg_c2pa_provenance`) attach a verifiable statement of how an asset was produced, so downstream users can distinguish an original from a machine-altered derivative.

None of this makes the model truthful. That is the point. It makes the model *answerable*, which is what an archive can actually guarantee and what a Post-Truth environment most urgently needs.

## Case Study: Heratio and the SAMAB Vol. 48 Manuscript

The distinction between fluency and truth is not abstract. In preparing the SAMAB Vol. 48 manuscript, I worked through a case in which AI-generated metadata quietly reinforced an archival bias: a body of African oral histories was classified under "folklore" rather than "primary source." The output was fluent, plausible, and wrong in a way that carries real consequences. The Eurocentric assumption baked into the training data, that oral testimony is folklore while written testimony is evidence, was reproduced as if it were a neutral fact.

The damage of such a misclassification is not confined to a single field. It propagates: it shapes how the collection is discovered, how it is weighted in search, what access and rights logic attaches to it, and whether a future researcher ever finds it as evidence at all. A confident, consistent, automated error had begun to rewrite the standing of a body of records.

What contained it was not a better model but the surrounding apparatus: the classification was an AI suggestion on the record, subject to human review, correctable, and logged. The lesson generalises. Archival AI must be trained toward, and corrected against, decolonized description, and it must operate inside a provenance framework, in Heratio's case aligned with Records in Contexts (RiC), so that every machine assertion remains attributable and reversible.

## Conclusion: Stewardship, Not Trust

AI is not inherently dangerous, and it is not honest either. It is indifferent to truth while fluent in the language of authority, and that combination is precisely what a Post-Truth era does not need more of, unless it is held to account. The archival response is neither adoption nor refusal but stewardship:

- Treat AI as a tool that produces claims, not truths, and never as a substitute for human judgement.
- Build the scaffolding of verification: tamper-evident logging, attributed human review, retained corrections, and content provenance.
- Confront the training data directly, curating toward inclusive and decolonized representation rather than laundering inherited silence as machine neutrality.

The machine cannot tell us what is true. What the archive can do, as it always has, is guarantee that every claim can be traced, questioned, and answered for. In an age of fluent falsehood, that is not a modest contribution. It may be the essential one.

## References

- Bender, E. M., Gebru, T., McMillan-Major, A. and Shmitchell, S. (2021) 'On the Dangers of Stochastic Parrots: Can Language Models Be Too Big?', *Proceedings of the 2021 ACM Conference on Fairness, Accountability, and Transparency*, pp. 610-623.
- Bowker, G. C. and Star, S. L. (1999) *Sorting Things Out: Classification and Its Consequences*. Cambridge, MA: MIT Press.
- Caswell, M. and Cifor, M. (2016) 'From Human Rights to Feminist Ethics: Radical Empathy in the Archives', *Archivaria*, 81, pp. 23-43.
- Coalition for Content Provenance and Authenticity (C2PA) (2023) *C2PA Technical Specification*. Available at: https://c2pa.org (Accessed: 2 July 2026).
- International Council on Archives (2021) *Records in Contexts: A Conceptual Model for Archival Description (RiC-CM)*. Version 0.2.
