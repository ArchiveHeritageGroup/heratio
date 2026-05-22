> Heratio Help Center article. Category: AI & Automation / Subscription.

# AI features - subscription service

The AI features in Heratio - Named Entity Recognition (NER), handwriting text
recognition (HTR), OCR, machine translation, summarisation, AI description
suggestions, AI condition assessment, and the RAG guardrails - are an
**optional paid subscription**. They are **not part of the base platform** and
are not included in the standard Heratio licence.

## What is and is not included

The **base Heratio platform** - cataloguing and description, digital objects,
access and rights, the GLAM modules, search, IIIF, reporting, the research
portal, and everything else - is fully functional with no AI subscription.

The **AI features** ship inside the platform but are **dormant by default**.
Heratio is an AI *client*: it never hosts an AI model. Every AI operation is a
call to the **AHG AI gateway**, a hosted service operated by The Archive and
Heritage Group. Without an active subscription there is no gateway credential,
so the AI features stay switched off - the AI menu items simply return nothing.

This keeps AI strictly optional. An institution that does not want AI, or that
runs fully air-gapped, does not subscribe, and nothing in the base platform
depends on it.

## What the subscription covers

An AI subscription provides authenticated access to the AHG AI gateway, which
backs:

- Named Entity Recognition (NER) - person / place / organisation extraction
- Handwriting Text Recognition (HTR) and OCR
- Machine translation, including the South African-language models
- Summarisation and AI description suggestions
- AI condition assessment
- RAG guardrails and AI inference-provenance signing

## How to subscribe

1. Email **The Archive and Heritage Group** at **johan@theahg.co.za** to
   request an AI subscription. State your institution and which AI features you
   intend to use.
2. AHG responds with the subscription terms. **Pricing is quoted on enquiry** -
   it depends on usage volume and the feature set, so there is no fixed price
   list.
3. On acceptance, AHG issues an **AI gateway API key** for your institution.

## Activating AI once subscribed

Your system operator enters the gateway credentials under
**Admin -> AHG Settings -> AI Services**:

- **Processing mode** - set to the mode AHG advises (cloud or hybrid).
- **API URL** - the AHG AI gateway endpoint AHG provides.
- **API key** - the `ahg_live_...` key issued for your institution.

Once saved, the AI menu items become live. Each AI feature also has its own
enable toggle in AI Services settings, so an operator can subscribe and then
roll features out gradually.

## Changing or ending a subscription

To change the feature scope or end a subscription, email johan@theahg.co.za.
When a subscription lapses the gateway credential stops authenticating: the AI
features return to dormant and the base platform continues unaffected.

## See also

- **AI Tools** - using the AI features day to day, once subscribed.
- **AI Inventory & Governance** - operator visibility into AI models and usage.
