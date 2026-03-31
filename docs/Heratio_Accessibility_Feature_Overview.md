# Heratio — Accessibility Feature Overview

**Product:** Heratio Framework v2.8.2
**Component:** WCAG 2.1 Level AA Accessibility
**Date:** 16 March 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## What It Does

Heratio provides built-in accessibility features that ensure archival content is usable by people with disabilities, including those who use screen readers, keyboard navigation, voice control, or assistive technologies. The system conforms to the **Web Content Accessibility Guidelines (WCAG) 2.1 at Level AA** — the internationally recognised standard for web accessibility.

## Key Features

### Automatic Accessibility Enhancements
- **Global injection** — accessibility features are loaded on every page via the theme layout, requiring no per-plugin configuration
- **Auto-scoped table headers** — `scope="col"` and `scope="row"` attributes are automatically added to all data tables
- **Auto-required fields** — `aria-required="true"` is automatically set on all required form inputs
- **Auto-labelled checkboxes** — batch selection checkboxes without labels are automatically given descriptive `aria-label` attributes
- **Real-time form validation** — `aria-invalid` and `aria-describedby` attributes are dynamically synchronised with Bootstrap validation states

### Screen Reader Support
- **ARIA landmarks** — banner, main, navigation, complementary, and contentinfo roles for efficient page navigation
- **ARIA live region** — dynamic content changes (AJAX updates, notifications) are announced to screen readers
- **Collapsible facets** — filter panels expose `aria-expanded`, `aria-controls`, and `role="button"` for full state awareness
- **Semantic headings** — correct heading hierarchy (h1 > h2 > h3) across all pages

### Keyboard Navigation
- **Full keyboard access** — all interactive elements (buttons, links, facets, modals) reachable via Tab
- **Visible focus indicators** — 3px blue outline on all focused elements
- **Skip navigation** — bypass repetitive header content with a single keypress
- **Escape key** — closes open modals and dropdowns
- **Keyboard shortcuts** — Ctrl+Shift+V (toggle voice), Ctrl+Shift+H (voice help)

### Visual Accessibility
- **Colour contrast** — all text meets the WCAG AA minimum contrast ratio (4.5:1 for normal text, 3:1 for large text)
- **Reduced motion** — animations and transitions are disabled for users who prefer reduced motion (`prefers-reduced-motion`)
- **High contrast mode** — borders and focus indicators are preserved in Windows High Contrast and forced-colours mode
- **Responsive design** — accessible across desktop, tablet, and mobile devices

### Voice Commands (Optional)
- **Voice navigation** — navigate pages, search, and browse by speaking commands
- **Voice dictation** — dictate into any text field with punctuation support
- **AI image description** — generate image descriptions via local LLM (LLaVA) — fully offline, no cloud dependency
- **Enable/disable** — toggle voice commands via spoken command or typed input (right-click mic button)
- **11 languages supported** — English, Afrikaans, isiZulu, isiXhosa, Sesotho, French, Portuguese, Spanish, German, and more

### Accessibility Statement
- Built-in accessibility statement page linked from the site footer
- Documents conformance status, features, known limitations, and contact information

## Compliance and Standards

| Standard | Conformance |
|----------|-------------|
| WCAG 2.1 Level A | Full |
| WCAG 2.1 Level AA | Full (with documented exceptions for base AtoM legacy pages) |
| WAI-ARIA 1.1 | Full |
| Section 508 (US) | Aligned |
| EN 301 549 (EU) | Aligned |

## Automated Testing

Accessibility is continuously verified using **axe-core** via Playwright:
- 11 automated test cases covering homepage, browse, search, admin, ARIA attributes, landmarks, live regions, table scopes, and CSS media queries
- Tests run against WCAG 2.1 Level AA criteria
- Known base-system limitations are documented and excluded from pass/fail

## Technical Requirements

- **Server:** PHP 8.3, MySQL 8, Elasticsearch 7.x
- **Browser:** Chrome 90+, Edge 90+, Safari 15+, Firefox 90+
- **Voice commands:** HTTPS required, Chrome/Edge recommended (Web Speech API)
- **AI image description:** Ollama with LLaVA:7b model (optional, runs locally)

## Who Benefits

- **Visually impaired users** — screen reader support, alt text, colour contrast, high contrast mode
- **Motor impaired users** — full keyboard navigation, voice commands, large click targets
- **Cognitively impaired users** — clear headings, predictable navigation, reduced motion
- **Institutions** — compliance with accessibility legislation (ADA, GDPR, POPIA, Section 508)
- **GLAM sector** — archives, libraries, museums, and galleries serving diverse public audiences

---

*For technical implementation details, see the [Accessibility Statement](Heratio_Accessibility_Statement.md).*
*For questions or feedback, contact johan@theahg.co.za.*
