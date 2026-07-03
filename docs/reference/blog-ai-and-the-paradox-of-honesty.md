# Blog Article: AI and the Paradox of Honesty (Truth in the Age of Machine Learning)

**Summary:** A Heratio blog article by Johan Pieterse arguing that a large language model is not "honest" but *indifferent to truth*: it cannot lie (that needs knowing the truth) and cannot tell the truth either, because it generates the most plausible continuation, not the accurate one, stating falsehood with the same confidence as fact. The archival response is not "honest AI" (unreachable) but *accountable AI*: every AI act logged, attributed, reviewed, and tamper-evident. Grounds the argument in real Heratio infrastructure (`ai_inference_log`, `ai_review_decision`, `ahg_ner_feedback`, `ahg_c2pa_provenance`) and a SAMAB Vol. 48 case study on decolonized description.

- **Source:** Heratio `blog_post` table, id 24, slug `ai-and-the-paradox-of-honesty-truth-in-the-age-of-machine-learning`, article group "The Future", status published (2026-07-02).
- **Author:** Johan Pieterse (Archivist and AI Ethics Researcher)
- **Related:** Rhodes / Post-Truth conference, SASA 2026 paper pipeline, SAMAB Vol. 48 manuscript.

## Core thesis

The popular defence of AI is that it is honest, that it merely reflects its data and does not deceive. This is the paradox to unsettle. A language model does not lie, because lying requires knowing the truth and choosing to depart from it. But for the same reason it cannot tell the truth either. It generates the most statistically plausible continuation of a prompt, and plausibility is not accuracy. When the plausible answer happens to be false, the model states it with the same fluency and confidence as when it is right. Bender and colleagues named this well: the model is a "stochastic parrot," assembling convincing language with no grounding in meaning or fact.

For a discipline whose entire authority rests on the integrity of the record, this is the central issue. AI's real danger in the archive is not that it is dishonest, but that it is indifferent to truth while sounding authoritative. The archivist's task is to build the scaffolding of verification, provenance, and human judgement that the machine itself cannot supply.

## The child genius (Rhodes metaphor)

At the Rhodes / Post-Truth conference the framing metaphor was: *"AI is a child genius, unmatched in its capacity to process and recall information. Yet its 'truth' is a reflection of the data it was trained on, which may carry the scars of historical bias, omission, or error."* A child prodigy can recite more than any adult in the room and still have no judgement about what any of it means, no sense of when it is out of its depth, and no instinct that a confident answer might be wrong. Prodigious recall and absent judgement live in the same mind. A Post-Truth culture does not fail for lack of fluent, confident speech; it drowns in it. An archive that automates fluent speech without automating accountability is pouring petrol on that fire.

## Fluency is not truth: the hallucination problem

Hallucination is a direct consequence of how the system works, not a bug that better prompting eliminates. A model asked for a citation will invent a plausible one; asked for provenance it does not know, it will construct one. AI does not merely reproduce what is in its data; it will confidently produce what was never in any data at all. An unverified AI output is not a record. It is a claim with no chain of custody, no author, and no accountability, dressed in the register of authority.

Two properties make this corrosive in a Post-Truth environment:

- **Confidence is decoupled from accuracy.** The model gives no reliable signal of its own uncertainty; a wrong answer and a right one arrive with the same tone.
- **Consistency can entrench error.** A model reproduces the same mistaken classification uniformly across a million records. Systematic, invisible error is worse than sporadic human error, because it is harder to detect and easier to trust.

## The training data dilemma: bias as inherited truth

Even where a model is not fabricating, it is inheriting. Training data is never neutral; it is a sediment of human history shaped by cultural, political, and institutional power. Categories are never innocent (Bowker and Star), and this applies with full force to the systems now automating those categories.

- **Archival silences become machine defaults.** If a corpus underrepresents marginalized or non-Western records, the model reproduces that silence as the normal shape of the world.
- **Amplification.** Statistical learning can sharpen bias, over-weighting whatever was most common in the data.

This is a failure of curation that the machine then scales. Who assembled the training data? Which archives were counted as authoritative? Whose records were absent, and how does that absence now propagate?

## From honesty to verifiability: what Heratio actually builds

The goal shifts from an unattainable "honest AI" to an achievable accountable AI: every AI act logged, attributed, reviewable, and tamper-evident. Provenance and chain of custody are the archival tradition. In Heratio this is concrete:

1. **Tamper-evident inference logging.** Every AI call is written to an append-only ledger (`ai_inference_log`) in which each entry carries the hash of the previous entry and an Ed25519 signature. A changed or deleted entry breaks the hash chain and fails verification. The record captures the service, model identity and version, and fingerprints of input and output, so an AI-derived assertion can be traced back to what produced it.
2. **Human-in-the-loop, on the record.** Archivist review is captured as a first-class act (`ai_review_decision`), linked to the specific inference, attributed to the reviewer, and countersigned by a second person for sensitive decisions.
3. **Correction as a feedback signal.** When an archivist overrides an AI suggestion, that correction is retained (for example `ahg_ner_feedback` for named-entity work) so human judgement accumulates rather than evaporating after each interaction.
4. **Content provenance at the object level.** Where AI touches or generates media, C2PA content credentials (`ahg_c2pa_provenance`) attach a verifiable statement of how an asset was produced, so users can distinguish an original from a machine-altered derivative.

None of this makes the model truthful. That is the point. It makes the model answerable.

## Case study: Heratio and the SAMAB Vol. 48 manuscript

In preparing the SAMAB Vol. 48 manuscript, AI-generated metadata quietly reinforced an archival bias: a body of African oral histories was classified under "folklore" rather than "primary source." The Eurocentric assumption that oral testimony is folklore while written testimony is evidence was reproduced as if it were a neutral fact. The damage propagates: it shapes how the collection is discovered, how it is weighted in search, what access and rights logic attaches to it, and whether a future researcher ever finds it as evidence. What contained it was not a better model but the surrounding apparatus: the classification was an AI suggestion on the record, subject to human review, correctable, and logged. Archival AI must be trained toward, and corrected against, decolonized description, and must operate inside a provenance framework, in Heratio's case aligned with Records in Contexts (RiC), so that every machine assertion remains attributable and reversible.

## Conclusion: stewardship, not trust

AI is not inherently dangerous, and it is not honest either. It is indifferent to truth while fluent in the language of authority. The archival response is neither adoption nor refusal but stewardship: treat AI as a tool that produces claims, not truths, never a substitute for human judgement; build the scaffolding of verification (tamper-evident logging, attributed human review, retained corrections, content provenance); and confront the training data directly, curating toward inclusive and decolonized representation rather than laundering inherited silence as machine neutrality. The machine cannot tell us what is true. What the archive can guarantee, as it always has, is that every claim can be traced, questioned, and answered for. In an age of fluent falsehood, that may be the essential contribution.
