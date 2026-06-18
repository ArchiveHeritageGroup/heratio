# Public Explore hub: exhibitions, language and accessibility

The **Explore** page at `/explore` is the public, no-login front door to
everything a visitor can do with the collection. It is part of the
**heratio#1211 "every museum for everyone - universal multilingual access"**
North Star. This page explains the two visitor-facing capabilities added in the
first increment: discovering the exhibitions (with their 3D walkthroughs) and
setting language + reading-comfort preferences that are remembered across visits.

## What the Explore hub shows

`/explore` is a single, jurisdiction-neutral landing page. Each card only
appears when the feature behind it is installed, so the grid is always live -
there are never dead links. Cards include:

- **Explore the exhibitions** -> `/exhibitions`. Lists every public exhibition
  space, each with its **3D walkthrough**, **wayfinding** floor plan and
  printable **catalogue**. This is the entry point to the digital twin.
- **What's on** -> `/whats-on`. Upcoming and live virtual openings across every
  exhibition space, with join / RSVP.
- **This collection at a glance**, **Ask the collection**, **Read a record in
  your language**, **Languages of this collection**, **Race against loss**,
  **Reconstructions gallery**, **Content Credentials**, **System map**, and the
  **Open data graph / Open data and APIs** developer surfaces.

## Choosing a language and reading comfort

At the top of the hub is a small panel - **"Make this collection easier to
use"** - with two controls. Both are remembered **on the device** (browser
session plus a 1-year cookie), need **no account**, and **work without
JavaScript** (they are real forms that submit and redirect back).

### Reading language

The **Reading language** picker lets a visitor choose the language they want
records presented in. The original record text always stays authoritative;
machine translation (clearly labelled) is provided via the AHG AI gateway. The
chosen language is applied site-wide by the existing locale layer.

> Tip: language can also be switched from the culture switcher in the site
> navigation. Both routes persist the same way, so the choice sticks.

### Reading comfort (accessibility)

The **Reading comfort** control offers three independent on/off preferences:

| Preference        | Body class             | Effect                                  |
|-------------------|------------------------|-----------------------------------------|
| **High contrast** | `a11y-high-contrast`   | Stronger foreground/background contrast |
| **Larger text**   | `a11y-larger-text`     | Increased base font size                |
| **Reduce motion** | `a11y-reduced-motion`  | Suppresses non-essential animation      |

When a visitor ticks a preference it is saved immediately and applied **across
the whole site** (every page, including record and exhibition pages), not just
on the hub. With JavaScript the change applies instantly with no reload; without
JavaScript the form submit saves the choice and returns the visitor to the page
they were on with the preference already applied.

The theme styles the three body classes above. An institution can extend or
override that styling in its own theme layer without touching this control.

## How persistence works (for operators)

- **Language** is handled by the existing `SetLocale` middleware and the
  `/set-locale` and `/reading-language` endpoints (session + 1-year `locale` /
  `ahg_reading_language` cookies). This increment did **not** change it.
- **Accessibility** is handled by the `AccessibilityPreferences` middleware and
  the `POST /accessibility-preferences` endpoint
  (`accessibility.preferences.set`). It stores a validated subset of
  `high-contrast,larger-text,reduced-motion` in the session and a 1-year
  `ahg_a11y_prefs` cookie. The middleware shares the resolved body-class string
  to views and injects a tiny applier script so the classes land on `<body>` on
  every page. Unknown / hand-edited tokens are dropped; an empty submission
  clears the preference.

Everything is **fail-soft**: zero exhibitions still renders the hub, a garbage
cookie resolves to "no preference", a non-HTML response is left untouched, and
no path ever returns a 500.

## Follow-ups (deferred from the first increment)

- Full per-exhibition multilingual **content** translation (labels, captions).
- A dedicated `/museums` vanity route.
- A formal screen-reader / WCAG conformance audit of the hub and walkthroughs.
- A persistent global header affordance for the language + comfort controls
  (currently surfaced on the hub; the site nav already carries the language
  switcher).
