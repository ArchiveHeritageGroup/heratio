# On-site AR companion (digital twin, phone-in-gallery)

**Summary:** Heratio's exhibition digital twin has an on-site "companion" web page (heratio#1191) that a visitor opens on their phone while standing in the physical gallery, typically via a QR code or short URL. It flips the twin outward: instead of exploring the room remotely in 3D, the visitor uses the twin's data to enrich the real room in front of them. This first slice is a 2D mobile-first page (twin-sourced object cards + grounded AI docent). Live camera AR (geo / marker anchoring) is an explicitly deferred later slice.

## Where it lives

- Package: `packages/ahg-exhibition` (unlocked).
- Route (public GET, multi-segment): `/exhibition-space/{slug}/companion` -> `exhibition-space.companion`.
- Controller: `ExhibitionSpaceController::companion()`.
- View: `resources/views/exhibition-space/companion.blade.php` - a standalone, self-contained mobile-first HTML document (no admin chrome on purpose; this is the visitor-in-gallery surface).

## What it reuses (no new AI plumbing)

- `ExhibitionSpaceService::accessibleTour($space)` - the ordered list of placed objects across the building, each with title, room name, description, thumbnail URL and record slug. Rendered as large tap-friendly cards.
- `ExhibitionSpaceService::roomSuggestedQuestions($space)` - grounded suggested-question chips for the docent.
- `exhibition-space.ask-room` endpoint (`askRoomAjax` -> `aiAnswerAboutRoom`) - the room AI docent. The page calls it client-side with `?q=...` and renders the grounded answer. All AI goes through the AHG gateway exactly as the existing room docent does; nothing new touches a GPU node directly.

## Design notes for the first slice

- Mobile-first: single column, 18px base font, 48px minimum tap targets, sticky header, safe-area insets for notched phones, dark high-contrast palette readable under gallery lighting.
- One-handed: docent ask-box and suggested-question chips are at the top; each object card has a "Ask about this" button that pre-fills the docent with "Tell me about <title>." and scrolls up.
- `<meta name="robots" content="noindex">` - these QR-linked pages should not be indexed.
- CSP-safe: the single inline `<style>` and `<script>` carry the `{{ $cspNonce ?? '' }}` nonce.
- Public + read-only: no auth, no writes, no CSRF surface (it only does a GET against the public ask-room endpoint).

## Explicit boundary - what this slice does NOT do

The issue's headline is "AR overlay". This slice deliberately stops short of that. It does **not** do:

- Camera passthrough / live video.
- WebXR / WebAR sessions.
- Marker tracking or geo-anchoring of object cards to where the objects physically stand.
- Any positioning derived from the twin's 3D object placements.

Those are the **next slice**. WebAR/WebXR maturity is still being evaluated (see `docs/reference/webgpu-walkthrough-evaluation.md`). The honest framing on the page itself is a banner: live camera AR is "coming soon"; for now the visitor scrolls the room's objects and asks the docent. Building the 2D companion first means the data path (twin object placements -> object info + grounded docent on a phone) is proven and shippable before the AR-anchoring complexity is layered on.

## How to reach it

QR-encode `https://<host>/exhibition-space/<space-slug>/companion` and print it on the gallery wall label / room entrance. Opening it on a phone in the room gives the visitor the twin's object info and docent for that space.
