> Heratio Help Center article. Category: Reference.

# Accessibility & WCAG 2.1 Compliance

## Overview

Heratio conforms to WCAG 2.1 Level AA. Accessibility features are globally injected on every page via the theme — no per-plugin configuration needed.

## Features

### Screen Reader Support
- **ARIA landmarks** — banner, main, navigation, complementary, contentinfo
- **ARIA live region** — dynamic content changes announced via 
- **Collapsible facets** — , , 
- **Form validation** — , ,  auto-linked
- **Table headers** —  and  auto-injected

### Keyboard Navigation
- All interactive elements reachable via **Tab**
- **Visible focus indicators** (3px blue outline)
- **Skip navigation** link at top of every page
- **Escape key** closes modals and dropdowns
- **Ctrl+Shift+V** — toggle voice commands
- **Ctrl+Shift+H** — open voice help

### Visual Accessibility
- Colour contrast meets AA minimum (4.5:1)
-  respected — animations disabled
-  mode supported (Windows High Contrast)

### Voice Commands
- 100+ voice commands in 11 languages
- Dictation mode with punctuation support
- AI image description via local LLM (LLaVA)
- PDF transcript reading
- Video/audio transcript reading
- Enable/disable via voice or right-click type input

### Accessibility Statement
A built-in accessibility statement page is linked from the site footer.

## Automated Testing

Accessibility is verified using axe-core via Playwright:
- 11 test cases on key pages (homepage, browse, search, admin)
- Run: 

## Known Limitations

- Some base Heratio admin pages may not fully meet AA criteria
- Third-party viewers (IIIF, Google Translate) have their own limitations
- PDF documents may not be fully accessible
