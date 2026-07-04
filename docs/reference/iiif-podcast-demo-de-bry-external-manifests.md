# IIIF Podcast Demo - de Bry Engraving and External IIIF Manifests

Reference for the "IIIF in Heratio" podcast demo. Records the verified demo asset and the external IIIF sources for the Mirador side-by-side comparison (Demo 2).

## The Heratio demo asset (verified working)

- **Record show page:** https://heratio.theahg.co.za/flowers-in-the-garden
- **Object:** "Ceremonies Performed by Saturioua Before Going on an Expedition Against the Enemy" - Theodor de Bry, 1591, after Jacques Le Moyne de Morgues. From *Brevis Narratio* (de Bry's *Grands Voyages*, Part II), the Florida / Timucua series. Depicts chief Saturiwa preparing his men for war.
- **digital_object id:** 1055
- **IIIF render:** verified HTTP 200 through the live pipeline (`/iiif/3/.../info.json`)
- **Dimensions:** 2339 x 1698 px, 16-bit RGB, 300 dpi

This is currently the only genuinely demo-grade image that renders end-to-end. The larger "marble statue" master is encrypted at rest (AHG-ENC-V2) and returns HTTP 501 through Cantaloupe - tracked as heratio#1396.

## External IIIF sources for the same 1591 volume (Demo 2 side-by-side)

The engraving is a plate from the *Brevis Narratio* (1591). Multiple institutions hold digitised copies. For the Mirador comparison, the goal is to load the same plate from an external IIIF manifest next to the Heratio copy.

### Internet Archive - VERIFIED, recommended

Three digitised copies of the full 1591 volume, each exposing a **IIIF Presentation API 3.0** manifest (same version Heratio's Mirador uses). All returned HTTP 200 when tested:

| Copy | Manifest URL | Canvases |
|---|---|---|
| Copy A | https://iiif.archive.org/iiif/brevisnarratioeo00lemo/manifest.json | 160 |
| Copy B | https://iiif.archive.org/iiif/brevisnarratioeo00lemo_1/manifest.json | 188 |
| Copy C | https://iiif.archive.org/iiif/brevisnarratio00debry/manifest.json | 152 |

These are full-book manifests, so the presenter must page to the Saturioua plate (roughly plate XI, after the front matter) before recording. Pre-navigate to the right canvas so it is not done live on camera.

### Library of Congress - holds it, IIIF-enabled, NOT verifiable from the app host

LOC holds both the full volume (item 02025203) and individual Timucua plates as separate Prints and Photographs items (for example "Killing alligators" item 2001696958, "Timucua dugouts and typical houses" item 2001695739). LOC exposes IIIF. However, LOC is behind Cloudflare bot protection, so the manifest URL could not be confirmed from the Heratio server - it must be tested in a real browser. LOC single-plate items are cleaner than the full-book IA manifests if an exact-plate match is wanted.

### Florida Memory (State Archives of Florida) - thematic backup

https://www.floridamemory.com/discover/historical_records/debry/ - holds the complete de Bry Timucua engraving series with exact per-plate titles. Good for confirming plate terminology. IIIF support not confirmed.

### Digital Commonwealth (Massachusetts)

Exposes IIIF manifests by appending `/manifest` to a record URL (Presentation 2.x). Holds de Bry America material but the exact Saturioua plate was not confirmed.

## Verification notes

- The app host (LAN) is blocked by Cloudflare on loc.gov and some US-gated IIIF hosts; those must be tested from a normal browser.
- Internet Archive IIIF (`iiif.archive.org`) was reachable and returned valid Presentation 3.0 manifests.
- Verified 2026-07-04.
