> Heratio Help Center article. Category: Reference.

# Accessibility and WCAG 2.2

## Overview

Heratio targets **WCAG 2.2 Level AA**. This is a self-assessment: automated scans and fixes, with no independent audit yet, so Heratio does not claim full conformance. The accessibility features below are injected on every page by the theme, so no package needs its own configuration. Each institution publishes its own statement at `/accessibility-statement`; see [Accessibility Statement](/help/article/accessibility-statement-user-guide).

## Features

### Screen reader support
- **ARIA landmarks:** banner, main, navigation, complementary, contentinfo.
- **ARIA live region:** dynamic changes are announced, and scripts can announce their own with `ahgAnnounce()`.
- **Collapsible facets** carry `aria-expanded` and `aria-controls`.
- **Form validation:** error messages are linked with `aria-describedby`, and `aria-invalid` follows Bootstrap's `.is-invalid`.
- **Table headers** get `scope` automatically.
- **Icon-only buttons** and form fields carry accessible names.

### Keyboard navigation
- Every interactive element is reachable with **Tab**.
- **Visible focus indicators:** a 3px theme-accent outline with a 2px offset (WCAG 2.4.7).
- A **skip navigation** link at the top of every page.
- **Escape** closes modals and dropdowns.
- **Ctrl+Shift+V** toggles voice commands; **Ctrl+Shift+H** opens voice help.

### Identify input purpose (WCAG 1.3.5)
Login, registration and password-reset forms declare their input purpose with `autocomplete` (`email`, `username`, `current-password`, `new-password`), so assistive technology and password managers fill them in correctly.

### Visual accessibility
- **Contrast:** text is checked against 4.5:1. The theme colours an administrator picks for the header, description bar, footer, card headers, buttons and sidebar are shaded automatically where needed, keeping their hue, so their text reaches 4.5:1.
- Links in running text are underlined, not told apart by colour alone.
- Small links in result lists have a 24px target (WCAG 2.5.8).
- `prefers-reduced-motion` is respected: animations are turned off.
- `forced-colors` (Windows High Contrast) is supported.

### Voice commands
- 100+ voice commands in 11 languages.
- Dictation with punctuation.
- AI image descriptions through a local model.
- Reading of PDF, video and audio transcripts.
- Turned on or off by voice or from the right-click menu.

## Automated testing

axe-core runs through Playwright with the WCAG 2.0, 2.1 and 2.2 A and AA rule sets, every night and on demand. It scans:

- the public pages: home, browse, login, a published record and the accessibility statement;
- the staff pages, signed in: browse, authority browse, settings, export, data migration, the error log and the dropdown manager.

The run fails on any new critical or serious violation. On 27 October 2026 every scanned page had none. To run it yourself:

```bash
HERATIO_URL=https://your-heratio npx playwright test tests/e2e/05-ui-components/accessibility.spec.ts --project=chromium
```

## Known limitations

- Pages outside the scans, particularly less-used admin screens, have not been checked.
- No manual audit yet: keyboard-only and screen-reader walkthroughs, zoom and reflow, and the quality of alternative text are not assessed.
- Third-party viewers (IIIF, 3D, video) and the Google Translate widget have their own limitations.
- PDF documents may not be fully accessible; alternative formats are available on request.
