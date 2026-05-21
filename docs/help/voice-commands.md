> Heratio Help Center article. Category: Viewers & Media.

# Voice Commands

## Overview

Heratio includes a voice command system for hands-free navigation, search, dictation, and AI-powered image description. Powered by the Web Speech API (browser-native).

## Getting Started

1. Click the **microphone button** in the navbar or the floating button (bottom-right)
2. Say a command (e.g. "browse", "search for photographs", "help")
3. The system responds with spoken feedback and on-screen toasts

**Right-click** the mic button to open a text input for typing commands manually.

## Command Categories

### Navigation
- "go home" / "browse" / "go to admin" / "go to settings"
- "go to donors" / "go to accessions" / "go to repositories"
- "browse archive" / "browse library" / "browse museum" / "browse gallery"
- "go back" / "next page" / "previous page"
- "search for [term]"

### Record Reading
- "read metadata" — read all populated fields aloud
- "read title" / "read description"
- "describe image" — AI description via LLaVA (images only)
- "read PDF" — read PDF transcript text
- "what type of file" — report file type

### AI Image Description
- "describe image" / "AI describe" — generate description
- "save to description" / "save to alt text" / "save to both"
- "discard" — discard generated description

### Media Detection
- PDFs: offers to read OCR/transcript text if available
- Videos/audio: offers to read transcript if available
- Non-OCRd PDFs: notifies user text is not readable

### Dictation
- "start dictating" — dictate into focused text field
- "stop dictating" — return to command mode
- Punctuation: "period", "comma", "question mark", "new line", etc.

### Voice Control
- "disable voice" / "voice off" — disable until re-enabled
- "enable voice" / "voice on" — re-enable
- "keep listening" — continuous mode
- "stop listening" — single command mode

### Accessibility
- "where am I" — announce current page
- "how many results" — announce result count
- "help" — show command list

## Settings

Admin > AHG Settings > Voice & AI:
- Language (11 languages)
- Confidence threshold
- Continuous listening mode
- Floating button visibility
- Hover-read (TTS on hover)
- Speech rate
- LLM provider (local Ollama / cloud Anthropic / hybrid)

## Browser Support

| Browser | Voice | TTS | Keyboard |
|---------|-------|-----|----------|
| Chrome 90+ | Full | Full | Full |
| Edge 90+ | Full | Full | Full |
| Safari 15+ | No voice | Full | Full |
| Firefox 90+ | No voice | Full | Full |

Voice commands require HTTPS and microphone permission.
