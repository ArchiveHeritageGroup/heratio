# Heratio Open-Core / Paid-Plugin Strategy (DRAFT — parked)

> Status: PARKED for later. Strategy discussion, not committed to. Tracks the "Heratio-as-WordPress-with-paid-plugins" idea.

## The idea

Make Heratio work like WordPress: an open core plus a plugin ecosystem, with **paid plugins/modules** as a revenue stream.

## Verdict: copy the mechanism, not the model

**The architecture fits; the market doesn't.**

- WordPress works because it is general-purpose across **millions** of sites, so independent developers profit selling plugins.
- Heratio is **GLAM / archival** — a few **thousand** institutions worldwide. That market is far too small to attract third-party developers to build paid plugins. A truly open third-party marketplace would sit empty.
- Therefore: do **open-core with paid FIRST-PARTY modules**, not a WordPress-style open third-party marketplace. (Model closer to GitLab / Odoo than WordPress.)

## What already exists (much of the plumbing is built)

- **Plugin architecture** — ~94 `ahg-*` packages, each a self-contained Laravel package (ServiceProvider, routes, `install.sql`, auto-seed). The plugin SDK effectively exists.
- **`ahg-marketplace`** package — commission rates, listing fees, currencies, payout rules.
- **Plugin Management** + `atom_plugin.is_enabled` — enable/disable per install.
- **heratio-keygen** — license-key generation.
- **AI gateway** — per-key scope + quota + metering (billing infrastructure for metered features).

## Proposed model

- **Free core** — the jurisdiction-neutral GLAM platform.
- **Paid first-party modules** — per-market compliance packs (GRAP / POPIA / GDPR / IPSAS / NAZ / CDPA …), AI services (metered), IIIF, preservation, connectors (e.g. Archivematica). Unlocked by a **license key** (keygen ↔ `is_enabled`).
- **Monetise** via subscriptions, hosting/SaaS, support, and metered AI — NOT by selling the binary.

## The AGPL constraint (design around this)

Heratio is **AGPL (copyleft)**. You cannot paywall the *code* — anyone may redistribute it. WordPress plugins hit the same GPL wall and monetise via **updates + support + services + hosting**, gated by license keys. Same play here. True code-paywalling would require **dual licensing** — a separate decision.

## Phased plan

1. **Decide the model** — open-core + paid first-party modules (recommended); defer third-party.
2. **Formalise the plugin contract + license-key gating** for premium modules (keygen ↔ `atom_plugin.is_enabled`); document the plugin SDK.
3. **Marketplace UI** — extend `ahg-marketplace`: browse → buy → issue key → enable, + payment integration.
4. **Metered billing** — wire AI/usage billing through the gateway.
5. **(Later) Vetted third-party** — only if the market grows. GLAM buyers are compliance-sensitive, so curation beats WordPress's open plugin free-for-all (which is a security minefield).

## Risks

- Niche market can't sustain independent plugin devs → keep it curated/first-party.
- AGPL undermines "pay for binary" → monetise services/updates/hosting, or dual-license.
- Building a robust SDK + marketplace + licensing + payments + update channel + dev docs is significant effort.
- Plugin security/quality — vet everything; GLAM compliance context raises the bar.

## AGPL, third-party vendors, and dual licensing

Scenario: a vendor builds their own front-end and sells a hosting service where customers run on a Heratio backend. Under AGPL-3.0:

- **Allowed:** selling the hosting/SaaS service (AGPL permits commercial use), and building a separate, even proprietary, front-end IF it talks to Heratio at arm's length over a network API (separate program, not a derivative).
- **Cannot avoid (AGPL section 13):** because Heratio is served over a network, the vendor must offer the Corresponding Source of the Heratio backend, including any modifications they make to it, to every network user, under AGPL. AGPL closes the SaaS loophole - they can host and charge, but cannot keep the backend or their changes closed.
- **Flips to not-allowed:** if the front-end embeds/links Heratio, is a modified fork, or is tightly coupled (not a real API boundary), it becomes a derivative work and must itself be AGPL/open-sourced.
- **Also required:** preserve copyright/license notices. (Trademark/branding of the "Heratio" name is a separate AHG matter, not AGPL.)

**Implication for us:** AGPL stops a vendor from *closing* our backend, but does not stop them profiting from a proprietary front-end plus hosting. The lever to prevent that or capture the value is **dual licensing** - sell a paid commercial licence to vendors who do not want the AGPL source-disclosure obligation. This is the same decision that gates first-party vs third-party below, and it is the primary way to monetise a copyleft core.

Not legal advice - confirm any real deal with a lawyer.

## The one decision that drives everything

First-party paid modules only (curated, realistic now) **vs.** seeding a third-party developer ecosystem (much harder in a niche). Resolve this before any build.
