# EU AI Act - Digital Omnibus amendments (deadline deferrals)

**Summary:** The EU's "Digital Omnibus on AI" is a package of targeted amendments to the EU AI Act that pushes back the high-risk compliance deadlines and adds a small set of new prohibitions. For Heratio this matters because the EU is one of our target markets and EU AI Act obligations are a per-market compliance regime - implemented as a pluggable module alongside the jurisdiction-neutral core, never baked into core. The core AI features (HTR, NER, summarise, condition scan, translate) all route through the AHG AI gateway (ai.theahg.co.za), which is the natural place to enforce transparency / watermarking / logging obligations for EU deployments.

This note is regulatory background captured for cross-agent recall. Verify the live legal position before advising a customer - the text was still moving through formal adoption when this was written.

## Status / timeline of the amendment itself

- **19 November 2025** - European Commission published the Digital Omnibus on AI.
- **7 May 2026** - European Parliament and Council of the EU reached a provisional agreement on the package.
- Parliament plenary then voted to adopt the amendment (reported 423 in favour, 57 against, 174 abstentions).
- The Council must still formally adopt the final text before it takes effect (expected within weeks of the vote).
- **Important caveat:** if the Omnibus is NOT formally adopted before 2 August 2026, the original AI Act timeline applies from that date as written. Until adoption is final, organisations should keep preparing against the original 2 August 2026 high-risk deadline.

## What changed (deadline extensions)

- **High-risk AI systems** (biometrics, employment, education, credit scoring, law enforcement, migration): high-risk obligations move from **2 August 2026** to **2 December 2027** (about a 16-month extension).
- **High-risk AI embedded in regulated products** (medical devices, machinery, vehicles): from **2 August 2027** to **2 August 2028** (about a 12-month extension).
- **Watermarking / machine-readable labelling of AI-generated content:** shifted from 2 August 2026 to **2 December 2026** for pre-market systems; newly placed systems must comply immediately.

## New restrictions added

- **AI "nudifier" apps** and systems generating child sexual abuse material added to the prohibited-practices tier, effective **2 December 2026**.

## What stayed the same

- The risk-based architecture, general-purpose AI (GPAI) obligations, the existing prohibition tier, AI Office oversight, and the penalty structure are all unchanged.
- Extra changes around the edges: broader permission to process sensitive data for bias detection, and SME exemptions extended to small mid-cap enterprises.

## Why the deferral

Timely implementation had stalled - national competent authorities were not yet designated and the harmonised standards / compliance tools needed for the high-risk requirements were not finalised, so the original deadline was not workable.

## Relevance to Heratio

- EU AI Act compliance is a **per-market module**, consistent with how Heratio treats GRAP 103, POPIA, GDPR, etc. - never default the product to any single jurisdiction.
- The deferral buys EU customers time, but the transparency / watermarking obligations for AI-generated content are the ones most likely to touch Heratio's AI tooling (HTR transcripts, NER, AI summaries, translations). Plan for content provenance / labelling at the gateway layer (ties in with the existing AI inference provenance roadmap).
- The gateway (ai.theahg.co.za) already logs every AI call - that audit trail is a head start on AI Act record-keeping obligations.

## Sources

- LinkedIn post (Anna August, PhD) - tags #euaiact #aiact #digitalomnibus (origin of this capture).
- [White & Case - EU agrees Digital Omnibus deal to simplify AI rules](https://www.whitecase.com/insight-alert/eu-agrees-digital-omnibus-deal-simplify-ai-rules)
- [Gibson Dunn - EU AI Act Omnibus Agreement: Postponed High-Risk Deadlines](https://www.gibsondunn.com/eu-ai-act-omnibus-agreement-postponed-high-risk-deadlines-and-other-key-changes/)
- [Cooley - Proposed Digital Omnibus on AI will impact compliance roadmaps](https://www.cooley.com/news/insight/2025/2025-11-24-eu-ai-act-proposed-digital-omnibus-on-ai-will-impact-businesses-ai-compliance-roadmaps)
- [Hogan Lovells - EU legislators agree to delay for high-risk AI rules](https://www.hoganlovells.com/en/publications/eu-legislators-agree-to-delay-for-highrisk-ai-rules)
- [IAPP - European Commission delivers draft high-risk AI guidelines](https://iapp.org/news/a/european-commission-delivers-draft-high-risk-ai-guidelines)
