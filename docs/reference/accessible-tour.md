# Accessible tour (#1194)

A described, keyboard-navigable, screen-reader-friendly alternative to the 3D walkthrough. A
WebGL canvas is inherently inaccessible to blind/low-vision and keyboard-only visitors, so this
is a **text alternative**: semantic HTML, ARIA, keyboard navigation and optional spoken
narration. A GLAM accessibility mandate and a differentiator.

## Where it lives (ahg-exhibition)

- `ExhibitionSpaceService::accessibleTour($space)` -> ordered stops across the building's rooms
  (one entry per placed object, room order, corridor markers skipped): `title, room,
  description (scope_and_content), thumb_url, slug, narration` (a prebuilt spoken sentence
  "Stop N, in <room>. <title>. <description>").
- `ExhibitionSpaceController::accessibleTour($slug)` -> route
  `exhibition-space.accessible-tour` (`/exhibition-space/{slug}/accessible-tour`, public GET
  like the walkthrough). Linked as **Accessible tour** in the shared exhibition nav actions.
- View `exhibition-space/accessible-tour.blade.php`:
  - Skip link, `<main tabindex=-1>`, each stop a `<section aria-labelledby>` with an `<h2>`,
    room, image with descriptive `alt`, the description, a per-stop Play and a "View full
    record" link.
  - `role="status" aria-live="polite"` announces the current stop.
  - Keyboard: **N / P** (and arrow keys) move between stops from anywhere; Next/Previous buttons
    too; focus moves to the current stop.
  - **TTS narration** via the browser `speechSynthesis` API - Play (continuous auto-tour),
    Stop, and per-stop Play. The controls only appear when the browser can speak; the page is
    fully usable (and screen-reader readable) without them.
  - **High-contrast** and **Larger text** toggles (`aria-pressed`), persisted in `localStorage`.

## First slice / follow-ups

First slice meets the acceptance: a blind/low-vision visitor can take a fully narrated,
keyboard-navigable tour. Client-side `speechSynthesis` is used for narration (zero server cost,
offline-capable). Follow-ups from the issue: server-side **neural TTS** audio per stop (reuse
the gateway TTS / `tour-audio`), and a **sign-language avatar** (stretch). Verified: a space
with 80 placed objects renders 80 stops; HTTP 200 with skip link, ARIA live region and per-stop
narration controls.
